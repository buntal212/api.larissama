<?php

namespace App\Actions\Udev;

use App\Models\Warung;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class SubscriptionReconcileService
{
    public const string RECENT_LOGS_KEY = 'udev_subscription_reconcile:recent_logs';
    public const int LOGS_TTL_SECONDS = 604800; // 7 hari

    public function __construct(
        protected UdevBillingClient $udev,
        protected SubscriptionInvoiceRegistry $registry,
    ) {}

    /**
     * Jalankan sinkronisasi masa aktif subscription warung berdasarkan data resmi UDEV.
     *
     * @return array{success: bool, updated: bool, subscription: array<string, mixed>|null, warung: array<string, string|null>|null, error_code?: string, error_message?: string}
     */
    public function reconcileWarung(int $warungId, string $externalCustomerId, ?string $associatedInvoiceNumber = null): array
    {
        // 1. Ambil data subscription resmi secara live dari UDEV
        $data = $this->udev->subscription($externalCustomerId);
        $subscription = $data['subscription'] ?? null;

        if (! is_array($subscription)
            || ! is_string($subscription['status'] ?? null)
            || ! is_string($subscription['starts_at'] ?? null)
            || ! is_string($subscription['expires_at'] ?? null)) {
            $this->recordSyncLog(
                $associatedInvoiceNumber ?? 'N/A',
                $warungId,
                'FAILED',
                'UDEV belum memiliki data subscription aktif yang dapat diterapkan.',
            );

            return [
                'success' => false,
                'updated' => false,
                'subscription' => null,
                'warung' => null,
                'error_code' => 'UDEV_SUBSCRIPTION_NOT_ACTIVE',
                'error_message' => 'UDEV belum memiliki data subscription aktif yang dapat diterapkan.',
            ];
        }

        // 2. Ambil warung dan validasi timezone
        $warung = Warung::query()->find($warungId);
        if (! $warung instanceof Warung
            || ! is_string($warung->timezone)
            || ! in_array($warung->timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            $this->recordSyncLog(
                $associatedInvoiceNumber ?? 'N/A',
                $warungId,
                'FAILED',
                'Zona waktu warung belum valid.',
            );

            return [
                'success' => false,
                'updated' => false,
                'subscription' => $subscription,
                'warung' => null,
                'error_code' => 'WARUNG_TIMEZONE_INVALID',
                'error_message' => 'Zona waktu warung belum valid.',
            ];
        }

        $startsAt = CarbonImmutable::parse($subscription['starts_at'])->setTimezone($warung->timezone)->toDateString();
        $udevExpiresAt = CarbonImmutable::parse($subscription['expires_at'])->setTimezone($warung->timezone)->toDateString();
        $changed = false;

        // 3. Database transaction dengan pessimistic locking (lockForUpdate)
        $updatedWarung = DB::transaction(function () use ($warung, $startsAt, $udevExpiresAt, &$changed): Warung {
            $lockedWarung = Warung::query()->lockForUpdate()->findOrFail($warung->getKey());
            $currentEnd = $lockedWarung->tanggal_berakhir?->toDateString();

            // Proteksi: masa aktif tidak boleh berkurang
            $effectiveEnd = $currentEnd !== null && $currentEnd > $udevExpiresAt ? $currentEnd : $udevExpiresAt;
            $currentStart = $lockedWarung->tanggal_mulai?->toDateString();
            $currentActive = (bool) $lockedWarung->aktif;

            if ($currentStart !== $startsAt || $currentEnd !== $effectiveEnd || ! $currentActive) {
                $lockedWarung->update([
                    'tanggal_mulai' => $startsAt,
                    'tanggal_berakhir' => $effectiveEnd,
                    'aktif' => true,
                ]);

                $lockedWarung->refresh();
                $changed = true;
            }

            return $lockedWarung;
        });

        // 4. Jika terkait dengan invoice tertentu, tandai reconciled di registry
        if ($associatedInvoiceNumber !== null) {
            $this->registry->markReconciled($associatedInvoiceNumber);
        }

        $message = $changed
            ? "Masa aktif warung berhasil diperbarui menjadi {$updatedWarung->tanggal_mulai?->toDateString()} s/d {$updatedWarung->tanggal_berakhir?->toDateString()}."
            : "Data warung sudah sinkron dengan UDEV ({$updatedWarung->tanggal_berakhir?->toDateString()}).";

        $this->recordSyncLog($associatedInvoiceNumber ?? 'N/A', $warungId, 'SUCCESS', $message);
        Log::info("[UDEV RECONCILE] Warung {$warungId} reconciled. Changed: ".($changed ? 'YES' : 'NO'));

        return [
            'success' => true,
            'updated' => $changed,
            'subscription' => $subscription,
            'warung' => [
                'tanggal_mulai' => $updatedWarung->tanggal_mulai?->toDateString(),
                'tanggal_berakhir' => $updatedWarung->tanggal_berakhir?->toDateString(),
                'aktif' => (bool) $updatedWarung->aktif,
            ],
        ];
    }

    /**
     * Catat log aktivitas sinkronisasi ke dalam cache untuk pemantauan dashboard Superadmin.
     */
    public function recordSyncLog(string $invoiceNumber, int $warungId, string $status, string $message): void
    {
        $recent = Cache::get(self::RECENT_LOGS_KEY, []);
        if (! is_array($recent)) {
            $recent = [];
        }

        array_unshift($recent, [
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
            'invoice_number' => $invoiceNumber,
            'warung_id' => $warungId,
            'status' => $status,
            'message' => $message,
        ]);

        $recent = array_slice($recent, 0, 50);
        Cache::put(self::RECENT_LOGS_KEY, $recent, self::LOGS_TTL_SECONDS);
    }

    /**
     * Ambil riwayat log sinkronisasi terakhir.
     *
     * @return list<array<string, mixed>>
     */
    public function getRecentLogs(int $limit = 20): array
    {
        $logs = Cache::get(self::RECENT_LOGS_KEY, []);
        if (! is_array($logs)) {
            return [];
        }

        return array_slice($logs, 0, $limit);
    }
}
