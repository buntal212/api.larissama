<?php

namespace App\Actions\Udev;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class SubscriptionInvoiceRegistry
{
    public const string ACTIVE_KEYS_CACHE_KEY = 'udev_invoice_registry:active_keys';
    public const string INVOICE_PREFIX = 'udev_invoice_registry:invoice:';
    public const string RECENT_COMPLETED_KEY = 'udev_invoice_registry:recent_completed';
    public const string REGISTRY_LOCK_KEY = 'udev_invoice_registry_lock';
    public const int CACHE_TTL_SECONDS = 604800; // 7 hari

    /**
     * Daftarkan invoice ke dalam registry untuk dipantau oleh scheduler.
     */
    public function register(string $invoiceNumber, int $warungId, string $externalCustomerId, string $status = 'pending'): array
    {
        $now = CarbonImmutable::now()->timestamp;
        $invoiceKey = self::INVOICE_PREFIX.$invoiceNumber;

        $existing = Cache::get($invoiceKey);
        if (is_array($existing)) {
            // Jika sudah ada dan belum selesai, perbarui status jika relevan
            $existing['status'] = $status;
            $existing['updated_at'] = $now;
            Cache::put($invoiceKey, $existing, self::CACHE_TTL_SECONDS);

            $this->ensureActiveKey($invoiceNumber);

            return $existing;
        }

        $record = [
            'invoice_number' => $invoiceNumber,
            'warung_id' => $warungId,
            'external_customer_id' => $externalCustomerId,
            'status' => $status,
            'reconciled' => false,
            'registered_at' => $now,
            'updated_at' => $now,
            'last_checked_at' => null,
            'next_check_at' => $now,
            'retry_count' => 0,
            'last_error' => null,
        ];

        Cache::put($invoiceKey, $record, self::CACHE_TTL_SECONDS);
        $this->ensureActiveKey($invoiceNumber);

        return $record;
    }

    /**
     * Perbarui status invoice dan jadwal pemeriksaan berikutnya (exponential backoff).
     */
    public function updateStatus(
        string $invoiceNumber,
        string $status,
        ?string $error = null,
        bool $incrementRetry = false,
    ): ?array {
        $invoiceKey = self::INVOICE_PREFIX.$invoiceNumber;
        $record = Cache::get($invoiceKey);
        if (! is_array($record)) {
            return null;
        }

        $now = CarbonImmutable::now()->timestamp;
        $record['status'] = $status;
        $record['updated_at'] = $now;
        $record['last_checked_at'] = $now;

        if ($error !== null) {
            $record['last_error'] = $error;
        }

        if ($incrementRetry) {
            $record['retry_count'] = ((int) ($record['retry_count'] ?? 0)) + 1;
            // Exponential backoff: 1m, 2m, 4m, 8m, max 30 menit
            $backoffSeconds = min(1800, 60 * (2 ** ($record['retry_count'] - 1)));
            $record['next_check_at'] = $now + $backoffSeconds;
        } else {
            $record['next_check_at'] = $now + 300; // 5 menit default
        }

        Cache::put($invoiceKey, $record, self::CACHE_TTL_SECONDS);

        return $record;
    }

    /**
     * Tandai invoice sudah berhasil direkonsiliasi ke warung dan keluarkan dari active list.
     */
    public function markReconciled(string $invoiceNumber): ?array
    {
        $invoiceKey = self::INVOICE_PREFIX.$invoiceNumber;
        $record = Cache::get($invoiceKey);
        if (! is_array($record)) {
            return null;
        }

        $now = CarbonImmutable::now()->timestamp;
        $record['status'] = 'paid';
        $record['reconciled'] = true;
        $record['updated_at'] = $now;
        $record['last_checked_at'] = $now;
        $record['reconciled_at'] = $now;
        $record['last_error'] = null;

        Cache::put($invoiceKey, $record, self::CACHE_TTL_SECONDS);

        $this->removeActiveKey($invoiceNumber);
        $this->addRecentCompleted($record);

        return $record;
    }

    /**
     * Tandai invoice kedaluwarsa atau dibatalkan dan keluarkan dari active list.
     */
    public function markExpiredOrCancelled(string $invoiceNumber, string $reason = 'expired'): ?array
    {
        $invoiceKey = self::INVOICE_PREFIX.$invoiceNumber;
        $record = Cache::get($invoiceKey);
        if (! is_array($record)) {
            return null;
        }

        $now = CarbonImmutable::now()->timestamp;
        $record['status'] = $reason;
        $record['updated_at'] = $now;
        $record['last_checked_at'] = $now;

        Cache::put($invoiceKey, $record, self::CACHE_TTL_SECONDS);
        $this->removeActiveKey($invoiceNumber);

        return $record;
    }

