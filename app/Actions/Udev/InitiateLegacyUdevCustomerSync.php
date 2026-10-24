<?php

namespace App\Actions\Udev;

use App\Models\User;
use App\Models\Warung;
use App\Models\WarungCustomerSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InitiateLegacyUdevCustomerSync
{
    /**
     * @return array{sync: WarungCustomerSync, created: bool}
     */
    public function execute(Warung $warung, User $owner, User $actor): array
    {
        $result = DB::transaction(function () use ($warung, $owner, $actor): array {
            $lockedWarung = Warung::query()->whereKey($warung->id)->lockForUpdate()->firstOrFail();
            $validOwner = User::query()
                ->whereKey($owner->id)
                ->where('warung_id', $lockedWarung->id)
                ->where('role', 'owner')
                ->exists();

            abort_unless($validOwner, 422, 'Owner harus berasal dari warung yang dipilih.');

            $existingSync = WarungCustomerSync::query()
                ->where('warung_id', $lockedWarung->id)
                ->first();

            if ($existingSync !== null) {
                return ['sync' => $existingSync, 'created' => false];
            }

            $sync = WarungCustomerSync::query()->create([
                'warung_id' => $lockedWarung->id,
                'owner_user_id' => $owner->id,
                'external_customer_id' => 'larissama-'.Str::ulid(),
                'status' => 'pending',
                'notice_version' => UdevCustomerConsentNotice::VERSION,
                'consented_at' => now(),
            ]);
            $sync->events()->create([
                'actor_user_id' => $actor->id,
                'event' => 'admin_recorded_verbal_consent',
                'created_at' => now(),
            ]);

            return ['sync' => $sync, 'created' => true];
        });

        if ($result['created']) {
            try {
                SyncCustomerJob::dispatch($result['sync']->id)->onQueue('udev-customers')->afterCommit();
                $result['sync']->forceFill(['last_enqueued_at' => now()])->save();
            } catch (\Throwable $exception) {
                Log::warning('UDEV customer queue dispatch failed', ['sync_id' => $result['sync']->id]);
            }
        }

        return ['sync' => $result['sync']->fresh(), 'created' => $result['created']];
    }
}
