<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminWarungManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_list_search_filter_paginate_show_and_update_warungs(): void
    {
        $alpha = Warung::factory()->create([
            'kode' => 'WRG-ADM-A',
            'nama' => 'Alpha Market',
            'aktif' => true,
        ]);
        $bravo = Warung::factory()->create([
            'kode' => 'WRG-ADM-B',
            'nama' => 'Bravo Market',
            'aktif' => true,
        ]);
        $inactive = Warung::factory()->create([
            'kode' => 'WRG-ADM-Z',
            'nama' => 'Zulu Market',
            'aktif' => false,
        ]);
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('admin-warung-management-test')->plainTextToken;
        $firstPageQuery = ['page' => '1', 'per_page' => '1', 'sort' => 'nama'];

        $this->assertOperationQueryMatchesOpenApi($firstPageQuery, '/admin/warungs', 'get');
        $firstPage = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs?'.http_build_query($firstPageQuery))
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($firstPage, '/admin/warungs', 'get');
        $this->assertSame([(string) $alpha->id], array_column($firstPage->json('data'), 'id'));
        $this->assertSame(['page' => 1, 'per_page' => 1, 'total' => 3, 'last_page' => 3], $firstPage->json('meta'));

        $secondPageQuery = ['page' => '2', 'per_page' => '1', 'sort' => 'nama'];
        $this->assertOperationQueryMatchesOpenApi($secondPageQuery, '/admin/warungs', 'get');
        $secondPage = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs?'.http_build_query($secondPageQuery))
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($secondPage, '/admin/warungs', 'get');
        $this->assertSame([(string) $bravo->id], array_column($secondPage->json('data'), 'id'));
        $this->assertSame(['page' => 2, 'per_page' => 1, 'total' => 3, 'last_page' => 3], $secondPage->json('meta'));

        $searchQuery = ['q' => 'Alpha'];
        $this->assertOperationQueryMatchesOpenApi($searchQuery, '/admin/warungs', 'get');
        $search = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs?'.http_build_query($searchQuery))
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($search, '/admin/warungs', 'get');
        $this->assertSame([(string) $alpha->id], array_column($search->json('data'), 'id'));

        $detail = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs/'.$alpha->id)
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($detail, '/admin/warungs/{id}', 'get');
        $warungResource = $detail->json('data');
        $this->assertEqualsCanonicalizing(
            ['id', 'kode', 'nama', 'alamat', 'telepon', 'logo', 'timezone', 'tanggal_mulai', 'tanggal_berakhir', 'aktif', 'created_at', 'updated_at'],
            array_keys($warungResource),
        );
        $this->assertSame((string) $alpha->id, $warungResource['id']);
        $this->assertSame('WRG-ADM-A', $warungResource['kode']);
        $this->assertTrue($warungResource['aktif']);

        $updatePayload = [
            'nama' => 'Alpha Updated',
            'alamat' => 'Jalan Baru 1',
            'timezone' => 'America/New_York',
            'aktif' => false,
        ];
        $this->assertOperationRequestMatchesOpenApi($updatePayload, [], '/admin/warungs/{id}', 'patch');

        $updated = $this->withToken($token)
            ->patchJson('/api/v1/admin/warungs/'.$alpha->id, $updatePayload)
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($updated, '/admin/warungs/{id}', 'patch');
        $this->assertSame('Alpha Updated', $updated->json('data.nama'));
        $this->assertSame('Jalan Baru 1', $updated->json('data.alamat'));
        $this->assertSame('America/New_York', $updated->json('data.timezone'));
        $this->assertFalse($updated->json('data.aktif'));
        $this->assertDatabaseHas('warungs', [
            'id' => $alpha->id,
            'kode' => 'WRG-ADM-A',
            'nama' => 'Alpha Updated',
            'alamat' => 'Jalan Baru 1',
            'timezone' => 'America/New_York',
            'aktif' => false,
        ]);

        $inactiveQuery = ['aktif' => 'false'];
        $this->assertOperationQueryMatchesOpenApi($inactiveQuery, '/admin/warungs', 'get');
        $inactiveList = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs?'.http_build_query($inactiveQuery))
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($inactiveList, '/admin/warungs', 'get');
        $this->assertEqualsCanonicalizing(
            [(string) $alpha->id, (string) $inactive->id],
            array_column($inactiveList->json('data'), 'id'),
        );

        $missing = $this->withToken($token)->getJson('/api/v1/admin/warungs/999999999')->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($missing, '/admin/warungs/{id}', 'get');
        $this->assertD13ErrorEnvelope($missing, 'NOT_FOUND');

        $badPageSize = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs?per_page=101')
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($badPageSize, '/admin/warungs', 'get');
        $this->assertD13ErrorEnvelope($badPageSize, 'VALIDATION_ERROR', 'per_page');

        $badSort = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs?sort=aktif')
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($badSort, '/admin/warungs', 'get');
        $this->assertD13ErrorEnvelope($badSort, 'VALIDATION_ERROR', 'sort');

        $emptyUpdate = $this->withToken($token)
            ->patchJson('/api/v1/admin/warungs/'.$alpha->id, [])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($emptyUpdate, '/admin/warungs/{id}', 'patch');
        $this->assertD13ErrorEnvelope($emptyUpdate, 'VALIDATION_ERROR', 'data');

        $invalidDates = $this->withToken($token)
            ->patchJson('/api/v1/admin/warungs/'.$alpha->id, [
                'tanggal_mulai' => '2026-10-31',
                'tanggal_berakhir' => '2026-10-01',
            ])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($invalidDates, '/admin/warungs/{id}', 'patch');
        $this->assertD13ErrorEnvelope($invalidDates, 'VALIDATION_ERROR', 'tanggal_berakhir');
        $this->assertDatabaseHas('warungs', [
            'id' => $alpha->id,
            'nama' => 'Alpha Updated',
            'aktif' => false,
            'tanggal_mulai' => null,
            'tanggal_berakhir' => null,
        ]);
    }

    public function test_openapi_response_schema_check_rejects_an_undocumented_field(): void
    {
        $document = $this->openApiDocument();
        $schema = $this->resolveOpenApiReference($document, '#/components/schemas/WarungResponse');
        $payload = json_decode('{"data":{"id":"1","unexpected":true}}', false, 512, JSON_THROW_ON_ERROR);
        $errors = $this->collectOpenApiSchemaErrors($payload, $schema, $document, '$');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('unexpected', implode("\n", $errors));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonSuperadminRoles(): array
    {
        return [
            'owner' => ['owner'],
            'manager' => ['manager'],
            'cashier' => ['kasir'],
        ];
    }

    #[DataProvider('nonSuperadminRoles')]
    public function test_tenant_roles_cannot_list_read_or_update_warungs(string $role): void
    {
        $warung = Warung::factory()->create([
            'kode' => 'WRG-ADMIN-LOCKED',
            'nama' => 'Warung Tidak Berubah',
        ]);
        $actor = User::factory()->create(['warung_id' => $warung->id, 'role' => $role]);
        $token = $actor->createToken('admin-warung-management-test')->plainTextToken;

        $list = $this->withToken($token)->getJson('/api/v1/admin/warungs')->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($list, '/admin/warungs', 'get');
        $this->assertD13ErrorEnvelope($list, 'FORBIDDEN');

        $detail = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs/'.$warung->id)
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($detail, '/admin/warungs/{id}', 'get');
        $this->assertD13ErrorEnvelope($detail, 'FORBIDDEN');

        $update = $this->withToken($token)
            ->patchJson('/api/v1/admin/warungs/'.$warung->id, ['nama' => 'Percobaan Ubah'])
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($update, '/admin/warungs/{id}', 'patch');
        $this->assertD13ErrorEnvelope($update, 'FORBIDDEN');
        $this->assertDatabaseHas('warungs', [
            'id' => $warung->id,
            'kode' => 'WRG-ADMIN-LOCKED',
            'nama' => 'Warung Tidak Berubah',
        ]);
    }

    private function assertD13ErrorEnvelope(TestResponse $response, string $expectedCode, ?string $field = null): void
    {
        $body = $response->json();

        $this->assertEqualsCanonicalizing(['code', 'message', 'errors', 'request_id'], array_keys($body));
        $this->assertSame($expectedCode, $body['code']);
        $this->assertIsString($body['message']);
        $this->assertIsArray($body['errors']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $body['request_id'],
        );

        if ($field !== null) {
            $this->assertArrayHasKey($field, $body['errors']);
        }
    }
}
