<?php

namespace Tests\Performance;

use App\Actions\Laporan\BuildLaporanPeriode;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\PenjualanRetur;
use App\Models\User;
use App\Models\Warung;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportReadPerformanceTest extends TestCase
{
    public function test_report_query_plans_and_http_load_with_large_tenant_dataset(): void
    {
        $this->assertPerformanceDatabaseIsIsolated();
        $fixture = $this->createLargeDataset();

        $this->assertReportQueryCountDoesNotGrowWithRows($fixture['warung']->id, $fixture['empty_warung']->id);
        $this->assertReportPlansUseTenantDateIndexes($fixture['warung']->id);
        $this->runConcurrentReportLoad($fixture['token']);
    }

    private function assertPerformanceDatabaseIsIsolated(): void
    {
        self::assertSame('testing', app()->environment(), 'Optional performance tests require APP_ENV=testing.');
        self::assertSame('mysql', DB::connection()->getDriverName(), 'Performance checks must run on MySQL.');
        self::assertSame(
            'larissama_performance',
            DB::connection()->getDatabaseName(),
            'Refusing to run performance checks outside the dedicated disposable database.',
        );
    }

    /** @return array{empty_warung: Warung, token: string, warung: Warung} */
    private function createLargeDataset(): array
    {
        $targetWarung = null;
        $targetUser = null;
        $purchases = [];
        $returns = [];
        $saleLines = [];
        $emptyWarung = null;
        $anchor = CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC');

        for ($tenantNumber = 0; $tenantNumber < 40; $tenantNumber++) {
            $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
            $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);

            if ($tenantNumber === 0) {
                $targetWarung = $warung;
                $targetUser = $manager;
            }

            $tenantSales = [];

            for ($rowNumber = 0; $rowNumber < 125; $rowNumber++) {
                $timestamp = $anchor
                    ->subDays($rowNumber % 30)
                    ->setTime($rowNumber % 24, ($rowNumber * 7) % 60)
                    ->toDateTimeString();
                $number = $tenantNumber.'-'.$rowNumber;
                $tenantSales[] = [
                    'warung_id' => $warung->id,
                    'user_id' => $manager->id,
                    'no_transaksi' => 'PERF-PJ-'.$number,
                    'nama_pelanggan' => null,
                    'idempotency_key' => 'perf-sale-'.$number,
                    'payload_hash' => hash('sha256', 'perf-sale-'.$number),
                    'idempotency_expires_at' => null,
                    'tanggal' => $timestamp,
                    'subtotal' => '15000.00',
                    'diskon' => '0.00',
                    'total' => '15000.00',
                    'bayar' => '15000.00',
                    'kembalian' => '0.00',
                    'metode_pembayaran' => $rowNumber % 2 === 0 ? 'cash' : 'qris',
                    'status' => 'selesai',
                    'status_pembayaran' => 'lunas',
                    'dibayar_pada' => $timestamp,
                    'pembayaran_user_id' => $manager->id,
                    'pembayaran_superadmin_id' => null,
                    'pembayaran_idempotency_key' => null,
                    'pembayaran_payload_hash' => null,
                    'pembayaran_idempotency_expires_at' => null,
                    'created_by_superadmin_id' => null,
                    'catatan' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                $purchases[] = [
                    'warung_id' => $warung->id,
                    'user_id' => $manager->id,
                    'no_transaksi' => 'PERF-PB-'.$number,
                    'idempotency_key' => 'perf-purchase-'.$number,
                    'payload_hash' => hash('sha256', 'perf-purchase-'.$number),
                    'idempotency_expires_at' => null,
                    'tanggal' => $timestamp,
                    'total' => '5000.00',
                    'catatan' => null,
                    'status' => 'tercatat',
                    'created_by_superadmin_id' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            foreach (array_chunk($tenantSales, 500) as $saleChunk) {
                DB::table('penjualans')->insert($saleChunk);
            }

            $saleIds = DB::table('penjualans')
                ->where('warung_id', $warung->id)
                ->whereIn('no_transaksi', array_column($tenantSales, 'no_transaksi'))
                ->pluck('id', 'no_transaksi');

            foreach ($tenantSales as $saleRow) {
                $number = substr($saleRow['no_transaksi'], strlen('PERF-PJ-'));
                $timestamp = $saleRow['dibayar_pada'];
                $saleId = $saleIds[$saleRow['no_transaksi']];
                $saleLines[] = [
                    'warung_id' => $warung->id,
                    'penjualan_id' => $saleId,
                    'menu_id' => null,
                    'nama_menu' => 'Item bebas uji beban '.$number,
                    'harga' => '15000.00',
                    'qty' => '1.00',
                    'diskon' => '0.00',
                    'subtotal' => '15000.00',
                    'catatan' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                $returns[] = [
                    'warung_id' => $warung->id,
                    'penjualan_id' => $saleId,
                    'user_id' => $manager->id,
                    'superadmin_id' => null,
                    'nominal' => '100.00',
                    'alasan' => 'Performance fixture',
                    'idempotency_key' => null,
                    'payload_hash' => null,
                    'idempotency_expires_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        foreach (array_chunk($purchases, 500) as $purchaseChunk) {
            DB::table('pembelians')->insert($purchaseChunk);
        }

        foreach (array_chunk($saleLines, 500) as $lineChunk) {
            DB::table('penjualan_rincis')->insert($lineChunk);
        }

        foreach (array_chunk($returns, 500) as $returnChunk) {
            DB::table('penjualan_returs')->insert($returnChunk);
        }

        self::assertInstanceOf(Warung::class, $targetWarung);
        self::assertInstanceOf(User::class, $targetUser);
        $emptyWarung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);

        return [
            'empty_warung' => $emptyWarung,
            'warung' => $targetWarung,
            'token' => $targetUser->createToken('optional-performance')->plainTextToken,
        ];
    }

    private function assertReportQueryCountDoesNotGrowWithRows(int $warungId, int $emptyWarungId): void
    {
        $report = app(BuildLaporanPeriode::class);
        $cases = [
            'sales summary' => [4, static fn (int $id): array => $report->penjualan((string) $id, '2026-10-10', '2026-10-10')],
            'purchase summary' => [2, static fn (int $id): array => $report->pembelian((string) $id, '2026-10-10', '2026-10-10')],
            'unpaid-order summary' => [2, static fn (int $id): array => $report->pesananBelumLunas((string) $id, '2026-10-10', '2026-10-10')],
            'daily sales report' => [3, static fn (int $id): array => $report->penjualanHarian((string) $id, '2026-10-10', '2026-10-10')],
            'item sales report' => [2, static fn (int $id): array => $report->penjualanItem((string) $id, '2026-10-10', '2026-10-10')],
        ];

        foreach ($cases as $name => [$expectedCount, $operation]) {
            $emptyCount = $this->countQueries($operation, $emptyWarungId);
            $denseCount = $this->countQueries($operation, $warungId);

            self::assertSame($expectedCount, $emptyCount, "Unexpected query count for empty {$name}.");
            self::assertSame($expectedCount, $denseCount, "Query count for {$name} grew with matching data.");
        }
    }

    /** @param callable(int): array<string, mixed> $operation */
    private function countQueries(callable $operation, int $warungId): int
    {
        $capturing = false;
        $count = 0;
        DB::listen(static function (QueryExecuted $event) use (&$capturing, &$count): void {
            if ($capturing) {
                $count++;
            }
        });

        $capturing = true;
        $operation($warungId);
        $capturing = false;

        return $count;
    }

    private function assertReportPlansUseTenantDateIndexes(int $warungId): void
    {
        $saleQuery = Penjualan::query()
            ->where('warung_id', $warungId)
            ->whereIn('status', ['selesai', 'diretur_sebagian', 'diretur_penuh'])
            ->where('status_pembayaran', 'lunas')
            ->where('dibayar_pada', '>=', '2026-10-10 00:00:00')
            ->where('dibayar_pada', '<', '2026-10-11 00:00:00')
            ->selectRaw('COUNT(*)');
        $unpaidQuery = Penjualan::query()
            ->where('warung_id', $warungId)
            ->where('status', 'menunggu_pembayaran')
            ->where('status_pembayaran', 'belum_lunas')
            ->where('tanggal', '>=', '2026-10-10 00:00:00')
            ->where('tanggal', '<', '2026-10-11 00:00:00')
            ->selectRaw('COUNT(*)');
        $purchaseQuery = Pembelian::query()
            ->where('warung_id', $warungId)
            ->where('status', 'tercatat')
            ->where('tanggal', '>=', '2026-10-10 00:00:00')
            ->where('tanggal', '<', '2026-10-11 00:00:00')
            ->selectRaw('COUNT(*)');
        $returnQuery = PenjualanRetur::query()
            ->where('warung_id', $warungId)
            ->where('created_at', '>=', '2026-10-10 00:00:00')
            ->where('created_at', '<', '2026-10-11 00:00:00')
            ->selectRaw('COUNT(*)');

        $salePlan = $this->explain($saleQuery->toSql(), $saleQuery->getBindings());
        $unpaidPlan = $this->explain($unpaidQuery->toSql(), $unpaidQuery->getBindings());
        $purchasePlan = $this->explain($purchaseQuery->toSql(), $purchaseQuery->getBindings());
        $returnPlan = $this->explain($returnQuery->toSql(), $returnQuery->getBindings());

        self::assertStringContainsString(
            'penjualans_warung_paid_at_idx',
            $salePlan,
            'Paid-sales date query should use the composite tenant/payment/date index.',
        );
        self::assertStringContainsString(
            'pembelians_warung_status_tanggal_idx',
            $purchasePlan,
            'Purchase date query should use the composite tenant/status/date index.',
        );
        self::assertStringContainsString(
            'penjualans_warung_payment_status_idx',
            $unpaidPlan,
            'Unpaid-order date query should use the composite tenant/payment/date index.',
        );
        self::assertStringContainsString(
            'penjualan_retur_warung_created_idx',
            $returnPlan,
            'Return date query should use the composite tenant/creation-date index.',
        );
    }

    /** @param array<int, mixed> $bindings */
    private function explain(string $sql, array $bindings): string
    {
        $row = DB::selectOne('EXPLAIN FORMAT=JSON '.$sql, $bindings);
        self::assertNotNull($row);
        $values = array_values((array) $row);
        self::assertIsString($values[0] ?? null);

        return (string) $values[0];
    }

    private function runConcurrentReportLoad(string $token): void
    {
        $baseUrl = rtrim((string) env('LARISSAMA_PERFORMANCE_BASE_URL'), '/');
        $concurrency = $this->boundedEnvironmentInteger('LARISSAMA_PERFORMANCE_CONCURRENCY', 4, 1, 8);
        $requestsPerWorker = $this->boundedEnvironmentInteger('LARISSAMA_PERFORMANCE_REQUESTS_PER_WORKER', 10, 1, 20);
        self::assertNotSame('', $baseUrl, 'Set LARISSAMA_PERFORMANCE_BASE_URL through the dedicated Compose file.');

        $paths = [
            '/api/v1/laporan/penjualan',
            '/api/v1/laporan/pembelian',
            '/api/v1/laporan/pesanan-belum-lunas',
            '/api/v1/laporan/penjualan-harian',
            '/api/v1/laporan/penjualan-item',
        ];

        foreach ($paths as $path) {
            $latenciesMs = [];
            $requestCount = 0;
            $overallStartedAt = microtime(true);

            for ($round = 0; $round < $requestsPerWorker; $round++) {
                $batchLatencies = $this->sendConcurrentGetBatch($baseUrl.$path, $token, $concurrency);
                array_push($latenciesMs, ...$batchLatencies);
                $requestCount += count($batchLatencies);
            }

            sort($latenciesMs);
            $p50 = $latenciesMs[(int) floor(($requestCount - 1) * 0.50)];
            $p95 = $latenciesMs[(int) floor(($requestCount - 1) * 0.95)];
            $elapsed = microtime(true) - $overallStartedAt;
            $throughput = $requestCount / max($elapsed, 0.001);

            fwrite(STDOUT, sprintf(
                "\nOptional report load %s: %d requests, concurrency=%d, status=all 200, p50=%.1fms, p95=%.1fms, throughput=%.2f req/s\n",
                $path,
                $requestCount,
                $concurrency,
                $p50,
                $p95,
                $throughput,
            ));
        }
    }

    /** @return array<int, float> */
    private function sendConcurrentGetBatch(string $url, string $token, int $concurrency): array
    {
        $multiHandle = curl_multi_init();
        $handles = [];

        for ($worker = 0; $worker < $concurrency; $worker++) {
            $handle = curl_init($url.'?date_from=2026-10-10&date_to=2026-10-10');
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Authorization: Bearer '.$token,
                ],
            ]);
            curl_multi_add_handle($multiHandle, $handle);
            $handles[] = $handle;
        }

        do {
            $multiStatus = curl_multi_exec($multiHandle, $active);

            if ($active > 0 && $multiStatus === CURLM_OK) {
                curl_multi_select($multiHandle, 1.0);
            }
        } while ($active > 0 && $multiStatus === CURLM_OK);

        $latenciesMs = [];

        foreach ($handles as $handle) {
            $body = curl_multi_getcontent($handle);
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $latenciesMs[] = curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000;

            self::assertSame(CURLE_OK, curl_errno($handle), 'Load request failed: '.curl_error($handle));
            self::assertSame(200, $status, 'Load endpoint returned '.$status.' with body '.$body);
            self::assertIsArray(json_decode((string) $body, true));

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        return $latenciesMs;
    }

    private function boundedEnvironmentInteger(string $name, int $default, int $minimum, int $maximum): int
    {
        $value = filter_var(env($name, $default), FILTER_VALIDATE_INT);
        self::assertIsInt($value, $name.' must be an integer.');
        self::assertGreaterThanOrEqual($minimum, $value, $name.' is below its safe minimum.');
        self::assertLessThanOrEqual($maximum, $value, $name.' is above its safe maximum.');

        return $value;
    }
}
