<?php

namespace Tests\Feature;

use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->withToken($token)->getJson('/api/v1/laporan/penjualan?date_from=2026-03-08&date_to=2026-03-08')
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 2)
            ->assertJsonPath('data.total_pendapatan', '5000.00')
            ->assertJsonPath('data.period.timezone', 'America/New_York');
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

        $this->withToken($token)->getJson('/api/v1/laporan/pembelian?date_from=2026-03-08&date_to=2026-03-08')
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 2)
            ->assertJsonPath('data.total_pembelian', '5000.00')
            ->assertJsonPath('data.period.timezone', 'America/New_York');
    }

    public function test_empty_reports_return_zero_for_both_transaction_types(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;

        foreach ([
            ['route' => 'penjualan', 'total_field' => 'data.total_pendapatan'],
            ['route' => 'pembelian', 'total_field' => 'data.total_pembelian'],
        ] as $report) {
            $this->withToken($token)->getJson(
                "/api/v1/laporan/{$report['route']}?date_from=2026-10-04&date_to=2026-10-04"
            )
                ->assertOk()
                ->assertJsonPath('data.jumlah_transaksi', 0)
                ->assertJsonPath($report['total_field'], '0.00')
                ->assertJsonPath('data.period.timezone', 'Asia/Jakarta');
        }
    }

    public function test_both_reports_reject_invalid_periods_with_422(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $invalidPeriods = [
            ['date_from' => '04-10-2026', 'date_to' => '2026-10-04'],
            ['date_from' => '2026-10-04'],
            ['date_from' => '2026-10-05', 'date_to' => '2026-10-04'],
        ];

        foreach (['penjualan', 'pembelian'] as $report) {
            foreach ($invalidPeriods as $period) {
                $this->withToken($token)->getJson(
                    "/api/v1/laporan/{$report}?".http_build_query($period)
                )->assertUnprocessable();
            }
        }
    }
}
