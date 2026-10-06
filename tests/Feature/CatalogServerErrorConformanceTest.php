<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogServerErrorConformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unexpected_catalog_write_errors_match_openapi_without_leaking_or_writing(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id, 'nama' => 'Makanan awal']);
        $menu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
            'nama' => 'Menu awal',
            'harga' => '10000.00',
        ]);
        $token = $manager->createToken('catalog-server-error-test')->plainTextToken;
        $insertCategoryTrigger = 'test_catalog_insert_category_'.Str::lower(Str::random(12));
        $updateCategoryTrigger = 'test_catalog_update_category_'.Str::lower(Str::random(12));
        $insertMenuTrigger = 'test_catalog_insert_menu_'.Str::lower(Str::random(12));
        $updateMenuTrigger = 'test_catalog_update_menu_'.Str::lower(Str::random(12));

        try {
            foreach ([
                [$insertCategoryTrigger, 'kategori_menus', 'INSERT'],
                [$updateCategoryTrigger, 'kategori_menus', 'UPDATE'],
                [$insertMenuTrigger, 'menus', 'INSERT'],
                [$updateMenuTrigger, 'menus', 'UPDATE'],
            ] as [$trigger, $table, $event]) {
                DB::unprepared(<<<SQL
                    CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} FOR EACH ROW
                    BEGIN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected catalog write failure';
                    END
                    SQL);
            }

            $requests = [
                [
                    'method' => 'post',
                    'path' => '/kategori-menus',
                    'payload' => ['nama' => 'Kategori baru', 'urutan' => 3],
                    'schema_path' => '/kategori-menus',
                ],
                [
                    'method' => 'patch',
                    'path' => '/kategori-menus/'.$category->id,
                    'payload' => ['nama' => 'Nama kategori baru'],
                    'schema_path' => '/kategori-menus/{id}',
                ],
                [
                    'method' => 'post',
                    'path' => '/menus',
                    'payload' => [
                        'kategori_menu_id' => (string) $category->id,
                        'nama' => 'Menu baru',
                        'harga' => '12000.00',
                    ],
                    'schema_path' => '/menus',
                ],
                [
                    'method' => 'patch',
                    'path' => '/menus/'.$menu->id,
                    'payload' => ['nama' => 'Nama menu baru'],
                    'schema_path' => '/menus/{id}',
                ],
            ];

            foreach ($requests as $request) {
                $this->assertOperationRequestMatchesOpenApi($request['payload'], [], $request['schema_path'], $request['method']);

                $response = $this->withToken($token)->json(
                    $request['method'],
                    '/api/v1'.$request['path'],
                    $request['payload'],
                );

                $response->assertInternalServerError()
                    ->assertDontSee('injected catalog write failure');
                $this->assertOperationResponseMatchesOpenApi($response, $request['schema_path'], $request['method']);
                $this->assertSame('INTERNAL_ERROR', $response->json('code'));
                $this->assertSame('Terjadi kesalahan pada server.', $response->json('message'));
            }

            $this->assertSame(1, KategoriMenu::query()->where('warung_id', $warung->id)->count());
            $this->assertDatabaseHas('kategori_menus', [
                'id' => $category->id,
                'nama' => 'Makanan awal',
            ]);
            $this->assertSame(1, Menu::query()->where('warung_id', $warung->id)->count());
            $this->assertDatabaseHas('menus', [
                'id' => $menu->id,
                'nama' => 'Menu awal',
                'harga' => '10000.00',
            ]);
        } finally {
            foreach ([$insertCategoryTrigger, $updateCategoryTrigger, $insertMenuTrigger, $updateMenuTrigger] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }

            DB::table('menus')->where('warung_id', $warung->id)->delete();
            DB::table('kategori_menus')->where('warung_id', $warung->id)->delete();
            $manager->tokens()->delete();
            $manager->delete();
            $warung->delete();
        }
    }

    public function beginDatabaseTransaction(): void
    {
        // MySQL trigger DDL is isolated to the disposable Compose test database.
    }
}
