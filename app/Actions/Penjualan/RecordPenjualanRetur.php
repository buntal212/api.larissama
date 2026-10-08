<?php

namespace App\Actions\Penjualan;

use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\PenjualanStateConflictException;
use App\Models\Penjualan;
use App\Models\PenjualanRetur;
use App\Models\User;
use App\Support\CanonicalRequestPayload;
use App\Support\IdempotencyKeyWindow;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPenjualanRetur
{
    public function __construct(
        private readonly CanonicalRequestPayload $payloadHasher,
        private readonly IdempotencyKeyWindow $idempotencyKeyWindow,
    ) {}

    /** @param array<string, mixed> $input */
    public function execute(User $actor, int $saleId, string $key, array $input, ?int $targetWarungId = null): PenjualanRetur
    {
        $warungId = $targetWarungId ?? (int) ($input['warung_id'] ?? $actor->warung_id);
        $superadminActor = $actor->role === 'superadmin';
        $hash = $this->payloadHasher->hash([
            'penjualan_id' => (string) $saleId,
            'request' => $input,
        ]);

        try {
            return DB::transaction(function () use ($actor, $warungId, $superadminActor, $saleId, $key, $input, $hash): PenjualanRetur {
                $existing = $this->findByKey($actor, $warungId, $key);
                if ($existing instanceof PenjualanRetur) {
                    return $this->replayOrFail($existing, $hash);
                }

                $sale = Penjualan::query()->where('warung_id', $warungId)->lockForUpdate()->findOrFail($saleId);
                $existing = $this->findByKey($actor, $warungId, $key);
                if ($existing instanceof PenjualanRetur) {
                    return $this->replayOrFail($existing, $hash);
                }

                if ($sale->status_pembayaran !== 'lunas') {
                    throw new PenjualanStateConflictException('PENJUALAN_BELUM_LUNAS', 'Pesanan yang belum lunas tidak dapat diretur.');
                }
                if ($sale->status === 'batal') {
                    throw new PenjualanStateConflictException('PENJUALAN_DIBATALKAN', 'Penjualan yang dibatalkan tidak dapat diretur.');
                }
                if ($sale->status === 'diretur_penuh') {
                    throw new PenjualanStateConflictException('PENJUALAN_SUDAH_DIRETUR_PENUH', 'Nilai penjualan sudah diretur seluruhnya.');
                }

                $returned = BigDecimal::of((string) PenjualanRetur::query()
                    ->where('warung_id', $warungId)
                    ->where('penjualan_id', $sale->getKey())
                    ->sum('nominal'));
                $saleTotal = BigDecimal::of((string) $sale->total);
                $amount = BigDecimal::of((string) $input['nominal'])->toScale(2);
                $remaining = $saleTotal->minus($returned)->toScale(2);

                if ($amount->compareTo($remaining) > 0) {
                    throw ValidationException::withMessages(['nominal' => ['Nominal retur melebihi saldo penjualan yang belum diretur.']]);
                }

                $event = PenjualanRetur::query()->create([
                    'warung_id' => $warungId,
                    'penjualan_id' => $sale->getKey(),
                    'user_id' => $superadminActor ? null : $actor->getKey(),
                    'superadmin_id' => $superadminActor ? $actor->getKey() : null,
                    'nominal' => (string) $amount,
                    'alasan' => $input['alasan'],
                    'idempotency_key' => $key,
                    'payload_hash' => $hash,
                    'idempotency_expires_at' => $this->idempotencyKeyWindow->expiresAt(),
                ]);

                $sale->forceFill([
                    'status' => $amount->compareTo($remaining) === 0 ? 'diretur_penuh' : 'diretur_sebagian',
                ])->save();

                return $event;
            }, 3);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->findByKey($actor, $warungId, $key);
            if (! $existing instanceof PenjualanRetur) {
                throw $exception;
            }

            return $this->replayOrFail($existing, $hash);
        }
    }

    private function findByKey(User $actor, int $warungId, string $key): ?PenjualanRetur
    {
        $event = PenjualanRetur::query()->where('warung_id', $warungId)
            ->where($actor->role === 'superadmin' ? 'superadmin_id' : 'user_id', $actor->getKey())->where('idempotency_key', $key)->lockForUpdate()->first();
        if ($event !== null && $this->idempotencyKeyWindow->hasExpired($event->idempotency_expires_at)) {
            $this->idempotencyKeyWindow->release($event);

            return null;
        }

        return $event;
    }

    private function replayOrFail(PenjualanRetur $event, string $hash): PenjualanRetur
    {
        if (! hash_equals((string) $event->payload_hash, $hash)) {
            throw new IdempotencyKeyConflictException;
        }

        return $event;
    }
}
