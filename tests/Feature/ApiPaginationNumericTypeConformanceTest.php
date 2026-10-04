<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiPaginationNumericTypeConformanceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidNumericPaginationOperations')]
    public function test_decimal_fraction_and_exponent_query_values_are_not_integers(
        string $path,
        string $role,
        string $parameter,
        string $value,
    ): void {
        $warung = Warung::factory()->create();
        $user = $role === 'superadmin'
            ? User::factory()->superadmin()->create()
            : User::factory()->create([
                'warung_id' => $warung->id,
                'role' => $role,
            ]);
        $token = $user->createToken('pagination-numeric-type-test')->plainTextToken;
        $query = [$parameter => $value];

        $this->assertOpenApiRejectsNumericIntegerForm($path, $parameter, $value);

        $response = $this->withToken($token)
            ->getJson('/api/v1'.$path.'?'.http_build_query($query))
            ->assertUnprocessable();

        $this->assertOperationResponseMatchesOpenApi($response, $path, 'get');
        $response->assertJsonPath('code', 'VALIDATION_ERROR');
        $response->assertJsonPath('message', 'Data belum valid.');
        $response->assertInvalid([$parameter => 'integer']);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function invalidNumericPaginationOperations(): array
    {
        $operations = [
            'admin warungs as superadmin' => ['/admin/warungs', 'superadmin'],
            'users as owner' => ['/users', 'owner'],
            'categories as manager' => ['/kategori-menus', 'manager'],
            'menus as manager' => ['/menus', 'manager'],
            'sales as manager' => ['/penjualans', 'manager'],
            'purchases as manager' => ['/pembelians', 'manager'],
        ];
        $cases = [];

        foreach ($operations as $name => [$path, $role]) {
            foreach (['page', 'per_page'] as $parameter) {
                foreach (['1.0', '1.5', '1e2'] as $value) {
                    $cases["{$name} {$parameter}={$value}"] = [$path, $role, $parameter, $value];
                }
            }
        }

        return $cases;
    }

    private function assertOpenApiRejectsNumericIntegerForm(string $path, string $parameter, string $value): void
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

        $this->assertIsArray($schema, "OpenAPI GET {$path} must define {$parameter} as a query parameter.");
        $this->assertSame('integer', $schema['type'] ?? null);
        $errors = $this->collectOpenApiSchemaErrors($value, $schema, $document, "query.{$parameter}");

        $this->assertContains("query.{$parameter} does not match OpenAPI type integer", $errors);
    }
}
