<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenjualanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_creates_sale_using_menu_snapshot_and_backend_totals(): void
    {
        $warung = Warung::factory()->create();
        $kasir = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'nama' => 'Nasi Goreng',
            'harga' => '15000.00',
        ]);
        $token = $kasir->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'diskon' => '2000.00',
            'bayar' => '50000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '2.00']],
        ];
        $headers = ['Idempotency-Key' => 'sale-snapshot-001'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
        $response = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '30000.00')
            ->assertJsonPath('data.diskon', '2000.00')
            ->assertJsonPath('data.total', '28000.00')
            ->assertJsonPath('data.bayar', '50000.00')
            ->assertJsonPath('data.kembalian', '22000.00')
            ->assertJsonPath('data.rincian.0.nama_menu', 'Nasi Goreng')
            ->assertJsonPath('data.rincian.0.harga', '15000.00')
            ->assertJsonPath('data.rincian.0.subtotal', '30000.00');
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');

        $saleId = (int) $response->json('data.id');
        $this->assertDatabaseHas('penjualans', [
            'id' => $saleId,
            'warung_id' => $warung->id,
            'user_id' => $kasir->id,
            'subtotal' => '30000.00',
            'total' => '28000.00',
        ]);
        $this->assertDatabaseHas('penjualan_rincis', [
            'penjualan_id' => $saleId,
            'menu_id' => $menu->id,
            'nama_menu' => 'Nasi Goreng',
            'harga' => '15000.00',
        ]);
    }

    public function test_sale_retry_replays_same_transaction_and_rejects_different_payload(): void
    {
        $warung = Warung::factory()->create();
        $kasir = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $token = $kasir->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'bayar' => '15000.00',
            'metode_pembayaran' => 'cash',
            'catatan' => 'Meja satu',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];
        $headers = ['Idempotency-Key' => 'sale-retry-001'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
        $first = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers)->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($first, '/penjualans', 'post');
        $saleId = $first->json('data.id');

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
        $replay = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers)
            ->assertCreated()->assertJsonPath('data.id', $saleId);
        $this->assertOperationResponseMatchesOpenApi($replay, '/penjualans', 'post');

        $payload['catatan'] = 'Meja dua';
        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
        $conflict = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers)
            ->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertOperationResponseMatchesOpenApi($conflict, '/penjualans', 'post');

        $sale = Penjualan::query()->whereKey($saleId)->firstOrFail();
        $this->assertSame(1, Penjualan::query()->where('warung_id', $warung->id)->count());
        $this->assertSame(1, $sale->rincian()->count());
    }

    public function test_sale_rejects_menu_from_another_warung_without_persisting_header(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $kasir = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'kasir']);
        $menuB = Menu::factory()->create(['warung_id' => $warungB->id]);
        $token = $kasir->createToken('feature-test')->plainTextToken;

        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'bayar' => '15000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menuB->id, 'qty' => '1.00']],
        ];
        $headers = ['Idempotency-Key' => 'sale-cross-tenant-001'];
        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
        $response = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers)->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');

        $this->assertSame(0, Penjualan::query()->count());
    }

    public function test_manager_can_only_list_sales_from_their_warung(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $managerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $cashierA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'kasir']);
        $cashierB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'kasir']);
        $saleA = Penjualan::factory()->create(['warung_id' => $warungA->id, 'user_id' => $cashierA->id]);
        Penjualan::factory()->create(['warung_id' => $warungB->id, 'user_id' => $cashierB->id]);
        $token = $managerA->createToken('feature-test')->plainTextToken;
        $query = ['page' => '1', 'per_page' => '1', 'sort' => '-tanggal', 'status' => 'selesai'];

        $this->assertOperationQueryMatchesOpenApi($query, '/penjualans', 'get');
        $response = $this->withToken($token)->getJson('/api/v1/penjualans?'.http_build_query($query))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $saleA->id);
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'get');
    }

    public function test_sale_detail_keeps_original_menu_snapshot_after_menu_changes(): void
    {
        $warung = Warung::factory()->create();
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'nama' => 'Nasi Goreng Awal',
            'harga' => '15000.00',
        ]);
        $token = $cashier->createToken('feature-test')->plainTextToken;

        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'bayar' => '15000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];
        $headers = ['Idempotency-Key' => 'sale-history-snapshot-001'];
        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
        $created = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers)->assertCreated();
        $saleId = (int) $created->json('data.id');

        $menu->update(['nama' => 'Nasi Goreng Baru', 'harga' => '20000.00']);

        $detail = $this->withToken($token)->getJson("/api/v1/penjualans/{$saleId}")
            ->assertOk()
            ->assertJsonPath('data.rincian.0.nama_menu', 'Nasi Goreng Awal')
            ->assertJsonPath('data.rincian.0.harga', '15000.00')
            ->assertJsonPath('data.rincian.0.subtotal', '15000.00');
        $this->assertOperationResponseMatchesOpenApi($detail, '/penjualans/{id}', 'get');

        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'nama' => 'Nasi Goreng Baru',
            'harga' => '20000.00',
        ]);
        $this->assertDatabaseHas('penjualan_rincis', [
            'penjualan_id' => $saleId,
            'menu_id' => $menu->id,
            'nama_menu' => 'Nasi Goreng Awal',
            'harga' => '15000.00',
            'subtotal' => '15000.00',
        ]);
    }

    public function test_manager_gets_404_for_sale_from_another_warung(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $managerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $cashierB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'kasir']);
        $saleB = Penjualan::factory()->create(['warung_id' => $warungB->id, 'user_id' => $cashierB->id]);
        $token = $managerA->createToken('feature-test')->plainTextToken;

        $response = $this->withToken($token)->getJson("/api/v1/penjualans/{$saleB->id}")->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans/{id}', 'get');

        $this->assertDatabaseHas('penjualans', [
            'id' => $saleB->id,
            'warung_id' => $warungB->id,
            'user_id' => $cashierB->id,
        ]);
    }

    public function test_manager_cannot_create_sales(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/penjualans', [], [
            'Idempotency-Key' => 'sale-manager-denied',
        ])->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');

        $this->assertSame(0, Penjualan::query()->count());
    }

    public function test_sales_report_uses_tenant_local_day_and_completed_sales_only(): void
    {
        $warungA = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $warungB = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $managerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $cashierA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'kasir']);
        $cashierB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'kasir']);

        Penjualan::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $cashierA->id,
            'tanggal' => '2026-10-03 16:59:59',
            'total' => '1000.00',
        ]);
        Penjualan::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $cashierA->id,
            'tanggal' => '2026-10-03 17:00:00',
            'total' => '33000.00',
        ]);
        Penjualan::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $cashierA->id,
            'tanggal' => '2026-10-04 16:59:59',
            'total' => '7000.00',
        ]);
        Penjualan::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $cashierA->id,
            'tanggal' => '2026-10-04 17:00:00',
            'total' => '2000.00',
        ]);
        Penjualan::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $cashierA->id,
            'tanggal' => '2026-10-04 12:00:00',
            'total' => '12345.00',
            'status' => 'batal',
        ]);
        Penjualan::factory()->create([
            'warung_id' => $warungB->id,
            'user_id' => $cashierB->id,
            'tanggal' => '2026-10-04 12:00:00',
            'total' => '99999.00',
        ]);
        $token = $managerA->createToken('feature-test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/laporan/penjualan?date_from=2026-10-04&date_to=2026-10-04')
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 2)
            ->assertJsonPath('data.total_pendapatan', '40000.00')
            ->assertJsonPath('data.period.timezone', 'Asia/Jakarta');
    }
}
