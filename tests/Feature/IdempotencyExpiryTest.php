<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use App\Models\Warung;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdempotencyExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sale_key_replays_and_conflicts_within_seven_days_then_is_reusable_at_expiry(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-10-05T12:00:00Z'));
        $warung = Warung::factory()->create();
        $kasir = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '1000.00']);
        $token = $kasir->createToken('idempotency-seven-day-sale')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00Z',
            'bayar' => '1000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];
        $headers = ['Idempotency-Key' => 'expiry-sale-001'];

        $first = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers)->assertCreated();
        $saleId = (int) $first->json('data.id');
        $this->assertSame('2026-10-12 12:00:00.000000', DB::table('penjualans')->where('id', $saleId)->value('idempotency_expires_at'));

        Carbon::setTestNow(CarbonImmutable::parse('2026-10-12T11:59:59.999999Z'));
        $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers)
            ->assertCreated()->assertJsonPath('data.id', (string) $saleId);
        $changedPayload = $payload;
        $changedPayload['catatan'] = 'Isi berbeda';
        $this->withToken($token)->postJson('/api/v1/penjualans', $changedPayload, $headers)
            ->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertSame(1, DB::table('penjualans')->count());

        Carbon::setTestNow(CarbonImmutable::parse('2026-10-12T12:00:00Z'));
        $second = $this->withToken($token)->postJson('/api/v1/penjualans', $payload, $headers)->assertCreated();
        $newSaleId = (int) $second->json('data.id');
        $this->assertNotSame($saleId, $newSaleId);
        $this->assertDatabaseHas('penjualans', [
            'id' => $saleId,
            'idempotency_key' => null,
            'payload_hash' => null,
            'idempotency_expires_at' => null,
            'total' => '1000.00',
        ]);
        $this->assertDatabaseHas('penjualans', [
            'id' => $newSaleId,
            'idempotency_key' => 'expiry-sale-001',
            'idempotency_expires_at' => '2026-10-19 12:00:00.000000',
        ]);
        $this->assertSame(2, DB::table('penjualans')->count());
    }

    public function test_purchase_key_can_be_reused_after_seven_days_without_removing_purchase_history(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-10-05T12:00:00Z'));
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('idempotency-seven-day-purchase')->plainTextToken;
        $payload = [
            'tanggal' => '2026-10-04T10:00:00Z',
            'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '25000.00']],
        ];
        $headers = ['Idempotency-Key' => 'expiry-purchase-001'];

        $first = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, $headers)->assertCreated();
        $purchaseId = (int) $first->json('data.id');
        $this->assertSame('2026-10-12 12:00:00.000000', DB::table('pembelians')->where('id', $purchaseId)->value('idempotency_expires_at'));

        Carbon::setTestNow(CarbonImmutable::parse('2026-10-12T12:00:00Z'));
        $second = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, $headers)->assertCreated();
        $newPurchaseId = (int) $second->json('data.id');
        $this->assertNotSame($purchaseId, $newPurchaseId);
        $this->assertDatabaseHas('pembelians', [
            'id' => $purchaseId,
            'idempotency_key' => null,
            'payload_hash' => null,
            'idempotency_expires_at' => null,
            'total' => '25000.00',
        ]);
        $this->assertSame(2, DB::table('pembelians')->count());
    }

    public function test_purchase_correction_key_expires_without_deleting_append_only_audit_event(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-10-05T12:00:00Z'));
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $purchaseToken = $manager->createToken('idempotency-correction-purchase')->plainTextToken;
        $purchase = $this->withToken($purchaseToken)->postJson('/api/v1/pembelians', [
            'tanggal' => '2026-10-04T10:00:00Z',
            'catatan' => 'Catatan awal',
            'rincian' => [['nama_item' => 'Belanja pasar', 'subtotal' => '25000.00']],
        ], ['Idempotency-Key' => 'expiry-correction-create-001'])->assertCreated()->json('data');
        $token = $owner->createToken('idempotency-correction-owner')->plainTextToken;
        $headers = ['Idempotency-Key' => 'expiry-correction-001'];
        $firstPayload = ['alasan' => 'Catatan struk diperjelas.', 'catatan' => 'Keterangan pertama'];
        $first = $this->withToken($token)->patchJson('/api/v1/pembelians/'.$purchase['id'], $firstPayload, $headers)->assertCreated();
        $firstCorrectionId = (int) $first->json('data.id');
        $this->assertSame('2026-10-12 12:00:00.000000', DB::table('pembelian_koreksis')->where('id', $firstCorrectionId)->value('idempotency_expires_at'));

        Carbon::setTestNow(CarbonImmutable::parse('2026-10-12T11:59:59.999999Z'));
        $this->withToken($token)->patchJson('/api/v1/pembelians/'.$purchase['id'], $firstPayload, $headers)
            ->assertCreated()->assertJsonPath('data.id', (string) $firstCorrectionId);
        $secondPayload = ['alasan' => 'Keterangan perlu diperinci.', 'catatan' => 'Keterangan kedua'];
        Carbon::setTestNow(CarbonImmutable::parse('2026-10-12T12:00:00Z'));
        $second = $this->withToken($token)->patchJson('/api/v1/pembelians/'.$purchase['id'], $secondPayload, $headers)->assertCreated();
        $secondCorrectionId = (int) $second->json('data.id');
        $this->assertNotSame($firstCorrectionId, $secondCorrectionId);
        $this->assertDatabaseHas('pembelian_koreksis', [
            'id' => $firstCorrectionId,
            'idempotency_key' => null,
            'payload_hash' => null,
            'idempotency_expires_at' => null,
            'alasan' => $firstPayload['alasan'],
        ]);
        $this->assertDatabaseHas('pembelian_koreksis', [
            'id' => $secondCorrectionId,
            'idempotency_key' => 'expiry-correction-001',
            'alasan' => $secondPayload['alasan'],
        ]);
        $this->assertSame(2, DB::table('pembelian_koreksis')->where('pembelian_id', $purchase['id'])->count());
        $this->assertDatabaseHas('pembelians', ['id' => $purchase['id'], 'catatan' => 'Keterangan kedua']);
    }
}
