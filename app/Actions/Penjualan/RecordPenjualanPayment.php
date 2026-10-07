<?php

namespace App\Actions\Penjualan;

use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\PenjualanStateConflictException;
use App\Models\Penjualan;
use App\Models\User;
use App\Support\CanonicalRequestPayload;
use App\Support\IdempotencyKeyWindow;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPenjualanPayment
{
    private const string MAXIMUM_MONEY = '9999999999999.99';

    public function __construct(
        private readonly CanonicalRequestPayload $payloadHasher,
        private readonly IdempotencyKeyWindow $idempotencyKeyWindow,
    ) {}

    /** @param array<string, mixed> $input */
    public function execute(User $actor, int $saleId, string $key, array $input): Penjualan
    {
        $hash = $this->payloadHasher->hash([
            'penjualan_id' => (string) $saleId,
            'request' => $input,
        ]);

        try {
            return DB::transaction(function () use ($actor, $saleId, $key, $input, $hash): Penjualan {
                $sale = Penjualan::query()
                    ->where('warung_id', $actor->warung_id)
                    ->lockForUpdate()
                    ->findOrFail($saleId);

                $existing = Penjualan::query()
                    ->where('warung_id', $actor->warung_id)
                    ->where('pembayaran_user_id', $actor->getKey())
                    ->where('pembayaran_idempotency_key', $key)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ($this->idempotencyKeyWindow->hasExpired($existing->pembayaran_idempotency_expires_at)) {
                        $existing->forceFill([
                            'pembayaran_idempotency_key' => null,
                            'pembayaran_payload_hash' => null,
                            'pembayaran_idempotency_expires_at' => null,
                        ])->save();
                    } else {
                        if (! hash_equals((string) $existing->pembayaran_payload_hash, $hash)) {
                            throw new IdempotencyKeyConflictException;
                        }

                        return $existing->load('rincian', 'koreksi', 'retur');
                    }
                }

                if ($sale->status !== 'menunggu_pembayaran' || $sale->status_pembayaran !== 'belum_lunas') {
                    throw new PenjualanStateConflictException('PENJUALAN_SUDAH_DIPROSES', 'Pesanan sudah dibayar atau tidak lagi dapat dibayar.');
                }

                $amount = BigDecimal::of((string) $input['bayar'])->toScale(2, RoundingMode::HalfUp);
                $total = BigDecimal::of((string) $sale->total);
                $method = (string) $input['metode_pembayaran'];

                if ($amount->compareTo(BigDecimal::of(self::MAXIMUM_MONEY)) > 0) {
                    throw ValidationException::withMessages(['bayar' => ['Nominal melebihi kapasitas DECIMAL(15,2).']]);
                }

                if ($method === 'cash') {
                    if ($amount->compareTo($total) < 0) {
                        throw ValidationException::withMessages(['bayar' => ['Pembayaran tunai harus sama dengan atau lebih besar dari total.']]);
                    }

                    $change = $amount->minus($total)->toScale(2, RoundingMode::HalfUp);
                } else {
                    if ($amount->compareTo($total) !== 0) {
                        throw ValidationException::withMessages(['bayar' => ['Pembayaran QRIS/transfer harus sama dengan total.']]);
                    }

                    $change = BigDecimal::zero()->toScale(2);
                }

                $sale->forceFill([
                    'bayar' => (string) $amount,
                    'kembalian' => (string) $change,
                    'metode_pembayaran' => $method,
                    'dibayar_pada' => CarbonImmutable::now('UTC')->toDateTimeString(),
                    'pembayaran_user_id' => $actor->getKey(),
                    'pembayaran_idempotency_key' => $key,
                    'pembayaran_payload_hash' => $hash,
                    'pembayaran_idempotency_expires_at' => $this->idempotencyKeyWindow->expiresAt(),
                    'status' => 'selesai',
                    'status_pembayaran' => 'lunas',
                ])->save();

                return $sale->load('rincian', 'koreksi', 'retur');
            }, 3);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = Penjualan::query()
                ->where('warung_id', $actor->warung_id)
                ->where('pembayaran_user_id', $actor->getKey())
                ->where('pembayaran_idempotency_key', $key)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            if (! hash_equals((string) $existing->pembayaran_payload_hash, $hash)) {
                throw new IdempotencyKeyConflictException;
            }

            return $existing->load('rincian', 'koreksi', 'retur');
        }
    }
}
