<?php

namespace App\Console\Commands;

use App\Actions\Udev\SubscriptionInvoiceRegistry;
use App\Actions\Udev\SubscriptionReconcileService;
use App\Actions\Udev\UdevBillingClient;
use App\Actions\Udev\UdevSyncLimiter;
use App\Models\Warung;
use App\Models\WarungCustomerSync;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('udev:recover-invoices')]
#[Description('Recover missing subscription invoices from UDEV Billing transactions')]
class RecoverSubscriptionInvoicesCommand extends Command
{
    public const string CHECKPOINT_KEY = 'udev_recovery:last_sync_id';

    public const int BATCH_SIZE = 50;

    public function handle(
        UdevBillingClient $udev,
        SubscriptionInvoiceRegistry $registry,
        SubscriptionReconcileService $reconcileService,
        UdevSyncLimiter $limiter,
    ): int {
        $lastSyncId = (int) Cache::get(self::CHECKPOINT_KEY, 0);

        // Ambil batch pelanggan tersinkron (maksimal 50)
        $customers = WarungCustomerSync::query()
            ->where('status', 'synced')
            ->whereNotNull('consented_at')
            ->where('id', '>', $lastSyncId)
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();

        // Jika sudah mencapai akhir daftar pelanggan, ulangi siklus dari awal (id 0)
        if ($customers->isEmpty() && $lastSyncId > 0) {
            Cache::forever(self::CHECKPOINT_KEY, 0);
            $customers = WarungCustomerSync::query()
                ->where('status', 'synced')
                ->whereNotNull('consented_at')
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->get();
        }

        if ($customers->isEmpty()) {
            $this->out('line', 'No mapped customers to recover.');

            return self::SUCCESS;
        }

        $recoveredCount = 0;
        $inspectedCustomers = 0;

        foreach ($customers as $sync) {
            $inspectedCustomers++;
            $warungId = (int) $sync->warung_id;
            $externalCustomerId = (string) $sync->external_customer_id;

            // Periksa anggaran rate limiter untuk recovery
            if (! $limiter->canExecute('recovery', false)) {
                $this->out('warn', 'Recovery reached limiter capacity. Checkpoint saved for next cycle.');
                Log::info('[UDEV RECOVERY] Halting recovery batch due to rate limit budget.');
                break;
            }

            $warungLock = Cache::lock("udev_sync_warung_lock:{$warungId}", 15);
            if (! $warungLock->get()) {
                continue;
            }

            try {
                $limiter->recordRequest();
                $transactions = $udev->transactions($externalCustomerId);
                $limiter->recordSuccess();

                $warung = Warung::query()->find($warungId);
                $unreconciledInvoices = [];

                // Urutkan transaksi dari yang terbaru
                usort($transactions, fn ($a, $b) => ($b['created_at'] ?? '') <=> ($a['created_at'] ?? ''));

                // Periksa transaksi terbaru
                $recentTransactions = array_slice($transactions, 0, 10);

                foreach ($recentTransactions as $tx) {
                    $invoiceNumber = (string) ($tx['invoice_number'] ?? '');
                    $txStatus = (string) ($tx['status'] ?? '');
                    if ($invoiceNumber === '') {
                        continue;
                    }

                    $existingRecord = $registry->getInvoice($invoiceNumber);

                    if ($txStatus === 'pending') {
                        $dueAt = isset($tx['due_at']) ? CarbonImmutable::parse($tx['due_at']) : null;
                        if ($dueAt !== null && $dueAt->isFuture() && $existingRecord === null) {
                            $registry->register($invoiceNumber, $warungId, $externalCustomerId, 'pending');
                            $recoveredCount++;
                            $this->out('info', "Recovered pending invoice {$invoiceNumber} into registry.");
                        }
                    } elseif ($txStatus === 'paid') {
                        if ($existingRecord === null || ! ($existingRecord['reconciled'] ?? false)) {
                            $registry->register($invoiceNumber, $warungId, $externalCustomerId, 'paid');
                            $unreconciledInvoices[] = $invoiceNumber;
                            $recoveredCount++;
                        }
                    }
                }

                // Jika ada invoice paid yang belum reconciled, jalankan rekonsiliasi warung 1 kali
                if (! empty($unreconciledInvoices) && $limiter->canExecute('recovery', true)) {
                    $limiter->recordRequest();
                    $latestInvoice = $unreconciledInvoices[0];
                    $reconcileResult = $reconcileService->reconcileWarung($warungId, $externalCustomerId, $latestInvoice);
                    if ($reconcileResult['success']) {
                        $limiter->recordSuccess();
                        foreach ($unreconciledInvoices as $invNum) {
                            $registry->markReconciled($invNum);
                        }
                        $this->out('info', "Recovered and reconciled paid invoices for Warung {$warungId}.");
                    }
                }

                // Perbarui checkpoint ID terakhir
                Cache::forever(self::CHECKPOINT_KEY, $sync->id);
            } catch (Throwable $e) {
                $limiter->recordFailure($e);
                Log::warning("[UDEV RECOVERY] Failed recovering customer {$sync->id}", [
                    'exception' => $e->getMessage(),
                ]);
            } finally {
                $warungLock->release();
            }
        }

        $this->out('info', "Recovery cycle completed: {$inspectedCustomers} customers inspected, {$recoveredCount} invoices recovered.");

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
