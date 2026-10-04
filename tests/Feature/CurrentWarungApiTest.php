<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurrentWarungApiTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('tenantRoles')]
    public function test_manager_and_cashier_receive_their_own_warung_profile(string $role): void
    {
        $warung = Warung::factory()->create([
            'kode' => 'WRG-CURRENT-A',
            'nama' => 'Warung Dari Token',
            'alamat' => 'Jalan Tenant A',
            'telepon' => '08123456789',
            'logo' => 'logo/tenant-a.png',
            'timezone' => 'Asia/Jakarta',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2026-12-31',
        ]);
        $otherWarung = Warung::factory()->create([
            'kode' => 'WRG-CURRENT-B',
            'nama' => 'Warung Tenant B',
        ]);
        $actor = User::factory()->create([
            'warung_id' => $warung->id,
            'role' => $role,
        ]);
        $token = $actor->createToken('current-warung-profile-test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/warung?warung_id='.$otherWarung->id)
            ->assertOk();

        $this->assertOperationResponseMatchesOpenApi($response, '/warung', 'get');

        $profile = $response->json('data');
        $this->assertEqualsCanonicalizing(
            ['id', 'kode', 'nama', 'alamat', 'telepon', 'logo', 'timezone', 'tanggal_mulai', 'tanggal_berakhir', 'aktif', 'created_at', 'updated_at'],
            array_keys($profile),
        );
        $this->assertSame((string) $warung->id, $profile['id']);
        $this->assertNotSame((string) $otherWarung->id, $profile['id']);
        $this->assertSame('WRG-CURRENT-A', $profile['kode']);
        $this->assertSame('Warung Dari Token', $profile['nama']);
        $this->assertSame('Jalan Tenant A', $profile['alamat']);
        $this->assertSame('08123456789', $profile['telepon']);
        $this->assertSame('logo/tenant-a.png', $profile['logo']);
        $this->assertSame('Asia/Jakarta', $profile['timezone']);
        $this->assertSame('2026-01-01', $profile['tanggal_mulai']);
        $this->assertSame('2026-12-31', $profile['tanggal_berakhir']);
        $this->assertTrue($profile['aktif']);
        $this->assertSame($warung->created_at->copy()->utc()->toISOString(), $profile['created_at']);
        $this->assertSame($warung->updated_at->copy()->utc()->toISOString(), $profile['updated_at']);
    }

    public function test_superadmin_cannot_use_tenant_current_profile_endpoint(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('current-warung-profile-test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/warung')
            ->assertForbidden();

        $this->assertOperationResponseMatchesOpenApi($response, '/warung', 'get');
        $this->assertD13ErrorEnvelope($response, 'FORBIDDEN');
    }

    public function test_current_profile_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/warung')->assertUnauthorized();

        $this->assertOperationResponseMatchesOpenApi($response, '/warung', 'get');
        $this->assertD13ErrorEnvelope($response, 'UNAUTHENTICATED');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function tenantRoles(): array
    {
        return [
            'manager' => ['manager'],
            'cashier' => ['kasir'],
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
