<?php

namespace App\Actions\Udev;

use App\Models\WarungCustomerSync;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UdevQueueAuditor
{
    /**
     * Audit antrean queue 'udev-customers' dan kelompokkan job menjadi keep dan prune.
     *
     * @return array{
     *     total_jobs: int,
     *     to_keep: array<int, array{job_id: int, sync_id: ?int, warung_id: ?int, external_customer_id: ?string, status: ?string, created_at: int}>,
     *     to_prune: array<int, array{job_id: int, sync_id: ?int, warung_id: ?int, external_customer_id: ?string, status: ?string, reason: string, created_at: int}>,
     *     summary: array<int, array{warung_id: ?int, external_customer_id: ?string, status: ?string, total: int, keep: int, prune: int}>
     * }
     */
    public function audit(): array
    {
        if (! Schema::hasTable('jobs')) {
            return [
                'total_jobs' => 0,
                'to_keep' => [],
                'to_prune' => [],
                'summary' => [],
            ];
        }

        $jobs = DB::table('jobs')
            ->where('queue', 'udev-customers')
            ->orderBy('id', 'asc')
            ->get();

        if ($jobs->isEmpty()) {
            return [
                'total_jobs' => 0,
                'to_keep' => [],
                'to_prune' => [],
                'summary' => [],
            ];
        }

        // Ekstraksi syncId untuk setiap job
        $jobsWithSync = $jobs->map(function ($job) {
            $syncId = $this->extractSyncId((string) $job->payload);

            return [
                'job_id' => (int) $job->id,
                'sync_id' => $syncId,
                'payload' => $job->payload,
                'attempts' => $job->attempts,
                'created_at' => (int) $job->created_at,
            ];
        });

        $syncIds = $jobsWithSync->pluck('sync_id')->filter()->unique()->values()->all();
        $syncRecords = WarungCustomerSync::query()
            ->with('warung')
            ->whereIn('id', $syncIds)
            ->get()
            ->keyBy('id');

        $toKeep = [];
        $toPrune = [];
        $summary = [];

        // Kelompokkan jobs berdasarkan sync_id
        /** @var Collection<int|string, Collection<int, array>> $grouped */
        $grouped = $jobsWithSync->groupBy(function ($item) {
            return $item['sync_id'] ?? 'unknown';
        });

        foreach ($grouped as $syncKey => $groupJobs) {
            $syncId = is_numeric($syncKey) ? (int) $syncKey : null;
            /** @var WarungCustomerSync|null $syncRecord */
            $syncRecord = $syncId ? $syncRecords->get($syncId) : null;

            $warungId = $syncRecord?->warung_id;
            $externalCustomerId = $syncRecord?->external_customer_id;
            $status = $syncRecord?->status;

            $totalGroupJobs = $groupJobs->count();
            $keepCount = 0;
            $pruneCount = 0;

            if ($syncRecord === null) {
                // Record sync tidak ditemukan di database
                foreach ($groupJobs as $jobItem) {
                    $toPrune[] = [
                        'job_id' => $jobItem['job_id'],
                        'sync_id' => $syncId,
                        'warung_id' => null,
                        'external_customer_id' => null,
                        'status' => 'orphan',
                        'reason' => 'orphan',
                        'created_at' => $jobItem['created_at'],
                    ];
                    $pruneCount++;
                }
            } elseif ($syncRecord->status === 'synced') {
                // Record sudah synced, semua job di antrean usang (obsolete)
                foreach ($groupJobs as $jobItem) {
                    $toPrune[] = [
                        'job_id' => $jobItem['job_id'],
                        'sync_id' => $syncId,
                        'warung_id' => $warungId,
                        'external_customer_id' => $externalCustomerId,
                        'status' => $status,
                        'reason' => 'obsolete',
                        'created_at' => $jobItem['created_at'],
                    ];
                    $pruneCount++;
                }
            } else {
                // Status pending / processing / retry_wait / blocked / failed:
                // Pertahankan 1 job tertua (elemen pertama karena di-query orderBy id asc)
                $first = true;
                foreach ($groupJobs as $jobItem) {
                    if ($first) {
                        $toKeep[] = [
                            'job_id' => $jobItem['job_id'],
                            'sync_id' => $syncId,
                            'warung_id' => $warungId,
                            'external_customer_id' => $externalCustomerId,
                            'status' => $status,
                            'created_at' => $jobItem['created_at'],
                        ];
                        $keepCount++;
                        $first = false;
                    } else {
                        $toPrune[] = [
                            'job_id' => $jobItem['job_id'],
                            'sync_id' => $syncId,
                            'warung_id' => $warungId,
                            'external_customer_id' => $externalCustomerId,
                            'status' => $status,
                            'reason' => 'duplicate',
                            'created_at' => $jobItem['created_at'],
                        ];
                        $pruneCount++;
                    }
                }
            }

            $summaryKey = $syncId ?? -1;
            $summary[$summaryKey] = [
                'sync_id' => $syncId,
                'warung_id' => $warungId,
                'external_customer_id' => $externalCustomerId,
                'status' => $status ?? 'orphan',
                'total' => $totalGroupJobs,
                'keep' => $keepCount,
                'prune' => $pruneCount,
            ];
        }

        return [
            'total_jobs' => $jobs->count(),
            'to_keep' => $toKeep,
            'to_prune' => $toPrune,
            'summary' => array_values($summary),
        ];
    }

    /**
     * Ekstrak syncId dari job payload string.
     */
    public function extractSyncId(string $payload): ?int
    {
        $decoded = json_decode($payload, true);
        if (is_array($decoded) && isset($decoded['data']['command'])) {
            try {
                $command = @unserialize($decoded['data']['command'], ['allowed_classes' => [SyncCustomerJob::class]]);
                if ($command instanceof SyncCustomerJob && isset($command->syncId)) {
                    return (int) $command->syncId;
                }
            } catch (\Throwable) {
                // Lanjut ke fallback regex jika unserialize gagal
            }
        }

        // Fallback pencarian berbasis regex
        if (preg_match('/syncId[^0-9]+(\d+)/', $payload, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Backup jobs yang akan di-prune ke file JSON sebelum penghapusan.
     *
     * @param  array<int, int>  $jobIds
     */
    public function backup(array $jobIds, ?string $customPath = null): string
    {
        if (empty($jobIds)) {
            return '';
        }

        $backupPath = $customPath ?: storage_path('app/queue-backups/udev_customers_queue_backup_'.now()->format('Ymd_His').'.json');
        File::ensureDirectoryExists(dirname($backupPath));

        $jobsToBackup = DB::table('jobs')
            ->where('queue', 'udev-customers')
            ->whereIn('id', $jobIds)
            ->get();

        File::put($backupPath, json_encode($jobsToBackup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $backupPath;
    }

    /**
     * Hapus job terpilih secara aman dan selektif.
     *
     * @param  array<int, int>  $jobIds
     */
    public function prune(array $jobIds): int
    {
        if (empty($jobIds)) {
            return 0;
        }

        $deletedCount = 0;
        foreach (array_chunk($jobIds, 500) as $chunk) {
            $deletedCount += DB::table('jobs')
                ->where('queue', 'udev-customers')
                ->whereIn('id', $chunk)
                ->delete();
        }

        Log::info('Selective cleanup udev-customers queue completed', [
            'deleted_count' => $deletedCount,
            'job_ids_count' => count($jobIds),
        ]);

        return $deletedCount;
    }
}
