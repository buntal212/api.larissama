<?php

namespace Tests\Feature;

use App\Models\Pembelian;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PembelianApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_record_purchase_using_only_item_name_and_amount(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/pembelians', [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '150000.00']],
        ], ['Idempotency-Key' => 'purchase-summary-001']);

        $response->assertCreated()
            ->assertJsonPath('data.total', '150000.00')
            ->assertJsonPath('data.rincian.0.nama_item', 'Belanja di pasar')
            ->assertJsonPath('data.rincian.0.qty', null)
            ->assertJsonPath('data.rincian.0.satuan', null)
            ->assertJsonPath('data.rincian.0.harga_satuan', null);

        $purchaseId = (int) $response->json('data.id');
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

        $response = $this->withToken($token)->postJson('/api/v1/pembelians', [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'rincian' => [
                ['nama_item' => 'Beras', 'qty' => '5.00', 'satuan' => 'kg', 'harga_satuan' => '15000.00', 'subtotal' => '75000.00'],
                ['nama_item' => 'Cabai', 'qty' => '0.50', 'satuan' => 'kg', 'harga_satuan' => '40000.00', 'subtotal' => '20000.00'],
            ],
        ], ['Idempotency-Key' => 'purchase-detail-001']);

        $response->assertCreated()
            ->assertJsonPath('data.total', '95000.00')
            ->assertJsonCount(2, 'data.rincian')
            ->assertJsonPath('data.rincian.0.subtotal', '75000.00')
            ->assertJsonPath('data.rincian.1.subtotal', '20000.00');
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

        $first = $this->withToken($token)->postJson('/api/v1/pembelians', $payload, [
            'Idempotency-Key' => 'purchase-retry-001',
        ])->assertCreated();
        $purchaseId = $first->json('data.id');

        $this->withToken($token)->postJson('/api/v1/pembelians', $payload, [
            'Idempotency-Key' => 'purchase-retry-001',
        ])->assertCreated()->assertJsonPath('data.id', $purchaseId);

        $payload['rincian'][0]['subtotal'] = '151000.00';
        $this->withToken($token)->postJson('/api/v1/pembelians', $payload, [
            'Idempotency-Key' => 'purchase-retry-001',
        ])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');

        $this->assertSame(1, Pembelian::query()->where('warung_id', $warung->id)->count());
        $this->assertSame('150000.00', Pembelian::query()->whereKey($purchaseId)->value('total'));
    }

    public function test_purchase_rejects_partial_quantity_price_pair_without_writing(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('feature-test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/pembelians', [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'rincian' => [['nama_item' => 'Gula', 'qty' => '2.00', 'subtotal' => '30000.00']],
        ], ['Idempotency-Key' => 'purchase-invalid-pair'])->assertUnprocessable();

        $this->assertSame(0, Pembelian::query()->count());
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

    public function test_cashier_cannot_list_or_create_purchases(): void
    {
        $warung = Warung::factory()->create();
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $token = $cashier->createToken('feature-test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/pembelians')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/pembelians', [], [
            'Idempotency-Key' => 'purchase-cashier-denied',
        ])->assertForbidden();
        $this->assertSame(0, Pembelian::query()->count());
    }
}
