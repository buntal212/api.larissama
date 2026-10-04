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
            'superadmin' => ['superadmin'],
        ];
    }

    #[DataProvider('rolesWithoutCategoryWriteAccess')]
    public function test_roles_without_category_write_access_cannot_create_or_update_categories(string $role): void
    {
        $warung = $role === 'superadmin' ? null : Warung::factory()->create();
        $actor = User::factory()->create(['warung_id' => $warung?->id, 'role' => $role]);
        $category = $warung === null ? null : KategoriMenu::factory()->create(['warung_id' => $warung->id]);
        $token = $actor->createToken('kategori-feature-test')->plainTextToken;

        $create = $this->withToken($token)
            ->postJson('/api/v1/kategori-menus', ['nama' => 'Tidak Diizinkan'])
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($create, '/kategori-menus', 'post');
        $this->assertD13ErrorEnvelope($create, 'FORBIDDEN');

        if ($category !== null) {
            $update = $this->withToken($token)
                ->patchJson('/api/v1/kategori-menus/'.$category->id, ['nama' => 'Tidak Diubah'])
                ->assertForbidden();
            $this->assertOperationResponseMatchesOpenApi($update, '/kategori-menus/{id}', 'patch');
            $this->assertD13ErrorEnvelope($update, 'FORBIDDEN');
            $this->assertDatabaseHas('kategori_menus', ['id' => $category->id, 'nama' => $category->nama]);
        }

        $this->assertDatabaseMissing('kategori_menus', ['nama' => 'Tidak Diizinkan']);
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
