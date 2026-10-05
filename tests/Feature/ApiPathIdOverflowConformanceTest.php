<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiPathIdOverflowConformanceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('largePathIdOperations')]
    public function test_detail_and_update_operations_return_404_for_large_positive_path_ids(
        string $path,
        string $method,
        string $role,
        array $body,
        string $id,
    ): void {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $superadmin = User::factory()->superadmin()->create();
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);
        Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
        ]);
        Penjualan::factory()->create(['warung_id' => $warung->id, 'user_id' => $cashier->id]);
        Pembelian::factory()->create(['warung_id' => $warung->id, 'user_id' => $manager->id]);

        $actor = $role === 'superadmin' ? $superadmin : $owner;
        $token = $actor->createToken('large-path-id-conformance')->plainTextToken;
        $before = $this->databaseSnapshot();
        $url = '/api/v1'.str_replace('{id}', $id, $path);

        $this->assertPathIdMatchesOpenApi($id, $path, $method);

        if ($method === 'patch') {
            $this->assertOperationRequestMatchesOpenApi($body, [], $path, $method);
            $response = $this->withToken($token)->patchJson($url, $body);
        } else {
            $response = $this->withToken($token)->getJson($url);
        }

        $response->assertNotFound();
        $this->assertSame('NOT_FOUND', $response->json('code'));
        $this->assertSame([], $response->json('errors'));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $response->json('request_id'),
        );
        $this->assertOperationResponseMatchesOpenApi($response, $path, $method);
        $this->assertSame($before, $this->databaseSnapshot());
    }

    /**
     * @return array<string, array{string, string, string, array<string, mixed>, string}>
     */
    public static function largePathIdOperations(): array
    {
        $operations = [
            'admin warung detail' => ['/admin/warungs/{id}', 'get', 'superadmin', []],
            'admin warung update' => ['/admin/warungs/{id}', 'patch', 'superadmin', ['nama' => 'Warung berubah']],
            'user detail' => ['/users/{id}', 'get', 'owner', []],
            'user update' => ['/users/{id}', 'patch', 'owner', ['nama' => 'Pengguna berubah']],
            'category detail' => ['/kategori-menus/{id}', 'get', 'owner', []],
            'category update' => ['/kategori-menus/{id}', 'patch', 'owner', ['nama' => 'Kategori berubah']],
            'menu detail' => ['/menus/{id}', 'get', 'owner', []],
            'menu update' => ['/menus/{id}', 'patch', 'owner', ['harga' => '17000.00']],
            'sale detail' => ['/penjualans/{id}', 'get', 'owner', []],
            'purchase detail' => ['/pembelians/{id}', 'get', 'owner', []],
        ];
        $ids = [
            'above php integer range' => '9223372036854775808',
            'above unsigned bigint range' => '9999999999999999999999999999999999999999',
        ];
        $cases = [];

        foreach ($operations as $operationName => [$path, $method, $role, $body]) {
            foreach ($ids as $idName => $id) {
                $cases["{$operationName} / {$idName}"] = [$path, $method, $role, $body, $id];
            }
        }

        return $cases;
    }

    private function assertPathIdMatchesOpenApi(string $id, string $path, string $method): void
    {
        $document = $this->openApiDocument();
        $operation = $document['paths'][$path][$method] ?? null;
        $this->assertIsArray($operation, "OpenAPI operation {$method} {$path} must exist.");

        $idParameter = null;

        foreach ($operation['parameters'] ?? [] as $parameter) {
            if (isset($parameter['$ref'])) {
                $parameter = $this->resolveOpenApiReference($document, $parameter['$ref']);
            }

            if (($parameter['in'] ?? null) === 'path' && ($parameter['name'] ?? null) === 'id') {
                $idParameter = $parameter;
                break;
            }
        }

        $this->assertIsArray($idParameter, "OpenAPI {$method} {$path} must define its path ID parameter.");
        $this->assertTrue($idParameter['required'] ?? false, "OpenAPI path ID for {$method} {$path} must be required.");
        $schema = $idParameter['schema'] ?? null;
        $this->assertIsArray($schema, "OpenAPI path ID for {$method} {$path} must define a schema.");
        $errors = $this->collectOpenApiSchemaErrors($id, $schema, $document, 'path.id');

        $this->assertSame([], $errors, implode("\n", $errors));
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function databaseSnapshot(): array
    {
        return collect([
            'warungs',
            'users',
            'personal_access_tokens',
            'kategori_menus',
            'menus',
            'penjualans',
            'penjualan_rincis',
            'pembelians',
            'pembelian_rincis',
        ])->mapWithKeys(fn (string $table): array => [
            $table => DB::table($table)
                ->orderBy('id')
                ->get()
                ->map(function (object $row) use ($table): array {
                    $attributes = (array) $row;

                    if ($table === 'personal_access_tokens') {
                        $attributes['last_used_at'] = null;
                        $attributes['updated_at'] = null;
                    }

                    return $attributes;
                })
                ->all(),
        ])->all();
    }
}
