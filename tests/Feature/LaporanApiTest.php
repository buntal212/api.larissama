<?php

namespace Tests\Feature;

use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LaporanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_uses_new_york_local_day_across_dst_transition(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'America/New_York']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $token = $manager->createToken('feature-test')->plainTextToken;

        foreach ([
            ['tanggal' => '2026-03-08 04:59:59', 'total' => '1000.00'],
            ['tanggal' => '2026-03-08 05:00:00', 'total' => '2000.00'],
            ['tanggal' => '2026-03-09 03:59:59', 'total' => '3000.00'],
            ['tanggal' => '2026-03-09 04:00:00', 'total' => '4000.00'],
        ] as $sale) {
            Penjualan::factory()->create([
                'warung_id' => $warung->id,
                'user_id' => $cashier->id,
                'tanggal' => $sale['tanggal'],
                'total' => $sale['total'],
            ]);
        }

        $query = ['date_from' => '2026-03-08', 'date_to' => '2026-03-08'];
        $this->assertOperationQueryMatchesOpenApi($query, '/laporan/penjualan', 'get');
        $response = $this->withToken($token)->getJson('/api/v1/laporan/penjualan?'.http_build_query($query))
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 2)
            ->assertJsonPath('data.total_pendapatan', '5000.00')
            ->assertJsonPath('data.period.timezone', 'America/New_York');

        $this->assertReportSuccessEnvelope($response, 'total_pendapatan');
        $this->assertOperationResponseMatchesOpenApi($response, '/laporan/penjualan', 'get');
    }

    public function test_purchase_report_uses_new_york_local_day_across_dst_transition(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'America/New_York']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;

        foreach ([
            ['tanggal' => '2026-03-08 04:59:59', 'total' => '1000.00'],
            ['tanggal' => '2026-03-08 05:00:00', 'total' => '2000.00'],
            ['tanggal' => '2026-03-09 03:59:59', 'total' => '3000.00'],
            ['tanggal' => '2026-03-09 04:00:00', 'total' => '4000.00'],
        ] as $purchase) {
            Pembelian::factory()->create([
                'warung_id' => $warung->id,
                'user_id' => $manager->id,
                'tanggal' => $purchase['tanggal'],
                'total' => $purchase['total'],
            ]);
        }

        $query = ['date_from' => '2026-03-08', 'date_to' => '2026-03-08'];
        $this->assertOperationQueryMatchesOpenApi($query, '/laporan/pembelian', 'get');
        $response = $this->withToken($token)->getJson('/api/v1/laporan/pembelian?'.http_build_query($query))
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 2)
            ->assertJsonPath('data.total_pembelian', '5000.00')
            ->assertJsonPath('data.period.timezone', 'America/New_York');

        $this->assertReportSuccessEnvelope($response, 'total_pembelian');
        $this->assertOperationResponseMatchesOpenApi($response, '/laporan/pembelian', 'get');
    }

    public function test_empty_reports_return_zero_for_both_transaction_types(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;

        foreach ([
            ['route' => 'penjualan', 'total_field' => 'total_pendapatan'],
            ['route' => 'pembelian', 'total_field' => 'total_pembelian'],
        ] as $report) {
            $response = $this->withToken($token)->getJson(
                "/api/v1/laporan/{$report['route']}?date_from=2026-10-04&date_to=2026-10-04"
            )
                ->assertOk()
                ->assertJsonPath('data.jumlah_transaksi', 0)
                ->assertJsonPath("data.{$report['total_field']}", '0.00')
                ->assertJsonPath('data.period.timezone', 'Asia/Jakarta');

            $this->assertReportSuccessEnvelope($response, $report['total_field']);
            $this->assertOperationResponseMatchesOpenApi($response, "/laporan/{$report['route']}", 'get');
        }
    }

    public function test_cashier_and_superadmin_cannot_read_either_report(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $superadmin = User::factory()->superadmin()->create();
        $transactionCounts = [
            Penjualan::query()->count(),
            Pembelian::query()->count(),
        ];

        $actors = [
            ['user' => $cashier, 'name' => 'cashier'],
            ['user' => $superadmin, 'name' => 'superadmin'],
        ];
        $reports = ['penjualan', 'pembelian'];

        foreach ($actors as $actor) {
            $token = $actor['user']->createToken("report-rbac-{$actor['name']}")->plainTextToken;

            foreach ($reports as $report) {
                $path = "/laporan/{$report}";
                $response = $this->withToken($token)
                    ->getJson("/api/v1{$path}?date_from=2026-10-04&date_to=2026-10-04")
                    ->assertForbidden()
                    ->assertJsonPath('code', 'FORBIDDEN');

                $this->assertOperationResponseMatchesOpenApi($response, $path, 'get');
            }
        }

        $this->assertSame($transactionCounts, [
            Penjualan::query()->count(),
            Pembelian::query()->count(),
        ]);
    }

    public function test_both_reports_reject_invalid_periods_with_422(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $invalidPeriods = [
            [
                'query' => ['date_from' => '04-10-2026', 'date_to' => '2026-10-04'],
                'error_field' => 'date_from',
            ],
            [
                'query' => ['date_from' => '2026-10-04'],
                'error_field' => 'date_to',
            ],
            [
                'query' => ['date_from' => '2026-10-05', 'date_to' => '2026-10-04'],
                'error_field' => 'date_to',
            ],
        ];

        foreach (['penjualan', 'pembelian'] as $report) {
            foreach ($invalidPeriods as $period) {
                $response = $this->withToken($token)->getJson(
                    "/api/v1/laporan/{$report}?".http_build_query($period['query'])
                )->assertUnprocessable();

                $this->assertD13ErrorEnvelope($response, $period['error_field']);
                $this->assertOperationResponseMatchesOpenApi($response, "/laporan/{$report}", 'get');
            }
        }
    }

    public function test_report_transaction_timestamps_use_mysql_second_precision(): void
    {
        foreach (['penjualans', 'pembelians'] as $table) {
            $precision = DB::table('information_schema.columns')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->where('table_name', $table)
                ->where('column_name', 'tanggal')
                ->value('datetime_precision');

            $this->assertNotNull($precision, "{$table}.tanggal exists in the test database");
            $this->assertSame(0, (int) $precision, "{$table}.tanggal uses whole-second precision");
        }
    }

    private function assertReportSuccessEnvelope(TestResponse $response, string $totalField): void
    {
        $body = $response->json();

        $this->assertEqualsCanonicalizing(['data'], array_keys($body));
        $this->assertEqualsCanonicalizing(
            ['period', 'jumlah_transaksi', $totalField],
            array_keys($body['data']),
        );
        $this->assertEqualsCanonicalizing(
            ['date_from', 'date_to', 'timezone'],
            array_keys($body['data']['period']),
        );
        $this->assertIsInt($body['data']['jumlah_transaksi']);
        $this->assertIsString($body['data'][$totalField]);
    }

    private function assertD13ErrorEnvelope(TestResponse $response, string $expectedField): void
    {
        $body = $response->json();

        $this->assertEqualsCanonicalizing(
            ['code', 'message', 'errors', 'request_id'],
            array_keys($body),
        );
        $this->assertSame('VALIDATION_ERROR', $body['code']);
        $this->assertIsString($body['message']);
        $this->assertNotEmpty($body['message']);
        $this->assertArrayHasKey($expectedField, $body['errors']);
        $this->assertNotEmpty($body['errors'][$expectedField]);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $body['request_id'],
        );

        foreach ($body['errors'] as $messages) {
            $this->assertIsArray($messages);

            foreach ($messages as $message) {
                $this->assertIsString($message);
            }
        }
    }
}
