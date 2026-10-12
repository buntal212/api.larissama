<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ['id', 'kode', 'nama', 'alamat', 'telepon', 'logo', 'timezone', 'tanggal_mulai', 'tanggal_berakhir', 'aktif', 'status_langganan', 'created_at', 'updated_at'],
            array_keys($profile),
        );
        $this->assertSame((string) $warung->id, $profile['id']);
        $this->assertNotSame((string) $otherWarung->id, $profile['id']);
        $this->assertSame('WRG-CURRENT-A', $profile['kode']);
        $this->assertSame('Warung Dari Token', $profile['nama']);
        $this->assertSame('Jalan Tenant A', $profile['alamat']);
        $this->assertSame('08123456789', $profile['telepon']);
        $this->assertSame(Storage::disk('public')->url('logo/tenant-a.png'), $profile['logo']);
        $this->assertSame('Asia/Jakarta', $profile['timezone']);
        $this->assertSame('2026-01-01', $profile['tanggal_mulai']);
        $this->assertSame('2026-12-31', $profile['tanggal_berakhir']);
        $this->assertTrue($profile['aktif']);
        $this->assertSame('aktif', $profile['status_langganan']);
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

    public function test_owner_updates_only_editable_fields_on_its_own_warung(): void
    {
        $warung = Warung::factory()->create([
            'nama' => 'Nama Lama',
            'alamat' => 'Alamat Lama',
            'telepon' => '0811111111',
            'timezone' => 'Asia/Jakarta',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2026-12-31',
        ]);
        $otherWarung = Warung::factory()->create(['nama' => 'Warung Lain']);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('current-warung-profile-test')->plainTextToken;
        $payload = [
            'nama' => 'Nama Baru',
            'alamat' => null,
            'telepon' => '0822222222',
            'timezone' => 'Asia/Makassar',
        ];

        $this->assertOperationRequestMatchesOpenApi($payload, [], '/warung', 'patch');
        $response = $this->withToken($token)->patchJson('/api/v1/warung', $payload)->assertOk();

        $this->assertOperationResponseMatchesOpenApi($response, '/warung', 'patch');
        $this->assertSame($payload['nama'], $response->json('data.nama'));
        $this->assertNull($response->json('data.alamat'));
        $this->assertSame($payload['telepon'], $response->json('data.telepon'));
        $this->assertSame($payload['timezone'], $response->json('data.timezone'));
        $this->assertSame((string) $warung->id, $response->json('data.id'));
        $this->assertNotSame((string) $otherWarung->id, $response->json('data.id'));
        $this->assertDatabaseHas('warungs', [
            'id' => $warung->id,
            'nama' => 'Nama Baru',
            'alamat' => null,
            'telepon' => '0822222222',
            'timezone' => 'Asia/Makassar',
            'kode' => $warung->kode,
            'aktif' => true,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2026-12-31',
        ]);
    }

    public function test_owner_profile_rejects_admin_fields_and_empty_patch(): void
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('current-warung-profile-test')->plainTextToken;

        $unknownFieldResponse = $this->withToken($token)
            ->patchJson('/api/v1/warung', ['warung_id' => $warung->id, 'aktif' => false])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($unknownFieldResponse, '/warung', 'patch');
        $this->assertD13ErrorEnvelope($unknownFieldResponse, 'VALIDATION_ERROR');
        $this->assertDatabaseHas('warungs', ['id' => $warung->id, 'aktif' => true]);

        $emptyResponse = $this->withToken($token)->patchJson('/api/v1/warung', [])->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($emptyResponse, '/warung', 'patch');
        $this->assertD13ErrorEnvelope($emptyResponse, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('data', $emptyResponse->json('errors'));
    }

    public function test_owner_can_upload_replace_and_delete_its_logo(): void
    {
        Storage::fake('public');
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('current-warung-profile-test')->plainTextToken;
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true);
        $this->assertIsString($png);

        $uploadResponse = $this->withToken($token)
            ->post('/api/v1/warung/logo', ['logo' => UploadedFile::fake()->createWithContent('logo.png', $png)])
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($uploadResponse, '/warung/logo', 'post');
        $uploadedPath = $warung->fresh()->logo;
        $this->assertIsString($uploadedPath);
        $this->assertStringStartsWith('warung-logos/'.$warung->id.'/', $uploadedPath);
        Storage::disk('public')->assertExists($uploadedPath);
        $this->assertSame(Storage::disk('public')->url($uploadedPath), $uploadResponse->json('data.logo'));

        $replaceResponse = $this->withToken($token)
            ->post('/api/v1/warung/logo', ['logo' => UploadedFile::fake()->createWithContent('replacement.png', $png)])
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($replaceResponse, '/warung/logo', 'post');
        $replacementPath = $warung->fresh()->logo;
        $this->assertNotSame($uploadedPath, $replacementPath);
        Storage::disk('public')->assertMissing($uploadedPath);
        Storage::disk('public')->assertExists($replacementPath);

        $deleteResponse = $this->withToken($token)->deleteJson('/api/v1/warung/logo')->assertOk();
        $this->assertOperationResponseMatchesOpenApi($deleteResponse, '/warung/logo', 'delete');
        $this->assertNull($deleteResponse->json('data.logo'));
        $this->assertDatabaseHas('warungs', ['id' => $warung->id, 'logo' => null]);
        Storage::disk('public')->assertMissing($replacementPath);
    }

    public function test_logo_upload_rejects_non_image_and_file_over_one_megabyte(): void
    {
        Storage::fake('public');
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('current-warung-profile-test')->plainTextToken;

        $invalidImageResponse = $this->withToken($token)
            ->post('/api/v1/warung/logo', ['logo' => UploadedFile::fake()->createWithContent('not-image.txt', 'not an image')])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($invalidImageResponse, '/warung/logo', 'post');
        $this->assertD13ErrorEnvelope($invalidImageResponse, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('logo', $invalidImageResponse->json('errors'));

        $oversizedImageResponse = $this->withToken($token)
            ->post('/api/v1/warung/logo', ['logo' => UploadedFile::fake()->createWithContent('large.png', str_repeat('x', 1024 * 1024 + 1))])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($oversizedImageResponse, '/warung/logo', 'post');
        $this->assertD13ErrorEnvelope($oversizedImageResponse, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('logo', $oversizedImageResponse->json('errors'));
        $this->assertDatabaseHas('warungs', ['id' => $warung->id, 'logo' => null]);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_non_owner_cannot_mutate_current_warung_profile_or_logo(): void
    {
        $warung = Warung::factory()->create(['nama' => 'Nama Lama']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('current-warung-profile-test')->plainTextToken;

        $profileResponse = $this->withToken($token)
            ->patchJson('/api/v1/warung', ['nama' => 'Nama Tidak Sah'])
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($profileResponse, '/warung', 'patch');
        $this->assertD13ErrorEnvelope($profileResponse, 'FORBIDDEN');

        $logoResponse = $this->withToken($token)
            ->deleteJson('/api/v1/warung/logo')
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($logoResponse, '/warung/logo', 'delete');
        $this->assertD13ErrorEnvelope($logoResponse, 'FORBIDDEN');
        $this->assertDatabaseHas('warungs', ['id' => $warung->id, 'nama' => 'Nama Lama']);
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
