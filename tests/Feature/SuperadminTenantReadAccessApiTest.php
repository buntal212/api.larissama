<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\PembelianRinci;
use App\Models\Penjualan;
use App\Models\PenjualanRinci;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminTenantReadAccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_reads_only_the_explicitly_selected_warung(): void
    {
        $selectedWarung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $otherWarung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $selectedOwner = User::factory()->create(['warung_id' => $selectedWarung->id, 'role' => 'owner']);
        $otherOwner = User::factory()->create(['warung_id' => $otherWarung->id, 'role' => 'owner']);
        $selectedCategory = KategoriMenu::factory()->create(['warung_id' => $selectedWarung->id]);
        $otherCategory = KategoriMenu::factory()->create(['warung_id' => $otherWarung->id]);
        $selectedMenu = Menu::factory()->create([
            'warung_id' => $selectedWarung->id,
            'kategori_menu_id' => $selectedCategory->id,
        ]);
        $otherMenu = Menu::factory()->create([
            'warung_id' => $otherWarung->id,
            'kategori_menu_id' => $otherCategory->id,
        ]);
        $selectedSale = Penjualan::factory()->create([
            'warung_id' => $selectedWarung->id,
            'user_id' => $selectedOwner->id,
            'tanggal' => '2026-10-04 03:00:00',
            'dibayar_pada' => '2026-10-04 03:00:00',
            'status' => 'selesai',
            'status_pembayaran' => 'lunas',
            'total' => '10000.00',
        ]);
        $otherSale = Penjualan::factory()->create([
            'warung_id' => $otherWarung->id,
            'user_id' => $otherOwner->id,
            'tanggal' => '2026-10-04 03:00:00',
            'dibayar_pada' => '2026-10-04 03:00:00',
            'status' => 'selesai',
            'status_pembayaran' => 'lunas',
            'total' => '25000.00',
        ]);
        PenjualanRinci::factory()->create([
            'warung_id' => $selectedWarung->id,
            'penjualan_id' => $selectedSale->id,
            'menu_id' => $selectedMenu->id,
        ]);
        PenjualanRinci::factory()->create([
            'warung_id' => $otherWarung->id,
            'penjualan_id' => $otherSale->id,
            'menu_id' => $otherMenu->id,
        ]);
        $selectedPurchase = Pembelian::factory()->create([
            'warung_id' => $selectedWarung->id,
            'user_id' => $selectedOwner->id,
            'tanggal' => '2026-10-04 03:00:00',
            'total' => '7000.00',
        ]);
        $otherPurchase = Pembelian::factory()->create([
            'warung_id' => $otherWarung->id,
            'user_id' => $otherOwner->id,
            'tanggal' => '2026-10-04 03:00:00',
            'total' => '19000.00',
        ]);
        PembelianRinci::factory()->create(['warung_id' => $selectedWarung->id, 'pembelian_id' => $selectedPurchase->id]);
        PembelianRinci::factory()->create(['warung_id' => $otherWarung->id, 'pembelian_id' => $otherPurchase->id]);
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('tenant-read-test')->plainTextToken;
        $warungQuery = ['warung_id' => (string) $selectedWarung->id];

        $lists = [
            ['/users', $selectedOwner->id, $otherOwner->id],
            ['/kategori-menus', $selectedCategory->id, $otherCategory->id],
            ['/menus', $selectedMenu->id, $otherMenu->id],
            ['/penjualans', $selectedSale->id, $otherSale->id],
            ['/pembelians', $selectedPurchase->id, $otherPurchase->id],
        ];

        foreach ($lists as [$path, $expectedId, $excludedId]) {
            $response = $this->withToken($token)
                ->getJson('/api/v1'.$path.'?'.http_build_query($warungQuery))
                ->assertOk();

            $this->assertContains((string) $expectedId, array_column($response->json('data'), 'id'));
            $this->assertNotContains((string) $excludedId, array_column($response->json('data'), 'id'));
            $this->assertSame(
                [(string) $selectedWarung->id],
                array_values(array_unique(array_column($response->json('data'), 'warung_id'))),
            );
        }

        $details = [
            ['/users/'.$selectedOwner->id, '/users/{id}'],
            ['/kategori-menus/'.$selectedCategory->id, '/kategori-menus/{id}'],
            ['/menus/'.$selectedMenu->id, '/menus/{id}'],
            ['/penjualans/'.$selectedSale->id, '/penjualans/{id}'],
            ['/pembelians/'.$selectedPurchase->id, '/pembelians/{id}'],
        ];

        foreach ($details as [$path, $contractPath]) {
            $response = $this->withToken($token)
                ->getJson('/api/v1'.$path.'?'.http_build_query($warungQuery))
                ->assertOk()
                ->assertJsonPath('data.warung_id', (string) $selectedWarung->id);
            $this->assertOperationResponseMatchesOpenApi($response, $contractPath, 'get');
        }

        $reports = [
            ['/laporan/penjualan', 'total_pendapatan', '10000.00'],
            ['/laporan/pembelian', 'total_pembelian', '7000.00'],
        ];

        foreach ($reports as [$path, $totalField, $expectedTotal]) {
            $response = $this->withToken($token)
                ->getJson('/api/v1'.$path.'?'.http_build_query([
                    ...$warungQuery,
                    'date_from' => '2026-10-04',
                    'date_to' => '2026-10-04',
                ]))
                ->assertOk()
                ->assertJsonPath('data.'.$totalField, $expectedTotal)
                ->assertJsonPath('data.period.timezone', 'Asia/Jakarta');
            $this->assertOperationResponseMatchesOpenApi($response, $path, 'get');
        }
    }

    public function test_superadmin_read_requests_require_a_valid_warung_id(): void
    {
        $warung = Warung::factory()->create();
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('tenant-read-validation-test')->plainTextToken;
        $missingSelectorPaths = [
            ['/users', '/users'],
            ['/users/1', '/users/{id}'],
            ['/kategori-menus', '/kategori-menus'],
            ['/kategori-menus/1', '/kategori-menus/{id}'],
            ['/menus', '/menus'],
            ['/menus/1', '/menus/{id}'],
            ['/penjualans', '/penjualans'],
            ['/penjualans/1', '/penjualans/{id}'],
            ['/pembelians', '/pembelians'],
            ['/pembelians/1', '/pembelians/{id}'],
            ['/laporan/penjualan?date_from=2026-10-04&date_to=2026-10-04', '/laporan/penjualan'],
            ['/laporan/pembelian?date_from=2026-10-04&date_to=2026-10-04', '/laporan/pembelian'],
        ];

        foreach ($missingSelectorPaths as [$path, $contractPath]) {
            $response = $this->withToken($token)
                ->getJson('/api/v1'.$path)
                ->assertUnprocessable();
            $response->assertJsonPath('code', 'VALIDATION_ERROR');
            $this->assertContains('warung_id', array_keys($response->json('errors')));
            $this->assertOperationResponseMatchesOpenApi($response, $contractPath, 'get');
        }

        $invalid = $this->withToken($token)
            ->getJson('/api/v1/menus?warung_id=999999999999999999999999999999')
            ->assertUnprocessable();
        $invalid->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertContains('warung_id', array_keys($invalid->json('errors')));

    }

    public function test_tenant_user_cannot_select_a_warung_for_read_requests(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('tenant-read-selector-forbidden-test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/menus?warung_id='.$warung->id)
            ->assertUnprocessable();

        $response->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertContains('warung_id', array_keys($response->json('errors')));
    }
}
