<?php

namespace App\Actions\Udev;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class UdevSyncLimiter
{
    public const int MAX_REQUESTS_PER_MINUTE = 10;
    public const int CIRCUIT_FAILURE_THRESHOLD = 5;
    public const int CIRCUIT_COOLDOWN_SECONDS = 120; // 2 menit

    public const string STATE_CLOSED = 'CLOSED';
    public const string STATE_OPEN = 'OPEN';
    public const string STATE_HALF_OPEN = 'HALF_OPEN';

    public const string LIMITER_WINDOW_PREFIX = 'udev_rate_limiter:window:';
    public const string CB_STATE_KEY = 'udev_circuit_breaker:state';
    public const string CB_FAILURES_KEY = 'udev_circuit_breaker:consecutive_failures';
    public const string CB_COOLDOWN_KEY = 'udev_circuit_breaker:cooldown_until';
    public const string CB_PROBING_KEY = 'udev_circuit_breaker:probing';

    /**
     * Memeriksa apakah request UDEV dapat dijalankan berdasarkan rate limiter dan circuit breaker.
     */
    public function canExecute(string $purpose = 'scheduler', bool $isHighPriority = false): bool
    {
        $now = CarbonImmutable::now()->timestamp;

        // 1. Evaluasi Circuit Breaker untuk proses otomatis (scheduler & recovery)
        $isManual = ($purpose === 'manual');
        if (! $isManual) {
            $circuitState = $this->evaluateCircuitState($now);
            if ($circuitState === self::STATE_OPEN) {
                return false;
            }

            if ($circuitState === self::STATE_HALF_OPEN) {
                // Di status HALF-OPEN, hanya 1 request probe yang diizinkan sekaligus
                $lock = Cache::lock(self::CB_PROBING_KEY, 10);
                if (! $lock->get()) {
                    return false;
                }
            }
        }

        // 2. Evaluasi Kuota Rate Limiter (maksimal 10 request / menit)
        $currentCount = $this->getCurrentMinuteCount();
        if ($currentCount >= self::MAX_REQUESTS_PER_MINUTE) {
            return false;
        }

        // Prioritas alokasi:
        // - High priority (paid reconcile / manual) dapat memakai sisa kapasitas hingga 10
        // - Pending check hanya boleh memakai jika pemakaian saat ini < 7 (cadangan 3 untuk paid reconcile)
        // - Recovery hanya boleh memakai jika pemakaian saat ini < 5 (cadangan 5 untuk scheduler)
        if ($isHighPriority || $isManual) {
            return true;
        }

        if ($purpose === 'recovery') {
            return $currentCount < 5;
        }

        // Default scheduler pending checks
        return $currentCount < 7;
    }

    /**
     * Catat 1 request yang dikirim ke UDEV.
     */
    public function recordRequest(): int
    {
        $minuteKey = $this->getCurrentMinuteKey();
        $count = (int) Cache::get($minuteKey, 0) + 1;
        Cache::put($minuteKey, $count, 120);

        return $count;
    }

    /**
     * Catat keberhasilan pemanggilan UDEV. Reset kegagalan beruntun pada circuit breaker.
     */
    public function recordSuccess(): void
    {
        $state = Cache::get(self::CB_STATE_KEY, self::STATE_CLOSED);
        if ($state === self::STATE_HALF_OPEN || $state === self::STATE_OPEN) {
            Log::info('[UDEV CIRCUIT BREAKER] Probe succeeded or service restored. Resetting circuit to CLOSED.');
        }

        Cache::put(self::CB_STATE_KEY, self::STATE_CLOSED, 86400);
        Cache::put(self::CB_FAILURES_KEY, 0, 86400);
        Cache::forget(self::CB_COOLDOWN_KEY);
        Cache::lock(self::CB_PROBING_KEY)->forceRelease();
    }

