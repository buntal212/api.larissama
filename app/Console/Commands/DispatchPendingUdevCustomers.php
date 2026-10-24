<?php

namespace App\Console\Commands;

use App\Actions\Udev\SyncCustomerJob;
use App\Actions\Udev\UdevCustomerClient;
use App\Models\WarungCustomerSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('udev:dispatch-pending-customers')]
#[Description('Queue pending Larissama customer registrations for UDEV')]
class DispatchPendingUdevCustomers extends Command
{
    public function handle(UdevCustomerClient $client): int
    {
        if (! $client->isConfigured()) {
            return self::SUCCESS;
        }

        WarungCustomerSync::query()
            ->where(function ($query): void {
                $query->where('status', 'pending')
                    ->orWhere('status', 'blocked')
                    ->orWhere(function ($retry): void {
                        $retry->where('status', 'retry_wait')->where('next_attempt_at', '<=', now());
                    });
            })
            ->where(function ($query): void {
                $query->whereNull('last_enqueued_at')->orWhere('last_enqueued_at', '<=', now()->subMinutes(5));
            })
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(function (WarungCustomerSync $sync): void {
                if ($sync->status === 'blocked') {
                    $sync->forceFill(['status' => 'pending', 'last_error_code' => null])->save();
                }

                try {
                    SyncCustomerJob::dispatch($sync->id)->onQueue('udev-customers');
                    $sync->forceFill(['last_enqueued_at' => now()])->save();
                } catch (\Throwable $exception) {
                    Log::warning('UDEV customer queue dispatch failed', ['sync_id' => $sync->id]);
                }
            });

        return self::SUCCESS;
    }
}
