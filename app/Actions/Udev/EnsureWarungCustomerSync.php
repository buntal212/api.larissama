<?php

namespace App\Actions\Udev;

use App\Models\User;
use App\Models\Warung;
use App\Models\WarungCustomerSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnsureWarungCustomerSync
{
    /**
     * Pastikan setiap warung memiliki catatan sinkronisasi pelanggan UDEV
     * dengan mapping 1 warung = 1 pelanggan menggunakan format deterministic 'WARUNG-{id}'.
     *
     * @return array{sync: ?WarungCustomerSync, enqueued: bool}
     */
    public function execute(Warung $warung, ?User $actor = null): array
    {
        $result = DB::transaction(function () use ($warung, $actor): array {
            $lockedWarung = Warung::query()->whereKey($warung->getKey())->lockForUpdate()->firstOrFail();

            /** @var User|null $owner */
            $owner = $lockedWarung->users()
                ->where('role', 'owner')
                ->orderBy('id')
                ->first();

            if ($owner === null) {
                return ['sync' => null, 'enqueued' => false];
            }

            $deterministicExternalId = WarungCustomerSync::generateExternalCustomerId($lockedWarung->id);

            /** @var WarungCustomerSync|null $existingSync */
            $existingSync = WarungCustomerSync::query()
                ->where('warung_id', $lockedWarung->id)
                ->lockForUpdate()
                ->first();

            if ($existingSync === null) {
                $sync = WarungCustomerSync::query()->create([
                    'warung_id' => $lockedWarung->id,
                    'owner_user_id' => $owner->id,
                    'external_customer_id' => $deterministicExternalId,
                    'status' => 'pending',
                    'notice_version' => UdevCustomerConsentNotice::VERSION,
                    'consented_at' => now(),
                ]);

                $sync->events()->create([
                    'actor_user_id' => $actor?->id,
                    'event' => 'auto_registered',
                    'created_at' => now(),
                ]);

                return ['sync' => $sync, 'enqueued' => true];
            }

            // Jika sync sudah tersinkron, kita pertahankan kecuali external_id belum mengikuti standar
            if ($existingSync->status === 'synced') {
                return ['sync' => $existingSync, 'enqueued' => false];
            }

            // Untuk warung yang belum tersinkron (pending/failed/blocked/retry_wait),
            // pastikan ID eksternal menggunakan mapping deterministic warung ID
            $needsUpdate = false;
            $updates = [];

            if ($existingSync->owner_user_id !== $owner->id) {
                $updates['owner_user_id'] = $owner->id;
                $needsUpdate = true;
            }

            if (empty($existingSync->external_customer_id) || str_starts_with($existingSync->external_customer_id, 'larissama-')) {
                $updates['external_customer_id'] = $deterministicExternalId;
                $needsUpdate = true;
            }

            if (in_array($existingSync->status, ['failed', 'blocked', 'retry_wait'], true)) {
                $updates['status'] = 'pending';
                $updates['attempt_count'] = 0;
                $updates['next_attempt_at'] = null;
                $updates['last_error_code'] = null;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $existingSync->forceFill($updates)->save();
                $existingSync->events()->create([
                    'actor_user_id' => $actor?->id,
                    'event' => 'auto_requeued',
                    'created_at' => now(),
                ]);
            }

            return ['sync' => $existingSync, 'enqueued' => true];
        });

        if ($result['enqueued'] && $result['sync'] instanceof WarungCustomerSync) {
            try {
                SyncCustomerJob::dispatch($result['sync']->id)->onQueue('udev-customers')->afterCommit();
                $result['sync']->forceFill(['last_enqueued_at' => now()])->save();
            } catch (\Throwable $exception) {
                Log::warning('UDEV customer queue dispatch failed', ['sync_id' => $result['sync']->id]);
            }
        }

        return [
            'sync' => $result['sync']?->fresh(),
            'enqueued' => $result['enqueued'],
        ];
    }
}