    /**
     * Catat kegagalan pemanggilan UDEV. Jika kegagalan teknis beruntun mencapai threshold, buka circuit.
     */
    public function recordFailure(Throwable $exception): void
    {
        $now = CarbonImmutable::now()->timestamp;

        // Periksa apakah ini rate limit HTTP 429 upstream dengan Retry-After
        if ($exception instanceof UdevBillingRequestException && $exception->upstreamStatus === 429) {
            $retryAfterSeconds = (int) ($exception->retryAfter ?? 60);
            if ($retryAfterSeconds <= 0) {
                $retryAfterSeconds = 60;
            }
            Cache::put(self::CB_STATE_KEY, self::STATE_OPEN, 86400);
            Cache::put(self::CB_COOLDOWN_KEY, $now + $retryAfterSeconds, 86400);
            Log::warning("[UDEV CIRCUIT BREAKER] Received HTTP 429 from UDEV. Circuit OPEN until {$retryAfterSeconds}s from now.");

            return;
        }

        // Hanya hitung kegagalan teknis (5xx, timeout, network failure)
        $isTechnical = true;
        if ($exception instanceof UdevBillingRequestException) {
            $status = $exception->upstreamStatus;
            // 404, 422 adalah penolakan bisnis, bukan kegagalan teknis gateway
            if ($status === 404 || $status === 422) {
                $isTechnical = false;
            }
        }

        if (! $isTechnical) {
            return;
        }

        $state = Cache::get(self::CB_STATE_KEY, self::STATE_CLOSED);

        if ($state === self::STATE_HALF_OPEN) {
            // Probe di HALF-OPEN gagal, langsung buka kembali circuit selama 2 menit
            Cache::put(self::CB_STATE_KEY, self::STATE_OPEN, 86400);
            Cache::put(self::CB_COOLDOWN_KEY, $now + self::CIRCUIT_COOLDOWN_SECONDS, 86400);
            Cache::lock(self::CB_PROBING_KEY)->forceRelease();
            Log::warning('[UDEV CIRCUIT BREAKER] HALF-OPEN probe failed. Circuit reopened for 120s.');

            return;
        }

        $failures = (int) Cache::get(self::CB_FAILURES_KEY, 0) + 1;
        Cache::put(self::CB_FAILURES_KEY, $failures, 86400);

        if ($failures >= self::CIRCUIT_FAILURE_THRESHOLD) {
            Cache::put(self::CB_STATE_KEY, self::STATE_OPEN, 86400);
            Cache::put(self::CB_COOLDOWN_KEY, $now + self::CIRCUIT_COOLDOWN_SECONDS, 86400);
            Log::warning("[UDEV CIRCUIT BREAKER] Circuit opened after {$failures} consecutive technical failures. Cooldown 120 seconds.");
        }
    }

    /**
     * Dapatkan ringkasan status rate limiter & circuit breaker.
     */
    public function getState(): array
    {
        $now = CarbonImmutable::now()->timestamp;
        $state = $this->evaluateCircuitState($now);
        $cooldownUntil = (int) Cache::get(self::CB_COOLDOWN_KEY, 0);
        $cooldownRemaining = max(0, $cooldownUntil - $now);

        return [
            'circuit_breaker' => [
                'state' => $state,
                'consecutive_failures' => (int) Cache::get(self::CB_FAILURES_KEY, 0),
                'threshold' => self::CIRCUIT_FAILURE_THRESHOLD,
                'cooldown_remaining_seconds' => $cooldownRemaining,
            ],
            'rate_limiter' => [
                'requests_current_minute' => $this->getCurrentMinuteCount(),
                'max_per_minute' => self::MAX_REQUESTS_PER_MINUTE,
                'remaining_this_minute' => max(0, self::MAX_REQUESTS_PER_MINUTE - $this->getCurrentMinuteCount()),
            ],
        ];
    }

    /**
     * Reset manual status limiter dan circuit breaker (untuk testing atau recovery Superadmin).
     */
    public function reset(): void
    {
        Cache::put(self::CB_STATE_KEY, self::STATE_CLOSED, 86400);
        Cache::put(self::CB_FAILURES_KEY, 0, 86400);
        Cache::forget(self::CB_COOLDOWN_KEY);
        Cache::lock(self::CB_PROBING_KEY)->forceRelease();
        Cache::forget($this->getCurrentMinuteKey());
    }

    private function evaluateCircuitState(int $now): string
    {
        $state = Cache::get(self::CB_STATE_KEY, self::STATE_CLOSED);

        if ($state === self::STATE_OPEN) {
            $cooldownUntil = (int) Cache::get(self::CB_COOLDOWN_KEY, 0);
            if ($now >= $cooldownUntil) {
                // Cooldown 2 menit sudah selesai, beralih ke HALF_OPEN untuk 1 probe
                Cache::put(self::CB_STATE_KEY, self::STATE_HALF_OPEN, 86400);

                return self::STATE_HALF_OPEN;
            }

            return self::STATE_OPEN;
        }

        return $state;
    }

    public function getCurrentMinuteCount(): int
    {
        return (int) Cache::get($this->getCurrentMinuteKey(), 0);
    }

    private function getCurrentMinuteKey(): string
    {
        return self::LIMITER_WINDOW_PREFIX.CarbonImmutable::now('UTC')->format('YmdHi');
    }
}
