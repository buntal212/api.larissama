<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WarungRegistrationAndSubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_starts_a_three_local_day_trial_and_owner_can_login(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07T17:30:00Z'));
        $payload = $this->registrationPayload();
        $this->assertOperationRequestMatchesOpenApi($payload, [], '/auth/register', 'post');

        $response = $this->postJson('/api/v1/auth/register', $payload)->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($response, '/auth/register', 'post');

        $warung = Warung::query()->firstOrFail();
        $owner = User::query()->where('username', 'pemilik_baru')->firstOrFail();
        $this->assertSame($warung->id, $owner->warung_id);
        $this->assertSame('owner', $owner->role);
        $this->assertTrue($owner->aktif);
        $this->assertTrue($warung->aktif);
        $this->assertTrue($warung->pendaftaran_disetujui);
        $this->assertSame('2026-10-08', $warung->tanggal_mulai?->toDateString());
        $this->assertSame('2026-10-10', $warung->tanggal_berakhir?->toDateString());
        $this->assertSame('aktif_trial', $response->json('data.status_pendaftaran'));
        $this->assertSame('aktif', $response->json('data.warung.status_langganan'));

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => $owner->username,
            'password' => $payload['owner']['password'],
        ])->assertOk();
        $this->assertOperationResponseMatchesOpenApi($login, '/auth/login', 'post');
        $this->assertSame(1, $owner->tokens()->count());
    }

    public function test_superadmin_approval_activates_a_thirty_day_subscription_using_local_dates(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07T01:00:00Z'));
        $warung = Warung::factory()->create([
            'timezone' => 'Pacific/Honolulu',
            'aktif' => false,
            'tanggal_mulai' => null,
            'tanggal_berakhir' => null,
            'pendaftaran_disetujui' => false,
        ]);
        $token = User::factory()->superadmin()->create()->createToken('approval-test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/warungs/'.$warung->id.'/persetujuan')
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($response, '/admin/warungs/{id}/persetujuan', 'post');

        $this->assertSame('2026-10-06', $response->json('data.tanggal_mulai'));
        $this->assertSame('2026-11-04', $response->json('data.tanggal_berakhir'));
        $this->assertTrue($response->json('data.aktif'));
        $this->assertSame('aktif', $response->json('data.status_langganan'));
        $this->assertTrue($warung->fresh()->allowsAccessAt(CarbonImmutable::parse('2026-11-05T09:59:59Z')));
        $this->assertFalse($warung->fresh()->allowsAccessAt(CarbonImmutable::parse('2026-11-05T10:00:00Z')));
    }

    public function test_superadmin_cannot_approve_a_warung_twice(): void
    {
        $warung = Warung::factory()->create(['aktif' => false, 'pendaftaran_disetujui' => false]);
        $token = User::factory()->superadmin()->create()->createToken('approval-conflict-test')->plainTextToken;
        $this->withToken($token)
            ->postJson('/api/v1/admin/warungs/'.$warung->id.'/persetujuan')
            ->assertOk();

        $conflict = $this->withToken($token)
            ->postJson('/api/v1/admin/warungs/'.$warung->id.'/persetujuan')
            ->assertStatus(409);
        $this->assertOperationResponseMatchesOpenApi($conflict, '/admin/warungs/{id}/persetujuan', 'post');
        $this->assertSame('CONFLICT', $conflict->json('code'));
    }

    public function test_registration_rejects_duplicate_owner_username_without_creating_a_second_warung(): void
    {
        User::factory()->create(['username' => 'pemilik_baru']);
        $payload = $this->registrationPayload();
        $this->assertOperationRequestMatchesOpenApi($payload, [], '/auth/register', 'post');

        $response = $this->postJson('/api/v1/auth/register', $payload)->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($response, '/auth/register', 'post');
        $this->assertSame('VALIDATION_ERROR', $response->json('code'));
        $this->assertArrayHasKey('owner.username', $response->json('errors'));
        $this->assertSame(1, Warung::query()->count());
    }

    public function test_superadmin_extends_a_live_subscription_from_its_existing_end_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07T17:30:00Z'));
        $warung = Warung::factory()->create([
            'timezone' => 'Asia/Jakarta',
            'tanggal_mulai' => '2026-10-01',
            'tanggal_berakhir' => '2026-10-31',
        ]);
        $token = User::factory()->superadmin()->create()->createToken('extension-test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/warungs/'.$warung->id.'/langganan/perpanjangan')
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($response, '/admin/warungs/{id}/langganan/perpanjangan', 'post');

        $this->assertSame('2026-11-30', $response->json('data.tanggal_berakhir'));
        $this->assertSame('aktif', $response->json('data.status_langganan'));
        $this->assertDatabaseHas('warungs', ['id' => $warung->id, 'tanggal_berakhir' => '2026-11-30', 'aktif' => true]);
    }

    public function test_superadmin_restarts_an_expired_subscription_from_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07T17:30:00Z'));
        $warung = Warung::factory()->create([
            'timezone' => 'Asia/Jakarta',
            'tanggal_mulai' => '2026-10-01',
            'tanggal_berakhir' => '2026-10-05',
        ]);
        $token = User::factory()->superadmin()->create()->createToken('expired-extension-test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/warungs/'.$warung->id.'/langganan/perpanjangan')
            ->assertOk();
        $this->assertSame('2026-10-08', $response->json('data.tanggal_mulai'));
        $this->assertSame('2026-11-06', $response->json('data.tanggal_berakhir'));
        $this->assertSame('aktif', $response->json('data.status_langganan'));
    }

    public function test_expired_owner_can_login_and_only_read_subscription_plans_during_grace(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07T17:30:00Z'));
        $warung = Warung::factory()->create([
            'timezone' => 'Asia/Jakarta',
            'tanggal_mulai' => '2026-10-01',
            'tanggal_berakhir' => '2026-10-07',
        ]);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $adminToken = User::factory()->superadmin()->create()->createToken('expired-status-test')->plainTextToken;
        $detail = $this->withToken($adminToken)->getJson('/api/v1/admin/warungs/'.$warung->id)->assertOk();

        $this->assertOperationResponseMatchesOpenApi($detail, '/admin/warungs/{id}', 'get');
        $this->assertSame('kedaluwarsa', $detail->json('data.status_langganan'));

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => $owner->username,
            'password' => 'password',
        ])->assertOk();
        $this->assertOperationResponseMatchesOpenApi($login, '/auth/login', 'post');
        $this->assertSame('kedaluwarsa', $login->json('data.warung.status_langganan'));

        $ownerToken = $login->json('data.access_token');
        Auth::forgetGuards();
        $me = $this->withToken($ownerToken)->getJson('/api/v1/auth/me')->assertOk();
        $this->assertOperationResponseMatchesOpenApi($me, '/auth/me', 'get');
        $this->assertSame('kedaluwarsa', $me->json('data.warung.status_langganan'), json_encode($me->json()));

        $blocked = $this->withToken($ownerToken)->getJson('/api/v1/warung')->assertForbidden();
        $this->assertSame('SUBSCRIPTION_PAYMENT_REQUIRED', $blocked->json('code'));
        $this->assertOperationResponseMatchesOpenApi($blocked, '/warung', 'get');

        config([
            'services.udev.base_url' => 'https://udev.example.test',
            'services.udev.token' => 'test-token',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://udev.example.test/api/v1/integrations/subscription-plans*' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $plans = $this->withToken($ownerToken)->getJson('/api/v1/subscription-plans')->assertOk();
        $this->assertOperationResponseMatchesOpenApi($plans, '/subscription-plans', 'get');
    }

    public function test_subscription_grace_allows_owner_through_day_thirty_and_rejects_day_thirty_one(): void
    {
        $warung = Warung::factory()->create([
            'timezone' => 'Asia/Jakarta',
            'tanggal_mulai' => '2026-10-01',
            'tanggal_berakhir' => '2026-10-07',
        ]);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);

        $this->travelTo(CarbonImmutable::parse('2026-11-05T17:00:00Z'));
        $dayThirty = $this->postJson('/api/v1/auth/login', [
            'username' => $owner->username,
            'password' => 'password',
        ])->assertOk();
        $ownerToken = $dayThirty->json('data.access_token');

        $this->travelTo(CarbonImmutable::parse('2026-11-06T17:00:00Z'));
        $dayThirtyOneLogin = $this->postJson('/api/v1/auth/login', [
            'username' => $owner->username,
            'password' => 'password',
        ])->assertForbidden();
        $this->assertSame('SUBSCRIPTION_GRACE_EXPIRED', $dayThirtyOneLogin->json('code'));
        $this->assertOperationResponseMatchesOpenApi($dayThirtyOneLogin, '/auth/login', 'post');

        $dayThirtyOneRequest = $this->withToken($ownerToken)->getJson('/api/v1/auth/me')->assertForbidden();
        $this->assertSame('SUBSCRIPTION_GRACE_EXPIRED', $dayThirtyOneRequest->json('code'));
        $this->assertOperationResponseMatchesOpenApi($dayThirtyOneRequest, '/auth/me', 'get');
    }

    public function test_manager_cannot_login_during_subscription_payment_grace(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07T17:30:00Z'));
        $warung = Warung::factory()->create([
            'timezone' => 'Asia/Jakarta',
            'tanggal_mulai' => '2026-10-01',
            'tanggal_berakhir' => '2026-10-07',
        ]);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => $manager->username,
            'password' => 'password',
        ])->assertForbidden();

        $this->assertSame('SUBSCRIPTION_PAYMENT_REQUIRED', $login->json('code'));
        $this->assertSame(0, $manager->tokens()->count());
        $this->assertOperationResponseMatchesOpenApi($login, '/auth/login', 'post');
    }

    public function test_tenant_owner_cannot_approve_or_extend_a_warung_subscription(): void
    {
        $warung = Warung::factory()->create(['aktif' => false, 'pendaftaran_disetujui' => false]);
        $actorWarung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $actorWarung->id, 'role' => 'owner']);
        $token = $owner->createToken('tenant-subscription-denial-test')->plainTextToken;

        $approval = $this->withToken($token)
            ->postJson('/api/v1/admin/warungs/'.$warung->id.'/persetujuan')
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($approval, '/admin/warungs/{id}/persetujuan', 'post');

        $extension = $this->withToken($token)
            ->postJson('/api/v1/admin/warungs/'.$warung->id.'/langganan/perpanjangan')
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($extension, '/admin/warungs/{id}/langganan/perpanjangan', 'post');
        $this->assertFalse($warung->fresh()->aktif);
        $this->assertNull($warung->fresh()->tanggal_berakhir);
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationPayload(): array
    {
        return [
            'nama' => 'Warung Pendaftaran',
            'timezone' => 'Asia/Jakarta',
            'alamat' => null,
            'telepon' => null,
            'data_consent' => true,
            'data_notice_version' => '2026-10-25.v2',
            'owner' => [
                'nama' => 'Pemilik Baru',
                'username' => 'pemilik_baru',
                'email' => 'pemilik@example.com',
                'password' => 'password-daftar',
            ],
        ];
    }
}
