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

class ApiSortTieBreakerTest extends TestCase
{
    use RefreshDatabase;

    private const TIED_LABEL = 'Sort Tie Fixture';

    #[DataProvider('sortableListOperations')]
    public function test_sort_uses_id_as_directional_tie_breaker_across_pages(
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
                'nama' => 'Sort Test Actor',
            ]);
        $token = $user->createToken('sort-tie-breaker-test')->plainTextToken;
        $rows = $this->createTiedRows($path, $warung, $user);
        $expectedIds = array_map(fn ($row): string => (string) $row->getKey(), $rows);

        if (str_starts_with($sort, '-')) {
            $expectedIds = array_reverse($expectedIds);
        }

        $query = [
            'page' => '1',
            'per_page' => '1',
            'sort' => $sort,
        ];

        if (in_array($path, ['/admin/warungs', '/users'], true)) {
            $query['q'] = self::TIED_LABEL;
        }

        $this->assertOperationQueryMatchesOpenApi($query, $path, 'get');
        $firstPage = $this->withToken($token)
            ->getJson('/api/v1'.$path.'?'.http_build_query($query))
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($firstPage, $path, 'get');
        $this->assertSame([$expectedIds[0]], array_column($firstPage->json('data'), 'id'));
        $this->assertSame(['page' => 1, 'per_page' => 1, 'total' => 2, 'last_page' => 2], $firstPage->json('meta'));

        $query['page'] = '2';
        $this->assertOperationQueryMatchesOpenApi($query, $path, 'get');
        $secondPage = $this->withToken($token)
            ->getJson('/api/v1'.$path.'?'.http_build_query($query))
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($secondPage, $path, 'get');
        $this->assertSame([$expectedIds[1]], array_column($secondPage->json('data'), 'id'));
        $this->assertSame(['page' => 2, 'per_page' => 1, 'total' => 2, 'last_page' => 2], $secondPage->json('meta'));
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
     * @return array<int, Warung|User|KategoriMenu|Menu|Penjualan|Pembelian>
     */
    private function createTiedRows(string $path, Warung $warung, User $actor): array
    {
        return match ($path) {
            '/admin/warungs' => [
                Warung::factory()->create(['nama' => self::TIED_LABEL]),
                Warung::factory()->create(['nama' => self::TIED_LABEL]),
            ],
            '/users' => [
                User::factory()->create([
                    'warung_id' => $warung->id,
                    'nama' => self::TIED_LABEL,
                ]),
                User::factory()->create([
                    'warung_id' => $warung->id,
                    'nama' => self::TIED_LABEL,
                ]),
            ],
            '/kategori-menus' => [
                KategoriMenu::factory()->create([
                    'warung_id' => $warung->id,
                    'nama' => self::TIED_LABEL,
                    'urutan' => 7,
                ]),
                KategoriMenu::factory()->create([
                    'warung_id' => $warung->id,
                    'nama' => self::TIED_LABEL,
                    'urutan' => 7,
                ]),
            ],
            '/menus' => $this->createTiedMenus($warung),
            '/penjualans' => [
                Penjualan::factory()->create([
                    'warung_id' => $warung->id,
                    'user_id' => $actor->id,
                    'tanggal' => '2026-10-04 12:00:00',
                ]),
                Penjualan::factory()->create([
                    'warung_id' => $warung->id,
                    'user_id' => $actor->id,
                    'tanggal' => '2026-10-04 12:00:00',
                ]),
            ],
            '/pembelians' => [
                Pembelian::factory()->create([
                    'warung_id' => $warung->id,
                    'user_id' => $actor->id,
                    'tanggal' => '2026-10-04 12:00:00',
                ]),
                Pembelian::factory()->create([
                    'warung_id' => $warung->id,
                    'user_id' => $actor->id,
                    'tanggal' => '2026-10-04 12:00:00',
                ]),
            ],
            default => throw new \InvalidArgumentException("Unsupported list path {$path}."),
        };
    }

    /**
     * @return array<int, Menu>
     */
    private function createTiedMenus(Warung $warung): array
    {
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);

        return [
            Menu::factory()->create([
                'warung_id' => $warung->id,
                'kategori_menu_id' => $category->id,
                'nama' => self::TIED_LABEL,
            ]),
            Menu::factory()->create([
                'warung_id' => $warung->id,
                'kategori_menu_id' => $category->id,
                'nama' => self::TIED_LABEL,
            ]),
        ];
    }
}
