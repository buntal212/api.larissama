<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiPaginationQueryConformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_per_page_maximum_matches_openapi_and_each_list_response(): void
    {
        $warung = Warung::factory()->create();
        $superadmin = User::factory()->superadmin()->create();
        $owner = User::factory()->create([
            'warung_id' => $warung->id,
            'role' => 'owner',
        ]);
        $manager = User::factory()->create([
            'warung_id' => $warung->id,
            'role' => 'manager',
        ]);
        $cashier = User::factory()->create([
            'warung_id' => $warung->id,
            'role' => 'kasir',
        ]);
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);
        Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
        ]);
        Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
        ]);
        Pembelian::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
        ]);

        $listOperations = [
            ['/admin/warungs', $superadmin],
            ['/users', $owner],
            ['/kategori-menus', $manager],
            ['/menus', $manager],
            ['/penjualans', $manager],
            ['/pembelians', $manager],
        ];

        foreach ($listOperations as [$path, $user]) {
            $query = ['per_page' => '100'];
            $this->assertOperationQueryMatchesOpenApi($query, $path, 'get');

            $token = $user->createToken('pagination-boundary-test')->plainTextToken;
            Auth::forgetGuards();
            $response = $this->withToken($token)
                ->getJson('/api/v1'.$path.'?'.http_build_query($query));
            $this->assertSame(
                200,
                $response->getStatusCode(),
                "GET {$path} failed: {$response->getContent()}",
            );

            $this->assertOperationResponseMatchesOpenApi($response, $path, 'get');
            $this->assertSame(100, $response->json('meta.per_page'), "{$path} must accept per_page=100.");
            $this->assertSame(1, $response->json('meta.page'), "{$path} must report page 1.");
        }
    }

    #[DataProvider('listOperations')]
    public function test_returns_422_for_per_page_101_when_openapi_maximum_is_100(string $path, string $role): void
    {
        $warung = Warung::factory()->create();
        $user = $role === 'superadmin'
            ? User::factory()->superadmin()->create()
            : User::factory()->create([
                'warung_id' => $warung->id,
                'role' => $role,
            ]);
        $token = $user->createToken('pagination-overflow-test')->plainTextToken;
        $query = ['per_page' => '101'];

        $this->assertOpenApiRejectsPerPageOverflow($path);

        $response = $this->withToken($token)
            ->getJson('/api/v1'.$path.'?'.http_build_query($query))
            ->assertUnprocessable();

        $this->assertOperationResponseMatchesOpenApi($response, $path, 'get');
        $response->assertJsonPath('code', 'VALIDATION_ERROR');
        $response->assertJsonPath('message', 'Data belum valid.');
        $response->assertInvalid(['per_page' => '100']);
    }

    #[DataProvider('paginationLowerBoundOperations')]
    public function test_returns_422_when_pagination_parameter_is_below_openapi_minimum(
        string $path,
        string $role,
        string $parameter,
    ): void {
        $warung = Warung::factory()->create();
        $user = $role === 'superadmin'
            ? User::factory()->superadmin()->create()
            : User::factory()->create([
                'warung_id' => $warung->id,
                'role' => $role,
            ]);
        $token = $user->createToken('pagination-lower-bound-test')->plainTextToken;
        $query = [$parameter => '0'];

        $this->assertOpenApiRejectsPaginationMinimum($path, $parameter);

        $response = $this->withToken($token)
            ->getJson('/api/v1'.$path.'?'.http_build_query($query))
            ->assertUnprocessable();

        $this->assertOperationResponseMatchesOpenApi($response, $path, 'get');
        $response->assertJsonPath('code', 'VALIDATION_ERROR');
        $response->assertJsonPath('message', 'Data belum valid.');
        $response->assertInvalid([$parameter => '1']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function listOperations(): array
    {
        return [
            'admin warungs as superadmin' => ['/admin/warungs', 'superadmin'],
            'users as owner' => ['/users', 'owner'],
            'categories as manager' => ['/kategori-menus', 'manager'],
            'menus as manager' => ['/menus', 'manager'],
            'sales as manager' => ['/penjualans', 'manager'],
            'purchases as manager' => ['/pembelians', 'manager'],
        ];
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function paginationLowerBoundOperations(): array
    {
        $operations = [];

        foreach (self::listOperations() as $name => [$path, $role]) {
            foreach (['page', 'per_page'] as $parameter) {
                $operations["{$name} with {$parameter}=0"] = [$path, $role, $parameter];
            }
        }

        return $operations;
    }

    private function assertOpenApiRejectsPerPageOverflow(string $path): void
    {
        $document = $this->openApiDocument();
        $operation = $document['paths'][$path]['get'] ?? null;
        $this->assertIsArray($operation, "OpenAPI GET {$path} must exist.");

        $perPageSchema = null;
        foreach ($operation['parameters'] ?? [] as $parameter) {
            if (isset($parameter['$ref'])) {
                $parameter = $this->resolveOpenApiReference($document, $parameter['$ref']);
            }

            if (($parameter['in'] ?? null) === 'query' && ($parameter['name'] ?? null) === 'per_page') {
                $perPageSchema = $parameter['schema'] ?? null;
                break;
            }
        }

        $this->assertIsArray($perPageSchema, "OpenAPI GET {$path} must define a per_page query schema.");
        $this->assertSame(100, $perPageSchema['maximum'] ?? null, "OpenAPI GET {$path} must cap per_page at 100.");

        $errors = $this->collectOpenApiSchemaErrors(101, $perPageSchema, $document, 'query.per_page');
        $this->assertContains('query.per_page is above OpenAPI maximum', $errors);
    }

    private function assertOpenApiRejectsPaginationMinimum(string $path, string $parameter): void
    {
        $document = $this->openApiDocument();
        $operation = $document['paths'][$path]['get'] ?? null;
        $this->assertIsArray($operation, "OpenAPI GET {$path} must exist.");

        $schema = null;
        foreach ($operation['parameters'] ?? [] as $operationParameter) {
            if (isset($operationParameter['$ref'])) {
                $operationParameter = $this->resolveOpenApiReference($document, $operationParameter['$ref']);
            }

            if (($operationParameter['in'] ?? null) === 'query' && ($operationParameter['name'] ?? null) === $parameter) {
                $schema = $operationParameter['schema'] ?? null;
                break;
            }
        }

        $this->assertIsArray($schema, "OpenAPI GET {$path} must define a {$parameter} query schema.");
        $this->assertSame(1, $schema['minimum'] ?? null, "OpenAPI GET {$path} must set {$parameter} minimum to 1.");

        $errors = $this->collectOpenApiSchemaErrors(0, $schema, $document, "query.{$parameter}");
        $this->assertContains("query.{$parameter} is below OpenAPI minimum", $errors);
    }
}
