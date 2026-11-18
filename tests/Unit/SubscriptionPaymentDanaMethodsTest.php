<?php

namespace Tests\Unit;

use App\Actions\Udev\UdevBillingClient;
use App\Actions\Udev\UdevBillingRequestException;
use App\Http\Controllers\Api\V1\SubscriptionBillingController;
use App\Http\Requests\StartSubscriptionCheckoutRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class SubscriptionPaymentDanaMethodsTest extends TestCase
{
    public function test_all_six_dana_methods_pass_request_validation(): void
    {
        $rules = (new StartSubscriptionCheckoutRequest)->rules();

        $validPayloads = [
            ['payment_method' => 'qris', 'provider' => 'dana'],
            ['payment_method' => 'dana_wallet', 'provider' => 'dana'],
            ['payment_method' => 'va_bri', 'provider' => 'dana', 'bank' => 'bri'],
            ['payment_method' => 'va_cimb', 'provider' => 'dana', 'bank' => 'cimb'],
            ['payment_method' => 'va_btpn', 'provider' => 'dana', 'bank' => 'btpn'],
            ['payment_method' => 'va_pani', 'provider' => 'dana', 'bank' => 'pani'],
        ];

        foreach ($validPayloads as $payload) {
            $validator = Validator::make($payload, $rules);
            $this->assertTrue(
                $validator->passes(),
                'Payload '.json_encode($payload).' should pass validation. Errors: '.json_encode($validator->errors()->all())
            );
        }
    }

    public function test_request_validation_rejects_invalid_banks_and_methods(): void
    {
        $rules = (new StartSubscriptionCheckoutRequest)->rules();

        $invalidPayloads = [
            ['payment_method' => 'va_cimb', 'bank' => 'invalid_bank'],
            ['payment_method' => 'va_bca'],
            ['payment_method' => 'gopay'],
            ['amount' => 50000],
            ['currency' => 'IDR'],
            ['merchant' => 'udev-central'],
            ['idempotency_key' => 'cannot-be-in-body'],
        ];

        foreach ($invalidPayloads as $payload) {
            $validator = Validator::make($payload, $rules);
            $this->assertFalse(
                $validator->passes(),
                'Payload '.json_encode($payload).' should fail validation.'
            );
        }
    }

    public function test_header_idempotency_key_is_validated_correctly(): void
    {
        $validRequest = StartSubscriptionCheckoutRequest::create('/api/v1/subscription-invoices/INV-001/checkout', 'POST');
        $validRequest->headers->set('Idempotency-Key', 'chk-valid-key-12345');
        $this->assertSame('chk-valid-key-12345', $validRequest->idempotencyKey());

        $invalidRequest = StartSubscriptionCheckoutRequest::create('/api/v1/subscription-invoices/INV-001/checkout', 'POST');
        $invalidRequest->headers->set('Idempotency-Key', 'invalid key with spaces');
        $this->assertNull($invalidRequest->idempotencyKey());

        $missingRequest = StartSubscriptionCheckoutRequest::create('/api/v1/subscription-invoices/INV-001/checkout', 'POST');
        $this->assertNull($missingRequest->idempotencyKey());
    }

    public function test_billing_client_sends_bank_parameter_for_virtual_accounts(): void
    {
        config([
            'services.udev.base_url' => 'https://apiudev.example.test',
            'services.udev.token' => 'test-integration-token',
        ]);

        Http::fake([
            'https://apiudev.example.test/api/v1/integrations/invoices/INV-VA-001/checkout' => Http::response([
                'data' => [
                    'invoice_number' => 'INV-VA-001',
                    'status' => 'pending',
                    'payment' => [
                        'status' => 'pending',
                        'provider' => 'dana',
                        'payment_method' => 'va_cimb',
                        'bank' => 'cimb',
                        'checkout_status' => 'ready',
                        'payment_code' => '8059000011223344',
                    ],
                ],
            ]),
        ]);

        $client = app(UdevBillingClient::class);
        $result = $client->startCheckout('INV-VA-001', 'idemp-va-cimb', 'va_cimb', 'dana', 'cimb');

        $this->assertSame('INV-VA-001', $result['invoice_number']);
        $this->assertSame('va_cimb', $result['payment']['payment_method']);
        $this->assertSame('8059000011223344', $result['payment']['payment_code']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://apiudev.example.test/api/v1/integrations/invoices/INV-VA-001/checkout'
                && $request->header('Idempotency-Key')[0] === 'idemp-va-cimb'
                && $request['payment_method'] === 'va_cimb'
                && $request['provider'] === 'dana'
                && $request['bank'] === 'cimb';
        });
    }

    public function test_upstream_failure_propagation_returns_safe_and_specific_messages(): void
    {
        $controller = app(SubscriptionBillingController::class);
        $method = new ReflectionMethod($controller, 'upstreamFailure');
        $method->setAccessible(true);

        // 1. Bank validation error (422)
        $bankException = new UdevBillingRequestException(
            upstreamStatus: 422,
            upstreamMessage: 'The selected bank is invalid.',
            upstreamErrors: ['bank' => ['The selected bank is invalid.']],
            upstreamErrorCode: 'VALIDATION_ERROR',
        );
        $response1 = $method->invoke($controller, $bankException);
        $this->assertSame(422, $response1->getStatusCode());
        $this->assertSame('Bank Virtual Account yang dipilih tidak valid.', $response1->getData()->message);
        $this->assertSame('UDEV_BILLING_REJECTED', $response1->getData()->code);
        $this->assertNotEmpty($response1->getData()->errors->bank);

        // 2. Payment method error (422)
        $methodException = new UdevBillingRequestException(
            upstreamStatus: 422,
            upstreamMessage: 'Metode pembayaran tidak didukung.',
            upstreamErrors: ['payment_method' => ['Metode pembayaran tidak valid.']],
        );
        $response2 = $method->invoke($controller, $methodException);
        $this->assertSame(422, $response2->getStatusCode());
        $this->assertSame('Metode pembayaran yang dipilih tidak didukung.', $response2->getData()->message);

        // 3. Invoice expired error (422)
        $expiredException = new UdevBillingRequestException(
            upstreamStatus: 422,
            upstreamMessage: 'Invoice sudah kedaluwarsa.',
            upstreamErrors: ['invoice' => ['Invoice langganan sudah kedaluwarsa.']],
        );
        $response3 = $method->invoke($controller, $expiredException);
        $this->assertSame(422, $response3->getStatusCode());
        $this->assertSame('Invoice langganan sudah kedaluwarsa.', $response3->getData()->message);

        // 4. Idempotency conflict (409)
        $conflictException = new UdevBillingRequestException(
            upstreamStatus: 409,
            upstreamMessage: "Idempotency key 'chk-1' sudah digunakan untuk checkout invoice INV-001 dengan parameter berbeda.",
        );
        $response4 = $method->invoke($controller, $conflictException);
        $this->assertSame(409, $response4->getStatusCode());
        $this->assertSame('CONFLICT', $response4->getData()->code);
        $this->assertStringContainsString('parameter berbeda', $response4->getData()->message);

        // 5. Gateway uncertain status (409)
        $uncertainException = new UdevBillingRequestException(
            upstreamStatus: 409,
            upstreamMessage: 'Transaksi untuk invoice INV-001 berstatus tidak pasti di gateway DANA.',
        );
        $response5 = $method->invoke($controller, $uncertainException);
        $this->assertSame(409, $response5->getStatusCode());
        $this->assertStringContainsString('menunggu konfirmasi gateway DANA', $response5->getData()->message);
    }

    public function test_all_six_dana_methods_in_client_catalog_have_zero_fee(): void
    {
        $client = app(UdevBillingClient::class);
        $methods = $client->danaGatewayMethods();

        $this->assertCount(6, $methods);
        $expectedCodes = ['qris', 'dana_wallet', 'va_bri', 'va_cimb', 'va_btpn', 'va_pani'];

        foreach ($expectedCodes as $code) {
            $this->assertArrayHasKey($code, $methods);
            $this->assertSame('dana', $methods[$code]['provider']);
            $this->assertSame('Rp 0', $methods[$code]['fee_label']);
            $this->assertEquals(0.0, (float) $methods[$code]['fee_rate']);
            $this->assertEquals(0.0, (float) $methods[$code]['fixed_amount']);
            $this->assertTrue($methods[$code]['verified']);
        }
    }
}
