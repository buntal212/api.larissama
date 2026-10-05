<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\PembelianRinci;
use App\Models\Penjualan;
use App\Models\PenjualanRinci;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApprovedTransactionRulesConformanceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidSalesAgainstApprovedRules')]
    public function test_sale_rejects_discount_or_payment_outside_approved_limits(
        string $case,
        array $changes,
        string $errorField,
    ): void {
        [$token, $menu] = $this->saleFixture('10.00');
        $payload = [
            'tanggal' => '2020-01-01T10:00:00Z',
            'bayar' => '10.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];
        $payload = array_replace_recursive($payload, $changes);
        $headers = ['Idempotency-Key' => 'approved-sale-'.$case];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
        $response = $this->withToken($token)
            ->postJson('/api/v1/penjualans', $payload, $headers)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors($errorField);
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');
        $this->assertSame(0, Penjualan::query()->count());
        $this->assertSame(0, PenjualanRinci::query()->count());
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function invalidSalesAgainstApprovedRules(): array
    {
        return [
            'line discount exceeds line subtotal' => [
                'line-discount',
                ['rincian' => [['diskon' => '10.01']]],
                'rincian.0.diskon',
            ],
            'header discount exceeds subtotal' => [
                'header-discount',
                ['diskon' => '10.01'],
                'diskon',
            ],
            'cash below total' => [
                'cash-short',
                ['bayar' => '9.99'],
                'bayar',
            ],
            'qris below total' => [
                'qris-short',
                ['metode_pembayaran' => 'qris', 'bayar' => '9.99'],
                'bayar',
            ],
            'qris above total' => [
                'qris-over',
                ['metode_pembayaran' => 'qris', 'bayar' => '10.01'],
                'bayar',
            ],
            'transfer below total' => [
                'transfer-short',
                ['metode_pembayaran' => 'transfer', 'bayar' => '9.99'],
                'bayar',
            ],
            'transfer above total' => [
                'transfer-over',
                ['metode_pembayaran' => 'transfer', 'bayar' => '10.01'],
                'bayar',
            ],
        ];
    }

    public function test_cash_may_equal_or_exceed_total_and_non_cash_must_match_exactly(): void
    {
        [$token, $menu] = $this->saleFixture('10.00');

        foreach ([
            ['method' => 'cash', 'paid' => '10.00', 'key' => 'cash-exact'],
            ['method' => 'cash', 'paid' => '12.00', 'key' => 'cash-over'],
            ['method' => 'qris', 'paid' => '10.00', 'key' => 'qris-exact'],
            ['method' => 'transfer', 'paid' => '10.00', 'key' => 'transfer-exact'],
        ] as $payment) {
            $payload = [
                'tanggal' => '2020-01-01T10:00:00Z',
                'bayar' => $payment['paid'],
                'metode_pembayaran' => $payment['method'],
                'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
            ];
            $headers = ['Idempotency-Key' => 'approved-payment-'.$payment['key']];

            $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
            $response = $this->withToken($token)
                ->postJson('/api/v1/penjualans', $payload, $headers)
                ->assertCreated()
                ->assertJsonPath('data.bayar', $payment['paid']);
            $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');
        }

        $this->assertSame(4, Penjualan::query()->count());
    }

    public function test_half_up_rounding_is_applied_per_sale_and_purchase_line(): void
    {
        [$token, $menu] = $this->saleFixture('0.50');
        $salePayload = [
            'tanggal' => '2020-01-01T10:00:00Z',
            'bayar' => '0.01',
            'metode_pembayaran' => 'qris',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '0.01']],
        ];
        $saleHeaders = ['Idempotency-Key' => 'approved-round-half-up-sale'];

        $this->assertOperationRequestMatchesOpenApi($salePayload, $saleHeaders, '/penjualans', 'post');
        $sale = $this->withToken($token)
            ->postJson('/api/v1/penjualans', $salePayload, $saleHeaders)
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '0.01')
            ->assertJsonPath('data.total', '0.01')
            ->assertJsonPath('data.rincian.0.subtotal', '0.01');
        $this->assertOperationResponseMatchesOpenApi($sale, '/penjualans', 'post');

        $purchasePayload = [
            'tanggal' => '2020-01-01T11:00:00Z',
            'rincian' => [[
                'nama_item' => 'Bahan uji pembulatan',
                'qty' => '0.01',
                'satuan' => 'unit',
                'harga_satuan' => '0.50',
            ]],
        ];
        $purchaseHeaders = ['Idempotency-Key' => 'approved-round-half-up-purchase'];

        $this->assertOperationRequestMatchesOpenApi($purchasePayload, $purchaseHeaders, '/pembelians', 'post');
        $purchase = $this->withToken($token)
            ->postJson('/api/v1/pembelians', $purchasePayload, $purchaseHeaders)
            ->assertCreated()
            ->assertJsonPath('data.total', '0.01')
            ->assertJsonPath('data.rincian.0.subtotal', '0.01');
        $this->assertOperationResponseMatchesOpenApi($purchase, '/pembelians', 'post');

        $this->assertDatabaseHas('penjualans', ['id' => $sale->json('data.id'), 'subtotal' => '0.01']);
        $this->assertDatabaseHas('pembelians', ['id' => $purchase->json('data.id'), 'total' => '0.01']);
        $this->assertSame(1, PenjualanRinci::query()->count());
        $this->assertSame(1, PembelianRinci::query()->count());
    }

    #[DataProvider('invalidQuantities')]
    public function test_sale_and_purchase_quantities_must_be_positive_with_two_decimals(string $quantity): void
    {
        [$token, $menu] = $this->saleFixture('1.00');
        $salePayload = [
            'tanggal' => '2020-01-01T10:00:00Z',
            'bayar' => '1.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => $quantity]],
        ];
        $saleHeaders = ['Idempotency-Key' => 'approved-sale-qty-'.str_replace('.', '-', $quantity)];

        $this->assertOperationRequestDoesNotMatchOpenApi($salePayload, '/penjualans', 'post');
        $saleResponse = $this->withToken($token)
            ->postJson('/api/v1/penjualans', $salePayload, $saleHeaders)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rincian.0.qty');
        $this->assertOperationResponseMatchesOpenApi($saleResponse, '/penjualans', 'post');

        $purchasePayload = [
            'tanggal' => '2020-01-01T11:00:00Z',
            'rincian' => [[
                'nama_item' => 'Bahan uji kuantitas',
                'qty' => $quantity,
                'harga_satuan' => '1.00',
            ]],
        ];
        $purchaseHeaders = ['Idempotency-Key' => 'approved-purchase-qty-'.str_replace('.', '-', $quantity)];

        $this->assertOperationRequestDoesNotMatchOpenApi($purchasePayload, '/pembelians', 'post');
        $purchaseResponse = $this->withToken($token)
            ->postJson('/api/v1/pembelians', $purchasePayload, $purchaseHeaders)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rincian.0.qty');
        $this->assertOperationResponseMatchesOpenApi($purchaseResponse, '/pembelians', 'post');

        $this->assertSame(0, Penjualan::query()->count());
        $this->assertSame(0, PenjualanRinci::query()->count());
        $this->assertSame(0, Pembelian::query()->count());
        $this->assertSame(0, PembelianRinci::query()->count());
    }

    /** @return array<string, array{string}> */
    public static function invalidQuantities(): array
    {
        return [
            'zero' => ['0.00'],
            'negative' => ['-1.00'],
            'more than two decimal places' => ['1.001'],
        ];
    }

    /** @return array{string, Menu} */
    private function saleFixture(string $price): array
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => $price]);

        return [$owner->createToken('approved-rules-test')->plainTextToken, $menu];
    }
}