    /**
     * Dapatkan semua invoice aktif yang memerlukan pemantauan oleh scheduler.
     *
     * @return list<array<string, mixed>>
     */
    public function getActiveInvoices(): array
    {
        $keys = Cache::get(self::ACTIVE_KEYS_CACHE_KEY, []);
        if (! is_array($keys) || empty($keys)) {
            return [];
        }

        $activeInvoices = [];
        $validKeys = [];

        foreach ($keys as $invoiceNumber) {
            if (! is_string($invoiceNumber)) {
                continue;
            }

            $record = Cache::get(self::INVOICE_PREFIX.$invoiceNumber);
            if (is_array($record)) {
                $validKeys[] = $invoiceNumber;
                $activeInvoices[] = $record;
            }
        }

        // Jika ada kunci yang sudah hilang dari cache, perbarui active keys
        if (count($validKeys) !== count($keys)) {
            Cache::put(self::ACTIVE_KEYS_CACHE_KEY, array_values(array_unique($validKeys)), self::CACHE_TTL_SECONDS);
        }

        return $activeInvoices;
    }

    /**
     * Ambil data invoice tertentu dari registry.
     */
    public function getInvoice(string $invoiceNumber): ?array
    {
        $data = Cache::get(self::INVOICE_PREFIX.$invoiceNumber);

        return is_array($data) ? $data : null;
    }

    /**
     * Ambil ringkasan status registry untuk monitoring.
     */
    public function getSummary(): array
    {
        $active = $this->getActiveInvoices();
        $now = CarbonImmutable::now()->timestamp;

        $pendingCount = 0;
        $paidUnreconciledCount = 0;
        $delayedCount = 0;

        foreach ($active as $item) {
            $status = $item['status'] ?? 'pending';
            $reconciled = (bool) ($item['reconciled'] ?? false);
            $registeredAt = (int) ($item['registered_at'] ?? $now);
            $ageSeconds = $now - $registeredAt;

            if ($status === 'paid' && ! $reconciled) {
                $paidUnreconciledCount++;
            } elseif ($status === 'pending') {
                $pendingCount++;
            }

            // Keterlambatan lebih dari 15 menit (900 detik)
            if ($ageSeconds > 900 && ! $reconciled) {
                $delayedCount++;
            }
        }

        $recentCompleted = Cache::get(self::RECENT_COMPLETED_KEY, []);

        return [
            'total_active' => count($active),
            'pending_count' => $pendingCount,
            'paid_unreconciled_count' => $paidUnreconciledCount,
            'delayed_over_15m_count' => $delayedCount,
            'recent_completed_count' => is_array($recentCompleted) ? count($recentCompleted) : 0,
            'active_invoices' => $active,
            'recent_completed' => is_array($recentCompleted) ? array_slice($recentCompleted, 0, 10) : [],
        ];
    }

    private function ensureActiveKey(string $invoiceNumber): void
    {
        $lock = Cache::lock(self::REGISTRY_LOCK_KEY, 10);
        try {
            $lock->block(5, function () use ($invoiceNumber): void {
                $keys = Cache::get(self::ACTIVE_KEYS_CACHE_KEY, []);
                if (! is_array($keys)) {
                    $keys = [];
                }

                if (! in_array($invoiceNumber, $keys, true)) {
                    $keys[] = $invoiceNumber;
                    Cache::put(self::ACTIVE_KEYS_CACHE_KEY, array_values(array_unique($keys)), self::CACHE_TTL_SECONDS);
                }
            });
        } catch (\Throwable) {
            // Fallback non-blocking
            $keys = Cache::get(self::ACTIVE_KEYS_CACHE_KEY, []);
            if (! is_array($keys)) {
                $keys = [];
            }
            if (! in_array($invoiceNumber, $keys, true)) {
                $keys[] = $invoiceNumber;
                Cache::put(self::ACTIVE_KEYS_CACHE_KEY, array_values(array_unique($keys)), self::CACHE_TTL_SECONDS);
            }
        }
    }

    private function removeActiveKey(string $invoiceNumber): void
    {
        $lock = Cache::lock(self::REGISTRY_LOCK_KEY, 10);
        try {
            $lock->block(5, function () use ($invoiceNumber): void {
                $keys = Cache::get(self::ACTIVE_KEYS_CACHE_KEY, []);
                if (! is_array($keys)) {
                    return;
                }

                $filtered = array_values(array_filter($keys, fn ($k): bool => $k !== $invoiceNumber));
                Cache::put(self::ACTIVE_KEYS_CACHE_KEY, $filtered, self::CACHE_TTL_SECONDS);
            });
        } catch (\Throwable) {
            $keys = Cache::get(self::ACTIVE_KEYS_CACHE_KEY, []);
            if (is_array($keys)) {
                $filtered = array_values(array_filter($keys, fn ($k): bool => $k !== $invoiceNumber));
                Cache::put(self::ACTIVE_KEYS_CACHE_KEY, $filtered, self::CACHE_TTL_SECONDS);
            }
        }
    }

    private function addRecentCompleted(array $record): void
    {
        $recent = Cache::get(self::RECENT_COMPLETED_KEY, []);
        if (! is_array($recent)) {
            $recent = [];
        }

        array_unshift($recent, [
            'invoice_number' => $record['invoice_number'],
            'warung_id' => $record['warung_id'],
            'status' => 'paid',
            'reconciled_at' => $record['reconciled_at'] ?? CarbonImmutable::now()->timestamp,
        ]);

        // Simpan maksimal 50 riwayat terakhir
        $recent = array_slice($recent, 0, 50);
        Cache::put(self::RECENT_COMPLETED_KEY, $recent, self::CACHE_TTL_SECONDS);
    }
}
