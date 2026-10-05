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

        $boundarySale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHours(72));
        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans/{id}', 'patch');
        $boundary = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$boundarySale->id, $payload, $headers)->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($boundary, '/penjualans/{id}', 'patch');

        $expiredSale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHours(72)->subSecond());
        $expiredHeaders = ['Idempotency-Key' => 'sale-edit-expired-001'];
        $expired = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$expiredSale->id, $payload, $expiredHeaders)->assertConflict();
        $this->assertOperationResponseMatchesOpenApi($expired, '/penjualans/{id}', 'patch');
        $this->assertSame('BATAS_KOREKSI_TERLEWATI', $expired->json('code'));
        $this->assertDatabaseHas('penjualans', ['id' => $expiredSale->id, 'catatan' => null]);
        $this->assertDatabaseMissing('penjualan_koreksis', ['penjualan_id' => $expiredSale->id]);

        $boundaryCancelSale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHours(72));
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

    public function test_only_owner_and_manager_can_mutate_sales_inside_their_warung(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08T12:00:00Z'));
        $warung = Warung::factory()->create();
        $foreignWarung = Warung::factory()->create();
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $foreignManager = User::factory()->create(['warung_id' => $foreignWarung->id, 'role' => 'manager']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $sale = $this->sale($warung, $cashier, $menu, CarbonImmutable::now('UTC')->subHour());
        $payload = ['alasan' => 'Catatan salah.', 'catatan' => 'Koreksi akses.'];
        $headers = ['Idempotency-Key' => 'sale-denied-correction-001'];
        $cashierToken = $cashier->createToken('sale-denied-correction-test')->plainTextToken;
        $foreignToken = $foreignManager->createToken('sale-foreign-correction-test')->plainTextToken;

        $hidden = $this->withToken($foreignToken)->patchJson('/api/v1/penjualans/'.$sale->id, $payload, $headers)->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($hidden, '/penjualans/{id}', 'patch');
        Auth::forgetGuards();
        $denied = $this->withToken($cashierToken)->patchJson('/api/v1/penjualans/'.$sale->id, $payload, $headers)->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($denied, '/penjualans/{id}', 'patch');

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

    private function sale(Warung $warung, User $cashier, Menu $menu, CarbonImmutable $createdAt): Penjualan
    {
        $sale = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
            'tanggal' => $createdAt,
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
