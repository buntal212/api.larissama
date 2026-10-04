<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
