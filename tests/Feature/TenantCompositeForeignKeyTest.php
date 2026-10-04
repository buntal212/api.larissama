<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantCompositeForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('crossTenantRelations')]
    public function test_composite_foreign_keys_reject_cross_tenant_references(
        string $relation,
        string $constraint,
    ): void {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $kasirA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'kasir']);
        $kasirB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'kasir']);
        $managerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $managerB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'manager']);
        $kategoriA = KategoriMenu::factory()->create(['warung_id' => $warungA->id]);
        $kategoriB = KategoriMenu::factory()->create(['warung_id' => $warungB->id]);
        $menuA = Menu::factory()->create([
            'warung_id' => $warungA->id,
            'kategori_menu_id' => $kategoriA->id,
        ]);
        $menuB = Menu::factory()->create([
            'warung_id' => $warungB->id,
            'kategori_menu_id' => $kategoriB->id,
        ]);
        $saleA = Penjualan::factory()->create(['warung_id' => $warungA->id, 'user_id' => $kasirA->id]);
        $saleB = Penjualan::factory()->create(['warung_id' => $warungB->id, 'user_id' => $kasirB->id]);
        $purchaseA = Pembelian::factory()->create(['warung_id' => $warungA->id, 'user_id' => $managerA->id]);
        $purchaseB = Pembelian::factory()->create(['warung_id' => $warungB->id, 'user_id' => $managerB->id]);

        $this->assertConstraintViolation($constraint, function () use (
            $relation,
            $warungB,
            $kasirA,
            $managerA,
            $kategoriA,
            $menuA,
            $menuB,
            $saleA,
            $saleB,
            $purchaseA,
        ): void {
            match ($relation) {
                'menu-category' => Menu::factory()->create([
                    'warung_id' => $warungB->id,
                    'kategori_menu_id' => $kategoriA->id,
                ]),
                'sale-user' => Penjualan::factory()->create([
                    'warung_id' => $warungB->id,
                    'user_id' => $kasirA->id,
                ]),
                'sale-detail-header' => $this->insertSaleDetail($warungB->id, $saleA->id, $menuB->id),
                'sale-detail-menu' => $this->insertSaleDetail($warungB->id, $saleB->id, $menuA->id),
                'purchase-user' => Pembelian::factory()->create([
                    'warung_id' => $warungB->id,
                    'user_id' => $managerA->id,
                ]),
                'purchase-detail-header' => $this->insertPurchaseDetail($warungB->id, $purchaseA->id),
                default => throw new \InvalidArgumentException("Unsupported relation {$relation}."),
            };
        });
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function crossTenantRelations(): array
    {
        return [
            'menu to category' => ['menu-category', 'menus_warung_kategori_fk'],
            'sale header to user' => ['sale-user', 'penjualans_warung_user_fk'],
            'sale detail to header' => ['sale-detail-header', 'penjualan_rincis_warung_header_fk'],
            'sale detail to menu' => ['sale-detail-menu', 'penjualan_rincis_warung_menu_fk'],
            'purchase header to user' => ['purchase-user', 'pembelians_warung_user_fk'],
            'purchase detail to header' => ['purchase-detail-header', 'pembelian_rincis_warung_header_fk'],
        ];
    }

    public function test_database_rejects_a_menu_with_a_missing_category(): void
    {
        $warung = Warung::factory()->create();

        $this->assertConstraintViolation('menus_warung_kategori_fk', fn () => Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => PHP_INT_MAX,
        ]));
    }

    public function test_menu_code_can_repeat_across_warungs_but_not_within_one(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $kategoriA = KategoriMenu::factory()->create(['warung_id' => $warungA->id]);
        $kategoriB = KategoriMenu::factory()->create(['warung_id' => $warungB->id]);

        Menu::factory()->create([
            'warung_id' => $warungA->id,
            'kategori_menu_id' => $kategoriA->id,
            'kode' => 'KODE-SAMA',
        ]);
        Menu::factory()->create([
            'warung_id' => $warungB->id,
            'kategori_menu_id' => $kategoriB->id,
            'kode' => 'KODE-SAMA',
        ]);

        $this->assertDatabaseCount('menus', 2);
        $this->assertConstraintViolation('menus_warung_kode_unique', fn () => Menu::factory()->create([
            'warung_id' => $warungA->id,
            'kategori_menu_id' => $kategoriA->id,
            'kode' => 'KODE-SAMA',
        ]));
    }

    public function test_sale_number_can_repeat_across_warungs_but_not_within_one(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $kasirA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'kasir']);
        $kasirB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'kasir']);

        Penjualan::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $kasirA->id,
            'no_transaksi' => 'PJ-NOMOR-SAMA',
        ]);
        Penjualan::factory()->create([
            'warung_id' => $warungB->id,
            'user_id' => $kasirB->id,
            'no_transaksi' => 'PJ-NOMOR-SAMA',
        ]);

        $this->assertDatabaseCount('penjualans', 2);
        $this->assertConstraintViolation('penjualans_warung_nomor_unique', fn () => Penjualan::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $kasirA->id,
            'no_transaksi' => 'PJ-NOMOR-SAMA',
        ]));
    }

    public function test_purchase_number_can_repeat_across_warungs_but_not_within_one(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $managerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $managerB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'manager']);

        Pembelian::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $managerA->id,
            'no_transaksi' => 'PB-NOMOR-SAMA',
        ]);
        Pembelian::factory()->create([
            'warung_id' => $warungB->id,
            'user_id' => $managerB->id,
            'no_transaksi' => 'PB-NOMOR-SAMA',
        ]);

        $this->assertDatabaseCount('pembelians', 2);
        $this->assertConstraintViolation('pembelians_warung_nomor_unique', fn () => Pembelian::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $managerA->id,
            'no_transaksi' => 'PB-NOMOR-SAMA',
        ]));
    }

    private function assertConstraintViolation(string $constraint, Closure $attempt): void
    {
        try {
            $attempt();
        } catch (QueryException $exception) {
            $this->assertStringContainsString($constraint, $exception->getMessage());

            return;
        }

        $this->fail("Expected database constraint {$constraint} to reject the write.");
    }

    private function insertSaleDetail(int $warungId, int $saleId, int $menuId): void
    {
        $now = now('UTC');

        DB::table('penjualan_rincis')->insert([
            'warung_id' => $warungId,
            'penjualan_id' => $saleId,
            'menu_id' => $menuId,
            'nama_menu' => 'Menu test',
            'harga' => '1000.00',
            'qty' => '1.00',
            'subtotal' => '1000.00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertPurchaseDetail(int $warungId, int $purchaseId): void
    {
        $now = now('UTC');

        DB::table('pembelian_rincis')->insert([
            'warung_id' => $warungId,
            'pembelian_id' => $purchaseId,
            'nama_item' => 'Item test',
            'subtotal' => '1000.00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
