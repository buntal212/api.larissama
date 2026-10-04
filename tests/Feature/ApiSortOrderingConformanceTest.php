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

class ApiSortOrderingConformanceTest extends TestCase
{
    use RefreshDatabase;

    private const MATCHING_LABEL = 'Sort Ordering Fixture';

    #[DataProvider('sortableListOperations')]
    public function test_sort_orders_rows_by_distinct_primary_values(
        string $path,
        string $role,
        string $sort,
    ): void {
        $warung = Warung::factory()->create();
        $user = $role === 'superadmin'
            ? User::factory()->superadmin()->create()
            : User::factory()->create([
                'warung_id' => $warung->id,
                'role' => $role,
                'nama' => 'Sort Ordering Actor',
            ]);
        $token = $user->createToken('sort-ordering-test')->plainTextToken;
        [$rows, $sortValues] = $this->createDistinctRows($path, $sort, $warung, $user);

        asort($sortValues, SORT_REGULAR);
        $expectedRows = array_map(fn (int $index) => $rows[$index], array_keys($sortValues));

        if (str_starts_with($sort, '-')) {
            $expectedRows = array_reverse($expectedRows);
        }

        $query = [
            'page' => '1',
            'per_page' => '3',
            'sort' => $sort,
        ];

        if (in_array($path, ['/admin/warungs', '/users'], true)) {
            $query['q'] = self::MATCHING_LABEL;
        }

        $this->assertOperationQueryMatchesOpenApi($query, $path, 'get');
        $response = $this->withToken($token)
            ->getJson('/api/v1'.$path.'?'.http_build_query($query))
            ->assertOk();

        $this->assertOperationResponseMatchesOpenApi($response, $path, 'get');
        $this->assertSame(
            array_map(fn ($row): string => (string) $row->getKey(), $expectedRows),
            array_column($response->json('data'), 'id'),
        );
        $this->assertSame([
            'page' => 1,
            'per_page' => 3,
            'total' => 3,
            'last_page' => 1,
        ], $response->json('meta'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function sortableListOperations(): array
    {
        return [
            'admin warungs ascending' => ['/admin/warungs', 'superadmin', 'nama'],
            'admin warungs descending' => ['/admin/warungs', 'superadmin', '-nama'],
            'users ascending' => ['/users', 'owner', 'nama'],
            'users descending' => ['/users', 'owner', '-nama'],
            'categories by order ascending' => ['/kategori-menus', 'manager', 'urutan'],
            'categories by order descending' => ['/kategori-menus', 'manager', '-urutan'],
            'categories by name ascending' => ['/kategori-menus', 'manager', 'nama'],
            'categories by name descending' => ['/kategori-menus', 'manager', '-nama'],
            'menus ascending' => ['/menus', 'manager', 'nama'],
            'menus descending' => ['/menus', 'manager', '-nama'],
            'sales ascending' => ['/penjualans', 'manager', 'tanggal'],
            'sales descending' => ['/penjualans', 'manager', '-tanggal'],
            'purchases ascending' => ['/pembelians', 'manager', 'tanggal'],
            'purchases descending' => ['/pembelians', 'manager', '-tanggal'],
        ];
    }

    /**
     * @return array{array<int, Warung|User|KategoriMenu|Menu|Penjualan|Pembelian>, array<int, int|string>}
     */
    private function createDistinctRows(string $path, string $sort, Warung $warung, User $actor): array
    {
        $sortColumn = ltrim($sort, '-');
        $rows = [];
        $sortValues = [];

        foreach (['C', 'A', 'B'] as $label) {
            $name = self::MATCHING_LABEL.' '.$label;
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
                        'warung_id' => $warung->id,
                        'nama' => $name,
                    ]),
                    $name,
                ],
                '/kategori-menus' => [
                    $this->createCategory($warung, $name, $order),
                    $sortColumn === 'urutan' ? $order : $name,
                ],
                '/menus' => [
                    $this->createMenu($warung, $name),
                    $name,
                ],
                '/penjualans' => [
                    Penjualan::factory()->create([
                        'warung_id' => $warung->id,
                        'user_id' => $actor->id,
                        'tanggal' => $date,
                    ]),
                    $date,
                ],
                '/pembelians' => [
                    Pembelian::factory()->create([
                        'warung_id' => $warung->id,
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

    private function createCategory(Warung $warung, string $name, int $order): KategoriMenu
    {
        return KategoriMenu::factory()->create([
            'warung_id' => $warung->id,
            'nama' => $name,
            'urutan' => $order,
        ]);
    }

    private function createMenu(Warung $warung, string $name): Menu
    {
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);

        return Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
            'nama' => $name,
        ]);
    }
}
