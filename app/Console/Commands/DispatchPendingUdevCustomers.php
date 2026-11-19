<?php

namespace App\Console\Commands;

use App\Actions\Udev\EnsureWarungCustomerSync;
use App\Actions\Udev\SyncCustomerJob;
use App\Actions\Udev\UdevCustomerClient;
use App\Models\Warung;
use App\Models\WarungCustomerSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('udev:dispatch-pending-customers')]
#[Description('Queue pending Larissama customer registrations for UDEV and discover unsynced warungs')]
class DispatchPendingUdevCustomers extends Command
{
    public function handle(UdevCustomerClient $client, EnsureWarungCustomerSync $ensureSync): int
    {
        if (! $client->isConfigured()) {
            return self::SUCCESS;
        }

        // Temukan dan daftarkan warung yang belum memiliki catatan sinkronisasi
        Warung::query()
            ->whereDoesntHave('customerSync')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(function (Warung $warung) use ($ensureSync): void {
                $ensureSync->execute($warung);
            });

        // Antrekan warung yang pending/blocked/retry_wait atau stale processing
        WarungCustomerSync::query()
            ->where(function ($query): void {
                $query->where('status', 'pending')
                    ->orWhere('status', 'blocked')
                    ->orWhere(function ($retry): void {
                        $retry->where('status', 'retry_wait')->where('next_attempt_at', '<=', now());
                    })
                    ->orWhere(function ($proc): void {
                        $proc->where('status', 'processing')
                            ->where(function ($stale): void {
                                $stale->whereNull('last_attempt_at')
                                    ->orWhere('last_attempt_at', '<=', now()->subMinutes(2));
                            });
                    });
            })
            ->where(function ($query): void {
                $query->whereNull('last_enqueued_at')->orWhere('last_enqueued_at', '<=', now()->subMinutes(5));
            })
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(function (WarungCustomerSync $sync): void {
                if ($sync->status === 'blocked' || $sync->status === 'processing') {
                    $sync->forceFill(['status' => 'pending', 'last_error_code' => null])->save();
                }

                try {
                    SyncCustomerJob::dispatchSafely($sync->id);
                    $sync->forceFill(['last_enqueued_at' => now()])->save();
                } catch (\Throwable $exception) {
                    Log::warning('UDEV customer queue dispatch failed', ['sync_id' => $sync->id]);
                }
            });

        return self::SUCCESS;
    }
}
