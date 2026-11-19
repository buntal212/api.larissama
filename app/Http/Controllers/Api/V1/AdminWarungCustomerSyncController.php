<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Udev\EnsureWarungCustomerSync;
use App\Actions\Udev\InitiateLegacyUdevCustomerSync;
use App\Actions\Udev\SyncCustomerJob;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminInitiateUdevCustomerSyncRequest;
use App\Models\Warung;
use App\Models\WarungCustomerSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AdminWarungCustomerSyncController extends Controller
{
    public function initiate(
        AdminInitiateUdevCustomerSyncRequest $request,
        int $id,
        InitiateLegacyUdevCustomerSync $initiate,
    ): JsonResponse {
        $warung = Warung::query()->findOrFail($id);
        Gate::authorize('update', $warung);
        $owner = $warung->users()
            ->whereKey($request->validated('owner_user_id'))
            ->where('role', 'owner')
            ->firstOrFail();
        $result = $initiate->execute($warung, $owner, $request->user());

        return response()->json(['data' => $this->data($result['sync'])], $result['created'] ? 202 : 200);
    }

    public function show(int $id): JsonResponse
    {
        $warung = Warung::query()->findOrFail($id);
        Gate::authorize('view', $warung);
        $sync = WarungCustomerSync::query()->where('warung_id', $warung->id)->firstOrFail();

        return response()->json(['data' => $this->data($sync)]);
    }

    public function retry(int $id): JsonResponse
    {
        $warung = Warung::query()->findOrFail($id);
        Gate::authorize('update', $warung);
        $sync = WarungCustomerSync::query()->where('warung_id', $warung->id)->firstOrFail();

        if (! in_array($sync->status, ['failed', 'blocked', 'retry_wait', 'pending'], true) && ! $sync->isStaleProcessing()) {
            abort(409, 'Hanya sinkronisasi pending, gagal, atau terblokir yang dapat di-retry.');
        }

        if (empty($sync->external_customer_id)) {
            $sync->external_customer_id = WarungCustomerSync::generateExternalCustomerId($sync->warung_id);
        }

        $sync->forceFill([
            'status' => 'pending',
            'attempt_count' => 0,
            'next_attempt_at' => null,
            'last_error_code' => null,
            'last_http_status' => null,
            'last_request_id' => null,
        ])->save();
        $sync->events()->create([
            'actor_user_id' => request()->user()->id,
            'event' => 'manual_retry',
            'created_at' => now(),
        ]);

        try {
            $dispatched = SyncCustomerJob::dispatchSafely($sync->id);
            if ($dispatched) {
                $sync->forceFill(['last_enqueued_at' => now()])->save();
            }
        } catch (\Throwable $exception) {
            Log::warning('UDEV customer queue dispatch failed', ['sync_id' => $sync->id]);
        }

        return response()->json(['data' => $this->data($sync->fresh())], 202);
    }

    public function resyncAll(Request $request, EnsureWarungCustomerSync $ensureSync): JsonResponse
    {
        Gate::authorize('viewAny', Warung::class);

        $warungs = Warung::query()
            ->where(function ($query): void {
                $query->whereDoesntHave('customerSync')
                    ->orWhereHas('customerSync', function ($syncQuery): void {
                        $syncQuery->whereIn('status', ['pending', 'failed', 'blocked', 'retry_wait', 'processing']);
                    });
            })
            ->get();

        $enqueuedCount = 0;
        foreach ($warungs as $warung) {
            $res = $ensureSync->execute($warung, $request->user());
            if ($res['enqueued']) {
                $enqueuedCount++;
            }
        }

        return response()->json([
            'data' => [
                'total_warungs' => $warungs->count(),
                'enqueued_count' => $enqueuedCount,
            ],
            'message' => "Berhasil memproses {$enqueuedCount} warung untuk sinkronisasi UDEV.",
        ], 200);
    }

    private function data(WarungCustomerSync $sync): array
    {
        return [
            'warung_id' => $sync->warung_id,
            'owner_user_id' => $sync->owner_user_id,
            'external_customer_id' => $sync->external_customer_id,
            'udev_customer_id' => $sync->udev_customer_id,
            'status' => $sync->status,
            'attempt_count' => $sync->attempt_count,
            'last_http_status' => $sync->last_http_status,
            'last_request_id' => $sync->last_request_id,
            'last_error_code' => $sync->last_error_code,
            'last_attempt_at' => $sync->last_attempt_at,
            'last_success_at' => $sync->last_success_at,
            'next_attempt_at' => $sync->next_attempt_at,
            'notice_version' => $sync->notice_version,
            'consented_at' => $sync->consented_at,
            'events' => $sync->events()->latest('id')->limit(20)->get(['id', 'actor_user_id', 'event', 'http_status', 'error_code', 'created_at']),
        ];
    }
}
