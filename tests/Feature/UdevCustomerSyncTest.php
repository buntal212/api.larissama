<?php

namespace Tests\Feature;

use App\Actions\Udev\SyncCustomerJob;
use App\Actions\Udev\UdevCustomerClient;
use App\Models\User;
use App\Models\WarungCustomerSync;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UdevCustomerSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_consent_and_queues_one_stable_customer_id(): void
    {
        Queue::fake();
        $payload = $this->registrationPayload();
        $withoutConsent = $payload;
        $withoutConsent['data_consent'] = false;
        $this->postJson('/api/v1/auth/register', $withoutConsent)->assertUnprocessable();
        $this->assertDatabaseCount('warungs', 0);

        $this->postJson('/api/v1/auth/register', $payload)->assertCreated();
        $sync = WarungCustomerSync::query()->firstOrFail();
        $this->assertSame('pending', $sync->status);
        $this->assertSame('2026-10-24.v1', $sync->notice_version);
        $this->assertNotNull($sync->consented_at);
        $this->assertSame($sync->warung_id, $sync->owner->warung_id);
        Queue::assertPushed(SyncCustomerJob::class, 1);

        config()->set('services.udev.base_url', 'https://sandbox.udev.example');
        config()->set('services.udev.token', 'test-token');
        Http::fake([
            'sandbox.udev.example/*' => Http::response([
                'data' => ['id' => 45, 'external_customer_id' => $sync->external_customer_id],
            ], 201),
        ]);
        (new SyncCustomerJob($sync->id))->handle(app(UdevCustomerClient::class));
        $this->assertSame('synced', $sync->fresh()->status);
        $this->assertSame(45, $sync->fresh()->udev_customer_id);
        Http::assertSent(function ($request) use ($sync): bool {
            $body = $request->data();

            return $request->url() === 'https://sandbox.udev.example/api/v1/integrations/customers'
                && $body['external_customer_id'] === $sync->external_customer_id
                && $body['name'] === 'Warung Pendaftaran'
                && $body['owner_name'] === 'Pemilik Baru'
                && $body['email'] === 'pemilik@example.com'
                && $body['phone'] === '081234567890'
                && $body['status'] === 'active'
                && ! array_key_exists('password', $body)
                && ! array_key_exists('application_id', $body);
        });
    }

    public function test_database_worker_sends_registration_after_local_commit(): void
    {
        config()->set('queue.default', 'database');
        config()->set('services.udev.base_url', 'https://sandbox.udev.example');
        config()->set('services.udev.token', 'test-token');
        Http::fake(function ($request) {
            return Http::response([
                'data' => ['id' => 99, 'external_customer_id' => $request['external_customer_id']],
            ], 201);
        });

        $this->postJson('/api/v1/auth/register', $this->registrationPayload())->assertCreated();
        $sync = WarungCustomerSync::query()->firstOrFail();
        $this->assertSame('pending', $sync->status);
        $this->assertDatabaseHas('jobs', ['queue' => 'udev-customers']);
        $this->assertSame(0, Http::recorded()->count());

        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'udev-customers']);
        $this->assertSame('synced', $sync->fresh()->status);
        $this->assertDatabaseCount('jobs', 0);
        Http::assertSentCount(1);
    }

    public function test_transient_failure_retries_with_same_id(): void
    {
        config()->set('services.udev.base_url', 'https://sandbox.udev.example');
        config()->set('services.udev.token', 'test-token');
        $sync = WarungCustomerSync::factory()->create();
        Http::fakeSequence()->push(['error_code' => 'temporarily_unavailable'], 503)->push([
            'data' => ['id' => 45, 'external_customer_id' => $sync->external_customer_id],
        ], 200);

        $job = new SyncCustomerJob($sync->id);
        $job->handle(app(UdevCustomerClient::class));
        $this->assertSame('retry_wait', $sync->fresh()->status);
        $this->assertSame(1, $sync->fresh()->attempt_count);
        $this->travel(61)->seconds();
        $job->handle(app(UdevCustomerClient::class));
        $this->assertSame('synced', $sync->fresh()->status);
        $this->assertSame(2, $sync->fresh()->attempt_count);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request['external_customer_id'] === $sync->external_customer_id);
    }

    public function test_only_superadmin_can_see_failures_and_manual_retry_is_audited(): void
    {
        Queue::fake();
        $sync = WarungCustomerSync::factory()->create();
        $sync->forceFill(['status' => 'failed', 'last_error_code' => 'manual_customer_conflict'])->save();
        $ownerToken = $sync->owner->createToken('owner')->plainTextToken;
        $admin = User::factory()->superadmin()->create();
        $adminToken = $admin->createToken('admin')->plainTextToken;
        $path = '/api/v1/admin/warungs/'.$sync->warung_id.'/udev-customer-sync';

        $this->withToken($ownerToken)->getJson($path)->assertForbidden();
        $this->withToken($ownerToken)->postJson($path.'/retry')->assertForbidden();
        Auth::forgetGuards();
        $detail = $this->withToken($adminToken)->getJson($path)->assertOk()->assertJsonPath('data.last_error_code', 'manual_customer_conflict');
        $this->assertOperationResponseMatchesOpenApi($detail, '/admin/warungs/{id}/udev-customer-sync', 'get');
        $retry = $this->withToken($adminToken)->postJson($path.'/retry')->assertStatus(202);
        $this->assertOperationResponseMatchesOpenApi($retry, '/admin/warungs/{id}/udev-customer-sync/retry', 'post');
        $this->assertSame('pending', $sync->fresh()->status);
        $this->assertDatabaseHas('warung_customer_sync_events', [
            'warung_customer_sync_id' => $sync->id,
            'actor_user_id' => $admin->id,
            'event' => 'manual_retry',
        ]);
        Queue::assertPushed(SyncCustomerJob::class, 1);
    }

    public function test_recovery_command_requeues_pending_sync_and_owner_fk_rejects_other_tenant(): void
    {
        Queue::fake();
        config()->set('services.udev.base_url', 'https://sandbox.udev.example');
        config()->set('services.udev.token', 'test-token');
        $sync = WarungCustomerSync::factory()->create();
        Artisan::call('udev:dispatch-pending-customers');
        Queue::assertPushed(SyncCustomerJob::class, 1);

        $other = WarungCustomerSync::factory()->create();
        $this->expectException(QueryException::class);
        $sync->forceFill(['owner_user_id' => $other->owner_user_id])->save();
    }

    public function test_auth_error_is_not_retried_and_rate_limit_honors_retry_after(): void
    {
        config()->set('services.udev.base_url', 'https://sandbox.udev.example');
        config()->set('services.udev.token', 'test-token');
        $sync = WarungCustomerSync::factory()->create();
        Http::fakeSequence()->push(['error_code' => 'rate_limited'], 429, ['Retry-After' => '120'])
            ->push(['error_code' => 'invalid_token'], 401);

        $job = new SyncCustomerJob($sync->id);
        $job->handle(app(UdevCustomerClient::class));
        $this->assertSame('retry_wait', $sync->fresh()->status);
        $this->assertTrue($sync->fresh()->next_attempt_at->greaterThanOrEqualTo(now()->addSeconds(119)));
        $this->travel(121)->seconds();
        $job->handle(app(UdevCustomerClient::class));
        $this->assertSame('failed', $sync->fresh()->status);
        $this->assertSame('invalid_token', $sync->fresh()->last_error_code);
        $this->assertSame(2, $sync->fresh()->attempt_count);
    }

    private function registrationPayload(): array
    {
        return [
            'nama' => 'Warung Pendaftaran',
            'timezone' => 'Asia/Jakarta',
            'telepon' => '081234567890',
            'data_consent' => true,
            'data_notice_version' => '2026-10-24.v1',
            'owner' => [
                'nama' => 'Pemilik Baru',
                'username' => 'pemilik_baru_udev',
                'email' => 'pemilik@example.com',
                'password' => 'password-daftar',
            ],
        ];
    }
}
