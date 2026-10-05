<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminWarungApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_provisions_a_warung_and_owner_with_tenant_context(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('admin-feature-test')->plainTextToken;
        $payload = $this->warungPayload('WRG-ADMIN-001', 'owner-admin-001');
        $this->assertOperationRequestMatchesOpenApi($payload, [], '/admin/warungs', 'post');

        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/warungs', $payload)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($response, '/admin/warungs', 'post');

        $body = $response->json();
        $this->assertEqualsCanonicalizing(['data'], array_keys($body));
        $this->assertEqualsCanonicalizing(['warung', 'owner'], array_keys($body['data']));
        $this->assertEqualsCanonicalizing(
            ['id', 'kode', 'nama', 'alamat', 'telepon', 'logo', 'timezone', 'tanggal_mulai', 'tanggal_berakhir', 'aktif', 'created_at', 'updated_at'],
            array_keys($body['data']['warung']),
        );
        $this->assertEqualsCanonicalizing(
            ['id', 'warung_id', 'nama', 'username', 'email', 'role', 'aktif', 'created_at', 'updated_at'],
            array_keys($body['data']['owner']),
        );
        $this->assertSame($payload['kode'], $body['data']['warung']['kode']);
        $this->assertSame($payload['nama'], $body['data']['warung']['nama']);
        $this->assertSame($payload['timezone'], $body['data']['warung']['timezone']);
        $this->assertTrue($body['data']['warung']['aktif']);
        $this->assertIsString($body['data']['warung']['id']);
        $this->assertSame('owner', $body['data']['owner']['role']);
        $this->assertSame($payload['owner']['username'], $body['data']['owner']['username']);
        $this->assertSame($body['data']['warung']['id'], $body['data']['owner']['warung_id']);
        $this->assertArrayNotHasKey('password', $body['data']['owner']);
        $this->assertStringNotContainsString($payload['owner']['password'], $response->getContent());

        $this->assertDatabaseHas('warungs', [
            'id' => (int) $body['data']['warung']['id'],
            'kode' => $payload['kode'],
            'timezone' => $payload['timezone'],
            'tanggal_mulai' => null,
            'tanggal_berakhir' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => (int) $body['data']['owner']['id'],
            'warung_id' => (int) $body['data']['warung']['id'],
            'username' => $payload['owner']['username'],
            'role' => 'owner',
            'aktif' => true,
        ]);

        $owner = User::query()->findOrFail((int) $body['data']['owner']['id']);
        $this->assertTrue(Hash::check($payload['owner']['password'], $owner->password));
    }

    public function test_superadmin_gets_schema_conformant_422_for_invalid_provision_payload(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('admin-feature-test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/warungs', [])
            ->assertUnprocessable();

        $this->assertOperationResponseMatchesOpenApi($response, '/admin/warungs', 'post');
        $this->assertD13ErrorEnvelope($response, 'VALIDATION_ERROR');
        $this->assertSame(0, Warung::query()->count());
    }

    public function test_superadmin_rejects_timezone_outside_the_iana_database_when_provisioning(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('admin-feature-test')->plainTextToken;
        $payload = $this->warungPayload('WRG-INVALID-TZ', 'owner-invalid-tz');
        $payload['timezone'] = 'Invalid/Timezone';

        $this->assertOperationRequestMatchesOpenApi($payload, [], '/admin/warungs', 'post');
        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/warungs', $payload)
            ->assertUnprocessable();

        $this->assertOperationResponseMatchesOpenApi($response, '/admin/warungs', 'post');
        $this->assertD13ErrorEnvelope($response, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('timezone', $response->json('errors'));
        $this->assertSame(0, Warung::query()->count());
        $this->assertSame(1, User::query()->count(), 'Invalid timezone must not create the owner.');
        $this->assertDatabaseMissing('users', ['username' => $payload['owner']['username']]);
    }

    public function test_superadmin_rejects_uppercase_owner_identifier_without_partial_provisioning(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('admin-feature-test')->plainTextToken;
        $payload = $this->warungPayload('WRG-UPPERCASE-OWNER', 'Owner123');
        $payload['owner']['email'] = 'Owner123@example.com';

        $this->assertOperationRequestDoesNotMatchOpenApi($payload, '/admin/warungs', 'post');
        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/warungs', $payload)
            ->assertUnprocessable();

        $this->assertOperationResponseMatchesOpenApi($response, '/admin/warungs', 'post');
        $this->assertD13ErrorEnvelope($response, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('owner.username', $response->json('errors'));
        $this->assertArrayHasKey('owner.email', $response->json('errors'));
        $this->assertSame(0, Warung::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseMissing('users', ['username' => 'Owner123']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function tenantRolesCannotProvisionWarungs(): array
    {
        return [
            'owner' => ['owner'],
            'manager' => ['manager'],
            'cashier' => ['kasir'],
        ];
    }

    #[DataProvider('tenantRolesCannotProvisionWarungs')]
    public function test_tenant_roles_receive_403_when_provisioning_warungs(string $role): void
    {
        $warung = Warung::factory()->create();
        $actor = User::factory()->create(['warung_id' => $warung->id, 'role' => $role]);
        $token = $actor->createToken('admin-feature-test')->plainTextToken;
        $payload = $this->warungPayload('WRG-DENIED-001', 'owner-denied-001');

        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/warungs', $payload)
            ->assertForbidden();

        $this->assertOperationResponseMatchesOpenApi($response, '/admin/warungs', 'post');
        $this->assertD13ErrorEnvelope($response, 'FORBIDDEN');
        $this->assertSame(1, Warung::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseMissing('warungs', ['kode' => $payload['kode']]);
        $this->assertDatabaseMissing('users', ['username' => $payload['owner']['username']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function warungPayload(string $kode, string $ownerUsername): array
    {
        return [
            'kode' => $kode,
            'nama' => 'Warung Uji Admin',
            'timezone' => 'Asia/Jakarta',
            'alamat' => null,
            'telepon' => null,
            'tanggal_mulai' => null,
            'tanggal_berakhir' => null,
            'owner' => [
                'nama' => 'Owner Uji',
                'username' => $ownerUsername,
                'email' => null,
                'password' => 'owner-test-password',
            ],
        ];
    }

    private function assertD13ErrorEnvelope(TestResponse $response, string $expectedCode): void
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
    }
}
