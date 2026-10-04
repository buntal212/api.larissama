<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_list_show_and_update_menu_with_decimal_resource(): void
    {
        $warung = Warung::factory()->create();
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id, 'nama' => 'Makanan']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $tea = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
            'kode' => 'M-TEH',
            'nama' => 'Teh',
            'harga' => '5000.00',
        ]);
        $coffee = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
            'kode' => 'M-KOPI',
            'nama' => 'Kopi Susu',
            'harga' => '12000.00',
        ]);
        $token = $manager->createToken('menu-feature-test')->plainTextToken;
        $createPayload = [
            'kategori_menu_id' => (string) $category->id,
            'kode' => 'M-NASI',
            'nama' => 'Nasi Goreng',
            'harga' => '15000.00',
            'deskripsi' => 'Porsi reguler',
        ];
        $this->assertOperationRequestMatchesOpenApi($createPayload, [], '/menus', 'post');

        $created = $this->withToken($token)
            ->postJson('/api/v1/menus', $createPayload)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($created, '/menus', 'post');
        $menu = $created->json('data');

        $this->assertEqualsCanonicalizing(['data'], array_keys($created->json()));
        $this->assertEqualsCanonicalizing(
            ['id', 'warung_id', 'kategori_menu_id', 'kode', 'nama', 'harga', 'deskripsi', 'aktif', 'created_at', 'updated_at'],
            array_keys($menu),
        );
        $this->assertIsString($menu['id']);
        $this->assertSame((string) $warung->id, $menu['warung_id']);
        $this->assertSame((string) $category->id, $menu['kategori_menu_id']);
        $this->assertSame('15000.00', $menu['harga']);
        $this->assertTrue($menu['aktif']);
        $this->assertArrayNotHasKey('harga_modal', $menu);
        $this->assertArrayNotHasKey('gambar', $menu);
        $this->assertDatabaseHas('menus', [
            'id' => (int) $menu['id'],
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
            'kode' => 'M-NASI',
            'harga' => '15000.00',
            'aktif' => true,
        ]);

        $filteredQuery = [
            'q' => 'Nasi',
            'kategori_menu_id' => (string) $category->id,
            'per_page' => '1',
            'sort' => 'nama',
        ];
        $this->assertOperationQueryMatchesOpenApi($filteredQuery, '/menus', 'get');
        $filtered = $this->withToken($token)
            ->getJson('/api/v1/menus?'.http_build_query($filteredQuery))
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($filtered, '/menus', 'get');
        $this->assertSame([(string) $menu['id']], array_column($filtered->json('data'), 'id'));
        $this->assertSame(['page' => 1, 'per_page' => 1, 'total' => 1, 'last_page' => 1], $filtered->json('meta'));

        $firstPageQuery = ['page' => '1', 'per_page' => '1', 'sort' => 'nama'];
        $this->assertOperationQueryMatchesOpenApi($firstPageQuery, '/menus', 'get');
        $firstPage = $this->withToken($token)
            ->getJson('/api/v1/menus?'.http_build_query($firstPageQuery))
            ->assertOk();
        $this->assertSame([(string) $coffee->id], array_column($firstPage->json('data'), 'id'));
        $this->assertSame(['page' => 1, 'per_page' => 1, 'total' => 3, 'last_page' => 3], $firstPage->json('meta'));

        $secondPageQuery = ['page' => '2', 'per_page' => '1', 'sort' => 'nama'];
        $this->assertOperationQueryMatchesOpenApi($secondPageQuery, '/menus', 'get');
        $secondPage = $this->withToken($token)
            ->getJson('/api/v1/menus?'.http_build_query($secondPageQuery))
            ->assertOk();
        $this->assertSame([(string) $menu['id']], array_column($secondPage->json('data'), 'id'));
        $this->assertSame(['page' => 2, 'per_page' => 1, 'total' => 3, 'last_page' => 3], $secondPage->json('meta'));

        $thirdPageQuery = ['page' => '3', 'per_page' => '1', 'sort' => 'nama'];
        $this->assertOperationQueryMatchesOpenApi($thirdPageQuery, '/menus', 'get');
        $thirdPage = $this->withToken($token)
            ->getJson('/api/v1/menus?'.http_build_query($thirdPageQuery))
            ->assertOk();
        $this->assertSame([(string) $tea->id], array_column($thirdPage->json('data'), 'id'));
        $this->assertSame(['page' => 3, 'per_page' => 1, 'total' => 3, 'last_page' => 3], $thirdPage->json('meta'));

        $detail = $this->withToken($token)
            ->getJson('/api/v1/menus/'.$menu['id'])
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($detail, '/menus/{id}', 'get');
        $this->assertSame('15000.00', $detail->json('data.harga'));

        $updatePayload = [
            'nama' => 'Nasi Spesial',
            'harga' => '16000.00',
            'aktif' => false,
        ];
        $this->assertOperationRequestMatchesOpenApi($updatePayload, [], '/menus/{id}', 'patch');

        $updated = $this->withToken($token)
            ->patchJson('/api/v1/menus/'.$menu['id'], $updatePayload)
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($updated, '/menus/{id}', 'patch');
        $this->assertSame('Nasi Spesial', $updated->json('data.nama'));
        $this->assertSame('16000.00', $updated->json('data.harga'));
        $this->assertFalse($updated->json('data.aktif'));
        $this->assertDatabaseHas('menus', [
            'id' => (int) $menu['id'],
            'warung_id' => $warung->id,
            'nama' => 'Nasi Spesial',
            'harga' => '16000.00',
            'aktif' => false,
        ]);

        $inactiveQuery = ['aktif' => 'false'];
        $this->assertOperationQueryMatchesOpenApi($inactiveQuery, '/menus', 'get');
        $inactive = $this->withToken($token)
            ->getJson('/api/v1/menus?'.http_build_query($inactiveQuery))
            ->assertOk();
        $this->assertSame([(string) $menu['id']], array_column($inactive->json('data'), 'id'));
    }

    public function test_menu_code_is_unique_per_warung_not_global(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $categoryA = KategoriMenu::factory()->create(['warung_id' => $warungA->id]);
        $categoryB = KategoriMenu::factory()->create(['warung_id' => $warungB->id]);
        $managerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        Menu::factory()->create([
            'warung_id' => $warungB->id,
            'kategori_menu_id' => $categoryB->id,
            'kode' => 'M-SHARED',
        ]);
        $token = $managerA->createToken('menu-feature-test')->plainTextToken;

        $payload = [
            'kategori_menu_id' => $categoryA->id,
            'kode' => 'M-SHARED',
            'nama' => 'Menu A',
            'harga' => '10000.00',
        ];
        $this->withToken($token)->postJson('/api/v1/menus', $payload)->assertCreated();

        $duplicate = $this->withToken($token)
            ->postJson('/api/v1/menus', [...$payload, 'nama' => 'Duplikat A'])
            ->assertUnprocessable();
        $this->assertD13ErrorEnvelope($duplicate, 'VALIDATION_ERROR', 'kode');
        $this->assertSame(1, Menu::query()->where('warung_id', $warungA->id)->where('kode', 'M-SHARED')->count());
        $this->assertSame(1, Menu::query()->where('warung_id', $warungB->id)->where('kode', 'M-SHARED')->count());
    }

    public function test_menu_rejects_category_from_another_warung_on_create_and_update(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $categoryA = KategoriMenu::factory()->create(['warung_id' => $warungA->id]);
        $categoryB = KategoriMenu::factory()->create(['warung_id' => $warungB->id]);
        $manager = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $menu = Menu::factory()->create(['warung_id' => $warungA->id, 'kategori_menu_id' => $categoryA->id]);
        $token = $manager->createToken('menu-feature-test')->plainTextToken;

        $create = $this->withToken($token)
            ->postJson('/api/v1/menus', [
                'kategori_menu_id' => $categoryB->id,
                'kode' => 'M-CROSS',
                'nama' => 'Kategori Silang',
                'harga' => '10000.00',
            ])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($create, '/menus', 'post');
        $this->assertD13ErrorEnvelope($create, 'VALIDATION_ERROR', 'kategori_menu_id');
        $this->assertDatabaseMissing('menus', ['kode' => 'M-CROSS']);

        $update = $this->withToken($token)
            ->patchJson('/api/v1/menus/'.$menu->id, ['kategori_menu_id' => $categoryB->id])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($update, '/menus/{id}', 'patch');
        $this->assertD13ErrorEnvelope($update, 'VALIDATION_ERROR', 'kategori_menu_id');
        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'warung_id' => $warungA->id,
            'kategori_menu_id' => $categoryA->id,
        ]);
    }

    public function test_manager_cannot_read_or_change_menu_from_another_warung(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $managerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $foreign = Menu::factory()->create([
            'warung_id' => $warungB->id,
            'kode' => 'M-FOREIGN',
            'nama' => 'Menu Tetap B',
        ]);
        $token = $managerA->createToken('menu-feature-test')->plainTextToken;

        $list = $this->withToken($token)->getJson('/api/v1/menus')->assertOk();
        $this->assertNotContains((string) $foreign->id, array_column($list->json('data'), 'id'));

        $detail = $this->withToken($token)->getJson('/api/v1/menus/'.$foreign->id)->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($detail, '/menus/{id}', 'get');
        $this->assertD13ErrorEnvelope($detail, 'NOT_FOUND');

        $update = $this->withToken($token)
            ->patchJson('/api/v1/menus/'.$foreign->id, ['nama' => 'Diubah Tenant A'])
            ->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($update, '/menus/{id}', 'patch');
        $this->assertD13ErrorEnvelope($update, 'NOT_FOUND');
        $this->assertDatabaseHas('menus', [
            'id' => $foreign->id,
            'warung_id' => $warungB->id,
            'nama' => 'Menu Tetap B',
        ]);
    }

    public function test_cashier_only_sees_active_menus_in_active_categories(): void
    {
        $warung = Warung::factory()->create();
        $activeCategory = KategoriMenu::factory()->create(['warung_id' => $warung->id, 'aktif' => true]);
        $inactiveCategory = KategoriMenu::factory()->create(['warung_id' => $warung->id, 'aktif' => false]);
        $visible = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $activeCategory->id,
            'kode' => 'M-VISIBLE',
            'aktif' => true,
        ]);
        $inactiveMenu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $activeCategory->id,
            'kode' => 'M-INACTIVE',
            'aktif' => false,
        ]);
        $inactiveCategoryMenu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $inactiveCategory->id,
            'kode' => 'M-INACTIVE-CATEGORY',
            'aktif' => true,
        ]);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $token = $cashier->createToken('menu-feature-test')->plainTextToken;

        $list = $this->withToken($token)
            ->getJson('/api/v1/menus?aktif=false')
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($list, '/menus', 'get');
        $this->assertSame([(string) $visible->id], array_column($list->json('data'), 'id'));

        foreach ([$inactiveMenu, $inactiveCategoryMenu] as $hidden) {
            $detail = $this->withToken($token)
                ->getJson('/api/v1/menus/'.$hidden->id)
                ->assertNotFound();
            $this->assertOperationResponseMatchesOpenApi($detail, '/menus/{id}', 'get');
            $this->assertD13ErrorEnvelope($detail, 'NOT_FOUND');
        }

        $inactiveCategoryFilter = $this->withToken($token)
            ->getJson('/api/v1/menus?kategori_menu_id='.$inactiveCategory->id)
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($inactiveCategoryFilter, '/menus', 'get');
        $this->assertD13ErrorEnvelope($inactiveCategoryFilter, 'VALIDATION_ERROR', 'kategori_menu_id');
    }

    public function test_manager_rejects_tenant_and_unavailable_catalog_field_injection(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $category = KategoriMenu::factory()->create(['warung_id' => $warungA->id]);
        $foreignCategory = KategoriMenu::factory()->create(['warung_id' => $warungB->id]);
        $manager = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $token = $manager->createToken('menu-feature-test')->plainTextToken;
        $payload = [
            'kategori_menu_id' => $category->id,
            'kode' => 'M-INJECT',
            'nama' => 'Injeksi',
            'harga' => '10000.00',
        ];

        foreach ([
            ['warung_id' => $warungB->id],
            ['harga_modal' => '5000.00'],
            ['gambar' => 'menu.jpg'],
        ] as $injected) {
            $response = $this->withToken($token)
                ->postJson('/api/v1/menus', [...$payload, ...$injected])
                ->assertUnprocessable();
            $this->assertOperationResponseMatchesOpenApi($response, '/menus', 'post');
            $this->assertD13ErrorEnvelope($response, 'VALIDATION_ERROR');
        }

        $badPrice = $this->withToken($token)
            ->postJson('/api/v1/menus', [...$payload, 'kode' => 'M-PRICE', 'harga' => '10000'])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($badPrice, '/menus', 'post');
        $this->assertD13ErrorEnvelope($badPrice, 'VALIDATION_ERROR', 'harga');

        $badPageSize = $this->withToken($token)->getJson('/api/v1/menus?per_page=101')->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($badPageSize, '/menus', 'get');
        $this->assertD13ErrorEnvelope($badPageSize, 'VALIDATION_ERROR', 'per_page');

        $badSort = $this->withToken($token)->getJson('/api/v1/menus?sort=-harga')->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($badSort, '/menus', 'get');
        $this->assertD13ErrorEnvelope($badSort, 'VALIDATION_ERROR', 'sort');

        $this->assertSame(0, Menu::query()->where('kode', 'M-INJECT')->count());
        $this->assertNotSame($category->warung_id, $foreignCategory->warung_id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesWithoutMenuWriteAccess(): array
    {
        return [
            'cashier' => ['kasir'],
            'superadmin' => ['superadmin'],
        ];
    }

    #[DataProvider('rolesWithoutMenuWriteAccess')]
    public function test_roles_without_menu_write_access_cannot_create_or_update_menus(string $role): void
    {
        $warung = $role === 'superadmin' ? null : Warung::factory()->create();
        $actor = User::factory()->create(['warung_id' => $warung?->id, 'role' => $role]);
        $category = $warung === null ? null : KategoriMenu::factory()->create(['warung_id' => $warung->id]);
        $menu = $category === null ? null : Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
        ]);
        $token = $actor->createToken('menu-feature-test')->plainTextToken;

        $create = $this->withToken($token)
            ->postJson('/api/v1/menus', [
                'kategori_menu_id' => $category?->id,
                'kode' => 'M-DENIED-'.$role,
                'nama' => 'Tidak Diizinkan',
                'harga' => '10000.00',
            ])
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($create, '/menus', 'post');
        $this->assertD13ErrorEnvelope($create, 'FORBIDDEN');

        if ($menu !== null) {
            $update = $this->withToken($token)
                ->patchJson('/api/v1/menus/'.$menu->id, ['nama' => 'Tidak Diubah'])
                ->assertForbidden();
            $this->assertOperationResponseMatchesOpenApi($update, '/menus/{id}', 'patch');
            $this->assertD13ErrorEnvelope($update, 'FORBIDDEN');
            $this->assertDatabaseHas('menus', ['id' => $menu->id, 'nama' => $menu->nama]);
        }

        $this->assertDatabaseMissing('menus', ['kode' => 'M-DENIED-'.$role]);
    }

    private function assertD13ErrorEnvelope(TestResponse $response, string $expectedCode, ?string $field = null): void
    {
        $body = $response->json();

        $this->assertEqualsCanonicalizing(['code', 'message', 'errors', 'request_id'], array_keys($body));
        $this->assertSame($expectedCode, $body['code']);
        $this->assertIsString($body['message']);
        $this->assertIsArray($body['errors']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $body['request_id'],
        );

        if ($field !== null) {
            $this->assertArrayHasKey($field, $body['errors']);
        }
    }
}
