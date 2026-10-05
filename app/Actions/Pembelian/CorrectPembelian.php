<?php

namespace App\Actions\Pembelian;

use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\PembelianStateConflictException;
use App\Models\Pembelian;
use App\Models\PembelianKoreksi;
use App\Models\User;
use App\Support\CanonicalRequestPayload;
use App\Support\IdempotencyKeyWindow;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectPembelian
{
    private const string MAXIMUM_MONEY = '9999999999999.99';

    public function __construct(
        private readonly CanonicalRequestPayload $payloadHasher,
        private readonly IdempotencyKeyWindow $idempotencyKeyWindow,
    ) {}

    /** @param array<string, mixed> $input */
    public function update(User $actor, int $purchaseId, string $idempotencyKey, array $input): PembelianKoreksi
    {
        return $this->change($actor, $purchaseId, 'ubah', $idempotencyKey, $input);
    }

    /** @param array<string, mixed> $input */
    public function cancel(User $actor, int $purchaseId, string $idempotencyKey, array $input): PembelianKoreksi
    {
        return $this->change($actor, $purchaseId, 'batalkan', $idempotencyKey, $input);
    }

    /** @param array<string, mixed> $input */
    private function change(User $actor, int $purchaseId, string $kind, string $idempotencyKey, array $input): PembelianKoreksi
    {
        $payloadHash = $this->payloadHasher->hash([
            'pembelian_id' => (string) $purchaseId,
            'jenis' => $kind,
            'request' => $input,
        ]);

        try {
            return DB::transaction(function () use ($actor, $purchaseId, $kind, $idempotencyKey, $input, $payloadHash): PembelianKoreksi {
                $existing = $this->findByKey($actor, $kind, $idempotencyKey);

                if ($existing instanceof PembelianKoreksi) {
                    return $this->replayOrFail($existing, $payloadHash);
                }

                $purchase = Pembelian::query()
                    ->where('warung_id', $actor->warung_id)
                    ->lockForUpdate()
                    ->with('rincian')
                    ->findOrFail($purchaseId);

                $existing = $this->findByKey($actor, $kind, $idempotencyKey);

                if ($existing instanceof PembelianKoreksi) {
                    return $this->replayOrFail($existing, $payloadHash);
                }

                if ($purchase->status === 'dibatalkan') {
                    throw new PembelianStateConflictException(
                        'PEMBELIAN_SUDAH_DIBATALKAN',
                        'Pembelian yang sudah dibatalkan tidak dapat diubah atau dibatalkan kembali.',
                    );
                }

                $before = $this->snapshot($purchase);
                $after = $kind === 'batalkan'
                    ? $this->cancelPurchase($purchase, $before)
                    : $this->updatePurchase($purchase, $before, $input);

                return PembelianKoreksi::query()->create([
                    'warung_id' => $actor->warung_id,
                    'pembelian_id' => $purchase->getKey(),
                    'user_id' => $actor->getKey(),
                    'jenis' => $kind,
                    'alasan' => $input['alasan'],
                    'sebelum' => $before,
                    'sesudah' => $after,
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'idempotency_expires_at' => $this->idempotencyKeyWindow->expiresAt(),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->findByKey($actor, $kind, $idempotencyKey);

            if (! $existing instanceof PembelianKoreksi) {
                throw $exception;
            }

            return $this->replayOrFail($existing, $payloadHash);
        }
    }

    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $before
     * @return array<string, mixed>
     */
    private function updatePurchase(Pembelian $purchase, array $before, array $input): array
    {
        $after = $before;

        if (array_key_exists('tanggal', $input)) {
            $after['tanggal'] = CarbonImmutable::parse((string) $input['tanggal'])->utc()->toISOString();
        }

        if (array_key_exists('catatan', $input)) {
            $after['catatan'] = $input['catatan'];
        }

        if (array_key_exists('rincian', $input)) {
            [$lineData, $total] = $this->calculateLines($input['rincian'], (int) $purchase->warung_id);
            $after['rincian'] = array_map(fn (array $line): array => [
                'nama_item' => $line['nama_item'],
                'qty' => $line['qty'],
                'satuan' => $line['satuan'],
                'harga_satuan' => $line['harga_satuan'],
                'subtotal' => $line['subtotal'],
            ], $lineData);
            $after['total'] = $total;
        }

        if ($before === $after) {
            throw ValidationException::withMessages([
                'alasan' => ['Tidak ada perubahan data untuk dicatat.'],
            ]);
        }

        $changes = ['total' => $after['total']];

        if (array_key_exists('tanggal', $input)) {
            $changes['tanggal'] = CarbonImmutable::parse((string) $input['tanggal'])->utc()->toDateTimeString();
        }

        if (array_key_exists('catatan', $input)) {
            $changes['catatan'] = $input['catatan'];
        }

        $purchase->forceFill($changes)->save();

        if (array_key_exists('rincian', $input)) {
            $purchase->rincian()->delete();
            $purchase->rincian()->createMany($lineData);
        }

        return $after;
    }

    /** @param array<string, mixed> $before
     * @return array<string, mixed>
     */
    private function cancelPurchase(Pembelian $purchase, array $before): array
    {
        $purchase->forceFill(['status' => 'dibatalkan'])->save();

        return [...$before, 'status' => 'dibatalkan'];
    }

    /** @param array<int, array<string, mixed>> $lines
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
    private function calculateLines(array $lines, int $warungId): array
    {
        $lineData = [];
        $total = BigDecimal::zero();

        foreach ($lines as $index => $line) {
            $quantity = $line['qty'] ?? null;
            $unitPrice = $line['harga_satuan'] ?? null;

            if (($quantity === null) !== ($unitPrice === null)) {
                throw ValidationException::withMessages([
                    "rincian.$index.qty" => ['Kuantitas dan harga satuan harus diisi bersama.'],
                    "rincian.$index.harga_satuan" => ['Kuantitas dan harga satuan harus diisi bersama.'],
                ]);
            }

            if ($quantity !== null && $unitPrice !== null) {
                $subtotal = BigDecimal::of((string) $unitPrice)
                    ->multipliedBy((string) $quantity)
                    ->toScale(2, RoundingMode::HalfUp);

                if (isset($line['subtotal']) && BigDecimal::of((string) $line['subtotal'])->compareTo($subtotal) !== 0) {
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

                $subtotal = BigDecimal::of((string) $line['subtotal'])->toScale(2, RoundingMode::HalfUp);
            }

            $this->assertDatabaseMoney($subtotal, "rincian.$index.subtotal");
            $total = $total->plus($subtotal);
            $this->assertDatabaseMoney($total, 'rincian');
            $lineData[] = [
                'warung_id' => $warungId,
                'nama_item' => $line['nama_item'],
                'qty' => $quantity,
                'satuan' => $line['satuan'] ?? null,
                'harga_satuan' => $unitPrice,
                'subtotal' => (string) $subtotal,
            ];
        }

        return [$lineData, (string) $total->toScale(2, RoundingMode::HalfUp)];
    }

    /** @return array<string, mixed> */
    private function snapshot(Pembelian $purchase): array
    {
        return [
            'status' => $purchase->status,
            'tanggal' => $purchase->tanggal?->utc()->toISOString(),
            'total' => (string) $purchase->total,
            'catatan' => $purchase->catatan,
            'rincian' => $purchase->rincian->map(fn ($line): array => [
                'nama_item' => $line->nama_item,
                'qty' => $line->qty,
                'satuan' => $line->satuan,
                'harga_satuan' => $line->harga_satuan,
                'subtotal' => $line->subtotal,
            ])->all(),
        ];
    }

    private function findByKey(User $actor, string $kind, string $idempotencyKey): ?PembelianKoreksi
    {
        $correction = PembelianKoreksi::query()
            ->where('warung_id', $actor->warung_id)
            ->where('user_id', $actor->getKey())
            ->where('jenis', $kind)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();

        if ($correction === null) {
            return null;
        }

        if ($this->idempotencyKeyWindow->hasExpired($correction->idempotency_expires_at)) {
            $this->idempotencyKeyWindow->release($correction);

            return null;
        }

        return $correction;
    }

    private function replayOrFail(PembelianKoreksi $correction, string $payloadHash): PembelianKoreksi
    {
        if (! hash_equals($correction->payload_hash, $payloadHash)) {
            throw new IdempotencyKeyConflictException;
        }

        return $correction;
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
