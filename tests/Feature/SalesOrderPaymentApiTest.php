<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesOrderPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_create_pending_order_seen_by_another_cashier_then_pay_it(): void
    {
        $warung = Warung::factory()->create();
        $recorder = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '15000.00']);
        $recorderToken = $recorder->createToken('order-test')->plainTextToken;
        $cashierToken = $cashier->createToken('payment-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-07T10:00:00+07:00',
            'nama_pelanggan' => 'Andi',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '2.00']],
        ];

        $created = $this->withToken($recorderToken)->postJson('/api/v1/penjualans', $payload, [
            'Idempotency-Key' => 'order-pending-001',
        ])->assertCreated()
            ->assertJsonPath('data.nama_pelanggan', 'Andi')
            ->assertJsonPath('data.status', 'menunggu_pembayaran')
            ->assertJsonPath('data.status_pembayaran', 'belum_lunas')
            ->assertJsonPath('data.total', '30000.00')
            ->assertJsonPath('data.bayar', '0.00')
            ->assertJsonPath('data.metode_pembayaran', null);
        $this->assertMatchesRegularExpression('/^PJ-[0-9A-HJKMNP-TV-Z]{26}$/', $created->json('data.no_transaksi'));
        $this->assertOperationResponseMatchesOpenApi($created, '/penjualans', 'post');

        Auth::forgetGuards();
        $list = $this->withToken($cashierToken)->getJson('/api/v1/penjualans?status_pembayaran=belum_lunas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $created->json('data.id'))
            ->assertJsonPath('data.0.no_transaksi', $created->json('data.no_transaksi'));
        $this->assertOperationResponseMatchesOpenApi($list, '/penjualans', 'get');

        $paymentPayload = ['bayar' => '50000.00', 'metode_pembayaran' => 'cash'];
        $paymentHeaders = ['Idempotency-Key' => 'order-payment-001'];
        Auth::forgetGuards();
        $paid = $this->withToken($cashierToken)->postJson(
            '/api/v1/penjualans/'.$created->json('data.id').'/pembayaran',
            $paymentPayload,
            [...$paymentHeaders, 'Authorization' => 'Bearer '.$cashierToken],
        )->assertCreated()
            ->assertJsonPath('data.no_transaksi', $created->json('data.no_transaksi'))
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.status_pembayaran', 'lunas')
            ->assertJsonPath('data.bayar', '50000.00')
            ->assertJsonPath('data.kembalian', '20000.00')
            ->assertJsonPath('data.metode_pembayaran', 'cash');
        $this->assertNotNull($paid->json('data.dibayar_pada'));
        $this->assertDatabaseHas('penjualans', [
            'id' => $created->json('data.id'),
            'pembayaran_user_id' => $cashier->id,
        ]);
        $this->assertOperationResponseMatchesOpenApi($paid, '/penjualans/{id}/pembayaran', 'post');

        $replay = $this->withToken($cashierToken)->postJson(
            '/api/v1/penjualans/'.$created->json('data.id').'/pembayaran',
            $paymentPayload,
            $paymentHeaders,
        )->assertCreated()->assertJsonPath('data.id', $created->json('data.id'));
        $this->assertSame(1, Penjualan::query()->count());
        $this->assertSame(1, DB::table('penjualans')->whereNotNull('pembayaran_idempotency_key')->count());
        $this->assertOperationResponseMatchesOpenApi($replay, '/penjualans/{id}/pembayaran', 'post');
    }

    public function test_pending_order_can_be_edited_with_reason_and_cashier_sees_paid_filter(): void
    {
        $warung = Warung::factory()->create();
        $recorder = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '12000.00']);
        $token = $recorder->createToken('edit-order-test')->plainTextToken;
        $sale = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $recorder->id,
            'status' => 'menunggu_pembayaran',
            'status_pembayaran' => 'belum_lunas',
            'bayar' => '0.00',
            'metode_pembayaran' => null,
            'dibayar_pada' => null,
            'pembayaran_user_id' => null,
            'tanggal' => '2026-10-01 03:00:00',
            'created_at' => '2026-10-01 03:00:00',
        ]);
        $sale->rincian()->create([
            'warung_id' => $warung->id,
            'menu_id' => $menu->id,
            'nama_menu' => $menu->nama,
            'harga' => '12000.00',
            'qty' => '1.00',
            'diskon' => '0.00',
            'subtotal' => '12000.00',
        ]);

        $updated = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$sale->id, [
            'alasan' => 'Pelanggan menambah satu porsi',
            'nama_pelanggan' => 'Budi',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '2.00']],
        ], ['Idempotency-Key' => 'pending-edit-001'])->assertCreated()
            ->assertJsonPath('data.alasan', 'Pelanggan menambah satu porsi')
            ->assertJsonPath('data.sesudah.status_pembayaran', 'belum_lunas')
            ->assertJsonPath('data.sesudah.total', '24000.00');

        $this->assertDatabaseHas('penjualans', [
            'id' => $sale->id,
            'nama_pelanggan' => 'Budi',
            'status_pembayaran' => 'belum_lunas',
            'total' => '24000.00',
        ]);
        $this->assertDatabaseHas('penjualan_koreksis', [
            'penjualan_id' => $sale->id,
            'alasan' => 'Pelanggan menambah satu porsi',
        ]);
        $this->assertOperationResponseMatchesOpenApi($updated, '/penjualans/{id}', 'patch');

        $cashierToken = $cashier->createToken('paid-filter-test')->plainTextToken;
        Auth::forgetGuards();
        $paidFilter = $this->withToken($cashierToken)->getJson('/api/v1/penjualans?status_pembayaran=lunas')
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        $this->assertOperationResponseMatchesOpenApi($paidFilter, '/penjualans', 'get');
    }

    public function test_pending_order_can_be_cancelled_with_reason_before_payment(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $sale = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'status' => 'menunggu_pembayaran',
            'status_pembayaran' => 'belum_lunas',
            'bayar' => '0.00',
            'metode_pembayaran' => null,
            'dibayar_pada' => null,
            'pembayaran_user_id' => null,
        ]);
        $token = $cashier->createToken('cancel-pending-order-test')->plainTextToken;

        $cancelled = $this->withToken($token)->postJson('/api/v1/penjualans/'.$sale->id.'/pembatalan', [
            'alasan' => 'Pelanggan membatalkan pesanan sebelum membayar',
        ], ['Idempotency-Key' => 'pending-cancel-001'])->assertCreated()
            ->assertJsonPath('data.jenis', 'batalkan')
            ->assertJsonPath('data.alasan', 'Pelanggan membatalkan pesanan sebelum membayar')
            ->assertJsonPath('data.sesudah.status', 'batal')
            ->assertJsonPath('data.sesudah.status_pembayaran', 'belum_lunas');

        $this->assertDatabaseHas('penjualans', [
            'id' => $sale->id,
            'status' => 'batal',
            'status_pembayaran' => 'belum_lunas',
        ]);
        $this->assertDatabaseHas('penjualan_koreksis', [
            'penjualan_id' => $sale->id,
            'user_id' => $cashier->id,
            'jenis' => 'batalkan',
            'alasan' => 'Pelanggan membatalkan pesanan sebelum membayar',
        ]);
        $this->assertOperationResponseMatchesOpenApi($cancelled, '/penjualans/{id}/pembatalan', 'post');
    }

    public function test_sales_report_uses_payment_local_day_instead_of_order_day(): void
    {
        CarbonImmutable::setTestNow('2026-10-08T04:00:00Z');
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $recorder = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '10000.00']);
        $createToken = $recorder->createToken('report-payment-create')->plainTextToken;
        $paymentToken = $cashier->createToken('report-payment-settle')->plainTextToken;
        $reportToken = $recorder->createToken('report-query')->plainTextToken;
        $order = $this->withToken($createToken)->postJson('/api/v1/penjualans', [
            'tanggal' => '2026-10-06T10:00:00+07:00',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ], ['Idempotency-Key' => 'report-order-001'])->assertCreated();
        Auth::forgetGuards();
        $this->withToken($paymentToken)->postJson('/api/v1/penjualans/'.$order->json('data.id').'/pembayaran', [
            'bayar' => '10000.00', 'metode_pembayaran' => 'qris',
        ], ['Idempotency-Key' => 'report-payment-001'])->assertCreated();

        Auth::forgetGuards();
        $oldPeriod = $this->withToken($reportToken)->getJson('/api/v1/laporan/penjualan?date_from=2026-10-06&date_to=2026-10-06')
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 0)
            ->assertJsonPath('data.total_pendapatan', '0.00');
        $newPeriod = $this->withToken($reportToken)->getJson('/api/v1/laporan/penjualan?date_from=2026-10-08&date_to=2026-10-08')
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 1)
            ->assertJsonPath('data.total_penjualan', '10000.00')
            ->assertJsonPath('data.total_pendapatan', '10000.00');
        $this->assertOperationResponseMatchesOpenApi($oldPeriod, '/laporan/penjualan', 'get');
        $this->assertOperationResponseMatchesOpenApi($newPeriod, '/laporan/penjualan', 'get');
        CarbonImmutable::setTestNow();
    }
}
