<?php

namespace App\Actions\Auth;

use App\Actions\Udev\SyncCustomerJob;
use App\Models\User;
use App\Models\Warung;
use App\Models\WarungCustomerSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RegisterWarung
{
    /**
     * @param  array{
     *     nama:string,
     *     timezone:string,
     *     alamat?:?string,
     *     telepon?:?string,
     *     data_consent:bool,
     *     data_notice_version:string,
     *     owner:array{nama:string,username:string,email?:?string,password:string}
     * }  $attributes
     * @return array{warung: Warung, owner: User, sync: WarungCustomerSync}
     */
    public function execute(array $attributes): array
    {
        $result = DB::transaction(function () use ($attributes): array {
            $ownerAttributes = $attributes['owner'];
            $noticeVersion = $attributes['data_notice_version'];
            $today = CarbonImmutable::now('UTC')->setTimezone($attributes['timezone'])->startOfDay();
            unset($attributes['owner'], $attributes['data_consent'], $attributes['data_notice_version']);

            $warung = Warung::query()->create([
                ...$attributes,
                'kode' => 'WRG-'.Str::ulid(),
                'tanggal_mulai' => $today->toDateString(),
                'tanggal_berakhir' => $today->addDays(2)->toDateString(),
                'aktif' => true,
                'pendaftaran_disetujui' => true,
            ]);

            $owner = $warung->users()->create([
                ...$ownerAttributes,
                'role' => 'owner',
                'aktif' => true,
            ]);

            $sync = WarungCustomerSync::query()->create([
                'warung_id' => $warung->id,
                'owner_user_id' => $owner->id,
                'external_customer_id' => 'larissama-'.Str::ulid(),
                'status' => 'pending',
                'notice_version' => $noticeVersion,
                'consented_at' => now(),
            ]);

            return [
                'warung' => $warung,
                'owner' => $owner,
                'sync' => $sync,
            ];
        });

        try {
            SyncCustomerJob::dispatch($result['sync']->id)->onQueue('udev-customers')->afterCommit();
            $result['sync']->forceFill(['last_enqueued_at' => now()])->save();
        } catch (\Throwable $exception) {
            Log::warning('UDEV customer queue dispatch failed', ['sync_id' => $result['sync']->id]);
        }

        return ['warung' => $result['warung'], 'owner' => $result['owner']];
    }
}
