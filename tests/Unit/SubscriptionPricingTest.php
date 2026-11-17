<?php

namespace Tests\Unit;

use App\Actions\Udev\SubscriptionInvoiceRegistry;
use App\Http\Controllers\Api\V1\SubscriptionBillingController;
use App\Models\User;
use App\Models\Warung;
use App\Models\WarungCustomerSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use UnexpectedValueException;

class SubscriptionPricingTest extends TestCase
{
    protected SubscriptionBillingController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.udev.base_url' => 'https://apiudev.example.test',
            'services.udev.token' => 'test-integration-token',
        ]);

        if (! Schema::hasTable('warungs')) {
            Schema::create('warungs', function ($table) {
                $table->id();
                $table->string('kode')->nullable();
                $table->string('nama')->nullable();
                $table->string('timezone')->default('Asia/Jakarta');
                $table->date('tanggal_mulai')->nullable();
                $table->date('tanggal_berakhir')->nullable();
                $table->boolean('aktif')->default(true);
                $table->boolean('pendaftaran_disetujui')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('warung_customer_syncs')) {
            Schema::create('warung_customer_syncs', function ($table) {
                $table->id();
                $table->foreignId('warung_id')->nullable();
                $table->foreignId('owner_user_id')->nullable();
                $table->string('external_customer_id')->nullable();
                $table->string('status')->default('synced');
                $table->string('notice_version')->nullable();
                $table->timestamp('consented_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->foreignId('warung_id')->nullable();
                $table->string('nama')->nullable();
                $table->string('username')->nullable();
                $table->string('email')->nullable();
                $table->string('role')->default('owner');
                $table->boolean('aktif')->default(true);
                $table->timestamps();
            });
        }

        $this->controller = new SubscriptionBillingController;
    }

    /**
     * Skenario 1: Harga Normal (tanpa referral) untuk 1 periode.
     * Harga normal = Rp 175.000, diskon = Rp 0, total = Rp 175.000.
     */
    public function test_pricing_normal_without_referral_passes_validation(): void
    {
        $pricing = [
            'unit_price' => '175000.00',
            'period_count' => 1,
            'normal_price' => '175000.00',
            'referral_code' => null,
            'referral_type' => null,
            'discount_amount' => '0.00',
            'final_price' => '175000.00',
            'currency' => 'IDR',
        ];

        // Harus lolos tanpa exception
        $this->controller->validatePricingConsistency($pricing, 1);
        $this->assertTrue(true);
    }

    /**
     * Skenario 2: Referral Khusus (Special Referral, diskon Rp 75.000).
     * Harga normal = Rp 175.000, diskon = Rp 75.000, total = Rp 100.000.
     * Konsistensi matematis: 175.000 - 75.000 = 100.000.
     */
    public function test_pricing_special_referral_passes_validation(): void
    {
        $pricing = [
            'unit_price' => '175000.00',
            'period_count' => 1,
            'normal_price' => '175000.00',
            'referral_code' => 'REF-SPECIAL',
            'referral_type' => 'special',
            'discount_amount' => '75000.00',
            'final_price' => '100000.00',
            'currency' => 'IDR',
        ];

        $this->controller->validatePricingConsistency($pricing, 1);
        $this->assertTrue(true);
    }

    /**
     * Skenario 3: Referral Umum (General Referral, diskon Rp 50.000).
     * Harga normal = Rp 175.000, diskon = Rp 50.000, total = Rp 125.000.
     * Konsistensi matematis: 175.000 - 50.000 = 125.000.
     */
    public function test_pricing_general_referral_passes_validation(): void
    {
        $pricing = [
            'unit_price' => '175000.00',
            'period_count' => 1,
            'normal_price' => '175000.00',
            'referral_code' => 'REF-UMUM',
            'referral_type' => 'general',
            'discount_amount' => '50000.00',
            'final_price' => '125000.00',
            'currency' => 'IDR',
        ];

        $this->controller->validatePricingConsistency($pricing, 1);
        $this->assertTrue(true);
    }

    /**
     * Skenario 4: Beberapa Periode (Multiple Periods).
     * - Kasus A: 3 periode dengan referral khusus (175.000 x 3 = 525.000; diskon 75.000 x 3 = 225.000; final = 300.000)
     * - Kasus B: 6 periode harga normal (175.000 x 6 = 1.050.000; diskon = 0; final = 1.050.000)
     * - Kasus C: 12 periode dengan referral umum (175.000 x 12 = 2.100.000; diskon 50.000 x 12 = 600.000; final = 1.500.000)
     */
    public function test_pricing_multiple_periods_passes_validation(): void
    {
        // 3 Periode + Referral Khusus
        $pricing3Periods = [
            'unit_price' => '175000.00',
            'period_count' => 3,
            'normal_price' => '525000.00',
            'referral_code' => 'REF-SPECIAL',
            'referral_type' => 'special',
            'discount_amount' => '225000.00',
            'final_price' => '300000.00',
            'currency' => 'IDR',
        ];
        $this->controller->validatePricingConsistency($pricing3Periods, 3);

        // 6 Periode Normal (Tanpa Referral)
        $pricing6Periods = [
            'unit_price' => '175000.00',
            'period_count' => 6,
            'normal_price' => '1050000.00',
            'referral_code' => null,
            'referral_type' => null,
            'discount_amount' => '0.00',
            'final_price' => '1050000.00',
            'currency' => 'IDR',
        ];
        $this->controller->validatePricingConsistency($pricing6Periods, 6);

        // 12 Periode + Referral Umum
        $pricing12Periods = [
            'unit_price' => '175000.00',
            'period_count' => 12,
            'normal_price' => '2100000.00',
            'referral_code' => 'REF-UMUM',
            'referral_type' => 'general',
            'discount_amount' => '600000.00',
            'final_price' => '1500000.00',
            'currency' => 'IDR',
        ];
        $this->controller->validatePricingConsistency($pricing12Periods, 12);

        $this->assertTrue(true);
    }

    /**
     * Skenario 5 (Bug Kasus): Paket Rp 10.000 dengan diskon referral Rp 75.000 dan total Rp 100.000.
     * 10.000 - 75.000 != 100.000 (tidak konsisten secara matematis).
     * Validasi harus melempar UnexpectedValueException dan memblokir invoice.
     */
    public function test_pricing_inconsistency_bug_case_is_blocked(): void
    {
        $inconsistentPricing = [
            'unit_price' => '10000.00',
            'period_count' => 1,
            'normal_price' => '10000.00',
            'referral_code' => 'REF-SPECIAL',
            'referral_type' => 'special',
            'discount_amount' => '75000.00',
            'final_price' => '100000.00',
            'currency' => 'IDR',
        ];

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('UDEV_PRICING_INCONSISTENT');

        $this->controller->validatePricingConsistency($inconsistentPricing, 1);
    }

    /**
     * Skenario 6: Final price tidak cocok dengan normal_price - discount_amount.
     */
    public function test_pricing_final_price_mismatch_is_blocked(): void
    {
        $mismatchPricing = [
            'unit_price' => '175000.00',
            'period_count' => 1,
            'normal_price' => '175000.00',
            'discount_amount' => '25000.00',
            'final_price' => '160000.00', // seharusnya 150000.00
            'currency' => 'IDR',
        ];

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('UDEV_PRICING_INCONSISTENT: Total invoice tidak sesuai dengan harga normal dikurangi diskon.');

        $this->controller->validatePricingConsistency($mismatchPricing, 1);
    }

    /**
     * Skenario 7: Normal price tidak cocok dengan unit_price * period_count.
     */
    public function test_pricing_unit_price_period_mismatch_is_blocked(): void
    {
        $mismatchPricing = [
            'unit_price' => '175000.00',
            'period_count' => 3,
            'normal_price' => '400000.00', // seharusnya 525000.00
            'discount_amount' => '0.00',
            'final_price' => '400000.00',
            'currency' => 'IDR',
        ];

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('UDEV_PRICING_INCONSISTENT: Harga normal tidak sesuai dengan perkalian harga per periode.');

        $this->controller->validatePricingConsistency($mismatchPricing, 3);
    }

    /**
     * Skenario 8: Diskon melebihi harga normal paket.
     */
    public function test_pricing_discount_exceeds_normal_price_is_blocked(): void
    {
        $excessDiscountPricing = [
            'unit_price' => '50000.00',
            'period_count' => 1,
            'normal_price' => '50000.00',
            'discount_amount' => '60000.00', // melebihi 50.000
            'final_price' => '0.00',
            'currency' => 'IDR',
        ];

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('UDEV_PRICING_INCONSISTENT: Nilai diskon tidak boleh melebihi harga normal paket.');

        $this->controller->validatePricingConsistency($excessDiscountPricing, 1);
    }

    /**
     * Skenario 9: Komponen harga negatif atau periode tidak valid.
     */
    public function test_pricing_negative_or_zero_period_is_blocked(): void
    {
        $negativePricing = [
            'unit_price' => '-1000.00',
            'period_count' => 1,
            'normal_price' => '-1000.00',
            'discount_amount' => '0.00',
            'final_price' => '-1000.00',
        ];

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('UDEV_PRICING_INCONSISTENT: Komponen harga tidak boleh bernilai negatif');

        $this->controller->validatePricingConsistency($negativePricing, 1);
    }

    /**
     * Skenario 10: Endpoint Invoice Preview memblokir respons inkonsisten dari UDEV dengan HTTP 422.
     */
    public function test_preview_endpoint_blocks_inconsistent_udev_response_with_422(): void
    {
        $warung = Warung::query()->forceCreate([
            'id' => 101,
            'kode' => 'WRG-PRICING-1',
            'nama' => 'Warung Test Pricing',
            'timezone' => 'Asia/Jakarta',
            'aktif' => true,
            'pendaftaran_disetujui' => true,
        ]);

        $owner = User::query()->forceCreate([
            'id' => 201,
            'nama' => 'Owner Test Pricing 1',
            'role' => 'owner',
            'warung_id' => $warung->id,
            'aktif' => true,
        ]);

        WarungCustomerSync::query()->create([
            'warung_id' => $warung->id,
            'owner_user_id' => $owner->id,
            'external_customer_id' => 'CUST-WRG-PRICING-1',
            'status' => 'synced',
            'consented_at' => CarbonImmutable::now(),
        ]);

        // Mock UDEV mengembalikan rincian harga yang cacat matematis
        Http::fake([
            'https://apiudev.example.test/api/v1/integrations/customers/CUST-WRG-PRICING-1/invoice-preview' => Http::response([
                'data' => [
                    'customer_external_id' => 'CUST-WRG-PRICING-1',
                    'plan' => [
                        'code' => 'PKT0002',
                        'name' => 'Paket percobaan',
                        'duration_days' => 30,
                        'period_count' => 1,
                    ],
                    'pricing' => [
                        'unit_price' => '10000.00',
                        'period_count' => 1,
                        'normal_price' => '10000.00',
                        'referral_code' => 'REF-SPECIAL',
                        'referral_type' => 'special',
                        'discount_amount' => '75000.00',
                        'final_price' => '100000.00', // Cacat matematis!
                        'currency' => 'IDR',
                    ],
                    'available_gateways' => [],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($owner);
        $response = $this->postJson('/api/v1/subscription-invoices/preview', [
            'subscription_plan_code' => 'PKT0002',
            'period_count' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'UDEV_PRICING_INCONSISTENT',
                'message' => 'Rincian harga dari UDEV tidak konsisten secara matematis. Pembuatan invoice diblokir.',
            ]);
    }

    /**
     * Skenario 11: Endpoint Store Invoice memblokir pembuatan invoice jika UDEV mengembalikan invoice inkonsisten.
     */
    public function test_store_endpoint_blocks_inconsistent_invoice_creation_with_422(): void
    {
        $warung = Warung::query()->forceCreate([
            'id' => 102,
            'kode' => 'WRG-PRICING-2',
            'nama' => 'Warung Test Pricing 2',
            'timezone' => 'Asia/Jakarta',
            'aktif' => true,
            'pendaftaran_disetujui' => true,
        ]);

        $owner = User::query()->forceCreate([
            'id' => 202,
            'nama' => 'Owner Test Pricing 2',
            'role' => 'owner',
            'warung_id' => $warung->id,
            'aktif' => true,
        ]);

        WarungCustomerSync::query()->create([
            'warung_id' => $warung->id,
            'owner_user_id' => $owner->id,
            'external_customer_id' => 'CUST-WRG-PRICING-2',
            'status' => 'synced',
            'consented_at' => CarbonImmutable::now(),
        ]);

        Http::fake([
            // Tidak ada transaksi pending yang cocok
            'https://apiudev.example.test/api/v1/integrations/customers/CUST-WRG-PRICING-2/transactions*' => Http::response([
                'data' => [],
            ], 200),
            // UDEV merespons pembuatan invoice dengan data yang cacat matematis
            'https://apiudev.example.test/api/v1/integrations/customers/CUST-WRG-PRICING-2/invoices' => Http::response([
                'data' => [
                    'invoice_number' => 'INV/TEST/CORRUPTED',
                    'status' => 'pending',
                    'period_count' => 1,
                    'pricing' => [
                        'normal_price' => '10000.00',
                        'referral_code' => 'REF-SPECIAL',
                        'referral_type' => 'special',
                        'discount_amount' => '75000.00',
                        'final_price' => '100000.00', // Cacat matematis
                        'currency' => 'IDR',
                    ],
                ],
            ], 201),
        ]);

        Sanctum::actingAs($owner);
        $response = $this->withHeader('Idempotency-Key', 'idemp-pricing-test-key-01')
            ->postJson('/api/v1/subscription-invoices', [
                'subscription_plan_code' => 'PKT0002',
                'period_count' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'UDEV_PRICING_INCONSISTENT',
                'message' => 'Rincian harga dari UDEV tidak konsisten secara matematis. Pembuatan invoice diblokir.',
            ]);

        // Pastikan invoice tidak terdaftar di registry
        $this->assertNull(Cache::get(SubscriptionInvoiceRegistry::INVOICE_PREFIX.'INV/TEST/CORRUPTED'));
    }
}
