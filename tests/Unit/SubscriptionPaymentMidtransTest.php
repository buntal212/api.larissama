<?php

namespace Tests\Unit;

use App\Actions\Udev\UdevBillingClient;
use App\Http\Requests\StartSubscriptionCheckoutRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SubscriptionPaymentMidtransTest extends TestCase
{
    public function test_billing_client_payment_methods_returns_only_midtrans_methods(): void
    {
        $client = new UdevBillingClient;
        $methods = $client->paymentMethods();

        $this->assertCount(7, $methods);

        $codes = array_column($methods, 'code');
        $this->assertSame([
            'qris',
            'va_bca',
            'va_bni',
            'va_bri',
            'va_mandiri',
            'va_permata',
            'gopay',
        ], $codes);

        foreach ($methods as $method) {
            $this->assertSame('midtrans', $method['provider']);
            $this->assertArrayHasKey('name', $method);
            $this->assertArrayHasKey('category', $method);
            $this->assertNotSame('dana', $method['provider']);
            $this->assertNotContains($method['code'], ['dana_wallet', 'va_pani', 'va_cimb', 'va_btpn']);
        }
    }

    public function test_checkout_validation_accepts_valid_midtrans_methods(): void
    {
        $rules = (new StartSubscriptionCheckoutRequest)->rules();

        $validPayloads = [
            ['payment_method' => 'qris', 'provider' => 'midtrans'],
            ['payment_method' => 'va_bca', 'provider' => 'midtrans'],
            ['payment_method' => 'va_bni', 'provider' => 'midtrans'],
            ['payment_method' => 'va_bri', 'provider' => 'midtrans'],
            ['payment_method' => 'va_mandiri', 'provider' => 'midtrans'],
            ['payment_method' => 'va_permata', 'provider' => 'midtrans'],
            ['payment_method' => 'gopay', 'provider' => 'midtrans'],
        ];

        foreach ($validPayloads as $payload) {
            $validator = Validator::make($payload, $rules);
            $this->assertTrue($validator->passes(), 'Payload '.json_encode($payload).' should pass validation.');
        }
    }

    public function test_checkout_validation_rejects_dana_and_unsupported_methods(): void
    {
        $rules = (new StartSubscriptionCheckoutRequest)->rules();

        $invalidPayloads = [
            ['payment_method' => 'dana_wallet'],
            ['payment_method' => 'va_pani'],
            ['payment_method' => 'va_cimb'],
            ['payment_method' => 'va_btpn'],
            ['payment_method' => 'shopeepay'],
            ['payment_method' => 'qris', 'provider' => 'dana'],
            ['payment_method' => 'va_bca', 'provider' => 'xendit'],
        ];

        foreach ($invalidPayloads as $payload) {
            $validator = Validator::make($payload, $rules);
            $this->assertFalse($validator->passes(), 'Payload '.json_encode($payload).' should fail validation.');
        }
    }

    public function test_billing_client_start_checkout_sends_midtrans_provider_and_maps_bank(): void
    {
        config([
            'services.udev.base_url' => 'https://apiudev.example.test',
            'services.udev.token' => 'test-integration-token',
        ]);

        Http::fake([
            'https://apiudev.example.test/api/v1/integrations/invoices/INV-12345/checkout' => Http::response([
                'data' => [
                    'invoice_number' => 'INV-12345',
                    'status' => 'pending',
                    'payment' => [
                        'provider' => 'midtrans',
                        'method' => 'va_bca',
                        'payment_code' => '910128392183',
                    ],
                ],
            ], 200),
        ]);

        $client = new UdevBillingClient;
        $response = $client->startCheckout('INV-12345', 'idemp-key-1', 'va_bca', 'midtrans');

        $this->assertSame('INV-12345', $response['invoice_number']);
        $this->assertSame('midtrans', $response['payment']['provider']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://apiudev.example.test/api/v1/integrations/invoices/INV-12345/checkout'
                && ($data['provider'] ?? null) === 'midtrans'
                && ($data['payment_method'] ?? null) === 'va_bca'
                && ($data['bank'] ?? null) === 'bca';
        });
    }

    public function test_billing_client_start_checkout_with_mandiri_bill_payment(): void
    {
        config([
            'services.udev.base_url' => 'https://apiudev.example.test',
            'services.udev.token' => 'test-integration-token',
        ]);

        Http::fake([
            'https://apiudev.example.test/api/v1/integrations/invoices/INV-99999/checkout' => Http::response([
                'data' => [
                    'invoice_number' => 'INV-99999',
                    'status' => 'pending',
                    'payment' => [
                        'provider' => 'midtrans',
                        'method' => 'va_mandiri',
                        'payment_code' => '888812345678',
                        'biller_code' => '70012',
                    ],
                ],
            ], 200),
        ]);

        $client = new UdevBillingClient;
        $response = $client->startCheckout('INV-99999', 'idemp-key-2', 'va_mandiri', 'midtrans');

        $this->assertSame('INV-99999', $response['invoice_number']);
        $this->assertSame('70012', $response['payment']['biller_code']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://apiudev.example.test/api/v1/integrations/invoices/INV-99999/checkout'
                && ($data['provider'] ?? null) === 'midtrans'
                && ($data['payment_method'] ?? null) === 'va_mandiri'
                && ($data['bank'] ?? null) === 'mandiri';
        });
    }
}
