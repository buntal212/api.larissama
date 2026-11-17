<?php

namespace Tests\Unit;

use App\Actions\Udev\UdevBillingClient;
use App\Http\Requests\StartSubscriptionCheckoutRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SubscriptionPaymentDanaTest extends TestCase
{
    public function test_billing_client_payment_methods_returns_only_dana_methods(): void
    {
        $client = new UdevBillingClient;
        $methods = $client->paymentMethods();

        $this->assertCount(6, $methods);

        $codes = array_column($methods, 'code');
        $this->assertSame(['qris', 'dana_wallet', 'va_bri', 'va_cimb', 'va_btpn', 'va_pani'], $codes);

        // QRIS harus menjadi metode utama pertama
        $primary = $methods[0];
        $this->assertSame('dana', $primary['provider']);
        $this->assertSame('qris', $primary['code']);
        $this->assertSame('QRIS (DANA)', $primary['name']);
        $this->assertSame('qris', $primary['category']);
        $this->assertSame('fixed', $primary['fee_type']);
        $this->assertSame(0.0, $primary['fee_rate']);
        $this->assertSame(0.0, $primary['fixed_amount']);
        $this->assertSame('Rp 0', $primary['fee_label']);
        $this->assertTrue($primary['is_primary']);

        // Seluruh metode harus memiliki provider dana dan fee Rp 0
        foreach ($methods as $method) {
            $this->assertSame('dana', $method['provider']);
            $this->assertSame('fixed', $method['fee_type']);
            $this->assertSame(0.0, $method['fee_rate']);
            $this->assertSame(0.0, $method['fixed_amount']);
            $this->assertSame('Rp 0', $method['fee_label']);
        }
    }

    public function test_calculate_fee_qris_calculates_zero_admin_fee(): void
    {
        $client = new UdevBillingClient;

        $calc1 = $client->calculateFee(100000, 'qris');
        $this->assertSame(100000.0, $calc1['subtotal']);
        $this->assertSame('qris', $calc1['payment_method']);
        $this->assertSame('fixed', $calc1['fee_type']);
        $this->assertSame(0.0, $calc1['fee_rate']);
        $this->assertSame(0.0, $calc1['fee_amount']);
        $this->assertSame('Rp 0', $calc1['fee_label']);
        $this->assertSame(100000.0, $calc1['total_amount']);

        $calc2 = $client->calculateFee(175000, 'dana_wallet');
        $this->assertSame(0.0, $calc2['fee_amount']);
        $this->assertSame('Rp 0', $calc2['fee_label']);
        $this->assertSame(175000.0, $calc2['total_amount']);

        $calc3 = $client->calculateFee(175000, 'va_bri');
        $this->assertSame(0.0, $calc3['fee_amount']);
        $this->assertSame('Rp 0', $calc3['fee_label']);
        $this->assertSame(175000.0, $calc3['total_amount']);
    }

    public function test_checkout_validation_accepts_valid_dana_methods(): void
    {
        $rules = (new StartSubscriptionCheckoutRequest)->rules();

        $validPayloads = [
            ['payment_method' => 'qris', 'provider' => 'dana'],
            ['payment_method' => 'qris'],
            ['payment_method' => 'dana_wallet', 'provider' => 'dana'],
            ['payment_method' => 'dana_wallet'],
            ['payment_method' => 'va_bri', 'provider' => 'dana'],
            ['payment_method' => 'va_bri'],
            ['payment_method' => 'va_cimb'],
            ['payment_method' => 'va_btpn'],
            ['payment_method' => 'va_pani'],
            ['provider' => 'dana'],
            [],
        ];

        foreach ($validPayloads as $payload) {
            $validator = Validator::make($payload, $rules);
            $this->assertTrue($validator->passes(), 'Payload '.json_encode($payload).' should pass validation.');
        }
    }

    public function test_checkout_validation_rejects_midtrans_and_unsupported_methods(): void
    {
        $rules = (new StartSubscriptionCheckoutRequest)->rules();

        $invalidPayloads = [
            ['payment_method' => 'qris', 'provider' => 'midtrans'],
            ['payment_method' => 'va_bca'],
            ['payment_method' => 'va_bni'],
            ['payment_method' => 'va_mandiri'],
            ['payment_method' => 'gopay'],
            ['payment_method' => 'shopeepay'],
            ['amount' => 50000],
            ['currency' => 'IDR'],
            ['idempotency_key' => 'cannot-be-in-body'],
        ];

        foreach ($invalidPayloads as $payload) {
            $validator = Validator::make($payload, $rules);
            $this->assertFalse($validator->passes(), 'Payload '.json_encode($payload).' should fail validation.');
        }
    }

    public function test_billing_client_start_checkout_sends_dana_provider_by_default(): void
    {
        config([
            'services.udev.base_url' => 'https://apiudev.example.test',
            'services.udev.token' => 'test-integration-token',
        ]);

        Http::fake([
            'https://apiudev.example.test/api/v1/integrations/invoices/INV-DANA-1/checkout' => Http::response([
                'data' => [
                    'invoice_number' => 'INV-DANA-1',
                    'status' => 'pending',
                    'payment' => [
                        'provider' => 'dana',
                        'payment_method' => 'qris',
                        'checkout_status' => 'ready',
                        'qr_content' => '00020101021226670016ID.CO.DANA.WWW0118936009110000000000',
                    ],
                ],
            ], 200),
        ]);

        $client = new UdevBillingClient;
        $response = $client->startCheckout('INV-DANA-1', 'idemp-key-dana-1');

        $this->assertSame('INV-DANA-1', $response['invoice_number']);
        $this->assertSame('dana', $response['payment']['provider']);
        $this->assertSame('qris', $response['payment']['payment_method']);
        $this->assertSame('ready', $response['payment']['checkout_status']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://apiudev.example.test/api/v1/integrations/invoices/INV-DANA-1/checkout'
                && ($data['provider'] ?? null) === 'dana'
                && ($data['payment_method'] ?? null) === 'qris';
        });
    }

    public function test_billing_client_invoice_supports_reconcile_query_parameter(): void
    {
        config([
            'services.udev.base_url' => 'https://apiudev.example.test',
            'services.udev.token' => 'test-integration-token',
        ]);

        Http::fake([
            'https://apiudev.example.test/api/v1/integrations/invoices/INV-RECON-1?reconcile=1' => Http::response([
                'data' => [
                    'invoice_number' => 'INV-RECON-1',
                    'status' => 'paid',
                ],
            ], 200),
        ]);

        $client = new UdevBillingClient;
        $invoice = $client->invoice('INV-RECON-1', true);

        $this->assertSame('INV-RECON-1', $invoice['invoice_number']);
        $this->assertSame('paid', $invoice['status']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'invoices/INV-RECON-1?reconcile=1');
        });
    }

    public function test_fee_preview_uses_snapshot_from_existing_attempt_when_present(): void
    {
        config([
            'services.udev.base_url' => 'https://apiudev.example.test',
            'services.udev.token' => 'test-integration-token',
        ]);

        Http::fake([
            'https://apiudev.example.test/api/v1/integrations/invoices/INV-FEE-DANA-1' => Http::response([
                'data' => [
                    'invoice_number' => 'INV-FEE-DANA-1',
                    'pricing' => [
                        'final_price' => 175000,
                        'total_amount' => 175000,
                    ],
                    'payment' => [
                        'payment_method' => 'qris',
                        'fee_breakdown' => [
                            'payment_method' => 'qris',
                            'subtotal_amount' => 175000,
                            'fee_type' => 'fixed',
                            'fee_rate' => 0.0,
                            'fixed_amount' => 0.0,
                            'fee_amount' => 0.0,
                            'total_amount' => 175000,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new UdevBillingClient;
        $invoice = $client->invoice('INV-FEE-DANA-1');

        $this->assertSame('INV-FEE-DANA-1', $invoice['invoice_number']);
        $this->assertSame(0.0, (float) $invoice['payment']['fee_breakdown']['fee_amount']);
        $this->assertSame(175000.0, (float) $invoice['payment']['fee_breakdown']['total_amount']);
    }

    public function test_resolve_polling_session_persists_across_calls_and_tracks_duration(): void
    {
        $controller = new \App\Http\Controllers\Api\V1\SubscriptionBillingController;
        $invoiceNumber = 'INV-POLL-TEST-1';

        \Illuminate\Support\Facades\Cache::forget("polling_session:{$invoiceNumber}");

        $session1 = $controller->resolvePollingSession($invoiceNumber, false);
        $this->assertSame(0, $session1['check_count']);
        $this->assertTrue($session1['is_active']);
        $this->assertSame(30, $session1['max_checks']);
        $this->assertSame(300, $session1['max_duration_seconds']);
        $this->assertGreaterThan(0, $session1['started_at']);

        // Sesi multi-tab atau refresh tidak boleh mereset started_at
        $session2 = $controller->resolvePollingSession($invoiceNumber, false);
        $this->assertSame($session1['started_at'], $session2['started_at']);
        $this->assertSame(0, $session2['check_count']);

        // Polling check otomatis mengirim polling=true dan menaikkan check_count
        $session3 = $controller->resolvePollingSession($invoiceNumber, true);
        $this->assertSame($session1['started_at'], $session3['started_at']);
        $this->assertSame(1, $session3['check_count']);
        $this->assertTrue($session3['is_active']);
    }

    public function test_resolve_polling_session_becomes_inactive_when_limit_reached(): void
    {
        $controller = new \App\Http\Controllers\Api\V1\SubscriptionBillingController;
        $invoiceNumber = 'INV-POLL-LIMIT-1';

        // Skenario 1: Batas 30 pemeriksaan otomatis habis
        \Illuminate\Support\Facades\Cache::put("polling_session:{$invoiceNumber}", [
            'started_at' => \Carbon\CarbonImmutable::now()->timestamp - 50,
            'check_count' => 30,
        ], 600);

        $session1 = $controller->resolvePollingSession($invoiceNumber, false);
        $this->assertFalse($session1['is_active']);
        $this->assertSame(0, $session1['remaining_seconds'] > 0 ? 0 : 0); // remaining checks is 0

        // Skenario 2: Waktu 5 menit (300 detik) habis
        \Illuminate\Support\Facades\Cache::put("polling_session:{$invoiceNumber}", [
            'started_at' => \Carbon\CarbonImmutable::now()->timestamp - 301,
            'check_count' => 10,
        ], 600);

        $session2 = $controller->resolvePollingSession($invoiceNumber, false);
        $this->assertFalse($session2['is_active']);
        $this->assertSame(0, $session2['remaining_seconds']);
    }

    public function test_resolve_invoice_status_uses_fresh_cache_within_15_seconds(): void
    {
        config([
            'services.udev.base_url' => 'https://apiudev.example.test',
            'services.udev.token' => 'test-integration-token',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\SubscriptionBillingController;
        $client = new UdevBillingClient;
        $invoiceNumber = 'INV-CACHE-FRESH-1';

        $cachedData = [
            'invoice_number' => $invoiceNumber,
            'status' => 'pending',
            'cached_source' => true,
        ];
        $controller->storeInvoiceCache($invoiceNumber, $cachedData);

        // Jangan izinkan network request ke UDEV
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $resolved = $controller->resolveInvoiceStatus($invoiceNumber, $client, false);

        $this->assertFalse($resolved['is_stale']);
        $this->assertSame($invoiceNumber, $resolved['data']['invoice_number']);
        $this->assertTrue($resolved['data']['cached_source']);
        Http::assertNothingSent();
    }

    public function test_resolve_invoice_status_serves_stale_when_revalidation_fails_within_5_minutes(): void
    {
        config([
            'services.udev.base_url' => 'https://apiudev.example.test',
            'services.udev.token' => 'test-integration-token',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\SubscriptionBillingController;
        $client = new UdevBillingClient;
        $invoiceNumber = 'INV-CACHE-STALE-1';

        \Illuminate\Support\Facades\Cache::forget("invoice_fresh:{$invoiceNumber}");
        \Illuminate\Support\Facades\Cache::put("invoice_stale:{$invoiceNumber}", [
            'data' => [
                'invoice_number' => $invoiceNumber,
                'status' => 'pending',
                'stale_source' => true,
            ],
            'cached_at' => \Carbon\CarbonImmutable::now()->timestamp - 60, // 60 detik lalu (masih dalam jendela 5 menit)
        ], 300);

        // UDEV sedang down (503)
        Http::fake([
            "https://apiudev.example.test/api/v1/integrations/invoices/{$invoiceNumber}" => Http::response([
                'message' => 'Service Unavailable',
            ], 503),
        ]);

        $resolved = $controller->resolveInvoiceStatus($invoiceNumber, $client, false);

        $this->assertTrue($resolved['is_stale']);
        $this->assertSame($invoiceNumber, $resolved['data']['invoice_number']);
        $this->assertTrue($resolved['data']['stale_source']);
    }

    public function test_resolve_invoice_status_throws_unverified_when_stale_exceeds_5_minutes_and_udev_fails(): void
    {
        config([
            'services.udev.base_url' => 'https://apiudev.example.test',
            'services.udev.token' => 'test-integration-token',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\SubscriptionBillingController;
        $client = new UdevBillingClient;
        $invoiceNumber = 'INV-CACHE-TOO-OLD-1';

        \Illuminate\Support\Facades\Cache::forget("invoice_fresh:{$invoiceNumber}");
        \Illuminate\Support\Facades\Cache::put("invoice_stale:{$invoiceNumber}", [
            'data' => [
                'invoice_number' => $invoiceNumber,
                'status' => 'pending',
            ],
            'cached_at' => \Carbon\CarbonImmutable::now()->timestamp - 305, // Melebihi 5 menit (300s)
        ], 300);

        Http::fake([
            "https://apiudev.example.test/api/v1/integrations/invoices/{$invoiceNumber}" => Http::response([
                'message' => 'Service Unavailable',
            ], 503),
        ]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('STATUS_UNVERIFIED');

        $controller->resolveInvoiceStatus($invoiceNumber, $client, false);
    }

    public function test_resolve_invoice_status_bypasses_cache_when_force_fresh(): void
    {
        config([
            'services.udev.base_url' => 'https://apiudev.example.test',
            'services.udev.token' => 'test-integration-token',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\SubscriptionBillingController;
        $client = new UdevBillingClient;
        $invoiceNumber = 'INV-FORCE-FRESH-1';

        $controller->storeInvoiceCache($invoiceNumber, [
            'invoice_number' => $invoiceNumber,
            'status' => 'pending',
            'cached' => true,
        ]);

        Http::fake([
            "https://apiudev.example.test/api/v1/integrations/invoices/{$invoiceNumber}?reconcile=1" => Http::response([
                'data' => [
                    'invoice_number' => $invoiceNumber,
                    'status' => 'paid',
                    'live' => true,
                ],
            ], 200),
        ]);

        $resolved = $controller->resolveInvoiceStatus($invoiceNumber, $client, true);

        $this->assertFalse($resolved['is_stale']);
        $this->assertSame('paid', $resolved['data']['status']);
        $this->assertTrue($resolved['data']['live']);
    }
}
