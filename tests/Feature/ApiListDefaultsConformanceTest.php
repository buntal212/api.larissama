<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiListDefaultsConformanceTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE_LABEL = 'Default Sort Fixture';

    #[DataProvider('listOperations')]
    public function test_omitted_query_uses_openapi_pagination_and_sort_defaults(
        string $path,
        string $role,
        string $defaultSort,
    ): void {
        $this->assertOpenApiDefaults($path, $defaultSort);

        $warung = $role === 'superadmin' ? null : Warung::factory()->create();
        $user = $role === 'superadmin'
            ? User::factory()->superadmin()->create()
            : User::factory()->create([
                'warung_id' => $warung?->id,
                'role' => $role,
                'nama' => $path === '/users' ? 'ZZZ Default Sort Actor' : 'Default Sort Actor',
            ]);
        $token = $user->createToken('list-defaults-test')->plainTextToken;
        [$rows, $sortValues] = $this->createRows($path, $warung, $user);

        asort($sortValues, SORT_REGULAR);
        $expectedRows = array_map(fn (int $index) => $rows[$index], array_keys($sortValues));

        if (str_starts_with($defaultSort, '-')) {
            $expectedRows = array_reverse($expectedRows);
        }

        $this->assertOperationQueryMatchesOpenApi([], $path, 'get');
        $response = $this->withToken($token)
            ->getJson('/api/v1'.$path)
            ->assertOk();

        $this->assertOperationResponseMatchesOpenApi($response, $path, 'get');
        $this->assertSame(
            array_map(fn ($row): string => (string) $row->getKey(), $expectedRows),
            array_column($response->json('data'), 'id'),
        );
        $this->assertSame([
            'page' => 1,
            'per_page' => 20,
            'total' => count($expectedRows),
            'last_page' => 1,
        ], $response->json('meta'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function listOperations(): array
    {
        return [
            'admin warungs' => ['/admin/warungs', 'superadmin', 'nama'],
            'users' => ['/users', 'owner', 'nama'],
            'categories' => ['/kategori-menus', 'manager', 'urutan'],
            'menus' => ['/menus', 'manager', 'nama'],
            'sales' => ['/penjualans', 'manager', '-tanggal'],
            'purchases' => ['/pembelians', 'manager', '-tanggal'],
        ];
    }

    private function assertOpenApiDefaults(string $path, string $defaultSort): void
    {
        $document = $this->openApiDocument();
        $operation = $document['paths'][$path]['get'] ?? null;
        $this->assertIsArray($operation, "OpenAPI GET {$path} must exist.");

        $parameters = [];
        foreach ($operation['parameters'] ?? [] as $parameter) {
            if (isset($parameter['$ref'])) {
                $parameter = $this->resolveOpenApiReference($document, $parameter['$ref']);
            }

            $parameters[$parameter['name']] = $parameter;
        }

        $this->assertSame(1, $parameters['page']['schema']['default'] ?? null);
        $this->assertSame(20, $parameters['per_page']['schema']['default'] ?? null);
        $this->assertSame($defaultSort, $parameters['sort']['schema']['default'] ?? null);
    }

    /**
     * @return array{array<int, Warung|User|KategoriMenu|Menu|Penjualan|Pembelian>, array<int, int|string>}
     */
    private function createRows(string $path, ?Warung $warung, User $actor): array
    {
        $rows = [];
        $sortValues = [];

        if ($path === '/users') {
            $rows[] = $actor;
            $sortValues[] = $actor->nama;
        }

        $category = $path === '/menus'
            ? KategoriMenu::factory()->create(['warung_id' => $warung?->id])
            : null;

        foreach (['C', 'A', 'B'] as $label) {
            $name = self::FIXTURE_LABEL.' '.$label;
            $date = match ($label) {
                'A' => '2026-10-01 12:00:00',
                'B' => '2026-10-02 12:00:00',
                'C' => '2026-10-03 12:00:00',
            };
            $order = match ($label) {
                'A' => 10,
                'B' => 20,
                'C' => 30,
            };

            [$row, $sortValue] = match ($path) {
                '/admin/warungs' => [
                    Warung::factory()->create(['nama' => $name]),
                    $name,
                ],
                '/users' => [
                    User::factory()->create([
                        'warung_id' => $warung?->id,
                        'nama' => $name,
                    ]),
                    $name,
                ],
                '/kategori-menus' => [
                    KategoriMenu::factory()->create([
                        'warung_id' => $warung?->id,
                        'nama' => $name,
                        'urutan' => $order,
                    ]),
                    $order,
                ],
                '/menus' => [
                    Menu::factory()->create([
                        'warung_id' => $warung?->id,
                        'kategori_menu_id' => $category?->id,
                        'nama' => $name,
                    ]),
                    $name,
                ],
                '/penjualans' => [
                    Penjualan::factory()->create([
                        'warung_id' => $warung?->id,
                        'user_id' => $actor->id,
                        'tanggal' => $date,
                    ]),
                    $date,
                ],
                '/pembelians' => [
                    Pembelian::factory()->create([
                        'warung_id' => $warung?->id,
                        'user_id' => $actor->id,
                        'tanggal' => $date,
                    ]),
                    $date,
                ],
                default => throw new \InvalidArgumentException("Unsupported list path {$path}."),
            };

            $rows[] = $row;
            $sortValues[] = $sortValue;
        }

        return [$rows, $sortValues];
    }
}
