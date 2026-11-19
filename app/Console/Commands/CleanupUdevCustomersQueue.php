<?php

namespace App\Console\Commands;

use App\Actions\Udev\UdevQueueAuditor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('udev:cleanup-queue {--dry-run : Simulasi audit dan pembersihan tanpa menghapus data} {--force : Jalankan pembersihan tanpa konfirmasi interaktif} {--backup-path= : Lokasi custom file backup JSON}')]
#[Description('Pembersihan selektif duplikasi job udev-customers dengan proteksi dry-run dan backup otomatis')]
class CleanupUdevCustomersQueue extends Command
{
    public function handle(UdevQueueAuditor $auditor): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');
        $customBackupPath = $this->option('backup-path');

        $this->info($isDryRun
            ? 'Memulai simulasi dry-run pembersihan selektif antrean udev-customers...'
            : 'Memulai proses pembersihan selektif antrean udev-customers...'
        );

        $auditResult = $auditor->audit();

        $totalJobs = $auditResult['total_jobs'];
        $toKeep = $auditResult['to_keep'];
        $toPrune = $auditResult['to_prune'];

        if (empty($toPrune)) {
            $this->info('Antrean udev-customers bersih. Tidak ada job duplikat atau usang yang perlu dibersihkan.');

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
        $this->line(sprintf('Total Job Saat Ini: <comment>%d</comment>', $totalJobs));
        $this->line(sprintf('Job Valid Dipertahankan: <info>%d</info>', count($toKeep)));
        $this->line(sprintf('Job Duplikat/Usang Akan Dipangkas: <error>%d</error>', count($toPrune)));
        $this->newLine();

        if ($isDryRun) {
            $this->info('[DRY-RUN] Simulasi pembersihan selesai. Tidak ada baris yang dihapus dan tidak ada file backup yang dibuat.');
            $this->line('Untuk mengeksekusi pembersihan selektif yang sebenarnya, jalankan:');
            $this->comment('php artisan udev:cleanup-queue');

            return self::SUCCESS;
        }

        if (! $isForce) {
            $confirmed = $this->confirm(
                sprintf('Apakah Anda yakin ingin memangkas %d job duplikat/usang ini secara permanen?', count($toPrune)),
                false
            );

            if (! $confirmed) {
                $this->warn('Operasi pembersihan dibatalkan oleh pengguna.');

                return self::SUCCESS;
            }
        }

        $jobIdsToPrune = array_column($toPrune, 'job_id');

        $this->info('Menyimpan cadangan (backup) job yang akan dipangkas...');
        $backupPath = $auditor->backup($jobIdsToPrune, $customBackupPath ? (string) $customBackupPath : null);
        $this->info(sprintf('Backup berhasil disimpan di: %s', $backupPath));

        $this->info('Menghapus job duplikat/usang...');
        $deletedCount = $auditor->prune($jobIdsToPrune);

        $this->newLine();
        $this->info(sprintf('Berhasil memangkas %d job duplikat/usang dari queue udev-customers.', $deletedCount));
        $this->line(sprintf('Jumlah job tersisa di antrean: <info>%d</info> (job valid siap diproses worker).', count($toKeep)));

        return self::SUCCESS;
    }
}
