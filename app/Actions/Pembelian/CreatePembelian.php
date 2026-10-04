<?php

namespace App\Actions\Pembelian;

use App\Exceptions\IdempotencyKeyConflictException;
use App\Models\Pembelian;
use App\Models\User;
use App\Support\CanonicalRequestPayload;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePembelian
{
    private const string MAXIMUM_MONEY = '9999999999999.99';

    public function __construct(private readonly CanonicalRequestPayload $payloadHasher) {}

    /** @param array<string, mixed> $input */
    public function execute(User $actor, string $idempotencyKey, array $input): Pembelian
    {
        $payloadHash = $this->payloadHasher->hash($input);

        try {
            return DB::transaction(function () use ($actor, $idempotencyKey, $input, $payloadHash): Pembelian {
                $existing = $this->findByKey($actor, $idempotencyKey);

                if ($existing !== null) {
                    return $this->replayOrFail($existing, $payloadHash);
                }

                $lineData = [];
                $total = BigDecimal::zero();

                foreach ($input['rincian'] as $index => $line) {
                    $quantity = $line['qty'] ?? null;
                    $unitPrice = $line['harga_satuan'] ?? null;

                    if (($quantity === null) !== ($unitPrice === null)) {
                        throw ValidationException::withMessages([
                            "rincian.$index.qty" => ['Kuantitas dan harga satuan harus diisi bersama.'],
                            "rincian.$index.harga_satuan" => ['Kuantitas dan harga satuan harus diisi bersama.'],
                        ]);
                    }

                    if ($quantity !== null && $unitPrice !== null) {
                        $lineSubtotal = BigDecimal::of((string) $unitPrice)
                            ->multipliedBy((string) $quantity)
                            ->toScale(2, RoundingMode::HalfUp);

                        if (isset($line['subtotal']) && BigDecimal::of((string) $line['subtotal'])->compareTo($lineSubtotal) !== 0) {
                            throw ValidationException::withMessages([
                                "rincian.$index.subtotal" => ['Subtotal tidak cocok dengan kuantitas dan harga satuan.'],
                            ]);
                        }
                    } else {
                        if (! isset($line['subtotal'])) {
                            throw ValidationException::withMessages([
                                "rincian.$index.subtotal" => ['Subtotal wajib untuk rincian nominal.'],
                            ]);
                        }

                        $lineSubtotal = BigDecimal::of((string) $line['subtotal'])->toScale(2, RoundingMode::HalfUp);
                    }

                    $this->assertDatabaseMoney($lineSubtotal, "rincian.$index.subtotal");
                    $total = $total->plus($lineSubtotal);
                    $this->assertDatabaseMoney($total, 'rincian');
                    $lineData[] = [
                        'warung_id' => $actor->warung_id,
                        'nama_item' => $line['nama_item'],
                        'qty' => $quantity,
                        'satuan' => $line['satuan'] ?? null,
                        'harga_satuan' => $unitPrice,
                        'subtotal' => (string) $lineSubtotal,
                    ];
                }

                $purchase = Pembelian::query()->create([
                    'warung_id' => $actor->warung_id,
                    'user_id' => $actor->getKey(),
                    'no_transaksi' => 'PB-'.Str::ulid(),
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'tanggal' => CarbonImmutable::parse((string) $input['tanggal'])->utc()->toDateTimeString(),
                    'total' => (string) $total->toScale(2, RoundingMode::HalfUp),
                    'catatan' => $input['catatan'] ?? null,
                ]);

                $purchase->rincian()->createMany($lineData);

                return $purchase->load('rincian');
            }, 3);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->findByKey($actor, $idempotencyKey);

            if ($existing === null) {
                throw $exception;
            }

            return $this->replayOrFail($existing, $payloadHash);
        }
    }

    private function findByKey(User $actor, string $idempotencyKey): ?Pembelian
    {
        return Pembelian::query()
            ->where('warung_id', $actor->warung_id)
            ->where('user_id', $actor->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->with('rincian')
            ->first();
    }

    private function replayOrFail(Pembelian $purchase, string $payloadHash): Pembelian
    {
        if (! hash_equals($purchase->payload_hash, $payloadHash)) {
            throw new IdempotencyKeyConflictException;
        }

        return $purchase;
    }

    private function assertDatabaseMoney(BigDecimal $amount, string $field): void
    {
        if ($amount->compareTo(BigDecimal::of(self::MAXIMUM_MONEY)) > 0) {
            throw ValidationException::withMessages([
                $field => ['Nominal melebihi kapasitas DECIMAL(15,2).'],
            ]);
        }
    }
}
