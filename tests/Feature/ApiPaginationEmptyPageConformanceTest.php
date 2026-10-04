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

class ApiPaginationEmptyPageConformanceTest extends TestCase
{
    use RefreshDatabase;

    private const MATCHING_LABEL = 'Pagination fixture';

    #[DataProvider('listOperations')]
    public function test_empty_results_and_page_after_last_page_keep_openapi_metadata(
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
                'nama' => 'Pagination test actor',
            ]);
        $token = $user->createToken('empty-pagination-test')->plainTextToken;
        $emptyQuery = [
            'page' => '1',
            'per_page' => '20',
            'sort' => $sort,
        ];

        if (in_array($path, ['/admin/warungs', '/users'], true)) {
            $emptyQuery['q'] = 'No matching pagination fixture';
        }

        $this->assertOperationQueryMatchesOpenApi($emptyQuery, $path, 'get');
        $emptyResponse = $this->withToken($token)
            ->getJson('/api/v1'.$path.'?'.http_build_query($emptyQuery))
            ->assertOk();

        $this->assertOperationResponseMatchesOpenApi($emptyResponse, $path, 'get');
        $this->assertSame([], $emptyResponse->json('data'));
        $this->assertSame([
            'page' => 1,
            'per_page' => 20,
            'total' => 0,
            'last_page' => 1,
        ], $emptyResponse->json('meta'));

        $this->createMatchingRows($path, $warung, $user);
        $outOfRangeQuery = [
            'page' => '3',
            'per_page' => '1',
            'sort' => $sort,
        ];

        if (in_array($path, ['/admin/warungs', '/users'], true)) {
            $outOfRangeQuery['q'] = self::MATCHING_LABEL;
        }

        $this->assertOperationQueryMatchesOpenApi($outOfRangeQuery, $path, 'get');
        $outOfRangeResponse = $this->withToken($token)
            ->getJson('/api/v1'.$path.'?'.http_build_query($outOfRangeQuery))
            ->assertOk();

        $this->assertOperationResponseMatchesOpenApi($outOfRangeResponse, $path, 'get');
        $this->assertSame([], $outOfRangeResponse->json('data'));
        $this->assertSame([
            'page' => 3,
            'per_page' => 1,
            'total' => 2,
            'last_page' => 2,
        ], $outOfRangeResponse->json('meta'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function listOperations(): array
    {
        return [
            'admin warungs as superadmin' => ['/admin/warungs', 'superadmin', 'nama'],
            'users as owner' => ['/users', 'owner', 'nama'],
            'categories as manager' => ['/kategori-menus', 'manager', 'nama'],
            'menus as manager' => ['/menus', 'manager', 'nama'],
            'sales as manager' => ['/penjualans', 'manager', '-tanggal'],
            'purchases as manager' => ['/pembelians', 'manager', '-tanggal'],
        ];
    }

    private function createMatchingRows(string $path, Warung $warung, User $actor): void
    {
        match ($path) {
            '/admin/warungs' => [
                Warung::factory()->create(['nama' => self::MATCHING_LABEL.' A']),
                Warung::factory()->create(['nama' => self::MATCHING_LABEL.' B']),
            ],
            '/users' => [
                User::factory()->create([
                    'warung_id' => $warung->id,
                    'nama' => self::MATCHING_LABEL.' A',
                ]),
                User::factory()->create([
                    'warung_id' => $warung->id,
                    'nama' => self::MATCHING_LABEL.' B',
                ]),
            ],
            '/kategori-menus' => [
                KategoriMenu::factory()->create([
                    'warung_id' => $warung->id,
                    'nama' => self::MATCHING_LABEL.' A',
                ]),
                KategoriMenu::factory()->create([
                    'warung_id' => $warung->id,
                    'nama' => self::MATCHING_LABEL.' B',
                ]),
            ],
            '/menus' => $this->createMatchingMenus($warung),
            '/penjualans' => [
                Penjualan::factory()->create([
                    'warung_id' => $warung->id,
                    'user_id' => $actor->id,
                ]),
                Penjualan::factory()->create([
                    'warung_id' => $warung->id,
                    'user_id' => $actor->id,
                ]),
            ],
            '/pembelians' => [
                Pembelian::factory()->create([
                    'warung_id' => $warung->id,
                    'user_id' => $actor->id,
                ]),
                Pembelian::factory()->create([
                    'warung_id' => $warung->id,
                    'user_id' => $actor->id,
                ]),
            ],
            default => throw new \InvalidArgumentException("Unsupported list path {$path}."),
        };
    }

    /**
     * @return array<int, Menu>
     */
    private function createMatchingMenus(Warung $warung): array
    {
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);

        return [
            Menu::factory()->create([
                'warung_id' => $warung->id,
                'kategori_menu_id' => $category->id,
                'nama' => self::MATCHING_LABEL.' A',
            ]),
            Menu::factory()->create([
                'warung_id' => $warung->id,
                'kategori_menu_id' => $category->id,
                'nama' => self::MATCHING_LABEL.' B',
            ]),
        ];
    }
}
