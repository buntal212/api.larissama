<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KategoriMenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_list_filter_show_and_update_categories(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $drinks = KategoriMenu::factory()->create([
            'warung_id' => $warungA->id,
            'nama' => 'Minuman',
            'urutan' => 1,
            'aktif' => false,
        ]);
        $food = KategoriMenu::factory()->create([
            'warung_id' => $warungA->id,
            'nama' => 'Makanan',
            'urutan' => 2,
            'aktif' => true,
        ]);
        KategoriMenu::factory()->create(['warung_id' => $warungB->id, 'nama' => 'Tenant B']);
        $token = $manager->createToken('kategori-feature-test')->plainTextToken;
        $createPayload = ['nama' => 'Dessert', 'urutan' => 3];
        $this->assertOperationRequestMatchesOpenApi($createPayload, [], '/kategori-menus', 'post');

        $created = $this->withToken($token)
            ->postJson('/api/v1/kategori-menus', $createPayload)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($created, '/kategori-menus', 'post');
        $category = $created->json('data');

        $this->assertEqualsCanonicalizing(['data'], array_keys($created->json()));
        $this->assertEqualsCanonicalizing(
            ['id', 'warung_id', 'nama', 'urutan', 'aktif', 'created_at', 'updated_at'],
            array_keys($category),
        );
        $this->assertIsString($category['id']);
        $this->assertSame((string) $warungA->id, $category['warung_id']);
        $this->assertSame('Dessert', $category['nama']);
        $this->assertSame(3, $category['urutan']);
        $this->assertTrue($category['aktif']);
        $this->assertDatabaseHas('kategori_menus', [
            'id' => (int) $category['id'],
            'warung_id' => $warungA->id,
            'nama' => 'Dessert',
            'urutan' => 3,
            'aktif' => true,
        ]);

        $firstPageQuery = ['page' => '1', 'per_page' => '1', 'sort' => 'urutan'];
        $this->assertOperationQueryMatchesOpenApi($firstPageQuery, '/kategori-menus', 'get');
        $list = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus?'.http_build_query($firstPageQuery))
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($list, '/kategori-menus', 'get');
        $this->assertSame([(string) $drinks->id], array_column($list->json('data'), 'id'));
        $this->assertSame(['page' => 1, 'per_page' => 1, 'total' => 3, 'last_page' => 3], $list->json('meta'));

        $secondPageQuery = ['page' => '2', 'per_page' => '1', 'sort' => 'urutan'];
        $this->assertOperationQueryMatchesOpenApi($secondPageQuery, '/kategori-menus', 'get');
        $secondPage = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus?'.http_build_query($secondPageQuery))
            ->assertOk();
        $this->assertSame([(string) $food->id], array_column($secondPage->json('data'), 'id'));
        $this->assertSame(['page' => 2, 'per_page' => 1, 'total' => 3, 'last_page' => 3], $secondPage->json('meta'));

        $searchQuery = ['q' => 'Dessert'];
        $this->assertOperationQueryMatchesOpenApi($searchQuery, '/kategori-menus', 'get');
        $search = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus?'.http_build_query($searchQuery))
            ->assertOk();
        $this->assertSame([(string) $category['id']], array_column($search->json('data'), 'id'));

        $inactiveQuery = ['aktif' => 'false'];
        $this->assertOperationQueryMatchesOpenApi($inactiveQuery, '/kategori-menus', 'get');
        $inactive = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus?'.http_build_query($inactiveQuery))
            ->assertOk();
        $this->assertSame([(string) $drinks->id], array_column($inactive->json('data'), 'id'));

        $activeQuery = ['aktif' => 'true', 'sort' => 'urutan'];
        $this->assertOperationQueryMatchesOpenApi($activeQuery, '/kategori-menus', 'get');
        $active = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus?'.http_build_query($activeQuery))
            ->assertOk();
        $this->assertSame([(string) $food->id, (string) $category['id']], array_column($active->json('data'), 'id'));

        $detail = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus/'.$category['id'])
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($detail, '/kategori-menus/{id}', 'get');
        $this->assertSame($category['id'], $detail->json('data.id'));

        $updatePayload = ['nama' => 'Camilan', 'aktif' => false];
        $this->assertOperationRequestMatchesOpenApi($updatePayload, [], '/kategori-menus/{id}', 'patch');

        $updated = $this->withToken($token)
            ->patchJson('/api/v1/kategori-menus/'.$category['id'], $updatePayload)
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($updated, '/kategori-menus/{id}', 'patch');
        $this->assertSame('Camilan', $updated->json('data.nama'));
        $this->assertFalse($updated->json('data.aktif'));
        $this->assertDatabaseHas('kategori_menus', [
            'id' => (int) $category['id'],
            'warung_id' => $warungA->id,
            'nama' => 'Camilan',
            'aktif' => false,
        ]);
    }

    public function test_manager_cannot_read_or_change_another_warungs_category(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $foreign = KategoriMenu::factory()->create([
            'warung_id' => $warungB->id,
            'nama' => 'Kategori Tetap B',
        ]);
        $token = $manager->createToken('kategori-feature-test')->plainTextToken;

        $list = $this->withToken($token)->getJson('/api/v1/kategori-menus')->assertOk();
        $this->assertNotContains((string) $foreign->id, array_column($list->json('data'), 'id'));

        $detail = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus/'.$foreign->id)
            ->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($detail, '/kategori-menus/{id}', 'get');
        $this->assertD13ErrorEnvelope($detail, 'NOT_FOUND');

        $update = $this->withToken($token)
            ->patchJson('/api/v1/kategori-menus/'.$foreign->id, ['nama' => 'Diubah Tenant A'])
            ->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($update, '/kategori-menus/{id}', 'patch');
        $this->assertD13ErrorEnvelope($update, 'NOT_FOUND');
        $this->assertDatabaseHas('kategori_menus', [
            'id' => $foreign->id,
            'warung_id' => $warungB->id,
            'nama' => 'Kategori Tetap B',
        ]);
    }

    public function test_cashier_only_sees_active_categories_even_when_filter_requests_inactive(): void
    {
        $warung = Warung::factory()->create();
        $active = KategoriMenu::factory()->create(['warung_id' => $warung->id, 'aktif' => true]);
        $inactive = KategoriMenu::factory()->create(['warung_id' => $warung->id, 'aktif' => false]);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $token = $cashier->createToken('kategori-feature-test')->plainTextToken;

        $list = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus?aktif=false')
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($list, '/kategori-menus', 'get');
        $this->assertSame([(string) $active->id], array_column($list->json('data'), 'id'));

        $detail = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus/'.$inactive->id)
            ->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($detail, '/kategori-menus/{id}', 'get');
        $this->assertD13ErrorEnvelope($detail, 'NOT_FOUND');
    }

    public function test_owner_cannot_select_or_view_another_warungs_category(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'owner']);
        $foreign = KategoriMenu::factory()->create(['warung_id' => $warungB->id]);
        $token = $owner->createToken('owner-category-scope-test')->plainTextToken;

        $injectedSelector = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus?warung_id='.$warungB->id)
            ->assertUnprocessable();
        $this->assertD13ErrorEnvelope($injectedSelector, 'VALIDATION_ERROR', 'warung_id');

        $foreignDetail = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus/'.$foreign->id)
            ->assertNotFound();
        $this->assertD13ErrorEnvelope($foreignDetail, 'NOT_FOUND');
    }

    public function test_manager_rejects_tenant_id_injection_and_invalid_list_options(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $category = KategoriMenu::factory()->create(['warung_id' => $warungA->id]);
        $token = $manager->createToken('kategori-feature-test')->plainTextToken;

        $create = $this->withToken($token)
            ->postJson('/api/v1/kategori-menus', ['nama' => 'Injeksi', 'warung_id' => $warungB->id])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($create, '/kategori-menus', 'post');
        $this->assertD13ErrorEnvelope($create, 'VALIDATION_ERROR', 'warung_id');

        $update = $this->withToken($token)
            ->patchJson('/api/v1/kategori-menus/'.$category->id, ['warung_id' => $warungB->id])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($update, '/kategori-menus/{id}', 'patch');
        $this->assertD13ErrorEnvelope($update, 'VALIDATION_ERROR', 'warung_id');

        $badPageSize = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus?per_page=101')
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($badPageSize, '/kategori-menus', 'get');
        $this->assertD13ErrorEnvelope($badPageSize, 'VALIDATION_ERROR', 'per_page');

        $badSort = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus?sort=nama;drop')
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($badSort, '/kategori-menus', 'get');
        $this->assertD13ErrorEnvelope($badSort, 'VALIDATION_ERROR', 'sort');

        $this->assertSame(1, KategoriMenu::query()->where('warung_id', $warungA->id)->count());
        $this->assertDatabaseMissing('kategori_menus', ['nama' => 'Injeksi']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesWithoutCategoryWriteAccess(): array
    {
        return [
            'cashier' => ['kasir'],
        ];
    }

    #[DataProvider('rolesWithoutCategoryWriteAccess')]
    public function test_roles_without_category_write_access_cannot_create_or_update_categories(string $role): void
    {
        $warung = Warung::factory()->create();
        $actor = User::factory()->create(['warung_id' => $warung->id, 'role' => $role]);
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);
        $token = $actor->createToken('kategori-feature-test')->plainTextToken;

        $create = $this->withToken($token)
            ->postJson('/api/v1/kategori-menus', ['nama' => 'Tidak Diizinkan'])
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($create, '/kategori-menus', 'post');
        $this->assertD13ErrorEnvelope($create, 'FORBIDDEN');

        $update = $this->withToken($token)
            ->patchJson('/api/v1/kategori-menus/'.$category->id, ['nama' => 'Tidak Diubah'])
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($update, '/kategori-menus/{id}', 'patch');
        $this->assertD13ErrorEnvelope($update, 'FORBIDDEN');
        $this->assertDatabaseHas('kategori_menus', ['id' => $category->id, 'nama' => $category->nama]);

        $this->assertDatabaseMissing('kategori_menus', ['nama' => 'Tidak Diizinkan']);
    }

    public function test_superadmin_reads_only_the_selected_tenant_catalog_and_hides_unselected_details(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $categoryA = KategoriMenu::factory()->create([
            'warung_id' => $warungA->id,
            'nama' => 'Katalog Warung A',
        ]);
        $categoryB = KategoriMenu::factory()->create([
            'warung_id' => $warungB->id,
            'nama' => 'Katalog Warung B',
        ]);
        $menuA = Menu::factory()->create([
            'warung_id' => $warungA->id,
            'kategori_menu_id' => $categoryA->id,
            'nama' => 'Menu Warung A',
        ]);
        $menuB = Menu::factory()->create([
            'warung_id' => $warungB->id,
            'kategori_menu_id' => $categoryB->id,
            'nama' => 'Menu Warung B',
        ]);
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('superadmin-catalog-read-test')->plainTextToken;
        $catalogs = [
            [
                'path' => '/kategori-menus',
                'detail_path' => '/kategori-menus/'.$categoryA->id,
                'foreign_detail_path' => '/kategori-menus/'.$categoryB->id,
                'id' => $categoryA->id,
                'foreign_id' => $categoryB->id,
                'sort' => 'urutan',
            ],
            [
                'path' => '/menus',
                'detail_path' => '/menus/'.$menuA->id,
                'foreign_detail_path' => '/menus/'.$menuB->id,
                'id' => $menuA->id,
                'foreign_id' => $menuB->id,
                'sort' => 'nama',
            ],
        ];

        foreach ($catalogs as $catalog) {
            $query = [
                'page' => '1',
                'per_page' => '20',
                'sort' => $catalog['sort'],
                'warung_id' => (string) $warungA->id,
            ];
            $this->assertOperationQueryMatchesOpenApi($query, $catalog['path'], 'get');
            $list = $this->withToken($token)
                ->getJson('/api/v1'.$catalog['path'].'?'.http_build_query($query))
                ->assertOk();
            $this->assertSame([(string) $catalog['id']], array_column($list->json('data'), 'id'));
            $this->assertNotContains((string) $catalog['foreign_id'], array_column($list->json('data'), 'id'));
            $this->assertOperationResponseMatchesOpenApi($list, $catalog['path'], 'get');

            $detailPath = $catalog['path'].'/{id}';
            $detail = $this->withToken($token)
                ->getJson('/api/v1'.$catalog['detail_path'].'?warung_id='.$warungA->id)
                ->assertOk()
                ->assertJsonPath('data.warung_id', (string) $warungA->id);
            $this->assertOperationResponseMatchesOpenApi($detail, $detailPath, 'get');

            $foreignDetail = $this->withToken($token)
                ->getJson('/api/v1'.$catalog['foreign_detail_path'].'?warung_id='.$warungA->id)
                ->assertNotFound();
            $this->assertOperationResponseMatchesOpenApi($foreignDetail, $detailPath, 'get');
            $this->assertD13ErrorEnvelope($foreignDetail, 'NOT_FOUND');
        }

        $warungBList = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus?warung_id='.$warungB->id)
            ->assertOk();
        $this->assertSame([(string) $categoryB->id], array_column($warungBList->json('data'), 'id'));

        $this->assertDatabaseHas('kategori_menus', ['id' => $categoryA->id, 'nama' => 'Katalog Warung A']);
        $this->assertDatabaseHas('kategori_menus', ['id' => $categoryB->id, 'nama' => 'Katalog Warung B']);
    }

    public function test_superadmin_must_select_an_existing_positive_warung_to_list_categories(): void
    {
        $warung = Warung::factory()->create();
        KategoriMenu::factory()->create(['warung_id' => $warung->id]);
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('superadmin-category-scope-test')->plainTextToken;

        foreach (['', '?warung_id=0', '?warung_id=bukan-integer', '?warung_id=999999'] as $query) {
            $response = $this->withToken($token)
                ->getJson('/api/v1/kategori-menus'.$query)
                ->assertUnprocessable();
            $this->assertOperationResponseMatchesOpenApi($response, '/kategori-menus', 'get');
            $this->assertD13ErrorEnvelope($response, 'VALIDATION_ERROR', 'warung_id');
        }

        $response = $this->withToken($token)
            ->getJson('/api/v1/kategori-menus?warung_id='.$warung->id)
            ->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_superadmin_cannot_create_or_update_categories_or_menus_with_a_tenant_selector(): void
    {
        $warung = Warung::factory()->create();
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id, 'nama' => 'Kategori Tetap']);
        $menu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
            'nama' => 'Menu Tetap',
        ]);
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('superadmin-catalog-write-test')->plainTextToken;

        $categoryCreate = $this->withToken($token)->postJson('/api/v1/kategori-menus', [
            'warung_id' => $warung->id,
            'nama' => 'Kategori Baru',
        ])->assertForbidden();
        $this->assertD13ErrorEnvelope($categoryCreate, 'FORBIDDEN');

        $categoryUpdate = $this->withToken($token)->patchJson('/api/v1/kategori-menus/'.$category->id, [
            'warung_id' => $warung->id,
            'nama' => 'Kategori Berubah',
        ])->assertForbidden();
        $this->assertD13ErrorEnvelope($categoryUpdate, 'FORBIDDEN');

        $menuCreate = $this->withToken($token)->postJson('/api/v1/menus', [
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
            'nama' => 'Menu Baru',
            'harga' => '12000.00',
        ])->assertForbidden();
        $this->assertD13ErrorEnvelope($menuCreate, 'FORBIDDEN');

        $menuUpdate = $this->withToken($token)->patchJson('/api/v1/menus/'.$menu->id, [
            'warung_id' => $warung->id,
            'nama' => 'Menu Berubah',
        ])->assertForbidden();
        $this->assertD13ErrorEnvelope($menuUpdate, 'FORBIDDEN');

        $this->assertDatabaseCount('kategori_menus', 1);
        $this->assertDatabaseCount('menus', 1);
        $this->assertDatabaseHas('kategori_menus', ['id' => $category->id, 'nama' => 'Kategori Tetap']);
        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'nama' => 'Menu Tetap']);
    }

    public function test_category_list_requires_a_valid_token_and_an_active_account(): void
    {
        $warung = Warung::factory()->create();
        $inactiveManager = User::factory()->inactive()->create([
            'warung_id' => $warung->id,
            'role' => 'manager',
        ]);

        $invalidToken = $this->withToken('invalid-token')
            ->getJson('/api/v1/kategori-menus')
            ->assertUnauthorized();
        $this->assertD13ErrorEnvelope($invalidToken, 'UNAUTHENTICATED');

        $inactiveToken = $inactiveManager->createToken('inactive-category-test')->plainTextToken;
        $inactive = $this->withToken($inactiveToken)
            ->getJson('/api/v1/kategori-menus')
            ->assertForbidden();
        $this->assertD13ErrorEnvelope($inactive, 'FORBIDDEN');
    }

    public function test_database_restricts_hard_delete_of_category_that_has_menu(): void
    {
        $warung = Warung::factory()->create();
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);
        Menu::factory()->create(['warung_id' => $warung->id, 'kategori_menu_id' => $category->id]);

        try {
            $category->delete();
            $this->fail('Foreign key should reject hard deleting a category with menus.');
        } catch (QueryException) {
            $this->assertDatabaseHas('kategori_menus', ['id' => $category->id, 'warung_id' => $warung->id]);
        }
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
