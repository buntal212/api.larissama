<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\PenjualanRinci;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerTenantAccessConformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_tenant_operations_without_reading_another_warung(): void
    {
        $warungA = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $warungB = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'owner']);
        $cashierA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'kasir']);
        $cashierB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'kasir']);
        $categoryA = KategoriMenu::factory()->create(['warung_id' => $warungA->id, 'nama' => 'Makanan A']);
        $categoryB = KategoriMenu::factory()->create(['warung_id' => $warungB->id, 'nama' => 'Makanan B']);
        $menuA = Menu::factory()->create([
            'warung_id' => $warungA->id,
            'kategori_menu_id' => $categoryA->id,
            'kode' => 'MENU-A',
            'harga' => '15000.00',
        ]);
        $menuB = Menu::factory()->create([
            'warung_id' => $warungB->id,
            'kategori_menu_id' => $categoryB->id,
            'kode' => 'MENU-B',
        ]);
        $ownSale = Penjualan::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $cashierA->id,
            'tanggal' => '2026-10-05 03:00:00',
            'total' => '7000.00',
        ]);
        PenjualanRinci::factory()->create([
            'penjualan_id' => $ownSale->id,
            'warung_id' => $warungA->id,
            'menu_id' => $menuA->id,
            'harga' => $menuA->harga,
            'subtotal' => '7000.00',
        ]);
        $foreignSale = Penjualan::factory()->create([
            'warung_id' => $warungB->id,
            'user_id' => $cashierB->id,
            'tanggal' => '2026-10-05 03:00:00',
        ]);
        $foreignPurchase = Pembelian::factory()->create([
            'warung_id' => $warungB->id,
            'user_id' => $cashierB->id,
            'tanggal' => '2026-10-05 03:00:00',
        ]);
        $token = $owner->createToken('owner-tenant-access-test')->plainTextToken;

        $profile = $this->withToken($token)->getJson('/api/v1/warung')->assertOk();
        $this->assertOperationResponseMatchesOpenApi($profile, '/warung', 'get');
        $this->assertSame((string) $warungA->id, $profile->json('data.id'));

        $categoryPayload = ['nama' => 'Minuman Owner', 'urutan' => 2];
        $this->assertOperationRequestMatchesOpenApi($categoryPayload, [], '/kategori-menus', 'post');
        $createdCategory = $this->withToken($token)
            ->postJson('/api/v1/kategori-menus', $categoryPayload)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($createdCategory, '/kategori-menus', 'post');
        $categoryId = $createdCategory->json('data.id');

        $categoryList = $this->withToken($token)->getJson('/api/v1/kategori-menus')->assertOk();
        $this->assertOperationResponseMatchesOpenApi($categoryList, '/kategori-menus', 'get');
        $this->assertContains((string) $categoryA->id, array_column($categoryList->json('data'), 'id'));
        $this->assertContains((string) $categoryId, array_column($categoryList->json('data'), 'id'));
        $this->assertNotContains((string) $categoryB->id, array_column($categoryList->json('data'), 'id'));
        $this->withToken($token)->getJson('/api/v1/kategori-menus/'.$categoryB->id)->assertNotFound();
        $categoryUpdate = $this->withToken($token)
            ->patchJson('/api/v1/kategori-menus/'.$categoryId, ['nama' => 'Minuman Owner Updated'])
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($categoryUpdate, '/kategori-menus/{id}', 'patch');

        $menuPayload = [
            'kategori_menu_id' => (string) $categoryId,
            'nama' => 'Es Teh',
            'harga' => '5000.00',
        ];
        $this->assertOperationRequestMatchesOpenApi($menuPayload, [], '/menus', 'post');
        $createdMenu = $this->withToken($token)->postJson('/api/v1/menus', $menuPayload)->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($createdMenu, '/menus', 'post');
        $menuId = $createdMenu->json('data.id');
        $menuList = $this->withToken($token)->getJson('/api/v1/menus')->assertOk();
        $this->assertOperationResponseMatchesOpenApi($menuList, '/menus', 'get');
        $this->assertContains((string) $menuA->id, array_column($menuList->json('data'), 'id'));
        $this->assertContains((string) $menuId, array_column($menuList->json('data'), 'id'));
        $this->assertNotContains((string) $menuB->id, array_column($menuList->json('data'), 'id'));
        $this->withToken($token)->getJson('/api/v1/menus/'.$menuB->id)->assertNotFound();
        $menuUpdate = $this->withToken($token)
            ->patchJson('/api/v1/menus/'.$menuId, ['harga' => '6000.00'])
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($menuUpdate, '/menus/{id}', 'patch');

        $salePayload = [
            'tanggal' => '2026-10-05T11:00:00+07:00',
            'bayar' => '30000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menuA->id, 'qty' => '2.00']],
        ];
        $saleHeaders = ['Idempotency-Key' => 'owner-sale-tenant-access-001'];
        $this->assertOperationRequestMatchesOpenApi($salePayload, $saleHeaders, '/penjualans', 'post');
        $createdSale = $this->withToken($token)
            ->postJson('/api/v1/penjualans', $salePayload, $saleHeaders)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($createdSale, '/penjualans', 'post');
        $createdSaleId = $createdSale->json('data.id');
        $this->assertSame((string) $owner->id, $createdSale->json('data.user_id'));

        $saleQuery = ['date_from' => '2026-10-05', 'date_to' => '2026-10-05'];
        $this->assertOperationQueryMatchesOpenApi($saleQuery, '/penjualans', 'get');
        $saleList = $this->withToken($token)
            ->getJson('/api/v1/penjualans?'.http_build_query($saleQuery))
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($saleList, '/penjualans', 'get');
        $saleIds = array_column($saleList->json('data'), 'id');
        $this->assertContains((string) $ownSale->id, $saleIds);
        $this->assertContains((string) $createdSaleId, $saleIds);
        $this->assertNotContains((string) $foreignSale->id, $saleIds);
        $saleDetail = $this->withToken($token)->getJson('/api/v1/penjualans/'.$ownSale->id)->assertOk();
        $this->assertOperationResponseMatchesOpenApi($saleDetail, '/penjualans/{id}', 'get');
        $this->withToken($token)->getJson('/api/v1/penjualans/'.$foreignSale->id)->assertNotFound();

        $purchasePayload = [
            'tanggal' => '2026-10-05T12:00:00+07:00',
            'rincian' => [['nama_item' => 'Belanja pasar', 'subtotal' => '150000.00']],
        ];
        $purchaseHeaders = ['Idempotency-Key' => 'owner-purchase-tenant-access-001'];
        $this->assertOperationRequestMatchesOpenApi($purchasePayload, $purchaseHeaders, '/pembelians', 'post');
        $createdPurchase = $this->withToken($token)
            ->postJson('/api/v1/pembelians', $purchasePayload, $purchaseHeaders)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($createdPurchase, '/pembelians', 'post');
        $createdPurchaseId = $createdPurchase->json('data.id');
        $purchaseList = $this->withToken($token)
            ->getJson('/api/v1/pembelians?'.http_build_query($saleQuery))
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($purchaseList, '/pembelians', 'get');
        $this->assertSame([(string) $createdPurchaseId], array_column($purchaseList->json('data'), 'id'));
        $purchaseDetail = $this->withToken($token)
            ->getJson('/api/v1/pembelians/'.$createdPurchaseId)
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($purchaseDetail, '/pembelians/{id}', 'get');
        $this->withToken($token)->getJson('/api/v1/pembelians/'.$foreignPurchase->id)->assertNotFound();

        foreach ([
            ['path' => '/laporan/penjualan', 'expected_total' => '37000.00', 'total_field' => 'total_pendapatan'],
            ['path' => '/laporan/pembelian', 'expected_total' => '150000.00', 'total_field' => 'total_pembelian'],
        ] as $report) {
            $this->assertOperationQueryMatchesOpenApi($saleQuery, $report['path'], 'get');
            $response = $this->withToken($token)
                ->getJson('/api/v1'.$report['path'].'?'.http_build_query($saleQuery))
                ->assertOk()
                ->assertJsonPath('data.'.$report['total_field'], $report['expected_total'])
                ->assertJsonPath('data.period.timezone', 'Asia/Jakarta');
            $this->assertOperationResponseMatchesOpenApi($response, $report['path'], 'get');
        }
    }
}
