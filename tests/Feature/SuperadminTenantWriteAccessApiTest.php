<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminTenantWriteAccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_must_select_a_warung_for_each_tenant_form_write(): void
    {
        $warung = Warung::factory()->create();
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'kategori_menu_id' => $category->id]);
        $user = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('superadmin-tenant-write-scope-test')->plainTextToken;

        $requests = [
            ['POST', '/api/v1/users', ['nama' => 'Kasir', 'username' => 'kasir_baru', 'password' => 'kasir-password', 'role' => 'kasir']],
            ['PATCH', '/api/v1/users/'.$user->id, ['nama' => 'Nama Baru']],
            ['POST', '/api/v1/kategori-menus', ['nama' => 'Kategori Baru']],
            ['PATCH', '/api/v1/kategori-menus/'.$category->id, ['nama' => 'Kategori Baru']],
            ['POST', '/api/v1/menus', ['kategori_menu_id' => (string) $category->id, 'nama' => 'Menu Baru', 'harga' => '12000.00']],
            ['PATCH', '/api/v1/menus/'.$menu->id, ['nama' => 'Menu Baru']],
        ];

        foreach ($requests as [$method, $uri, $payload]) {
            $response = $this->withToken($token)->json($method, $uri, $payload)->assertUnprocessable();
            $this->assertSame('VALIDATION_ERROR', $response->json('code'));
            $this->assertArrayHasKey('warung_id', $response->json('errors'));
        }

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('kategori_menus', 1);
        $this->assertDatabaseCount('menus', 1);
    }

    public function test_tenant_users_cannot_choose_warung_for_any_tenant_form_write(): void
    {
        $warung = Warung::factory()->create();
        $otherWarung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'kategori_menu_id' => $category->id]);
        $ownerToken = $owner->createToken('owner-tenant-write-scope-test')->plainTextToken;
        $managerToken = $manager->createToken('manager-tenant-write-scope-test')->plainTextToken;

        $requests = [
            [$ownerToken, 'POST', '/api/v1/users', ['warung_id' => $otherWarung->id, 'nama' => 'User', 'username' => 'tidak_boleh', 'password' => 'tenant-password', 'role' => 'kasir']],
            [$ownerToken, 'PATCH', '/api/v1/users/'.$manager->id, ['warung_id' => null, 'nama' => 'Nama Baru']],
            [$managerToken, 'POST', '/api/v1/kategori-menus', ['warung_id' => $otherWarung->id, 'nama' => 'Asing']],
            [$managerToken, 'PATCH', '/api/v1/kategori-menus/'.$category->id, ['warung_id' => null, 'nama' => 'Baru']],
            [$managerToken, 'POST', '/api/v1/menus', ['warung_id' => $otherWarung->id, 'kategori_menu_id' => $category->id, 'nama' => 'Asing', 'harga' => '10000.00']],
            [$managerToken, 'PATCH', '/api/v1/menus/'.$menu->id, ['warung_id' => null, 'nama' => 'Baru']],
        ];

        foreach ($requests as [$token, $method, $uri, $payload]) {
            $response = $this->withToken($token)->json($method, $uri, $payload)->assertUnprocessable();
            $this->assertSame('VALIDATION_ERROR', $response->json('code'));
            $this->assertArrayHasKey('warung_id', $response->json('errors'));
        }

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('kategori_menus', 1);
        $this->assertDatabaseCount('menus', 1);
        $this->assertDatabaseHas('users', ['id' => $manager->id, 'nama' => $manager->nama]);
        $this->assertDatabaseHas('kategori_menus', ['id' => $category->id, 'nama' => $category->nama]);
        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'nama' => $menu->nama]);
    }
}
