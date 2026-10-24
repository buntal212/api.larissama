<?php

namespace App\Actions\Udev;

use App\Models\WarungCustomerSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 15;

    public function __construct(public int $syncId) {}

    public function middleware(): array
    {
        return [
            (new RateLimited('udev-customers'))->dontRelease(),
            (new WithoutOverlapping('udev-customer-'.$this->syncId))->releaseAfter(15)->expireAfter(60),
        ];
    }

    public function handle(UdevCustomerClient $client): void
    {
        $sync = WarungCustomerSync::query()->with(['warung', 'owner'])->find($this->syncId);

        if ($sync === null || in_array($sync->status, ['synced', 'failed', 'blocked'], true)) {
            return;
        }

        if ($sync->next_attempt_at?->isFuture()) {
            $this->release($sync->next_attempt_at->diffInSeconds(now()) + 1);

            return;
        }

        if (! $client->isConfigured()) {
            $this->record($sync, 'blocked', 'not_configured');

            return;
        }

        $sync->forceFill([
            'attempt_count' => $sync->attempt_count + 1,
            'last_attempt_at' => now(),
            'last_http_status' => null,
            'last_request_id' => null,
            'next_attempt_at' => null,
        ])->save();

        try {
            $response = $client->upsert($sync);
        } catch (ConnectionException $exception) {
            $this->record($sync, 'retry_wait', 'network_error');
            $this->retryIfAllowed($sync, 60);

            return;
        }

        $sync->forceFill([
            'last_http_status' => $response->status(),
            'last_request_id' => substr((string) $response->header('X-Request-Id'), 0, 100) ?: null,
        ])->save();

        $data = $response->json('data');
        if (in_array($response->status(), [200, 201], true)
            && is_array($data)
            && ($data['external_customer_id'] ?? null) === $sync->external_customer_id
            && is_numeric($data['id'] ?? null)) {
            $sync->forceFill([
                'status' => 'synced',
                'udev_customer_id' => (int) $data['id'],
                'last_success_at' => now(),
                'last_error_code' => null,
                'next_attempt_at' => null,
            ])->save();
            $sync->events()->create(['event' => 'synced', 'http_status' => $response->status(), 'created_at' => now()]);

            return;
        }

        $errorCode = $response->json('error_code');
        $safeError = is_string($errorCode) && preg_match('/^[a-z_]{1,60}$/', $errorCode) ? $errorCode : 'http_'.$response->status();
        $retryable = $response->status() === 408 || $response->status() === 429 || $response->serverError()
            || (in_array($response->status(), [200, 201], true) && $safeError === 'http_'.$response->status());
        $this->record($sync, $retryable ? 'retry_wait' : 'failed', $safeError);

        if ($retryable) {
            $retryAfter = $response->header('Retry-After');
            $fallback = [10, 60, 300, 900, 1800][$sync->attempt_count - 1] ?? 1800;
            $retryAfterSeconds = is_numeric($retryAfter) ? (int) $retryAfter : null;
            if ($retryAfterSeconds === null && is_string($retryAfter) && strtotime($retryAfter) !== false) {
                $retryAfterSeconds = strtotime($retryAfter) - now()->timestamp;
            }
            $delay = $response->status() === 429 && $retryAfterSeconds !== null
                ? max(1, min(3600, $retryAfterSeconds))
                : $fallback;
            $this->retryIfAllowed($sync, $delay);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $sync = WarungCustomerSync::query()->find($this->syncId);
        if ($sync !== null && $sync->status !== 'synced') {
            $this->record($sync, 'failed', 'job_failed');
        }
    }

    private function retryIfAllowed(WarungCustomerSync $sync, int $delay): void
    {
        if ($sync->attempt_count >= 5) {
            $this->record($sync, 'failed', $sync->last_error_code ?? 'retry_exhausted');

            return;
        }

        $sync->forceFill(['next_attempt_at' => now()->addSeconds($delay)])->save();
        $this->release($delay);
    }

    private function record(WarungCustomerSync $sync, string $status, string $errorCode): void
    {
        $sync->forceFill(['status' => $status, 'last_error_code' => $errorCode])->save();
        $sync->events()->create([
            'event' => $status,
            'http_status' => $sync->last_http_status,
            'error_code' => $errorCode,
            'created_at' => now(),
        ]);
    }
}
