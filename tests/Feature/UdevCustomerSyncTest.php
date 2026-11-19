<?php

namespace Tests\Feature;

use App\Actions\Udev\EnsureWarungCustomerSync;
use App\Actions\Udev\SyncCustomerJob;
use App\Actions\Udev\UdevCustomerClient;
use App\Models\User;
use App\Models\Warung;
use App\Models\WarungCustomerSync;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
        $this->assertSame('2026-10-25.v2', $sync->notice_version);
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

    public function test_admin_provisioning_warung_automatically_registers_deterministic_udev_sync(): void
    {
        Queue::fake();
        $admin = User::factory()->superadmin()->create();
        $adminToken = $admin->createToken('admin')->plainTextToken;

        $response = $this->withToken($adminToken)->postJson('/api/v1/admin/warungs', [
            'nama' => 'Warung Baru Admin',
            'timezone' => 'Asia/Jakarta',
            'telepon' => '081122334455',
            'alamat' => 'Jl. Sudirman No. 1',
            'owner' => [
                'nama' => 'Owner Admin',
                'username' => 'owner_admin_123',
                'email' => 'owner_admin@example.com',
                'password' => 'password-aman-123',
            ],
        ])->assertCreated();

        $warungId = $response->json('data.warung.id');
        $sync = WarungCustomerSync::query()->where('warung_id', $warungId)->first();
        $this->assertNotNull($sync);
        $this->assertSame('pending', $sync->status);
        $this->assertSame('WARUNG-'.$warungId, $sync->external_customer_id);
        Queue::assertPushed(SyncCustomerJob::class, 1);
    }

    public function test_admin_approving_warung_ensures_deterministic_udev_sync(): void
    {
        Queue::fake();
        $admin = User::factory()->superadmin()->create();
        $adminToken = $admin->createToken('admin')->plainTextToken;
        $warung = Warung::factory()->create([
            'aktif' => false,
            'pendaftaran_disetujui' => false,
            'tanggal_mulai' => null,
            'tanggal_berakhir' => null,
        ]);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);

        $this->withToken($adminToken)->postJson("/api/v1/admin/warungs/{$warung->id}/persetujuan")->assertOk();

        $sync = WarungCustomerSync::query()->where('warung_id', $warung->id)->first();
        $this->assertNotNull($sync);
        $this->assertSame('pending', $sync->status);
        $this->assertSame('WARUNG-'.$warung->id, $sync->external_customer_id);
        $this->assertSame($owner->id, $sync->owner_user_id);
        Queue::assertPushed(SyncCustomerJob::class, 1);
    }

    public function test_manual_retry_allowed_for_both_pending_and_failed(): void
    {
        Queue::fake();
        $admin = User::factory()->superadmin()->create();
        $adminToken = $admin->createToken('admin')->plainTextToken;
        $sync = WarungCustomerSync::factory()->create([
            'status' => 'pending',
            'external_customer_id' => 'larissama-old-ulid',
        ]);

        // Retry on pending
        $this->withToken($adminToken)->postJson("/api/v1/admin/warungs/{$sync->warung_id}/udev-customer-sync/retry")
            ->assertStatus(202);

        $freshSync = $sync->fresh();
        $this->assertSame('pending', $freshSync->status);
        $this->assertSame('larissama-old-ulid', $freshSync->external_customer_id);
        Queue::assertPushed(SyncCustomerJob::class, 1);

        // Retry on failed
        $freshSync->forceFill(['status' => 'failed', 'last_error_code' => 'network_error'])->save();
        $this->withToken($adminToken)->postJson("/api/v1/admin/warungs/{$sync->warung_id}/udev-customer-sync/retry")
            ->assertStatus(202);

        $this->assertSame('pending', $freshSync->fresh()->status);
        $this->assertSame('larissama-old-ulid', $freshSync->fresh()->external_customer_id);
        Queue::assertPushed(SyncCustomerJob::class, 2);
    }

    public function test_bulk_resync_all_reprocesses_unsynced_and_failed_warungs(): void
    {
        Queue::fake();
        $admin = User::factory()->superadmin()->create();
        $adminToken = $admin->createToken('admin')->plainTextToken;

        // Warung 1: unsynced (no sync record)
        $w1 = Warung::factory()->create();
        User::factory()->create(['warung_id' => $w1->id, 'role' => 'owner']);

        // Warung 2: failed sync
        $w2 = Warung::factory()->create();
        $u2 = User::factory()->create(['warung_id' => $w2->id, 'role' => 'owner']);
        $s2 = WarungCustomerSync::query()->create([
            'warung_id' => $w2->id,
            'owner_user_id' => $u2->id,
            'status' => 'failed',
            'external_customer_id' => 'larissama-test',
            'notice_version' => '2026-10-25.v2',
            'consented_at' => now(),
        ]);

        $response = $this->withToken($adminToken)->postJson('/api/v1/admin/warungs/udev-customer-sync/resync-all')
            ->assertOk();

        $this->assertSame(2, $response->json('data.enqueued_count'));
        $this->assertDatabaseHas('warung_customer_syncs', [
            'warung_id' => $w1->id,
            'external_customer_id' => 'WARUNG-'.$w1->id,
            'status' => 'pending',
        ]);
        $this->assertSame('larissama-test', $s2->fresh()->external_customer_id);
        $this->assertSame('pending', $s2->fresh()->status);
        Queue::assertPushed(SyncCustomerJob::class, 2);
    }

    public function test_retry_returns_409_conflict_when_already_synced(): void
    {
        Queue::fake();
        $admin = User::factory()->superadmin()->create();
        $adminToken = $admin->createToken('admin')->plainTextToken;
        $sync = WarungCustomerSync::factory()->create([
            'status' => 'synced',
            'udev_customer_id' => 99,
        ]);

        $response = $this->withToken($adminToken)
            ->postJson("/api/v1/admin/warungs/{$sync->warung_id}/udev-customer-sync/retry")
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT')
            ->assertJsonPath('message', 'Data bertentangan dengan keadaan saat ini.');

        $this->assertOperationResponseMatchesOpenApi($response, '/admin/warungs/{id}/udev-customer-sync/retry', 'post');
        $this->assertSame('synced', $sync->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_retry_returns_409_conflict_when_actively_processing(): void
    {
        Queue::fake();
        $admin = User::factory()->superadmin()->create();
        $adminToken = $admin->createToken('admin')->plainTextToken;
        $sync = WarungCustomerSync::factory()->create([
            'status' => 'processing',
            'last_attempt_at' => now(),
        ]);

        $response = $this->withToken($adminToken)
            ->postJson("/api/v1/admin/warungs/{$sync->warung_id}/udev-customer-sync/retry")
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT')
            ->assertJsonPath('message', 'Data bertentangan dengan keadaan saat ini.');

        $this->assertOperationResponseMatchesOpenApi($response, '/admin/warungs/{id}/udev-customer-sync/retry', 'post');
        $this->assertSame('processing', $sync->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_retry_recovers_stale_processing_sync(): void
    {
        Queue::fake();
        $admin = User::factory()->superadmin()->create();
        $adminToken = $admin->createToken('admin')->plainTextToken;
        $sync = WarungCustomerSync::factory()->create([
            'status' => 'processing',
            'last_attempt_at' => now()->subMinutes(5),
            'external_customer_id' => 'WARUNG-999',
        ]);

        $this->withToken($adminToken)
            ->postJson("/api/v1/admin/warungs/{$sync->warung_id}/udev-customer-sync/retry")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame('pending', $sync->fresh()->status);
        Queue::assertPushed(SyncCustomerJob::class, 1);
    }

    public function test_retry_preserves_external_customer_id_when_already_mapped_in_udev(): void
    {
        Queue::fake();
        $admin = User::factory()->superadmin()->create();
        $adminToken = $admin->createToken('admin')->plainTextToken;
        $sync = WarungCustomerSync::factory()->create([
            'status' => 'failed',
            'external_customer_id' => 'larissama-legacy-99',
            'udev_customer_id' => 88,
        ]);

        $this->withToken($adminToken)
            ->postJson("/api/v1/admin/warungs/{$sync->warung_id}/udev-customer-sync/retry")
            ->assertStatus(202);

        $freshSync = $sync->fresh();
        $this->assertSame('pending', $freshSync->status);
        // Tetap mempertahankan external_customer_id lama agar tidak duplikat di UDEV APP001
        $this->assertSame('larissama-legacy-99', $freshSync->external_customer_id);
        $this->assertSame(88, $freshSync->udev_customer_id);
        Queue::assertPushed(SyncCustomerJob::class, 1);
    }

    public function test_ensure_sync_preserves_external_id_when_udev_customer_id_exists(): void
    {
        Queue::fake();
        $admin = User::factory()->superadmin()->create();
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $sync = WarungCustomerSync::query()->create([
            'warung_id' => $warung->id,
            'owner_user_id' => $owner->id,
            'external_customer_id' => 'larissama-legacy-456',
            'status' => 'failed',
            'notice_version' => '2026-10-25.v2',
            'consented_at' => now(),
        ]);
        $sync->forceFill(['udev_customer_id' => 101])->save();

        $ensure = new EnsureWarungCustomerSync;
        $result = $ensure->execute($warung, $admin);

        $this->assertSame('larissama-legacy-456', $result['sync']->external_customer_id);
        $this->assertSame(101, $result['sync']->udev_customer_id);
        $this->assertSame('pending', $result['sync']->status);
    }

    public function test_dispatch_safely_prevents_duplicate_database_jobs(): void
    {
        config()->set('queue.default', 'database');
        $sync = WarungCustomerSync::factory()->create(['status' => 'pending']);

        // First dispatch succeeds
        $first = SyncCustomerJob::dispatchSafely($sync->id);
        $this->assertTrue($first);
        $this->assertTrue(SyncCustomerJob::isQueued($sync->id));
        $this->assertSame(1, DB::table('jobs')->where('queue', 'udev-customers')->count());

        // Second dispatch for same syncId is skipped
        $second = SyncCustomerJob::dispatchSafely($sync->id);
        $this->assertFalse($second);
        $this->assertSame(1, DB::table('jobs')->where('queue', 'udev-customers')->count());
    }

    public function test_scheduler_does_not_duplicate_already_queued_jobs(): void
    {
        config()->set('queue.default', 'database');
        config()->set('services.udev.base_url', 'https://sandbox.udev.example');
        config()->set('services.udev.token', 'test-token');

        $sync = WarungCustomerSync::factory()->create([
            'status' => 'pending',
            'last_enqueued_at' => now()->subMinutes(10),
        ]);

        // Manually dispatch initial job
        SyncCustomerJob::dispatchSafely($sync->id);
        $this->assertSame(1, DB::table('jobs')->where('queue', 'udev-customers')->count());

        // Running the scheduler should not create duplicate job
        Artisan::call('udev:dispatch-pending-customers');
        $this->assertSame(1, DB::table('jobs')->where('queue', 'udev-customers')->count());
    }

    public function test_audit_queue_command_analyzes_jobs_and_identifies_status(): void
    {
        config()->set('queue.default', 'database');

        $s1 = WarungCustomerSync::factory()->create([
            'status' => 'synced',
            'udev_customer_id' => 10,
        ]);

        $s2 = WarungCustomerSync::factory()->create([
            'status' => 'pending',
        ]);

        // Dispatch jobs directly to simulate accumulated queue
        SyncCustomerJob::dispatch($s1->id)->onQueue('udev-customers');
        SyncCustomerJob::dispatch($s2->id)->onQueue('udev-customers');
        SyncCustomerJob::dispatch($s2->id)->onQueue('udev-customers');

        $this->assertSame(3, DB::table('jobs')->where('queue', 'udev-customers')->count());

        $exitCode = Artisan::call('udev:audit-queue');
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Total Job di Queue: 3', $output);
        $this->assertStringContainsString('Job Valid Dipertahankan: 1', $output);
        $this->assertStringContainsString('Job Duplikat/Usang Dapat Dipangkas: 2', $output);
    }

    public function test_cleanup_queue_command_dry_run_preserves_all_jobs(): void
    {
        config()->set('queue.default', 'database');

        $sync = WarungCustomerSync::factory()->create(['status' => 'pending']);
        SyncCustomerJob::dispatch($sync->id)->onQueue('udev-customers');
        SyncCustomerJob::dispatch($sync->id)->onQueue('udev-customers');

        $this->assertSame(2, DB::table('jobs')->where('queue', 'udev-customers')->count());

        $exitCode = Artisan::call('udev:cleanup-queue', ['--dry-run' => true]);
        $this->assertSame(0, $exitCode);

        // All jobs preserved in dry-run
        $this->assertSame(2, DB::table('jobs')->where('queue', 'udev-customers')->count());
    }

    public function test_cleanup_queue_command_selectively_prunes_duplicates_and_obsolete_creating_backup(): void
    {
        config()->set('queue.default', 'database');

        // Setup 4 synced warungs
        $syncedIds = [];
        for ($i = 1; $i <= 4; $i++) {
            $s = WarungCustomerSync::factory()->create([
                'status' => 'synced',
                'udev_customer_id' => 100 + $i,
            ]);
            $syncedIds[] = $s->id;
            // 1 obsolete job each
            SyncCustomerJob::dispatch($s->id)->onQueue('udev-customers');
        }

        // Setup 2 pending warungs (WARUNG-3 and WARUNG-6 equivalent)
        $pending1 = WarungCustomerSync::factory()->create(['status' => 'pending']);
        $pending2 = WarungCustomerSync::factory()->create(['status' => 'pending']);

        // Dispatch 3 duplicate jobs for pending1 and 3 duplicate jobs for pending2
        for ($i = 0; $i < 3; $i++) {
            SyncCustomerJob::dispatch($pending1->id)->onQueue('udev-customers');
            SyncCustomerJob::dispatch($pending2->id)->onQueue('udev-customers');
        }

        // Total jobs in queue = 4 (synced) + 6 (pending) = 10
        $this->assertSame(10, DB::table('jobs')->where('queue', 'udev-customers')->count());

        $customBackupPath = storage_path('app/queue-backups/test_cleanup_backup_'.uniqid().'.json');

        $exitCode = Artisan::call('udev:cleanup-queue', [
            '--force' => true,
            '--backup-path' => $customBackupPath,
        ]);
        $this->assertSame(0, $exitCode);

        // Verification: Exactly 2 valid jobs preserved (1 for pending1 and 1 for pending2)
        $remainingJobs = DB::table('jobs')->where('queue', 'udev-customers')->get();
        $this->assertCount(2, $remainingJobs);

        // Verification: Backup file created with exactly 8 pruned jobs
        $this->assertFileExists($customBackupPath);
        $backupData = json_decode(File::get($customBackupPath), true);
        $this->assertCount(8, $backupData);

        // Clean up backup file
        @unlink($customBackupPath);

        // Verification: failed_jobs untouched
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    private function registrationPayload(): array
    {
        return [
            'nama' => 'Warung Pendaftaran',
            'timezone' => 'Asia/Jakarta',
            'telepon' => '081234567890',
            'data_consent' => true,
            'data_notice_version' => '2026-10-25.v2',
            'owner' => [
                'nama' => 'Pemilik Baru',
                'username' => 'pemilik_baru_udev',
                'email' => 'pemilik@example.com',
                'password' => 'password-daftar',
            ],
        ];
    }
}
