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

class ApiPaginationLargePageConformanceTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_PAGE = '9223372036854775807';

    private const PAGE_ABOVE_PHP_INT_MAX = '9223372036854775808';

    #[DataProvider('listOperations')]
    public function test_maximum_64_bit_page_returns_empty_data_and_original_total(string $path, string $role): void
    {
        $warung = Warung::factory()->create();
        $user = $role === 'superadmin'
            ? User::factory()->superadmin()->create()
            : User::factory()->create([
                'warung_id' => $warung->id,
                'role' => $role,
            ]);
        $token = $user->createToken('large-pagination-page-test')->plainTextToken;
        $this->createOneMatchingRow($path, $warung, $user);
        $query = [
            'page' => self::MAX_PAGE,
            'per_page' => '100',
        ];

        $this->assertOperationQueryMatchesOpenApi($query, $path, 'get');
        $response = $this->withToken($token)
            ->getJson('/api/v1'.$path.'?'.http_build_query($query))
            ->assertOk();

        $this->assertOperationResponseMatchesOpenApi($response, $path, 'get');
        $this->assertSame([
            'page' => 9223372036854775807,
            'per_page' => 100,
            'total' => 1,
            'last_page' => 1,
        ], $response->json('meta'));
        $this->assertSame([], $response->json('data'));
    }

    #[DataProvider('listOperations')]
    public function test_page_above_php_integer_range_is_preserved_as_an_exact_json_integer(string $path, string $role): void
    {
        $warung = Warung::factory()->create();
        $user = $role === 'superadmin'
            ? User::factory()->superadmin()->create()
            : User::factory()->create([
                'warung_id' => $warung->id,
                'role' => $role,
            ]);
        $token = $user->createToken('unbounded-pagination-page-test')->plainTextToken;
        $this->createOneMatchingRow($path, $warung, $user);
        $query = [
            'page' => self::PAGE_ABOVE_PHP_INT_MAX,
            'per_page' => '100',
        ];

        $this->assertOperationQueryMatchesOpenApi($query, $path, 'get');
        $response = $this->withToken($token)
            ->getJson('/api/v1'.$path.'?'.http_build_query($query))
            ->assertOk();

        $this->assertOperationResponseMatchesOpenApi($response, $path, 'get');
        $this->assertSame([], $response->json('data'));
        $this->assertSame(100, $response->json('meta.per_page'));
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame(1, $response->json('meta.last_page'));
        $this->assertMatchesRegularExpression(
            '/"meta"\s*:\s*\{[^{}]*"page"\s*:\s*'.self::PAGE_ABOVE_PHP_INT_MAX.'(?=\s*[,}])/',
            $response->getContent(),
        );
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

    private function createOneMatchingRow(string $path, Warung $warung, User $actor): void
    {
        match ($path) {
            '/admin/warungs', '/users' => null,
            '/kategori-menus' => KategoriMenu::factory()->create([
                'warung_id' => $warung->id,
            ]),
            '/menus' => $this->createMenu($warung),
            '/penjualans' => Penjualan::factory()->create([
                'warung_id' => $warung->id,
                'user_id' => $actor->id,
            ]),
            '/pembelians' => Pembelian::factory()->create([
                'warung_id' => $warung->id,
                'user_id' => $actor->id,
            ]),
            default => throw new \InvalidArgumentException("Unsupported list path {$path}."),
        };
    }

    private function createMenu(Warung $warung): Menu
    {
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);

        return Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
        ]);
    }
}
