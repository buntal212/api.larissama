<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Penjualan;
use App\Models\PenjualanRetur;
use App\Models\PenjualanRinci;
use App\Models\User;
use App\Models\Warung;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PenjualanCorrectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_correct_and_cancel_sales_with_reason_and_audit_within_72_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06T12:00:00Z'));
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '100.00']);
        $sale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHour());
        $token = $manager->createToken('sale-correction-test')->plainTextToken;
        $headers = ['Idempotency-Key' => 'sale-correction-key-001'];
        $payload = ['alasan' => 'Catatan salah', 'catatan' => 'Tanpa sambal'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans/{id}', 'patch');
        $correction = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$sale->id, $payload, $headers)->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($correction, '/penjualans/{id}', 'patch');
        $this->assertSame(null, $correction->json('data.sebelum.catatan'));
        $this->assertSame('Tanpa sambal', $correction->json('data.sesudah.catatan'));
        $this->assertDatabaseHas('penjualans', ['id' => $sale->id, 'catatan' => 'Tanpa sambal']);
        $this->assertDatabaseHas('penjualan_koreksis', ['penjualan_id' => $sale->id, 'jenis' => 'ubah', 'alasan' => 'Catatan salah']);

        $replay = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$sale->id, $payload, $headers)->assertCreated();
        $this->assertSame($correction->json('data.id'), $replay->json('data.id'));
        $this->assertOperationResponseMatchesOpenApi($replay, '/penjualans/{id}', 'patch');
        $conflict = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$sale->id, [
            'alasan' => 'Alasan berbeda', 'catatan' => 'Tanpa sambal',
        ], $headers)->assertConflict();
        $this->assertOperationResponseMatchesOpenApi($conflict, '/penjualans/{id}', 'patch');
        $this->assertSame('IDEMPOTENCY_KEY_REUSED', $conflict->json('code'));

        $cancelSale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHour());
        $cancelPayload = ['alasan' => 'Transaksi tercatat dua kali.'];
        $cancelHeaders = ['Idempotency-Key' => 'sale-cancel-key-001'];
        $this->assertOperationRequestMatchesOpenApi($cancelPayload, $cancelHeaders, '/penjualans/{id}/pembatalan', 'post');
        $cancel = $this->withToken($token)->postJson('/api/v1/penjualans/'.$cancelSale->id.'/pembatalan', $cancelPayload, $cancelHeaders)->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($cancel, '/penjualans/{id}/pembatalan', 'post');
        $this->assertSame('batal', $cancel->json('data.sesudah.status'));
        $this->assertDatabaseHas('penjualans', ['id' => $cancelSale->id, 'status' => 'batal']);

        $cancelReplay = $this->withToken($token)->postJson(
            '/api/v1/penjualans/'.$cancelSale->id.'/pembatalan',
            $cancelPayload,
            $cancelHeaders,
        )->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($cancelReplay, '/penjualans/{id}/pembatalan', 'post');
        $this->assertSame($cancel->json('data.id'), $cancelReplay->json('data.id'));

        $cancelKeyConflict = $this->withToken($token)->postJson(
            '/api/v1/penjualans/'.$cancelSale->id.'/pembatalan',
            ['alasan' => 'Alasan berbeda untuk key yang sama.'],
            $cancelHeaders,
        )->assertConflict();
        $this->assertOperationResponseMatchesOpenApi($cancelKeyConflict, '/penjualans/{id}/pembatalan', 'post');
        $this->assertSame('IDEMPOTENCY_KEY_REUSED', $cancelKeyConflict->json('code'));
        $this->assertDatabaseCount('penjualan_koreksis', 2);
    }

    public function test_sale_correction_is_allowed_at_72_hour_boundary_and_rejected_afterward(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10T12:00:00Z'));
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $token = $owner->createToken('sale-edit-window-test')->plainTextToken;
        $payload = ['alasan' => 'Koreksi tepat batas waktu', 'catatan' => 'Tepat 72 jam'];
        $headers = ['Idempotency-Key' => 'sale-edit-boundary-001'];

        $boundarySale = $this->sale(
            $warung,
            $cashier,
            $menu,
            CarbonImmutable::now('UTC')->subDays(10),
            CarbonImmutable::now('UTC')->subHours(72),
        );
        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans/{id}', 'patch');
        $boundary = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$boundarySale->id, $payload, $headers)->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($boundary, '/penjualans/{id}', 'patch');

        $expiredSale = $this->sale(
            $warung,
            $cashier,
            $menu,
            CarbonImmutable::now('UTC')->subDays(10),
            CarbonImmutable::now('UTC')->subHours(72)->subSecond(),
        );
        $expiredHeaders = ['Idempotency-Key' => 'sale-edit-expired-001'];
        $expired = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$expiredSale->id, $payload, $expiredHeaders)->assertConflict();
        $this->assertOperationResponseMatchesOpenApi($expired, '/penjualans/{id}', 'patch');
        $this->assertSame('BATAS_KOREKSI_TERLEWATI', $expired->json('code'));
        $this->assertDatabaseHas('penjualans', ['id' => $expiredSale->id, 'catatan' => null]);
        $this->assertDatabaseMissing('penjualan_koreksis', ['penjualan_id' => $expiredSale->id]);

        $boundaryCancelSale = $this->sale(
            $warung,
            $cashier,
            $menu,
            CarbonImmutable::now('UTC')->subDays(10),
            CarbonImmutable::now('UTC')->subHours(72),
        );
        $boundaryCancel = $this->withToken($token)->postJson(
            '/api/v1/penjualans/'.$boundaryCancelSale->id.'/pembatalan',
            ['alasan' => 'Dibatalkan tepat pada batas 72 jam.'],
            ['Idempotency-Key' => 'sale-cancel-boundary-001'],
        )->assertCreated();
        $this->assertOperationRequestMatchesOpenApi(
            ['alasan' => 'Dibatalkan tepat pada batas 72 jam.'],
            ['Idempotency-Key' => 'sale-cancel-boundary-001'],
            '/penjualans/{id}/pembatalan',
            'post',
        );
        $this->assertOperationResponseMatchesOpenApi($boundaryCancel, '/penjualans/{id}/pembatalan', 'post');
        $this->assertSame('batal', $boundaryCancelSale->fresh()->status);

        $expiredCancelSale = $this->sale(
            $warung,
            $cashier,
            $menu,
            CarbonImmutable::now('UTC')->subDays(10),
            CarbonImmutable::now('UTC')->subHours(72)->subSecond(),
        );
        $expiredCancel = $this->withToken($token)->postJson(
            '/api/v1/penjualans/'.$expiredCancelSale->id.'/pembatalan',
            ['alasan' => 'Pembatalan terlambat.'],
            ['Idempotency-Key' => 'sale-cancel-expired-001'],
        )->assertConflict();
        $this->assertOperationResponseMatchesOpenApi($expiredCancel, '/penjualans/{id}/pembatalan', 'post');
        $this->assertSame('BATAS_KOREKSI_TERLEWATI', $expiredCancel->json('code'));
        $this->assertSame('selesai', $expiredCancelSale->fresh()->status);
        $this->assertDatabaseMissing('penjualan_koreksis', ['penjualan_id' => $expiredCancelSale->id]);
    }

    public function test_correction_reprices_replacement_lines_from_active_menu_and_recomputes_totals(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08T12:00:00Z'));
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '25.00']);
        $sale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHour());
        $token = $owner->createToken('sale-line-correction-test')->plainTextToken;
        $payload = [
            'alasan' => 'Rincian salah input.',
            'diskon' => '2.00',
            'rincian' => [[
                'menu_id' => (string) $menu->id,
                'qty' => '2.00',
                'diskon' => '5.00',
                'catatan' => 'Porsi dibagi dua.',
            ]],
        ];
        $headers = ['Idempotency-Key' => 'sale-line-correction-001'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans/{id}', 'patch');
        $response = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$sale->id, $payload, $headers)->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans/{id}', 'patch');

        $this->assertSame('45.00', $response->json('data.sesudah.subtotal'));
        $this->assertSame('2.00', $response->json('data.sesudah.diskon'));
        $this->assertSame('43.00', $response->json('data.sesudah.total'));
        $this->assertSame('25.00', $response->json('data.sesudah.rincian.0.harga'));
        $this->assertDatabaseHas('penjualans', ['id' => $sale->id, 'total' => '43.00']);
        $this->assertDatabaseHas('penjualan_rincis', [
            'penjualan_id' => $sale->id,
            'menu_id' => $menu->id,
            'qty' => '2.00',
            'subtotal' => '45.00',
        ]);
    }

    public function test_sale_correction_rejects_future_date_and_accepts_backdate(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08T12:00:00Z'));
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $sale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHour());
        $token = $owner->createToken('sale-correction-date-test')->plainTextToken;

        $futurePayload = ['alasan' => 'Tanggal transaksi salah.', 'tanggal' => '2026-10-08T12:00:01Z'];
        $future = $this->withToken($token)->patchJson(
            '/api/v1/penjualans/'.$sale->id,
            $futurePayload,
            ['Idempotency-Key' => 'sale-correction-future-001'],
        )->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($future, '/penjualans/{id}', 'patch');
        $this->assertSame('VALIDATION_ERROR', $future->json('code'));
        $this->assertArrayHasKey('tanggal', $future->json('errors'));
        $this->assertSame('2026-10-08 11:00:00', $sale->fresh()->getRawOriginal('tanggal'));
        $this->assertDatabaseMissing('penjualan_koreksis', ['penjualan_id' => $sale->id]);

        $backdatePayload = ['alasan' => 'Tanggal transaksi dikoreksi mundur.', 'tanggal' => '2020-01-02T03:04:05+07:00'];
        $backdateHeaders = ['Idempotency-Key' => 'sale-correction-backdate-001'];
        $this->assertOperationRequestMatchesOpenApi($backdatePayload, $backdateHeaders, '/penjualans/{id}', 'patch');
        $backdate = $this->withToken($token)->patchJson(
            '/api/v1/penjualans/'.$sale->id,
            $backdatePayload,
            $backdateHeaders,
        )->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($backdate, '/penjualans/{id}', 'patch');
        $this->assertSame('2020-01-01T20:04:05.000000Z', $backdate->json('data.sesudah.tanggal'));
        $this->assertSame('2020-01-01 20:04:05', $sale->fresh()->getRawOriginal('tanggal'));
    }

    public function test_sale_correction_rejects_invalid_payment_and_discount_without_writes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08T12:00:00Z'));
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $sale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHour());
        $token = $owner->createToken('sale-correction-validation-test')->plainTextToken;

        $cases = [
            [
                'payload' => ['alasan' => 'Diskon melebihi subtotal.', 'diskon' => '100.01'],
                'key' => 'sale-correction-discount-limit-001',
                'errors' => ['diskon'],
            ],
            [
                'payload' => ['alasan' => 'Pembayaran non-tunai kurang.', 'bayar' => '99.00', 'metode_pembayaran' => 'qris'],
                'key' => 'sale-correction-payment-amount-001',
                'errors' => ['bayar'],
            ],
            [
                'payload' => ['alasan' => 'Metode pembayaran tidak dikirim.', 'bayar' => '100.00'],
                'key' => 'sale-correction-payment-pair-001',
                'errors' => ['bayar', 'metode_pembayaran'],
            ],
        ];

        foreach ($cases as $case) {
            $headers = ['Idempotency-Key' => $case['key']];
            $this->assertOperationRequestMatchesOpenApi($case['payload'], $headers, '/penjualans/{id}', 'patch');
            $response = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$sale->id, $case['payload'], $headers)
                ->assertUnprocessable();
            $this->assertOperationResponseMatchesOpenApi($response, '/penjualans/{id}', 'patch');
            $this->assertSame('VALIDATION_ERROR', $response->json('code'));
            foreach ($case['errors'] as $field) {
                $this->assertArrayHasKey($field, $response->json('errors'));
            }
        }

        $this->assertDatabaseHas('penjualans', [
            'id' => $sale->id,
            'subtotal' => '100.00',
            'diskon' => '0.00',
            'total' => '100.00',
            'bayar' => '100.00',
            'metode_pembayaran' => 'cash',
        ]);
        $this->assertSame(0, DB::table('penjualan_koreksis')->where('penjualan_id', $sale->id)->count());
    }

    public function test_sale_detail_reads_back_correction_and_return_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08T12:00:00Z'));
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $sale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHour());
        $token = $manager->createToken('sale-history-readback-test')->plainTextToken;

        $correction = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$sale->id, [
            'alasan' => 'Catatan dilengkapi.', 'catatan' => 'Tanpa sambal.',
        ], ['Idempotency-Key' => 'sale-history-correction-001'])->assertCreated();
        $return = $this->withToken($token)->postJson('/api/v1/penjualans/'.$sale->id.'/retur', [
            'nominal' => '10.00', 'alasan' => 'Sebagian nilai diretur.',
        ], ['Idempotency-Key' => 'sale-history-return-001'])->assertCreated();

        $detail = $this->withToken($token)->getJson('/api/v1/penjualans/'.$sale->id)->assertOk();
        $this->assertOperationResponseMatchesOpenApi($detail, '/penjualans/{id}', 'get');
        $this->assertSame($correction->json('data.id'), $detail->json('data.riwayat_koreksi.0.id'));
        $this->assertSame('ubah', $detail->json('data.riwayat_koreksi.0.jenis'));
        $this->assertSame('Catatan dilengkapi.', $detail->json('data.riwayat_koreksi.0.alasan'));
        $this->assertSame('Tanpa sambal.', $detail->json('data.riwayat_koreksi.0.sesudah.catatan'));
        $this->assertSame($return->json('data.id'), $detail->json('data.riwayat_retur.0.id'));
        $this->assertSame('10.00', $detail->json('data.riwayat_retur.0.nominal'));
        $this->assertSame('Sebagian nilai diretur.', $detail->json('data.riwayat_retur.0.alasan'));
        $this->assertSame('diretur_sebagian', $detail->json('data.status'));
    }

    public function test_only_owner_and_manager_can_mutate_sales_inside_their_warung(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08T12:00:00Z'));
        $warung = Warung::factory()->create();
        $foreignWarung = Warung::factory()->create();
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $foreignManager = User::factory()->create(['warung_id' => $foreignWarung->id, 'role' => 'manager']);
        $superadmin = User::factory()->create(['warung_id' => null, 'role' => 'superadmin']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $sale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHour());
        $payload = ['alasan' => 'Catatan salah.', 'catatan' => 'Koreksi akses.'];
        $headers = ['Idempotency-Key' => 'sale-denied-correction-001'];
        $cashierToken = $cashier->createToken('sale-denied-correction-test')->plainTextToken;
        $foreignToken = $foreignManager->createToken('sale-foreign-correction-test')->plainTextToken;
        $superadminToken = $superadmin->createToken('sale-superadmin-correction-test')->plainTextToken;

        $hidden = $this->withToken($foreignToken)->patchJson('/api/v1/penjualans/'.$sale->id, $payload, $headers)->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($hidden, '/penjualans/{id}', 'patch');
        Auth::forgetGuards();
        $denied = $this->withToken($cashierToken)->patchJson('/api/v1/penjualans/'.$sale->id, $payload, $headers)->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($denied, '/penjualans/{id}', 'patch');

        $deniedActors = [
            ['token' => $cashierToken, 'status' => 403, 'label' => 'cashier'],
            ['token' => $foreignToken, 'status' => 404, 'label' => 'foreign-manager'],
            ['token' => $superadminToken, 'status' => 403, 'label' => 'superadmin'],
        ];
        $deniedOperations = [
            [
                'path' => '/penjualans/{id}/pembatalan',
                'method' => 'post',
                'request_path' => '/pembatalan',
                'payload' => ['alasan' => 'Uji akses pembatalan.'],
            ],
            [
                'path' => '/penjualans/{id}/retur',
                'method' => 'post',
                'request_path' => '/retur',
                'payload' => ['nominal' => '10.00', 'alasan' => 'Uji akses retur.'],
            ],
        ];

        foreach ($deniedOperations as $operation) {
            $operationSale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHour());

            foreach ($deniedActors as $actor) {
                $headers = ['Idempotency-Key' => 'sale-'.$actor['label'].$operation['request_path']];
                $request = $this->withToken($actor['token'])->postJson(
                    '/api/v1/penjualans/'.$operationSale->id.$operation['request_path'],
                    $operation['payload'],
                    $headers,
                )->assertStatus($actor['status']);

                $this->assertOperationResponseMatchesOpenApi($request, $operation['path'], $operation['method']);
                Auth::forgetGuards();
            }

            $this->assertSame('selesai', $operationSale->fresh()->status);
            $this->assertDatabaseMissing('penjualan_koreksis', ['penjualan_id' => $operationSale->id]);
            $this->assertDatabaseMissing('penjualan_returs', ['penjualan_id' => $operationSale->id]);
        }

        $this->assertSame(null, $sale->refresh()->catatan);
        $this->assertDatabaseCount('penjualan_koreksis', 0);
    }

    public function test_partial_and_full_returns_are_audited_capped_and_reported_on_return_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07T01:00:00Z'));
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $sale = $this->sale($warung, $cashier, $menu, CarbonImmutable::parse('2026-10-01T10:00:00Z'));
        $token = $manager->createToken('sale-return-test')->plainTextToken;

        foreach ([['nominal' => '50.00', 'alasan' => 'Satu menu dikembalikan.', 'key' => 'sale-return-half-001']] as $returnRequest) {
            $payload = ['nominal' => $returnRequest['nominal'], 'alasan' => $returnRequest['alasan']];
            $headers = ['Idempotency-Key' => $returnRequest['key']];
            $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans/{id}/retur', 'post');
            $response = $this->withToken($token)->postJson('/api/v1/penjualans/'.$sale->id.'/retur', $payload, $headers)->assertCreated();
            $this->assertOperationResponseMatchesOpenApi($response, '/penjualans/{id}/retur', 'post');
            $this->assertSame($returnRequest['nominal'], $response->json('data.nominal'));
        }

        $overRemaining = $this->withToken($token)->postJson('/api/v1/penjualans/'.$sale->id.'/retur', [
            'nominal' => '50.01', 'alasan' => 'Nominal melebihi sisa.',
        ], ['Idempotency-Key' => 'sale-return-over-remaining-001'])->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($overRemaining, '/penjualans/{id}/retur', 'post');
        $this->assertArrayHasKey('nominal', $overRemaining->json('errors'));

        $lastReturnPayload = ['nominal' => '50.00', 'alasan' => 'Sisa menu dikembalikan.'];
        $lastReturnHeaders = ['Idempotency-Key' => 'sale-return-rest-001'];
        $this->assertOperationRequestMatchesOpenApi($lastReturnPayload, $lastReturnHeaders, '/penjualans/{id}/retur', 'post');
        $lastReturn = $this->withToken($token)->postJson('/api/v1/penjualans/'.$sale->id.'/retur', $lastReturnPayload, $lastReturnHeaders)->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($lastReturn, '/penjualans/{id}/retur', 'post');

        $this->assertSame('diretur_penuh', $sale->refresh()->status);
        $this->assertSame('100.00', (string) PenjualanRetur::query()->where('penjualan_id', $sale->id)->sum('nominal'));
        $this->assertDatabaseCount('penjualan_returs', 2);

        $this->assertOperationQueryMatchesOpenApi(['date_from' => '2026-10-07', 'date_to' => '2026-10-07'], '/laporan/penjualan', 'get');
        $report = $this->withToken($token)->getJson('/api/v1/laporan/penjualan?date_from=2026-10-07&date_to=2026-10-07')->assertOk();
        $this->assertOperationResponseMatchesOpenApi($report, '/laporan/penjualan', 'get');
        $this->assertSame(0, $report->json('data.jumlah_transaksi'));
        $this->assertSame(2, $report->json('data.jumlah_retur'));
        $this->assertSame('100.00', $report->json('data.total_retur'));
        $this->assertSame('-100.00', $report->json('data.total_pendapatan'));

        $excessive = $this->withToken($token)->postJson('/api/v1/penjualans/'.$sale->id.'/retur', [
            'nominal' => '0.01', 'alasan' => 'Retur melebihi saldo.',
        ], ['Idempotency-Key' => 'sale-return-excess-001'])->assertConflict();
        $this->assertOperationResponseMatchesOpenApi($excessive, '/penjualans/{id}/retur', 'post');
    }

    public function test_returned_sales_cannot_be_corrected_or_cancelled_and_cancelled_sales_cannot_be_returned(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08T12:00:00Z'));
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $token = $manager->createToken('sale-state-conflict-test')->plainTextToken;

        $returnedSale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHour());
        $this->withToken($token)->postJson('/api/v1/penjualans/'.$returnedSale->id.'/retur', [
            'nominal' => '10.00', 'alasan' => 'Sebagian dikembalikan.',
        ], ['Idempotency-Key' => 'sale-state-return-001'])->assertCreated();

        $correction = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$returnedSale->id, [
            'alasan' => 'Koreksi setelah retur.', 'catatan' => 'Tidak boleh diterapkan.',
        ], ['Idempotency-Key' => 'sale-state-correction-001'])->assertConflict();
        $this->assertOperationResponseMatchesOpenApi($correction, '/penjualans/{id}', 'patch');
        $this->assertSame('PENJUALAN_TIDAK_AKTIF', $correction->json('code'));

        $cancelReturned = $this->withToken($token)->postJson(
            '/api/v1/penjualans/'.$returnedSale->id.'/pembatalan',
            ['alasan' => 'Batalkan setelah retur.'],
            ['Idempotency-Key' => 'sale-state-cancel-returned-001'],
        )->assertConflict();
        $this->assertOperationResponseMatchesOpenApi($cancelReturned, '/penjualans/{id}/pembatalan', 'post');
        $this->assertSame('PENJUALAN_TIDAK_AKTIF', $cancelReturned->json('code'));
        $this->assertSame('diretur_sebagian', $returnedSale->fresh()->status);
        $this->assertSame(0, DB::table('penjualan_koreksis')->where('penjualan_id', $returnedSale->id)->count());
        $this->assertSame(1, DB::table('penjualan_returs')->where('penjualan_id', $returnedSale->id)->count());

        $cancelledSale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHour());
        $this->withToken($token)->postJson(
            '/api/v1/penjualans/'.$cancelledSale->id.'/pembatalan',
            ['alasan' => 'Transaksi dibatalkan.'],
            ['Idempotency-Key' => 'sale-state-cancel-001'],
        )->assertCreated();

        $returnCancelled = $this->withToken($token)->postJson('/api/v1/penjualans/'.$cancelledSale->id.'/retur', [
            'nominal' => '10.00', 'alasan' => 'Retur setelah dibatalkan.',
        ], ['Idempotency-Key' => 'sale-state-return-cancelled-001'])->assertConflict();
        $this->assertOperationResponseMatchesOpenApi($returnCancelled, '/penjualans/{id}/retur', 'post');
        $this->assertSame('PENJUALAN_DIBATALKAN', $returnCancelled->json('code'));
        $this->assertSame('batal', $cancelledSale->fresh()->status);
        $this->assertDatabaseMissing('penjualan_returs', ['penjualan_id' => $cancelledSale->id]);
    }

    private function sale(
        Warung $warung,
        User $cashier,
        Menu $menu,
        CarbonImmutable $createdAt,
        ?CarbonImmutable $paidAt = null,
    ): Penjualan {
        $sale = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
            'tanggal' => $createdAt,
            'dibayar_pada' => $paidAt ?? $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'subtotal' => '100.00',
            'total' => '100.00',
            'bayar' => '100.00',
            'kembalian' => '0.00',
        ]);
        PenjualanRinci::factory()->create([
            'warung_id' => $warung->id,
            'penjualan_id' => $sale->id,
            'menu_id' => $menu->id,
            'nama_menu' => $menu->nama,
            'harga' => '100.00',
            'qty' => '1.00',
            'subtotal' => '100.00',
        ]);

        return $sale;
    }
}
