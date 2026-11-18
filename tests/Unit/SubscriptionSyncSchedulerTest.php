<?php

namespace Tests\Unit;

use App\Actions\Udev\SubscriptionInvoiceRegistry;
use App\Actions\Udev\SubscriptionReconcileService;
use App\Actions\Udev\UdevBillingClient;
use App\Actions\Udev\UdevBillingRequestException;
use App\Actions\Udev\UdevSyncLimiter;
use App\Console\Commands\RecoverSubscriptionInvoicesCommand;
use App\Console\Commands\SyncSubscriptionInvoicesCommand;
use App\Http\Controllers\Api\V1\SubscriptionBillingController;
use App\Models\User;
use App\Models\Warung;
use App\Models\WarungCustomerSync;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionSyncSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.udev.base_url' => 'https://apiudev.example.test',
            'services.udev.token' => 'test-integration-token',
        ]);
    }

    public function test_registry_lifecycle_and_summary(): void
    {
        $registry = new SubscriptionInvoiceRegistry;

        // 1. Registrasi
        $item = $registry->register('INV-REG-1', 10, 'CUST-10', 'pending');
        $this->assertSame('INV-REG-1', $item['invoice_number']);
        $this->assertSame('pending', $item['status']);
        $this->assertFalse($item['reconciled']);

        // 2. Ambil invoice aktif
        $actives = $registry->getActiveInvoices();
        $this->assertCount(1, $actives);
        $this->assertSame('INV-REG-1', $actives[0]['invoice_number']);

        // 3. Update status dengan backoff
        $updated = $registry->updateStatus('INV-REG-1', 'pending', 'Retry wait', true);
        $this->assertSame(1, $updated['retry_count']);
        $this->assertSame('Retry wait', $updated['last_error']);

        // 4. Mark reconciled
        $reconciled = $registry->markReconciled('INV-REG-1');
        $this->assertTrue($reconciled['reconciled']);
        $this->assertSame('paid', $reconciled['status']);

        // Invoice harus keluar dari daftar aktif
        $this->assertEmpty($registry->getActiveInvoices());

        // 5. Summary
        $summary = $registry->getSummary();
        $this->assertSame(0, $summary['total_active']);
        $this->assertSame(1, $summary['recent_completed_count']);
    }

    public function test_rate_limiter_enforces_10_requests_per_minute_and_priority(): void
    {
        $limiter = new UdevSyncLimiter;
        $limiter->reset();

        // 4 request pertama: semua purpose diizinkan
        for ($i = 0; $i < 4; $i++) {
            $this->assertTrue($limiter->canExecute('recovery', false));
            $this->assertTrue($limiter->canExecute('scheduler', false));
            $limiter->recordRequest();
        }

        // Catat request ke-5
        $limiter->recordRequest();
        $this->assertSame(5, $limiter->getCurrentMinuteCount());

        // Sekarang count = 5. Recovery ditolak karena threshold recovery < 5
        $this->assertFalse($limiter->canExecute('recovery', false));
        // Namun scheduler pending check masih diizinkan (threshold < 7)
        $this->assertTrue($limiter->canExecute('scheduler', false));

        // Tambah 2 request hingga count = 7
        $limiter->recordRequest();
        $limiter->recordRequest();
        $this->assertSame(7, $limiter->getCurrentMinuteCount());

        // Sekarang sudah 7 request:
        // Pending check ditolak (threshold < 7, sisa cadangan 3 untuk paid reconcile)
        $this->assertFalse($limiter->canExecute('scheduler', false));
        // High-priority (paid reconcile) masih diizinkan hingga 10 request
        $this->assertTrue($limiter->canExecute('scheduler', true));

        // Tambah 3 request hingga count = 10 (kapasitas penuh)
        for ($i = 7; $i < 10; $i++) {
            $limiter->recordRequest();
        }
        $this->assertSame(10, $limiter->getCurrentMinuteCount());

        // Total 10 request: semua diblokir
        $this->assertFalse($limiter->canExecute('scheduler', true));
        $this->assertFalse($limiter->canExecute('manual', true));
    }

    public function test_circuit_breaker_trips_after_5_consecutive_technical_failures_and_cooldown(): void
    {
        $limiter = new UdevSyncLimiter;
        $limiter->reset();

        $this->assertSame('CLOSED', $limiter->getState()['circuit_breaker']['state']);

        $techException = new Exception('Network Connection Timeout');

        // 4 kegagalan: circuit tetap CLOSED
        for ($i = 0; $i < 4; $i++) {
            $limiter->recordFailure($techException);
            $this->assertSame('CLOSED', $limiter->getState()['circuit_breaker']['state']);
        }

        // Kegagalan ke-5: circuit menjadi OPEN
        $limiter->recordFailure($techException);
        $state = $limiter->getState()['circuit_breaker'];
        $this->assertSame('OPEN', $state['state']);
        $this->assertGreaterThan(115, $state['cooldown_remaining_seconds']);

        // Saat OPEN, proses otomatis diblokir
        $this->assertFalse($limiter->canExecute('scheduler', true));
        $this->assertFalse($limiter->canExecute('recovery', false));

        // Setelah cooldown 2 menit (120s), beralih ke HALF_OPEN
        Cache::put(UdevSyncLimiter::CB_COOLDOWN_KEY, CarbonImmutable::now()->timestamp - 1, 86400);

        // HALF-OPEN mengizinkan 1 probe
        $this->assertTrue($limiter->canExecute('scheduler', false));

        // Jika probe sukses, circuit kembali CLOSED
        $limiter->recordSuccess();
        $this->assertSame('CLOSED', $limiter->getState()['circuit_breaker']['state']);
        $this->assertSame(0, $limiter->getState()['circuit_breaker']['consecutive_failures']);
    }

    public function test_circuit_breaker_reopens_if_half_open_probe_fails(): void
    {
        $limiter = new UdevSyncLimiter;
        $limiter->reset();

        // Buka circuit
        Cache::put(UdevSyncLimiter::CB_STATE_KEY, UdevSyncLimiter::STATE_OPEN, 86400);
        Cache::put(UdevSyncLimiter::CB_COOLDOWN_KEY, CarbonImmutable::now()->timestamp - 1, 86400);

        // State berubah ke HALF_OPEN saat dievaluasi
        $this->assertTrue($limiter->canExecute('scheduler', false));
        $this->assertSame('HALF_OPEN', $limiter->getState()['circuit_breaker']['state']);

        // Probe gagal
        $limiter->recordFailure(new Exception('Upstream 504 Gateway Timeout'));

        // Harus kembali OPEN dengan cooldown 2 menit baru
        $this->assertSame('OPEN', $limiter->getState()['circuit_breaker']['state']);
        $this->assertGreaterThan(110, $limiter->getState()['circuit_breaker']['cooldown_remaining_seconds']);
    }

    public function test_circuit_breaker_respects_http_429_retry_after(): void
    {
        $limiter = new UdevSyncLimiter;
        $limiter->reset();

        $exception429 = new UdevBillingRequestException(
            upstreamStatus: 429,
            retryAfter: '45',
            upstreamRequestId: 'req-429-test',
        );

        $limiter->recordFailure($exception429);

        $state = $limiter->getState()['circuit_breaker'];
        $this->assertSame('OPEN', $state['state']);
        $this->assertGreaterThan(40, $state['cooldown_remaining_seconds']);
        $this->assertLessThanOrEqual(45, $state['cooldown_remaining_seconds']);
    }

    public function test_reconcile_service_protects_existing_expiry_and_is_idempotent(): void
    {
        $client = new UdevBillingClient;
        $registry = new SubscriptionInvoiceRegistry;
        $service = new SubscriptionReconcileService($client, $registry);

        Http::fake([
            'https://apiudev.example.test/api/v1/integrations/customers/CUST-MOCK-1/subscription' => Http::response([
                'data' => [
                    'subscription' => [
                        'status' => 'active',
                        'starts_at' => '2026-11-01T00:00:00Z',
                        'expires_at' => '2026-12-01T23:59:59Z',
                    ],
                ],
            ], 200),
        ]);

        Warung::query()->forceCreate([
            'id' => 999,
            'kode' => 'W-999',
            'nama' => 'Warung Uji Proteksi',
            'timezone' => 'Asia/Jakarta',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_berakhir' => '2027-01-01', // Masa aktif lama lebih panjang dari UDEV
            'aktif' => true,
            'pendaftaran_disetujui' => true,
        ]);

        $result = $service->reconcileWarung(999, 'CUST-MOCK-1', 'INV-MOCK-999');

        $this->assertTrue($result['success']);
        // Masa aktif tidak boleh berkurang ke 2026-12-01, harus tetap 2027-01-01
        $this->assertSame('2027-01-01', $result['warung']['tanggal_berakhir']);

        // Panggilan kedua harus idempotent (updated = false)
        $result2 = $service->reconcileWarung(999, 'CUST-MOCK-1', 'INV-MOCK-999');
        $this->assertTrue($result2['success']);
        $this->assertFalse($result2['updated']);
        $this->assertSame('2027-01-01', $result2['warung']['tanggal_berakhir']);
    }

    public function test_sync_command_synchronizes_settled_payment_when_browser_closed(): void
    {
        $client = new UdevBillingClient;
        $registry = new SubscriptionInvoiceRegistry;
        $service = new SubscriptionReconcileService($client, $registry);
        $limiter = new UdevSyncLimiter;
        $limiter->reset();

        $registry->register('INV-SETTLED-1', 998, 'CUST-SETTLED-1', 'pending');

        Warung::query()->forceCreate([
            'id' => 998,
            'kode' => 'W-998',
            'nama' => 'Warung Browser Closed',
            'timezone' => 'Asia/Jakarta',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_berakhir' => '2026-11-30',
            'aktif' => true,
            'pendaftaran_disetujui' => true,
        ]);

        Http::fake([
            'https://apiudev.example.test/api/v1/integrations/invoices/INV-SETTLED-1?reconcile=1' => Http::response([
                'data' => [
                    'invoice_number' => 'INV-SETTLED-1',
                    'status' => 'paid',
                ],
            ], 200),
            'https://apiudev.example.test/api/v1/integrations/customers/CUST-SETTLED-1/subscription' => Http::response([
                'data' => [
                    'subscription' => [
                        'status' => 'active',
                        'starts_at' => '2026-11-01T00:00:00Z',
                        'expires_at' => '2026-12-30T23:59:59Z',
                    ],
                ],
            ], 200),
        ]);

        $command = new SyncSubscriptionInvoicesCommand;
        $exitCode = $command->handle($client, $registry, $service, $limiter);

        $this->assertSame(0, $exitCode);
        $this->assertEmpty($registry->getActiveInvoices());

        $invoiceRecord = $registry->getInvoice('INV-SETTLED-1');
        $this->assertTrue($invoiceRecord['reconciled']);
        $this->assertSame('paid', $invoiceRecord['status']);

        $updatedWarung = Warung::query()->find(998);
        $this->assertSame('2026-12-31', $updatedWarung->tanggal_berakhir?->toDateString());
        $this->assertTrue((bool) $updatedWarung->aktif);
    }

    public function test_sync_command_marks_expired_invoices(): void
    {
        $client = new UdevBillingClient;
        $registry = new SubscriptionInvoiceRegistry;
        $service = new SubscriptionReconcileService($client, $registry);
        $limiter = new UdevSyncLimiter;
        $limiter->reset();

        $registry->register('INV-EXPIRED-1', 997, 'CUST-EXPIRED-1', 'pending');

        Http::fake([
            'https://apiudev.example.test/api/v1/integrations/invoices/INV-EXPIRED-1?reconcile=1' => Http::response([
                'data' => [
                    'invoice_number' => 'INV-EXPIRED-1',
                    'status' => 'pending',
                    'payment' => [
                        'checkout_expires_at' => '2026-11-10T12:00:00Z',
                    ],
                    'due_at' => '2026-11-10T12:00:00Z',
                ],
            ], 200),
        ]);

        $command = new SyncSubscriptionInvoicesCommand;
        $exitCode = $command->handle($client, $registry, $service, $limiter);

        $this->assertSame(0, $exitCode);
        $this->assertEmpty($registry->getActiveInvoices());

        $record = $registry->getInvoice('INV-EXPIRED-1');
        $this->assertSame('expired', $record['status']);
    }

    public function test_recovery_command_recovers_missing_invoices_and_respects_checkpoint(): void
    {
        $client = new UdevBillingClient;
        $registry = new SubscriptionInvoiceRegistry;
        $service = new SubscriptionReconcileService($client, $registry);
        $limiter = new UdevSyncLimiter;
        $limiter->reset();

        Warung::query()->forceCreate([
            'id' => 996,
            'kode' => 'W-996',
            'nama' => 'Warung Recovery',
            'timezone' => 'Asia/Jakarta',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_berakhir' => '2026-11-30',
            'aktif' => true,
            'pendaftaran_disetujui' => true,
        ]);

        $owner = User::query()->forceCreate([
            'id' => 996,
            'nama' => 'Owner Recovery',
            'username' => 'owner_recovery_996',
            'password' => 'secret123',
            'role' => 'owner',
            'warung_id' => 996,
            'aktif' => true,
        ]);

        WarungCustomerSync::query()->forceCreate([
            'id' => 10,
            'warung_id' => 996,
            'owner_user_id' => $owner->id,
            'external_customer_id' => 'CUST-RECOVER-1',
            'status' => 'synced',
            'notice_version' => '2026-10-25.v2',
            'consented_at' => CarbonImmutable::now(),
        ]);

        Http::fake([
            'https://apiudev.example.test/api/v1/integrations/customers/CUST-RECOVER-1/transactions?page=1&per_page=100' => Http::response([
                'data' => [
                    [
                        'invoice_number' => 'INV-RECOVER-PAID-1',
                        'status' => 'paid',
                        'created_at' => '2026-11-17T10:00:00Z',
                    ],
                    [
                        'invoice_number' => 'INV-RECOVER-PENDING-1',
                        'status' => 'pending',
                        'due_at' => CarbonImmutable::now()->addDays(2)->toIso8601String(),
                        'created_at' => '2026-11-17T11:00:00Z',
                    ],
                ],
            ], 200),
            'https://apiudev.example.test/api/v1/integrations/customers/CUST-RECOVER-1/subscription' => Http::response([
                'data' => [
                    'subscription' => [
                        'status' => 'active',
                        'starts_at' => '2026-11-01T00:00:00Z',
                        'expires_at' => '2027-02-01T23:59:59Z',
                    ],
                ],
            ], 200),
        ]);

        $command = new RecoverSubscriptionInvoicesCommand;
        $exitCode = $command->handle($client, $registry, $service, $limiter);

        $this->assertSame(0, $exitCode);

        // Checkpoint ID harus tercatat
        $this->assertSame(10, (int) Cache::get(RecoverSubscriptionInvoicesCommand::CHECKPOINT_KEY));

        // Invoice pending harus masuk ke active registry
        $activeInvoices = $registry->getActiveInvoices();
        $this->assertCount(1, $activeInvoices);
        $this->assertSame('INV-RECOVER-PENDING-1', $activeInvoices[0]['invoice_number']);

        // Warung harus sudah reconciled
        $warung = Warung::query()->find(996);
        $this->assertSame('2027-02-02', $warung->tanggal_berakhir?->toDateString());
    }

    public function test_superadmin_monitoring_and_manual_resync_access_control(): void
    {
        $client = new UdevBillingClient;
        $registry = new SubscriptionInvoiceRegistry;
        $service = new SubscriptionReconcileService($client, $registry);
        $limiter = new UdevSyncLimiter;
        $controller = new SubscriptionBillingController;

        // User biasa (owner) tidak boleh akses
        $ownerUser = new User;
        $ownerUser->id = 1;
        $ownerUser->role = 'owner';
        $ownerUser->warung_id = 995;

        $requestOwner = Request::create('/api/v1/subscription/monitoring', 'GET');
        $requestOwner->setUserResolver(fn () => $ownerUser);

        $responseOwner = $controller->monitoring($requestOwner, $registry, $limiter, $service);
        $this->assertSame(403, $responseOwner->getStatusCode());

        // Superadmin boleh akses
        $superadminUser = new User;
        $superadminUser->id = 99;
        $superadminUser->role = 'superadmin';

        $requestSuperadmin = Request::create('/api/v1/subscription/monitoring', 'GET');
        $requestSuperadmin->setUserResolver(fn () => $superadminUser);

        $responseSuperadmin = $controller->monitoring($requestSuperadmin, $registry, $limiter, $service);
        $this->assertSame(200, $responseSuperadmin->getStatusCode());
        $data = $responseSuperadmin->getData(true)['data'];
        $this->assertArrayHasKey('registry', $data);
        $this->assertArrayHasKey('limiter', $data);
        $this->assertArrayHasKey('recent_logs', $data);
    }
}
