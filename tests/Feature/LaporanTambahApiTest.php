<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Penjualan;
use App\Models\PenjualanRinci;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LaporanTambahApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_order_report_counts_only_current_unpaid_orders_in_local_order_period(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $otherWarung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $otherManager = User::factory()->create(['warung_id' => $otherWarung->id, 'role' => 'manager']);

        Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'tanggal' => '2026-10-08 15:30:00',
            'status' => 'menunggu_pembayaran',
            'status_pembayaran' => 'belum_lunas',
            'dibayar_pada' => null,
            'pembayaran_user_id' => null,
            'metode_pembayaran' => null,
            'bayar' => '0.00',
            'total' => '25000.00',
        ]);
        Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'tanggal' => '2026-10-08 15:40:00',
            'total' => '9000.00',
        ]);
        Penjualan::factory()->create([
            'warung_id' => $otherWarung->id,
            'user_id' => $otherManager->id,
            'tanggal' => '2026-10-08 15:30:00',
            'status' => 'menunggu_pembayaran',
            'status_pembayaran' => 'belum_lunas',
            'dibayar_pada' => null,
            'pembayaran_user_id' => null,
            'metode_pembayaran' => null,
            'bayar' => '0.00',
            'total' => '60000.00',
        ]);

        $token = $manager->createToken('report-pending')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/v1/laporan/pesanan-belum-lunas?date_from=2026-10-08&date_to=2026-10-08')
            ->assertOk()
            ->assertJsonPath('data.jumlah_pesanan', 1)
            ->assertJsonPath('data.total_belum_lunas', '25000.00')
            ->assertJsonPath('data.period.timezone', 'Asia/Jakarta');

        $this->assertOperationQueryMatchesOpenApi(['date_from' => '2026-10-08', 'date_to' => '2026-10-08'], '/laporan/pesanan-belum-lunas', 'get');
        $this->assertOperationResponseMatchesOpenApi($response, '/laporan/pesanan-belum-lunas', 'get');
    }

    public function test_sales_summary_and_daily_report_use_local_payment_and_return_dates(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashSale = $this->createPaidSale($warung, $manager, '2026-10-07 16:30:00', 'cash', '3000.00');
        $qrisSale = $this->createPaidSale($warung, $manager, '2026-10-07 17:30:00', 'qris', '1000.00');
        DB::table('penjualan_returs')->insert([
            'warung_id' => $warung->id,
            'penjualan_id' => $qrisSale->id,
            'user_id' => $manager->id,
            'nominal' => '500.00',
            'alasan' => 'Retur sebagian untuk pengujian laporan.',
            'created_at' => '2026-10-08 16:00:00',
            'updated_at' => '2026-10-08 16:00:00',
        ]);

        $token = $manager->createToken('report-daily')->plainTextToken;
        $summary = $this->withToken($token)->getJson('/api/v1/laporan/penjualan?date_from=2026-10-07&date_to=2026-10-08')
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 2)
            ->assertJsonPath('data.total_penjualan', '4000.00')
            ->assertJsonPath('data.rata_rata_transaksi', '2000.00')
            ->assertJsonPath('data.per_metode_pembayaran.0.metode_pembayaran', 'cash')
            ->assertJsonPath('data.per_metode_pembayaran.0.total_penjualan', '3000.00')
            ->assertJsonPath('data.per_metode_pembayaran.1.metode_pembayaran', 'qris')
            ->assertJsonPath('data.jumlah_retur', 1)
            ->assertJsonPath('data.total_retur', '500.00')
            ->assertJsonPath('data.total_pendapatan', '3500.00');
        $this->assertOperationResponseMatchesOpenApi($summary, '/laporan/penjualan', 'get');

        $daily = $this->withToken($token)->getJson('/api/v1/laporan/penjualan-harian?date_from=2026-10-07&date_to=2026-10-08')
            ->assertOk()
            ->assertJsonCount(2, 'data.data')
            ->assertJsonPath('data.data.0.tanggal', '2026-10-07')
            ->assertJsonPath('data.data.0.total_penjualan', '3000.00')
            ->assertJsonPath('data.data.1.tanggal', '2026-10-08')
            ->assertJsonPath('data.data.1.total_penjualan', '1000.00')
            ->assertJsonPath('data.data.1.total_retur', '500.00')
            ->assertJsonPath('data.data.1.total_pendapatan', '500.00');
        $this->assertOperationQueryMatchesOpenApi(['date_from' => '2026-10-07', 'date_to' => '2026-10-08'], '/laporan/penjualan-harian', 'get');
        $this->assertOperationResponseMatchesOpenApi($daily, '/laporan/penjualan-harian', 'get');
    }

    public function test_item_report_preserves_menu_and_free_form_snapshots_and_excludes_unpaid_orders(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'nama' => 'Nama menu terbaru', 'harga' => '99000.00']);
        $paid = $this->createPaidSale($warung, $manager, '2026-10-07 16:30:00', 'cash', '7000.00');
        PenjualanRinci::query()->create([
            'warung_id' => $warung->id,
            'penjualan_id' => $paid->id,
            'menu_id' => $menu->id,
            'nama_menu' => 'Nama menu saat transaksi',
            'harga' => '5000.00',
            'qty' => '1.00',
            'diskon' => '0.00',
            'subtotal' => '5000.00',
        ]);
        PenjualanRinci::query()->create([
            'warung_id' => $warung->id,
            'penjualan_id' => $paid->id,
            'menu_id' => null,
            'nama_menu' => 'Kerupuk',
            'harga' => '2000.00',
            'qty' => '1.00',
            'diskon' => '0.00',
            'subtotal' => '2000.00',
        ]);

        $pending = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'tanggal' => '2026-10-08 15:30:00',
            'status' => 'menunggu_pembayaran',
            'status_pembayaran' => 'belum_lunas',
            'dibayar_pada' => null,
            'pembayaran_user_id' => null,
            'metode_pembayaran' => null,
            'bayar' => '0.00',
        ]);
        PenjualanRinci::query()->create([
            'warung_id' => $warung->id,
            'penjualan_id' => $pending->id,
            'menu_id' => $menu->id,
            'nama_menu' => 'Belum dibayar',
            'harga' => '1000.00',
            'qty' => '1.00',
            'diskon' => '0.00',
            'subtotal' => '1000.00',
        ]);

        $token = $manager->createToken('report-item')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/v1/laporan/penjualan-item?date_from=2026-10-07&date_to=2026-10-08')
            ->assertOk()
            ->assertJsonCount(2, 'data.data')
            ->assertJsonPath('data.data.0.jenis_item', 'bebas')
            ->assertJsonPath('data.data.0.nama_item', 'Kerupuk')
            ->assertJsonPath('data.data.1.jenis_item', 'menu')
            ->assertJsonPath('data.data.1.menu_id', (string) $menu->id)
            ->assertJsonPath('data.data.1.nama_item', 'Nama menu saat transaksi')
            ->assertJsonPath('data.data.1.harga_saat_transaksi', '5000.00');

        $this->assertOperationQueryMatchesOpenApi(['date_from' => '2026-10-07', 'date_to' => '2026-10-08'], '/laporan/penjualan-item', 'get');
        $this->assertOperationResponseMatchesOpenApi($response, '/laporan/penjualan-item', 'get');
    }

    private function createPaidSale(Warung $warung, User $manager, string $paidAt, string $method, string $total): Penjualan
    {
        return Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'tanggal' => $paidAt,
            'dibayar_pada' => $paidAt,
            'metode_pembayaran' => $method,
            'total' => $total,
            'subtotal' => $total,
            'bayar' => $total,
            'status' => 'selesai',
            'status_pembayaran' => 'lunas',
        ]);
    }
}
