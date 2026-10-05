<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SaleInactiveCatalogConformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_sales_reject_inactive_menu_or_category_and_accept_active_catalog(): void
    {
        $warung = Warung::factory()->create();
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $activeCategory = KategoriMenu::factory()->create(['warung_id' => $warung->id, 'aktif' => true]);
        $inactiveCategory = KategoriMenu::factory()->create(['warung_id' => $warung->id, 'aktif' => false]);
        $inactiveMenu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $activeCategory->id,
            'aktif' => false,
        ]);
        $menuInInactiveCategory = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $inactiveCategory->id,
            'aktif' => true,
        ]);
        $activeMenu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $activeCategory->id,
            'aktif' => true,
        ]);
        $token = $cashier->createToken('inactive-catalog-sale-test')->plainTextToken;

        foreach ([
            ['menu' => $inactiveMenu, 'key' => 'inactive-menu'],
            ['menu' => $menuInInactiveCategory, 'key' => 'inactive-category'],
        ] as $case) {
            $payload = $this->salePayload($case['menu']);
            $headers = ['Idempotency-Key' => 'sale-'.$case['key']];
            $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');

            $response = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers)
                ->assertUnprocessable();

            $this->assertValidationError($response, 'rincian.0.menu_id');
            $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');
            $this->assertDatabaseCount('penjualans', 0);
            $this->assertDatabaseCount('penjualan_rincis', 0);
        }

        $activePayload = $this->salePayload($activeMenu);
        $activeHeaders = ['Idempotency-Key' => 'sale-active-menu'];
        $this->assertOperationRequestMatchesOpenApi($activePayload, $activeHeaders, '/penjualans', 'post');
        $created = $this->withToken($token)->postJson('/api/v1/penjualans', $activePayload, $activeHeaders)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($created, '/penjualans', 'post');
        $this->assertDatabaseCount('penjualans', 1);
        $this->assertDatabaseCount('penjualan_rincis', 1);
        $this->assertDatabaseHas('penjualan_rincis', ['menu_id' => $activeMenu->id]);
    }

    /** @return array<string, mixed> */
    private function salePayload(Menu $menu): array
    {
        return [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'bayar' => (string) $menu->harga,
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];
    }

    private function assertValidationError(TestResponse $response, string $field): void
    {
        $body = $response->json();
        $this->assertSame('VALIDATION_ERROR', $body['code']);
        $this->assertArrayHasKey($field, $body['errors']);
        $this->assertNotEmpty($body['errors'][$field]);
        $this->assertArrayHasKey('request_id', $body);
    }
}
