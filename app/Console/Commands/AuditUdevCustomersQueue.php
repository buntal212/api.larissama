<?php

namespace App\Console\Commands;

use App\Actions\Udev\UdevQueueAuditor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('udev:audit-queue')]
#[Description('Audit antrean queue udev-customers dan identifikasi job valid, duplikat, serta usang')]
class AuditUdevCustomersQueue extends Command
{
    public function handle(UdevQueueAuditor $auditor): int
    {
        $this->info('Memulai audit antrean queue udev-customers...');

        $auditResult = $auditor->audit();

        $totalJobs = $auditResult['total_jobs'];
        $toKeep = count($auditResult['to_keep']);
        $toPrune = count($auditResult['to_prune']);

        if ($totalJobs === 0) {
            $this->info('Antrean udev-customers kosong. Tidak ada job yang ditemukan.');

            return self::SUCCESS;
        }

        $headers = ['Sync ID', 'Warung ID', 'External Customer ID', 'Status DB', 'Total Jobs', 'Keep (Pertahankan)', 'Prune (Pangkas)'];
        $rows = [];

        foreach ($auditResult['summary'] as $item) {
            $rows[] = [
                $item['sync_id'] ?? 'N/A',
                $item['warung_id'] ?? 'N/A',
                $item['external_customer_id'] ?? 'N/A',
                $item['status'],
                $item['total'],
                $item['keep'],
                $item['prune'],
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->line(sprintf('Total Job di Queue: <comment>%d</comment>', $totalJobs));
        $this->line(sprintf('Job Valid Dipertahankan: <info>%d</info>', $toKeep));
        $this->line(sprintf('Job Duplikat/Usang Dapat Dipangkas: <error>%d</error>', $toPrune));
        $this->newLine();

        if ($toPrune > 0) {
            $this->warn('Ditemukan job duplikat atau usang dalam antrean.');
            $this->line('Jalankan perintah berikut untuk simulasi pembersihan aman:');
            $this->comment('php artisan udev:cleanup-queue --dry-run');
        } else {
            $this->info('Antrean optimal. Semua job di antrean valid dan tidak ada duplikasi.');
        }

        return self::SUCCESS;
    }
}
