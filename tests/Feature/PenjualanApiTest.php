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

class PenjualanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_timestamp_is_stored_as_utc_and_listed_by_warung_local_date(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '1000.00']);
        $token = $cashier->createToken('sale-utc-instant-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T23:30:00-04:00',
            'bayar' => '1000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];
        $headers = ['Idempotency-Key' => 'sale-utc-instant-001'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
        $created = $this->withToken($token)
            ->postJson('/api/v1/penjualans', $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('data.tanggal', '2026-10-05T03:30:00.000000Z');
        $this->assertOperationResponseMatchesOpenApi($created, '/penjualans', 'post');

        $saleId = (int) $created->json('data.id');
        $this->assertSame('2026-10-05 03:30:00', DB::table('penjualans')->where('id', $saleId)->value('tanggal'));

        foreach ([
            ['date' => '2026-10-04', 'expected_ids' => []],
            ['date' => '2026-10-05', 'expected_ids' => [(string) $saleId]],
        ] as $period) {
            $query = ['date_from' => $period['date'], 'date_to' => $period['date']];
            $this->assertOperationQueryMatchesOpenApi($query, '/penjualans', 'get');
            $listed = $this->withToken($token)
                ->getJson('/api/v1/penjualans?'.http_build_query($query))
                ->assertOk();

            $this->assertSame($period['expected_ids'], array_column($listed->json('data'), 'id'));
            $this->assertOperationResponseMatchesOpenApi($listed, '/penjualans', 'get');
        }
    }

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

    public function test_sale_calculates_exact_decimal_subtotal_total_and_change(): void
    {
        $warung = Warung::factory()->create();
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '17.25']);
        $token = $cashier->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'bayar' => '60.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '3.00']],
        ];
        $headers = ['Idempotency-Key' => 'sale-decimal-exact-001'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
        $response = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '51.75')
            ->assertJsonPath('data.diskon', '0.00')
            ->assertJsonPath('data.total', '51.75')
            ->assertJsonPath('data.bayar', '60.00')
            ->assertJsonPath('data.kembalian', '8.25')
            ->assertJsonPath('data.rincian.0.qty', '3.00')
            ->assertJsonPath('data.rincian.0.harga', '17.25')
            ->assertJsonPath('data.rincian.0.subtotal', '51.75');
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');

        $saleId = (int) $response->json('data.id');
        $this->assertDatabaseHas('penjualans', [
            'id' => $saleId,
            'subtotal' => '51.75',
            'total' => '51.75',
            'bayar' => '60.00',
            'kembalian' => '8.25',
        ]);
        $this->assertDatabaseHas('penjualan_rincis', [
            'penjualan_id' => $saleId,
            'menu_id' => $menu->id,
            'harga' => '17.25',
            'qty' => '3.00',
            'subtotal' => '51.75',
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

    public function test_sale_rejects_client_warung_id_injection_without_writing(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $cashier = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warungA->id]);
        $token = $cashier->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'warung_id' => (string) $warungB->id,
            'bayar' => '15000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];

        $response = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, [
            'Idempotency-Key' => 'sale-tenant-injection-denied',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('warung_id');
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');

        $this->assertDatabaseCount('penjualans', 0);
        $this->assertDatabaseCount('penjualan_rincis', 0);
    }

    public function test_manager_can_read_all_sales_in_their_warung_but_not_another_warung(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $managerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $cashierA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'kasir']);
        $cashierB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'kasir']);
        $saleA = Penjualan::factory()->create(['warung_id' => $warungA->id, 'user_id' => $cashierA->id]);
        $saleB = Penjualan::factory()->create(['warung_id' => $warungB->id, 'user_id' => $cashierB->id]);
        PenjualanRinci::factory()->create(['warung_id' => $warungA->id, 'penjualan_id' => $saleA->id]);
        PenjualanRinci::factory()->create(['warung_id' => $warungB->id, 'penjualan_id' => $saleB->id]);
        $token = $managerA->createToken('feature-test')->plainTextToken;
        $query = ['page' => '1', 'per_page' => '1', 'sort' => '-tanggal', 'status' => 'selesai'];

        $this->assertOperationQueryMatchesOpenApi($query, '/penjualans', 'get');
        $response = $this->withToken($token)->getJson('/api/v1/penjualans?'.http_build_query($query))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $saleA->id)
            ->assertJsonPath('data.0.user_id', (string) $cashierA->id)
            ->assertJsonPath('meta.total', 1);
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'get');

        $ownDetail = $this->withToken($token)->getJson("/api/v1/penjualans/{$saleA->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $saleA->id)
            ->assertJsonPath('data.user_id', (string) $cashierA->id)
            ->assertJsonPath('data.rincian.0.penjualan_id', (string) $saleA->id);
        $this->assertOperationResponseMatchesOpenApi($ownDetail, '/penjualans/{id}', 'get');

        $foreignDetail = $this->withToken($token)->getJson("/api/v1/penjualans/{$saleB->id}")
            ->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($foreignDetail, '/penjualans/{id}', 'get');
        $this->assertDatabaseHas('penjualans', ['id' => $saleB->id, 'user_id' => $cashierB->id]);
    }

    public function test_cashier_can_read_all_sales_in_their_warung_but_not_another_warung(): void
    {
        $warung = Warung::factory()->create();
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $otherCashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $ownSale = Penjualan::factory()->create(['warung_id' => $warung->id, 'user_id' => $cashier->id]);
        $otherSale = Penjualan::factory()->create(['warung_id' => $warung->id, 'user_id' => $otherCashier->id]);
        PenjualanRinci::factory()->create(['warung_id' => $warung->id, 'penjualan_id' => $ownSale->id]);
        PenjualanRinci::factory()->create(['warung_id' => $warung->id, 'penjualan_id' => $otherSale->id]);
        $token = $cashier->createToken('feature-test')->plainTextToken;
        $query = ['page' => '1', 'per_page' => '20', 'sort' => '-tanggal'];

        $this->assertOperationQueryMatchesOpenApi($query, '/penjualans', 'get');
        $list = $this->withToken($token)
            ->getJson('/api/v1/penjualans?'.http_build_query($query))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
        $this->assertOperationResponseMatchesOpenApi($list, '/penjualans', 'get');

        $ownDetail = $this->withToken($token)->getJson("/api/v1/penjualans/{$ownSale->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $ownSale->id);
        $this->assertOperationResponseMatchesOpenApi($ownDetail, '/penjualans/{id}', 'get');

        $otherDetail = $this->withToken($token)->getJson("/api/v1/penjualans/{$otherSale->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $otherSale->id);
        $this->assertOperationResponseMatchesOpenApi($otherDetail, '/penjualans/{id}', 'get');
        $this->assertDatabaseHas('penjualans', ['id' => $otherSale->id, 'user_id' => $otherCashier->id]);
    }

    public function test_superadmin_cannot_read_tenant_sales(): void
    {
        $warung = Warung::factory()->create();
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $sale = Penjualan::factory()->create(['warung_id' => $warung->id, 'user_id' => $cashier->id]);
        $superadmin = User::factory()->create(['warung_id' => null, 'role' => 'superadmin']);
        $token = $superadmin->createToken('feature-test')->plainTextToken;
        $query = ['page' => '1', 'per_page' => '20', 'sort' => '-tanggal'];

        $this->assertOperationQueryMatchesOpenApi($query, '/penjualans', 'get');
        $response = $this->withToken($token)
            ->getJson('/api/v1/penjualans?'.http_build_query($query))
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'get');

        $detail = $this->withToken($token)
            ->getJson('/api/v1/penjualans/'.$sale->id)
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
        $this->assertOperationResponseMatchesOpenApi($detail, '/penjualans/{id}', 'get');
        $this->assertDatabaseHas('penjualans', ['id' => $sale->id, 'warung_id' => $warung->id]);
    }

    public function test_invalid_sale_list_status_and_date_filters_return_schema_conformant_validation_errors(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $invalidQueries = [
            ['query' => ['status' => 'tidak-valid'], 'error' => 'status'],
            ['query' => ['date_from' => '04-10-2026', 'date_to' => '2026-10-04'], 'error' => 'date_from'],
            ['query' => ['date_from' => '2026-10-05', 'date_to' => '2026-10-04'], 'error' => 'date_to'],
        ];

        foreach ($invalidQueries as $case) {
            $response = $this->withToken($token)
                ->getJson('/api/v1/penjualans?'.http_build_query($case['query']))
                ->assertUnprocessable()
                ->assertJsonPath('code', 'VALIDATION_ERROR')
                ->assertJsonValidationErrors($case['error']);

            $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'get');
        }

        $this->assertDatabaseCount('penjualans', 0);
        $this->assertDatabaseCount('penjualan_rincis', 0);
    }

    public function test_sale_list_requires_date_from_and_date_to_as_a_pair(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $partialQueries = [
            ['query' => ['date_from' => '2026-10-04'], 'error' => 'date_to'],
            ['query' => ['date_to' => '2026-10-04'], 'error' => 'date_from'],
        ];

        foreach ($partialQueries as $case) {
            $response = $this->withToken($token)
                ->getJson('/api/v1/penjualans?'.http_build_query($case['query']))
                ->assertUnprocessable()
                ->assertJsonPath('code', 'VALIDATION_ERROR')
                ->assertJsonValidationErrors($case['error']);

            $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'get');
        }

        $this->assertDatabaseCount('penjualans', 0);
        $this->assertDatabaseCount('penjualan_rincis', 0);
    }

    public function test_sale_list_filters_by_the_shop_local_day_with_an_exclusive_end(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $beforeStart = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
            'tanggal' => '2026-10-03 16:59:59',
        ]);
        $atStart = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
            'tanggal' => '2026-10-03 17:00:00',
        ]);
        $beforeEnd = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
            'tanggal' => '2026-10-04 16:59:59',
        ]);
        $atEnd = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
            'tanggal' => '2026-10-04 17:00:00',
        ]);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $query = [
            'page' => '1',
            'per_page' => '20',
            'date_from' => '2026-10-04',
            'date_to' => '2026-10-04',
        ];

        $this->assertOperationQueryMatchesOpenApi($query, '/penjualans', 'get');
        $response = $this->withToken($token)
            ->getJson('/api/v1/penjualans?'.http_build_query($query))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', (string) $beforeEnd->id)
            ->assertJsonPath('data.1.id', (string) $atStart->id);
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'get');

        $this->assertNotSame($beforeStart->id, $response->json('data.0.id'));
        $this->assertNotSame($atEnd->id, $response->json('data.0.id'));
    }

    public function test_sale_list_uses_the_shop_timezone_across_a_dst_short_day(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'America/New_York']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $beforeStart = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
            'tanggal' => '2026-03-08 04:59:59',
        ]);
        $atStart = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
            'tanggal' => '2026-03-08 05:00:00',
        ]);
        $beforeEnd = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
            'tanggal' => '2026-03-09 03:59:59',
        ]);
        $atEnd = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
            'tanggal' => '2026-03-09 04:00:00',
        ]);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $query = ['page' => '1', 'per_page' => '20', 'date_from' => '2026-03-08', 'date_to' => '2026-03-08'];

        $this->assertOperationQueryMatchesOpenApi($query, '/penjualans', 'get');
        $response = $this->withToken($token)
            ->getJson('/api/v1/penjualans?'.http_build_query($query))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', (string) $beforeEnd->id)
            ->assertJsonPath('data.1.id', (string) $atStart->id);
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'get');

        $this->assertNotSame($beforeStart->id, $response->json('data.0.id'));
        $this->assertNotSame($atEnd->id, $response->json('data.0.id'));
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

    public function test_manager_can_create_sales(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'bayar' => '15000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];
        $headers = ['Idempotency-Key' => 'sale-manager-denied'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
        $response = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('data.status_pembayaran', 'lunas');
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');

        $this->assertDatabaseCount('penjualans', 1);
        $this->assertDatabaseCount('penjualan_rincis', 1);
    }

    public function test_superadmin_cannot_create_a_sale_for_a_tenant(): void
    {
        $warung = Warung::factory()->create();
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $superadmin = User::factory()->create(['warung_id' => null, 'role' => 'superadmin']);
        $token = $superadmin->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'bayar' => '15000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];
        $headers = ['Idempotency-Key' => 'sale-superadmin-denied'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
        $response = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers)
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');

        $this->assertDatabaseCount('penjualans', 0);
        $this->assertDatabaseCount('penjualan_rincis', 0);
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
