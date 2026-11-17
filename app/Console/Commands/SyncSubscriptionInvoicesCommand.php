<?php

namespace App\Console\Commands;

use App\Actions\Udev\SubscriptionInvoiceRegistry;
use App\Actions\Udev\SubscriptionReconcileService;
use App\Actions\Udev\UdevBillingClient;
use App\Actions\Udev\UdevSyncLimiter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('udev:sync-invoices')]
#[Description('Synchronize pending and paid subscription invoices with UDEV Billing')]
class SyncSubscriptionInvoicesCommand extends Command
{
    public function handle(
        UdevBillingClient $udev,
        SubscriptionInvoiceRegistry $registry,
        SubscriptionReconcileService $reconcileService,
        UdevSyncLimiter $limiter,
    ): int {
        $now = CarbonImmutable::now()->timestamp;
        $activeInvoices = $registry->getActiveInvoices();

        if (empty($activeInvoices)) {
            $this->out('line', 'No active subscription invoices to synchronize.');

            return self::SUCCESS;
        }

        // Urutkan prioritas:
        // 1. Paid tapi belum reconciled (prioritas utama)
        // 2. Pending invoice
        usort($activeInvoices, function (array $a, array $b): int {
            $aPaidUnreconciled = (($a['status'] ?? '') === 'paid' && ! ($a['reconciled'] ?? false));
            $bPaidUnreconciled = (($b['status'] ?? '') === 'paid' && ! ($b['reconciled'] ?? false));

            if ($aPaidUnreconciled && ! $bPaidUnreconciled) {
                return -1;
            }
            if (! $aPaidUnreconciled && $bPaidUnreconciled) {
                return 1;
            }

            return ($a['next_check_at'] ?? 0) <=> ($b['next_check_at'] ?? 0);
        });

        $processedCount = 0;
        $reconciledCount = 0;

        foreach ($activeInvoices as $item) {
            $invoiceNumber = (string) ($item['invoice_number'] ?? '');
            $warungId = (int) ($item['warung_id'] ?? 0);
            $externalCustomerId = (string) ($item['external_customer_id'] ?? '');
            $nextCheckAt = (int) ($item['next_check_at'] ?? 0);
            $isPaidUnreconciled = (($item['status'] ?? '') === 'paid' && ! ($item['reconciled'] ?? false));

            if ($invoiceNumber === '' || $warungId <= 0 || $externalCustomerId === '') {
                continue;
            }

            // Hormati exponential backoff (kecuali paid belum reconcile)
            if (! $isPaidUnreconciled && $nextCheckAt > $now) {
                continue;
            }

            // Periksa alokasi rate limiter & circuit breaker
            if (! $limiter->canExecute('scheduler', $isPaidUnreconciled)) {
                $this->out('warn', 'UDEV sync limiter capacity reached or circuit open. Ending current sync cycle.');
                Log::info('[UDEV SYNC] Scheduler halting due to limiter or circuit breaker status.', $limiter->getState());
                break;
            }

            // Kunci per invoice dan per warung
            $invoiceLock = Cache::lock("udev_sync_invoice_lock:{$invoiceNumber}", 15);
            $warungLock = Cache::lock("udev_sync_warung_lock:{$warungId}", 15);

            if (! $invoiceLock->get()) {
                continue;
            }

            if (! $warungLock->get()) {
                $invoiceLock->release();

                continue;
            }

            try {
                $limiter->recordRequest();
                $processedCount++;

                // Panggil invoice dengan reconcile=1 agar UDEV Billing merekonsiliasi gateway secara live
                $liveInvoice = $udev->invoice($invoiceNumber, true);
                $limiter->recordSuccess();

                $liveStatus = (string) ($liveInvoice['status'] ?? 'pending');

                if ($liveStatus === 'paid') {
                    $registry->updateStatus($invoiceNumber, 'paid');
                    $result = $reconcileService->reconcileWarung($warungId, $externalCustomerId, $invoiceNumber);

                    if ($result['success']) {
                        $reconciledCount++;
                        $this->out('info', "Invoice {$invoiceNumber} settled and Warung {$warungId} subscription active.");
                    } else {
                        // Jika UDEV internal subscription belum terupdate, retry pada siklus berikutnya
                        $registry->updateStatus($invoiceNumber, 'paid', $result['error_message'] ?? 'Subscription not ready', true);
                        $this->out('warn', "Invoice {$invoiceNumber} is paid but subscription reconcile pending. Retrying later.");
                    }
                } elseif ($liveStatus === 'pending') {
                    // Periksa apakah invoice sudah kedaluwarsa resmi di UDEV
                    $paymentExpiry = isset($liveInvoice['payment']['checkout_expires_at'])
                        ? CarbonImmutable::parse($liveInvoice['payment']['checkout_expires_at'])->timestamp
                        : 0;
                    $dueAt = isset($liveInvoice['due_at'])
                        ? CarbonImmutable::parse($liveInvoice['due_at'])->timestamp
                        : 0;

                    $isPastPayment = $paymentExpiry > 0 && $now >= $paymentExpiry;
                    $isPastDue = $dueAt > 0 && $now >= $dueAt;

                    if ($isPastPayment && $isPastDue) {
                        $registry->markExpiredOrCancelled($invoiceNumber, 'expired');
                        $this->out('line', "Invoice {$invoiceNumber} expired.");
                    } else {
                        $registry->updateStatus($invoiceNumber, 'pending');
                    }
                } elseif (in_array($liveStatus, ['expired', 'cancelled', 'failed'], true)) {
                    $registry->markExpiredOrCancelled($invoiceNumber, $liveStatus);
                    $this->out('line', "Invoice {$invoiceNumber} is {$liveStatus}. Removed from active registry.");
                }
            } catch (Throwable $e) {
                $limiter->recordFailure($e);
                $registry->updateStatus($invoiceNumber, $item['status'] ?? 'pending', $e->getMessage(), true);
                Log::warning("[UDEV SYNC] Failed syncing invoice {$invoiceNumber}", [
                    'exception' => $e->getMessage(),
                ]);
                $this->out('error', "Failed syncing invoice {$invoiceNumber}: {$e->getMessage()}");
            } finally {
                $invoiceLock->release();
                $warungLock->release();
            }
        }

        $this->out('info', "Sync completed: {$processedCount} processed, {$reconciledCount} reconciled.");

        return self::SUCCESS;
    }

    private function out(string $type, string $message): void
    {
        if ($this->output === null) {
            return;
        }

        match ($type) {
            'info' => $this->info($message),
            'warn' => $this->warn($message),
            'error' => $this->error($message),
            default => $this->line($message),
        };
    }
}
