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

            // Jika sync sudah tersinkron, kita pertahankan
            if ($existingSync->status === 'synced') {
                return ['sync' => $existingSync, 'enqueued' => false];
            }

            // Jika sedang aktif diproses oleh antrean/worker atau sudah ada di antrean saat pending, jangan ditimpa
            if ($existingSync->isActivelyProcessing() || ($existingSync->status === 'pending' && SyncCustomerJob::isQueued($existingSync->id))) {
                return ['sync' => $existingSync, 'enqueued' => false];
            }

            // Untuk warung yang belum tersinkron (pending/failed/blocked/retry_wait atau stale processing),
            // pertahankan external_customer_id yang sudah ada (termasuk format lama larissama-*).
            // Format deterministic WARUNG-{id} hanya diberikan jika external_customer_id belum ada.
            $needsUpdate = false;
            $updates = [];

            if ($existingSync->owner_user_id !== $owner->id) {
                $updates['owner_user_id'] = $owner->id;
                $needsUpdate = true;
            }

            if (empty($existingSync->external_customer_id)) {
                $updates['external_customer_id'] = $deterministicExternalId;
                $needsUpdate = true;
            }

            if (in_array($existingSync->status, ['failed', 'blocked', 'retry_wait'], true) || $existingSync->isStaleProcessing()) {
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
                $dispatched = SyncCustomerJob::dispatchSafely($result['sync']->id, afterCommit: true);
                if ($dispatched) {
                    $result['sync']->forceFill(['last_enqueued_at' => now()])->save();
                }
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
