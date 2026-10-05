<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\PembelianRinci;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PembelianApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_record_purchase_using_only_item_name_and_amount(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '150000.00']],
        ];
        $headers = ['Idempotency-Key' => 'purchase-summary-001'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/pembelians', 'post');
        $response = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, $headers);

        $response->assertCreated()
            ->assertJsonPath('data.total', '150000.00')
            ->assertJsonPath('data.rincian.0.nama_item', 'Belanja di pasar')
            ->assertJsonPath('data.rincian.0.qty', null)
            ->assertJsonPath('data.rincian.0.satuan', null)
            ->assertJsonPath('data.rincian.0.harga_satuan', null);
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'post');

        $purchaseId = (int) $response->json('data.id');
        $detail = $this->withToken($token)->getJson("/api/v1/pembelians/{$purchaseId}")
            ->assertOk()
            ->assertJsonPath('data.rincian.0.nama_item', 'Belanja di pasar');
        $this->assertOperationResponseMatchesOpenApi($detail, '/pembelians/{id}', 'get');

        $this->assertDatabaseHas('pembelians', [
            'id' => $purchaseId,
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'total' => '150000.00',
        ]);
        $this->assertDatabaseHas('pembelian_rincis', [
            'pembelian_id' => $purchaseId,
            'nama_item' => 'Belanja di pasar',
            'qty' => null,
            'harga_satuan' => null,
            'subtotal' => '150000.00',
        ]);
    }

    public function test_manager_can_record_detailed_purchase_with_backend_total(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'rincian' => [
                ['nama_item' => 'Beras', 'qty' => '5.00', 'satuan' => 'kg', 'harga_satuan' => '15000.00', 'subtotal' => '75000.00'],
                ['nama_item' => 'Cabai', 'qty' => '0.50', 'satuan' => 'kg', 'harga_satuan' => '40000.00', 'subtotal' => '20000.00'],
            ],
        ];
        $headers = ['Idempotency-Key' => 'purchase-detail-001'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/pembelians', 'post');
        $response = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, $headers);

        $response->assertCreated()
            ->assertJsonPath('data.total', '95000.00')
            ->assertJsonCount(2, 'data.rincian')
            ->assertJsonPath('data.rincian.0.subtotal', '75000.00')
            ->assertJsonPath('data.rincian.1.subtotal', '20000.00');
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'post');
    }

    public function test_invalid_purchase_list_date_filters_return_schema_conformant_validation_errors(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $invalidQueries = [
            ['query' => ['date_from' => '04-10-2026', 'date_to' => '2026-10-04'], 'error' => 'date_from'],
            ['query' => ['date_from' => '2026-10-05', 'date_to' => '2026-10-04'], 'error' => 'date_to'],
        ];

        foreach ($invalidQueries as $case) {
            $response = $this->withToken($token)
                ->getJson('/api/v1/pembelians?'.http_build_query($case['query']))
                ->assertUnprocessable()
                ->assertJsonPath('code', 'VALIDATION_ERROR')
                ->assertJsonValidationErrors($case['error']);

            $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'get');
        }

        $this->assertDatabaseCount('pembelians', 0);
        $this->assertDatabaseCount('pembelian_rincis', 0);
    }

    public function test_purchase_list_requires_date_from_and_date_to_as_a_pair(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $partialQueries = [
            ['query' => ['date_from' => '2026-10-04'], 'error' => 'date_to'],
            ['query' => ['date_to' => '2026-10-04'], 'error' => 'date_from'],
        ];

        foreach ($partialQueries as $case) {
            $response = $this->withToken($token)
                ->getJson('/api/v1/pembelians?'.http_build_query($case['query']))
                ->assertUnprocessable()
                ->assertJsonPath('code', 'VALIDATION_ERROR')
                ->assertJsonValidationErrors($case['error']);

            $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'get');
        }

        $this->assertDatabaseCount('pembelians', 0);
        $this->assertDatabaseCount('pembelian_rincis', 0);
    }

    public function test_purchase_list_filters_by_the_shop_local_day_with_an_exclusive_end(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $beforeStart = Pembelian::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'tanggal' => '2026-10-03 16:59:59',
        ]);
        $atStart = Pembelian::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'tanggal' => '2026-10-03 17:00:00',
        ]);
        $beforeEnd = Pembelian::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'tanggal' => '2026-10-04 16:59:59',
        ]);
        $atEnd = Pembelian::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'tanggal' => '2026-10-04 17:00:00',
        ]);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $query = [
            'page' => '1',
            'per_page' => '20',
            'date_from' => '2026-10-04',
            'date_to' => '2026-10-04',
        ];

        $this->assertOperationQueryMatchesOpenApi($query, '/pembelians', 'get');
        $response = $this->withToken($token)
            ->getJson('/api/v1/pembelians?'.http_build_query($query))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', (string) $beforeEnd->id)
            ->assertJsonPath('data.1.id', (string) $atStart->id);
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'get');

        $this->assertNotSame($beforeStart->id, $response->json('data.0.id'));
        $this->assertNotSame($atEnd->id, $response->json('data.0.id'));
    }

    public function test_purchase_list_uses_the_shop_timezone_across_a_dst_short_day(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'America/New_York']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $beforeStart = Pembelian::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'tanggal' => '2026-03-08 04:59:59',
        ]);
        $atStart = Pembelian::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'tanggal' => '2026-03-08 05:00:00',
        ]);
        $beforeEnd = Pembelian::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'tanggal' => '2026-03-09 03:59:59',
        ]);
        $atEnd = Pembelian::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $manager->id,
            'tanggal' => '2026-03-09 04:00:00',
        ]);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $query = ['page' => '1', 'per_page' => '20', 'date_from' => '2026-03-08', 'date_to' => '2026-03-08'];

        $this->assertOperationQueryMatchesOpenApi($query, '/pembelians', 'get');
        $response = $this->withToken($token)
            ->getJson('/api/v1/pembelians?'.http_build_query($query))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', (string) $beforeEnd->id)
            ->assertJsonPath('data.1.id', (string) $atStart->id);
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'get');

        $this->assertNotSame($beforeStart->id, $response->json('data.0.id'));
        $this->assertNotSame($atEnd->id, $response->json('data.0.id'));
    }

    public function test_purchase_retry_replays_same_header_and_rejects_different_payload(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '150000.00']],
        ];
        $headers = ['Idempotency-Key' => 'purchase-retry-001'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/pembelians', 'post');
        $first = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, $headers)->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($first, '/pembelians', 'post');
        $purchaseId = $first->json('data.id');

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/pembelians', 'post');
        $replay = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, $headers)
            ->assertCreated()->assertJsonPath('data.id', $purchaseId);
        $this->assertOperationResponseMatchesOpenApi($replay, '/pembelians', 'post');

        $payload['rincian'][0]['subtotal'] = '151000.00';
        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/pembelians', 'post');
        $conflict = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, $headers)
            ->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertOperationResponseMatchesOpenApi($conflict, '/pembelians', 'post');

        $this->assertSame(1, Pembelian::query()->where('warung_id', $warung->id)->count());
        $this->assertSame('150000.00', Pembelian::query()->whereKey($purchaseId)->value('total'));
    }

    #[DataProvider('invalidPurchaseLineShapes')]
    public function test_invalid_purchase_line_shapes_return_422_without_writing(
        array $line,
        bool $matchesOpenApi,
        array $expectedErrors,
    ): void {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'rincian' => [$line],
        ];

        if ($matchesOpenApi) {
            $this->assertOperationRequestMatchesOpenApi($payload, ['Idempotency-Key' => 'purchase-invalid-shape'], '/pembelians', 'post');
        } else {
            $this->assertOperationRequestDoesNotMatchOpenApi($payload, '/pembelians', 'post');
        }

        $response = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, [
            'Idempotency-Key' => 'purchase-invalid-shape',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'post');
        $response->assertJsonValidationErrors(array_keys($expectedErrors));

        foreach ($expectedErrors as $field => $message) {
            $this->assertSame([$message], $response->json('errors')[$field] ?? null);
        }

        $this->assertDatabaseCount('pembelians', 0);
        $this->assertDatabaseCount('pembelian_rincis', 0);
    }

    /**
     * @return array<string, array{array<string, string>, bool, array<string, string>}>
     */
    public static function invalidPurchaseLineShapes(): array
    {
        return [
            'quantity without unit price' => [
                ['nama_item' => 'Gula', 'qty' => '2.00', 'subtotal' => '30000.00'],
                false,
                [
                    'rincian.0.qty' => 'Kuantitas dan harga satuan harus diisi bersama.',
                    'rincian.0.harga_satuan' => 'Kuantitas dan harga satuan harus diisi bersama.',
                ],
            ],
            'unit price without quantity' => [
                ['nama_item' => 'Gula', 'harga_satuan' => '15000.00', 'subtotal' => '30000.00'],
                false,
                [
                    'rincian.0.qty' => 'Kuantitas dan harga satuan harus diisi bersama.',
                    'rincian.0.harga_satuan' => 'Kuantitas dan harga satuan harus diisi bersama.',
                ],
            ],
            'nominal line without subtotal' => [
                ['nama_item' => 'Gula'],
                false,
                ['rincian.0.subtotal' => 'Subtotal wajib untuk rincian nominal.'],
            ],
            'calculated subtotal does not match' => [
                [
                    'nama_item' => 'Gula',
                    'qty' => '2.00',
                    'harga_satuan' => '15000.00',
                    'subtotal' => '29000.00',
                ],
                true,
                ['rincian.0.subtotal' => 'Subtotal tidak cocok dengan kuantitas dan harga satuan.'],
            ],
        ];
    }

    public function test_manager_can_record_calculated_purchase_without_client_subtotal(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'rincian' => [
                ['nama_item' => 'Beras', 'qty' => '5.00', 'satuan' => 'kg', 'harga_satuan' => '15000.00'],
                ['nama_item' => 'Cabai', 'qty' => '0.50', 'satuan' => 'kg', 'harga_satuan' => '40000.00'],
            ],
        ];

        $this->assertOperationRequestMatchesOpenApi($payload, ['Idempotency-Key' => 'purchase-no-subtotal'], '/pembelians', 'post');
        $response = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, [
            'Idempotency-Key' => 'purchase-no-subtotal',
        ])->assertCreated()
            ->assertJsonPath('data.total', '95000.00')
            ->assertJsonPath('data.rincian.0.subtotal', '75000.00')
            ->assertJsonPath('data.rincian.1.subtotal', '20000.00');

        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'post');
        $this->assertDatabaseHas('pembelians', [
            'id' => (int) $response->json('data.id'),
            'warung_id' => $warung->id,
            'total' => '95000.00',
        ]);
        $this->assertDatabaseCount('pembelian_rincis', 2);
    }

    public function test_purchase_rejects_client_warung_id_injection_without_writing(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'warung_id' => (string) $warungB->id,
            'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '150000.00']],
        ];

        $response = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, [
            'Idempotency-Key' => 'purchase-tenant-injection-denied',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('warung_id');
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'post');

        $this->assertDatabaseCount('pembelians', 0);
        $this->assertDatabaseCount('pembelian_rincis', 0);
    }

    public function test_purchase_report_sums_tenant_headers_without_multiplying_detail_rows(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $managerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $managerB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'manager']);
        $first = Pembelian::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $managerA->id,
            'tanggal' => '2026-10-04 10:00:00',
            'total' => '150000.00',
        ]);
        $second = Pembelian::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $managerA->id,
            'tanggal' => '2026-10-04 12:00:00',
            'total' => '95000.00',
        ]);
        Pembelian::factory()->create([
            'warung_id' => $warungB->id,
            'user_id' => $managerB->id,
            'tanggal' => '2026-10-04 12:00:00',
            'total' => '900000.00',
        ]);
        $first->rincian()->createMany([
            ['warung_id' => $warungA->id, 'nama_item' => 'Beras', 'qty' => '5.00', 'satuan' => 'kg', 'harga_satuan' => '15000.00', 'subtotal' => '75000.00'],
            ['warung_id' => $warungA->id, 'nama_item' => 'Gula', 'qty' => '2.00', 'satuan' => 'kg', 'harga_satuan' => '37500.00', 'subtotal' => '75000.00'],
        ]);
        $second->rincian()->createMany([
            ['warung_id' => $warungA->id, 'nama_item' => 'Cabai', 'qty' => '0.50', 'satuan' => 'kg', 'harga_satuan' => '40000.00', 'subtotal' => '20000.00'],
        ]);
        $token = $managerA->createToken('feature-test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/laporan/pembelian?date_from=2026-10-04&date_to=2026-10-04')
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 2)
            ->assertJsonPath('data.total_pembelian', '245000.00')
            ->assertJsonPath('data.period.timezone', 'Asia/Jakarta');
    }

    public function test_purchase_report_aggregate_exceeds_header_capacity_and_ignores_list_pagination(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;

        foreach ([
            ['tanggal' => '2026-10-04 10:00:00', 'total' => '6000000000000.00'],
            ['tanggal' => '2026-10-04 11:00:00', 'total' => '6000000000000.00'],
        ] as $purchase) {
            Pembelian::factory()->create([
                'warung_id' => $warung->id,
                'user_id' => $manager->id,
                'tanggal' => $purchase['tanggal'],
                'total' => $purchase['total'],
            ]);
        }

        $reportUrl = '/api/v1/laporan/pembelian?date_from=2026-10-04&date_to=2026-10-04';
        $reportBefore = $this->withToken($token)->getJson($reportUrl)
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 2)
            ->assertJsonPath('data.total_pembelian', '12000000000000.00');

        $firstPageQuery = ['page' => '1', 'per_page' => '1', 'sort' => '-tanggal'];
        $this->assertOperationQueryMatchesOpenApi($firstPageQuery, '/pembelians', 'get');
        $firstPage = $this->withToken($token)->getJson('/api/v1/pembelians?'.http_build_query($firstPageQuery))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
        $this->assertOperationResponseMatchesOpenApi($firstPage, '/pembelians', 'get');
        $secondPageQuery = ['page' => '2', 'per_page' => '1', 'sort' => '-tanggal'];
        $this->assertOperationQueryMatchesOpenApi($secondPageQuery, '/pembelians', 'get');
        $secondPage = $this->withToken($token)->getJson('/api/v1/pembelians?'.http_build_query($secondPageQuery))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
        $reportAfter = $this->withToken($token)->getJson($reportUrl)->assertOk();

        $this->assertNotSame($firstPage->json('data.0.id'), $secondPage->json('data.0.id'));
        $this->assertSame($reportBefore->json(), $reportAfter->json());
        $reportAfter->assertJsonPath('data.jumlah_transaksi', 2)
            ->assertJsonPath('data.total_pembelian', '12000000000000.00');
    }

    public function test_cashier_cannot_list_or_create_purchases(): void
    {
        $warung = Warung::factory()->create();
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $token = $cashier->createToken('feature-test')->plainTextToken;

        $list = $this->withToken($token)->getJson('/api/v1/pembelians')->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($list, '/pembelians', 'get');
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '150000.00']],
        ];
        $headers = ['Idempotency-Key' => 'purchase-cashier-denied'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/pembelians', 'post');
        $create = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, $headers)
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
        $this->assertOperationResponseMatchesOpenApi($create, '/pembelians', 'post');

        $this->assertDatabaseCount('pembelians', 0);
        $this->assertDatabaseCount('pembelian_rincis', 0);
    }

    public function test_superadmin_cannot_list_purchases_for_a_tenant(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $purchase = Pembelian::factory()->create(['warung_id' => $warung->id, 'user_id' => $manager->id]);
        $superadmin = User::factory()->create(['warung_id' => null, 'role' => 'superadmin']);
        $token = $superadmin->createToken('feature-test')->plainTextToken;
        $query = ['page' => '1', 'per_page' => '20', 'sort' => '-tanggal'];

        $this->assertOperationQueryMatchesOpenApi($query, '/pembelians', 'get');
        $response = $this->withToken($token)
            ->getJson('/api/v1/pembelians?'.http_build_query($query))
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'get');
        $this->assertDatabaseHas('pembelians', ['id' => $purchase->id, 'warung_id' => $warung->id]);
    }

    public function test_superadmin_cannot_create_a_purchase_for_a_tenant(): void
    {
        $superadmin = User::factory()->create(['warung_id' => null, 'role' => 'superadmin']);
        $token = $superadmin->createToken('feature-test')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '150000.00']],
        ];
        $headers = ['Idempotency-Key' => 'purchase-superadmin-denied'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/pembelians', 'post');
        $response = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, $headers)
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'post');

        $this->assertDatabaseCount('pembelians', 0);
        $this->assertDatabaseCount('pembelian_rincis', 0);
    }

    public function test_manager_gets_404_for_purchase_from_another_warung(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $managerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $managerB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'manager']);
        $purchaseB = Pembelian::factory()->create(['warung_id' => $warungB->id, 'user_id' => $managerB->id]);
        $token = $managerA->createToken('feature-test')->plainTextToken;

        $response = $this->withToken($token)->getJson("/api/v1/pembelians/{$purchaseB->id}")->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians/{id}', 'get');

        $this->assertDatabaseHas('pembelians', [
            'id' => $purchaseB->id,
            'warung_id' => $warungB->id,
            'user_id' => $managerB->id,
        ]);
    }

    public function test_manager_can_read_purchases_created_by_another_manager_in_same_warung_only(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $managerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $managerB = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $managerC = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'manager']);
        $purchaseByManagerB = Pembelian::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $managerB->id,
            'total' => '95000.00',
        ]);
        PembelianRinci::factory()->create([
            'warung_id' => $warungA->id,
            'pembelian_id' => $purchaseByManagerB->id,
            'nama_item' => 'Belanja bahan',
            'subtotal' => '95000.00',
        ]);
        $purchaseFromWarungB = Pembelian::factory()->create([
            'warung_id' => $warungB->id,
            'user_id' => $managerC->id,
        ]);
        PembelianRinci::factory()->create([
            'warung_id' => $warungB->id,
            'pembelian_id' => $purchaseFromWarungB->id,
        ]);
        $token = $managerA->createToken('feature-test')->plainTextToken;
        $query = ['page' => '1', 'per_page' => '20', 'sort' => '-tanggal'];

        $this->assertOperationQueryMatchesOpenApi($query, '/pembelians', 'get');
        $list = $this->withToken($token)
            ->getJson('/api/v1/pembelians?'.http_build_query($query))
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($list, '/pembelians', 'get');
        $this->assertSame(1, $list->json('meta.total'));
        $this->assertSame([(string) $purchaseByManagerB->id], array_column($list->json('data'), 'id'));

        $detail = $this->withToken($token)
            ->getJson('/api/v1/pembelians/'.$purchaseByManagerB->id)
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($detail, '/pembelians/{id}', 'get');
        $this->assertSame((string) $managerB->id, $detail->json('data.user_id'));
        $this->assertSame('Belanja bahan', $detail->json('data.rincian.0.nama_item'));

        $foreignDetail = $this->withToken($token)
            ->getJson('/api/v1/pembelians/'.$purchaseFromWarungB->id)
            ->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($foreignDetail, '/pembelians/{id}', 'get');
        $this->assertDatabaseHas('pembelians', [
            'id' => $purchaseFromWarungB->id,
            'warung_id' => $warungB->id,
            'user_id' => $managerC->id,
        ]);
    }

    public function test_owner_can_read_purchases_created_by_other_users_in_their_warung_only(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $ownerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'owner']);
        $managerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $managerB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'manager']);
        $purchaseInWarungA = Pembelian::factory()->create([
            'warung_id' => $warungA->id,
            'user_id' => $managerA->id,
            'total' => '64000.00',
        ]);
        PembelianRinci::factory()->create([
            'warung_id' => $warungA->id,
            'pembelian_id' => $purchaseInWarungA->id,
            'nama_item' => 'Belanja beras',
            'subtotal' => '64000.00',
        ]);
        $purchaseInWarungB = Pembelian::factory()->create([
            'warung_id' => $warungB->id,
            'user_id' => $managerB->id,
        ]);
        PembelianRinci::factory()->create([
            'warung_id' => $warungB->id,
            'pembelian_id' => $purchaseInWarungB->id,
        ]);
        $token = $ownerA->createToken('owner-purchase-read-test')->plainTextToken;
        $query = ['page' => '1', 'per_page' => '20', 'sort' => '-tanggal'];

        $this->assertOperationQueryMatchesOpenApi($query, '/pembelians', 'get');
        $list = $this->withToken($token)
            ->getJson('/api/v1/pembelians?'.http_build_query($query))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $purchaseInWarungA->id)
            ->assertJsonPath('data.0.user_id', (string) $managerA->id);
        $this->assertOperationResponseMatchesOpenApi($list, '/pembelians', 'get');

        $detail = $this->withToken($token)
            ->getJson('/api/v1/pembelians/'.$purchaseInWarungA->id)
            ->assertOk()
            ->assertJsonPath('data.user_id', (string) $managerA->id)
            ->assertJsonPath('data.rincian.0.nama_item', 'Belanja beras');
        $this->assertOperationResponseMatchesOpenApi($detail, '/pembelians/{id}', 'get');

        $foreignDetail = $this->withToken($token)
            ->getJson('/api/v1/pembelians/'.$purchaseInWarungB->id)
            ->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($foreignDetail, '/pembelians/{id}', 'get');
        $foreignDetail->assertJsonPath('code', 'NOT_FOUND');
        $this->assertDatabaseHas('pembelians', [
            'id' => $purchaseInWarungB->id,
            'warung_id' => $warungB->id,
            'user_id' => $managerB->id,
        ]);
    }

    public function test_summary_and_detailed_purchases_leave_sales_and_menu_unchanged(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'nama' => 'Nasi Goreng',
            'harga' => '15000.00',
        ]);
        $sale = Penjualan::factory()->create(['warung_id' => $warung->id, 'user_id' => $cashier->id]);
        $sale->rincian()->create([
            'warung_id' => $warung->id,
            'menu_id' => $menu->id,
            'nama_menu' => 'Nasi Goreng',
            'harga' => '15000.00',
            'qty' => '1.00',
            'diskon' => '0.00',
            'subtotal' => '15000.00',
        ]);
        $token = $manager->createToken('feature-test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/pembelians', [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '150000.00']],
        ], ['Idempotency-Key' => 'purchase-independent-summary-001'])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/pembelians', [
            'tanggal' => '2026-10-04T11:00:00+07:00',
            'rincian' => [
                ['nama_item' => 'Beras', 'qty' => '5.00', 'satuan' => 'kg', 'harga_satuan' => '15000.00'],
                ['nama_item' => 'Cabai', 'qty' => '0.50', 'satuan' => 'kg', 'harga_satuan' => '40000.00'],
            ],
        ], ['Idempotency-Key' => 'purchase-independent-detailed-001'])->assertCreated();

        $this->assertSame(2, Pembelian::query()->where('warung_id', $warung->id)->count());
        $this->assertSame(3, DB::table('pembelian_rincis')->where('warung_id', $warung->id)->count());
        $this->assertSame(1, Penjualan::query()->where('warung_id', $warung->id)->count());
        $this->assertSame(1, DB::table('penjualan_rincis')->where('warung_id', $warung->id)->count());
        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'nama' => 'Nasi Goreng',
            'harga' => '15000.00',
        ]);
        $this->assertDatabaseHas('penjualan_rincis', [
            'penjualan_id' => $sale->id,
            'menu_id' => $menu->id,
            'nama_menu' => 'Nasi Goreng',
            'harga' => '15000.00',
            'subtotal' => '15000.00',
        ]);
    }
}
