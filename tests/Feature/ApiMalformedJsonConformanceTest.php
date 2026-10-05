<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiMalformedJsonConformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_json_body_operation_returns_schema_conformant_400_for_malformed_json(): void
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $superadmin = User::factory()->superadmin()->create();
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);
        $menu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
        ]);
        $ownerToken = $owner->createToken('malformed-json-owner')->plainTextToken;
        $superadminToken = $superadmin->createToken('malformed-json-superadmin')->plainTextToken;

        $operations = [
            ['path' => '/api/v1/auth/login', 'contract_path' => '/auth/login', 'method' => 'POST'],
            ['path' => '/api/v1/auth/login', 'contract_path' => '/auth/login', 'method' => 'POST', 'content' => " \n\t"],
            ['path' => '/api/v1/admin/warungs', 'contract_path' => '/admin/warungs', 'method' => 'POST', 'token' => $superadminToken],
            ['path' => "/api/v1/admin/warungs/{$warung->id}", 'contract_path' => '/admin/warungs/{id}', 'method' => 'PATCH', 'token' => $superadminToken],
            ['path' => '/api/v1/users', 'contract_path' => '/users', 'method' => 'POST', 'token' => $ownerToken],
            ['path' => "/api/v1/users/{$manager->id}", 'contract_path' => '/users/{id}', 'method' => 'PATCH', 'token' => $ownerToken],
            ['path' => '/api/v1/kategori-menus', 'contract_path' => '/kategori-menus', 'method' => 'POST', 'token' => $ownerToken],
            ['path' => "/api/v1/kategori-menus/{$category->id}", 'contract_path' => '/kategori-menus/{id}', 'method' => 'PATCH', 'token' => $ownerToken],
            ['path' => '/api/v1/menus', 'contract_path' => '/menus', 'method' => 'POST', 'token' => $ownerToken],
            ['path' => "/api/v1/menus/{$menu->id}", 'contract_path' => '/menus/{id}', 'method' => 'PATCH', 'token' => $ownerToken],
            ['path' => '/api/v1/penjualans', 'contract_path' => '/penjualans', 'method' => 'POST', 'token' => $ownerToken, 'idempotency_key' => 'malformed-sale-001'],
            ['path' => '/api/v1/pembelians', 'contract_path' => '/pembelians', 'method' => 'POST', 'token' => $ownerToken, 'idempotency_key' => 'malformed-purchase-001'],
        ];

        $tableCounts = $this->trackedTableCounts();
        $targetRows = [
            'warung' => $warung->fresh()->getRawOriginal(),
            'manager' => $manager->fresh()->getRawOriginal(),
            'category' => $category->fresh()->getRawOriginal(),
            'menu' => $menu->fresh()->getRawOriginal(),
        ];

        foreach ($operations as $operation) {
            $server = [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ];

            if (isset($operation['token'])) {
                $server['HTTP_AUTHORIZATION'] = 'Bearer '.$operation['token'];
            }

            if (isset($operation['idempotency_key'])) {
                $server['HTTP_IDEMPOTENCY_KEY'] = $operation['idempotency_key'];
            }

            $response = $this->call(
                $operation['method'],
                $operation['path'],
                [],
                [],
                [],
                $server,
                $operation['content'] ?? '{"malformed":',
            );

            $response->assertStatus(400);
            $this->assertSame('BAD_REQUEST', $response->json('code'));
            $this->assertSame('JSON request tidak dapat dibaca.', $response->json('message'));
            $this->assertSame([], $response->json('errors'));
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $response->json('request_id'),
            );
            $this->assertOperationResponseMatchesOpenApi($response, $operation['contract_path'], $operation['method']);
        }

        $this->assertSame($tableCounts, $this->trackedTableCounts());
        $this->assertSame($targetRows['warung'], $warung->fresh()->getRawOriginal());
        $this->assertSame($targetRows['manager'], $manager->fresh()->getRawOriginal());
        $this->assertSame($targetRows['category'], $category->fresh()->getRawOriginal());
        $this->assertSame($targetRows['menu'], $menu->fresh()->getRawOriginal());
    }

    public function test_required_json_body_operations_reject_a_missing_body_without_writes(): void
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $superadmin = User::factory()->superadmin()->create();
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);
        $menu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
        ]);
        $ownerToken = $owner->createToken('empty-body-owner')->plainTextToken;
        $superadminToken = $superadmin->createToken('empty-body-superadmin')->plainTextToken;

        $operations = [
            ['path' => '/api/v1/auth/login', 'contract_path' => '/auth/login', 'method' => 'POST'],
            ['path' => '/api/v1/admin/warungs', 'contract_path' => '/admin/warungs', 'method' => 'POST', 'token' => $superadminToken],
            ['path' => "/api/v1/admin/warungs/{$warung->id}", 'contract_path' => '/admin/warungs/{id}', 'method' => 'PATCH', 'token' => $superadminToken],
            ['path' => '/api/v1/users', 'contract_path' => '/users', 'method' => 'POST', 'token' => $ownerToken],
            ['path' => "/api/v1/users/{$manager->id}", 'contract_path' => '/users/{id}', 'method' => 'PATCH', 'token' => $ownerToken],
            ['path' => '/api/v1/kategori-menus', 'contract_path' => '/kategori-menus', 'method' => 'POST', 'token' => $ownerToken],
            ['path' => "/api/v1/kategori-menus/{$category->id}", 'contract_path' => '/kategori-menus/{id}', 'method' => 'PATCH', 'token' => $ownerToken],
            ['path' => '/api/v1/menus', 'contract_path' => '/menus', 'method' => 'POST', 'token' => $ownerToken],
            ['path' => "/api/v1/menus/{$menu->id}", 'contract_path' => '/menus/{id}', 'method' => 'PATCH', 'token' => $ownerToken],
            ['path' => '/api/v1/penjualans', 'contract_path' => '/penjualans', 'method' => 'POST', 'token' => $ownerToken, 'idempotency_key' => 'empty-body-sale-001'],
            ['path' => '/api/v1/pembelians', 'contract_path' => '/pembelians', 'method' => 'POST', 'token' => $ownerToken, 'idempotency_key' => 'empty-body-purchase-001'],
        ];

        $tableCounts = $this->trackedTableCounts();
        $targetRows = [
            'warung' => $warung->fresh()->getRawOriginal(),
            'manager' => $manager->fresh()->getRawOriginal(),
            'category' => $category->fresh()->getRawOriginal(),
            'menu' => $menu->fresh()->getRawOriginal(),
        ];

        foreach ($operations as $operation) {
            $document = $this->openApiDocument();
            $requestBody = $document['paths'][$operation['contract_path']][strtolower($operation['method'])]['requestBody'] ?? null;
            $this->assertIsArray($requestBody);
            $this->assertTrue($requestBody['required'] ?? false, $operation['method'].' '.$operation['contract_path'].' must require a request body.');

            $server = [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => '0',
            ];

            if (isset($operation['token'])) {
                $server['HTTP_AUTHORIZATION'] = 'Bearer '.$operation['token'];
            }

            if (isset($operation['idempotency_key'])) {
                $server['HTTP_IDEMPOTENCY_KEY'] = $operation['idempotency_key'];
            }

            Auth::forgetGuards();
            $response = $this->call(
                $operation['method'],
                $operation['path'],
                [],
                [],
                [],
                $server,
                '',
            );

            $response->assertUnprocessable();
            $this->assertSame('VALIDATION_ERROR', $response->json('code'));
            $this->assertNotSame([], $response->json('errors'));
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $response->json('request_id'),
            );
            $this->assertOperationResponseMatchesOpenApi($response, $operation['contract_path'], $operation['method']);
        }

        $this->assertSame($tableCounts, $this->trackedTableCounts());
        $this->assertSame($targetRows['warung'], $warung->fresh()->getRawOriginal());
        $this->assertSame($targetRows['manager'], $manager->fresh()->getRawOriginal());
        $this->assertSame($targetRows['category'], $category->fresh()->getRawOriginal());
        $this->assertSame($targetRows['menu'], $menu->fresh()->getRawOriginal());
    }

    /** @return array<string, int> */
    private function trackedTableCounts(): array
    {
        return collect(['warungs', 'users', 'kategori_menus', 'menus', 'penjualans', 'penjualan_rincis', 'pembelians', 'pembelian_rincis'])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();
    }
}
