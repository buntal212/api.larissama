<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use App\Models\Warung;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SuperadminTransactionWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_pay_correct_cancel_and_return_sales_in_selected_warung(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08T12:00:00Z'));
        $warung = Warung::factory()->create();
        $otherWarung = Warung::factory()->create();
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '10000.00']);
        $superadmin = User::factory()->create(['warung_id' => null, 'role' => 'superadmin']);
        $token = $superadmin->createToken('superadmin-transaction-write')->plainTextToken;

        $paidPayload = [
            'warung_id' => (string) $warung->id,
            'tanggal' => '2026-10-08T11:00:00Z',
            'bayar' => '10000.00',
            'metode_pembayaran' => 'qris',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];
        $this->assertOperationRequestMatchesOpenApi($paidPayload, ['Idempotency-Key' => 'sa-sale-create-001'], '/penjualans', 'post');
        $paid = $this->withToken($token)->postJson('/api/v1/penjualans', $paidPayload, ['Idempotency-Key' => 'sa-sale-create-001'])
            ->assertCreated()
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.created_by_superadmin_id', (string) $superadmin->id)
            ->assertJsonPath('data.pembayaran_user_id', null)
            ->assertJsonPath('data.pembayaran_superadmin_id', (string) $superadmin->id);
        $this->assertOperationResponseMatchesOpenApi($paid, '/penjualans', 'post');
        $replayedPaid = $this->withToken($token)->postJson('/api/v1/penjualans', $paidPayload, ['Idempotency-Key' => 'sa-sale-create-001'])->assertCreated();
        $this->assertSame($paid->json('data.id'), $replayedPaid->json('data.id'));

        $pendingPayload = [
            'warung_id' => (string) $warung->id,
            'tanggal' => '2026-10-08T11:05:00Z',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '2.00']],
        ];
        $pending = $this->withToken($token)->postJson('/api/v1/penjualans', $pendingPayload, ['Idempotency-Key' => 'sa-sale-create-002'])->assertCreated();
        $saleId = $pending->json('data.id');
        $payBody = ['warung_id' => (string) $warung->id, 'bayar' => '20000.00', 'metode_pembayaran' => 'transfer'];
        $this->assertOperationRequestMatchesOpenApi($payBody, ['Idempotency-Key' => 'sa-sale-pay-001'], '/penjualans/{id}/pembayaran', 'post');
        $payment = $this->withToken($token)->postJson('/api/v1/penjualans/'.$saleId.'/pembayaran', $payBody, ['Idempotency-Key' => 'sa-sale-pay-001'])->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($payment, '/penjualans/{id}/pembayaran', 'post');
        $replayedPayment = $this->withToken($token)->postJson('/api/v1/penjualans/'.$saleId.'/pembayaran', $payBody, ['Idempotency-Key' => 'sa-sale-pay-001'])->assertCreated();
        $this->assertSame($payment->json('data.id'), $replayedPayment->json('data.id'));
        $this->assertDatabaseHas('penjualans', ['id' => $saleId, 'pembayaran_user_id' => null, 'pembayaran_superadmin_id' => $superadmin->id]);

        $editBody = ['warung_id' => (string) $warung->id, 'alasan' => 'Nama pelanggan diperbaiki.', 'nama_pelanggan' => 'Dina'];
        $this->assertOperationRequestMatchesOpenApi($editBody, ['Idempotency-Key' => 'sa-sale-correct-001'], '/penjualans/{id}', 'patch');
        $correction = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$saleId, $editBody, ['Idempotency-Key' => 'sa-sale-correct-001'])->assertCreated();
        $this->assertSame((string) $superadmin->id, $correction->json('data.superadmin_id'));
        $this->assertNull($correction->json('data.user_id'));
        $this->assertOperationResponseMatchesOpenApi($correction, '/penjualans/{id}', 'patch');
        $replayedCorrection = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$saleId, $editBody, ['Idempotency-Key' => 'sa-sale-correct-001'])->assertCreated();
        $this->assertSame($correction->json('data.id'), $replayedCorrection->json('data.id'));

        $returnBody = ['warung_id' => (string) $warung->id, 'nominal' => '5000.00', 'alasan' => 'Sebagian pesanan dikembalikan.'];
        $this->assertOperationRequestMatchesOpenApi($returnBody, ['Idempotency-Key' => 'sa-sale-return-001'], '/penjualans/{id}/retur', 'post');
        $return = $this->withToken($token)->postJson('/api/v1/penjualans/'.$saleId.'/retur', $returnBody, ['Idempotency-Key' => 'sa-sale-return-001'])->assertCreated();
        $this->assertSame((string) $superadmin->id, $return->json('data.superadmin_id'));
        $this->assertOperationResponseMatchesOpenApi($return, '/penjualans/{id}/retur', 'post');

        $cancelBody = ['warung_id' => (string) $warung->id, 'alasan' => 'Pesanan dibuat untuk meja yang salah.'];
        $cancelSale = $this->withToken($token)->postJson('/api/v1/penjualans/'.$paid->json('data.id').'/pembatalan', $cancelBody, ['Idempotency-Key' => 'sa-sale-cancel-001'])->assertCreated();
        $this->assertSame((string) $superadmin->id, $cancelSale->json('data.superadmin_id'));

        $wrongScope = $this->withToken($token)->patchJson('/api/v1/penjualans/'.$saleId, [
            'warung_id' => (string) $otherWarung->id,
            'alasan' => 'Tidak boleh lintas warung.',
            'catatan' => 'Tidak diubah.',
        ], ['Idempotency-Key' => 'sa-sale-cross-tenant-001'])->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($wrongScope, '/penjualans/{id}', 'patch');
    }

    public function test_superadmin_can_create_correct_and_cancel_purchases_and_actor_is_audited(): void
    {
        $warung = Warung::factory()->create();
        $superadmin = User::factory()->create(['warung_id' => null, 'role' => 'superadmin']);
        $token = $superadmin->createToken('superadmin-purchase-write')->plainTextToken;
        $createBody = [
            'warung_id' => (string) $warung->id,
            'tanggal' => '2026-10-08T10:00:00Z',
            'rincian' => [['nama_item' => 'Belanja pasar', 'subtotal' => '25000.00']],
        ];
        $this->assertOperationRequestMatchesOpenApi($createBody, ['Idempotency-Key' => 'sa-purchase-create-001'], '/pembelians', 'post');
        $created = $this->withToken($token)->postJson('/api/v1/pembelians', $createBody, ['Idempotency-Key' => 'sa-purchase-create-001'])
            ->assertCreated()
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.created_by_superadmin_id', (string) $superadmin->id);
        $this->assertOperationResponseMatchesOpenApi($created, '/pembelians', 'post');
        $replayedCreate = $this->withToken($token)->postJson('/api/v1/pembelians', $createBody, ['Idempotency-Key' => 'sa-purchase-create-001'])->assertCreated();
        $this->assertSame($created->json('data.id'), $replayedCreate->json('data.id'));

        $purchaseId = $created->json('data.id');
        $editBody = ['warung_id' => (string) $warung->id, 'alasan' => 'Nominal struk dikoreksi.', 'rincian' => [['nama_item' => 'Belanja pasar', 'subtotal' => '30000.00']]];
        $this->assertOperationRequestMatchesOpenApi($editBody, ['Idempotency-Key' => 'sa-purchase-correct-001'], '/pembelians/{id}', 'patch');
        $correction = $this->withToken($token)->patchJson('/api/v1/pembelians/'.$purchaseId, $editBody, ['Idempotency-Key' => 'sa-purchase-correct-001'])->assertCreated();
        $this->assertNull($correction->json('data.user_id'));
        $this->assertSame((string) $superadmin->id, $correction->json('data.superadmin_id'));
        $this->assertOperationResponseMatchesOpenApi($correction, '/pembelians/{id}', 'patch');

        $cancelBody = ['warung_id' => (string) $warung->id, 'alasan' => 'Transaksi pembelian rangkap.'];
        $this->assertOperationRequestMatchesOpenApi($cancelBody, ['Idempotency-Key' => 'sa-purchase-cancel-001'], '/pembelians/{id}/pembatalan', 'post');
        $cancel = $this->withToken($token)->postJson('/api/v1/pembelians/'.$purchaseId.'/pembatalan', $cancelBody, ['Idempotency-Key' => 'sa-purchase-cancel-001'])->assertCreated();
        $this->assertSame((string) $superadmin->id, $cancel->json('data.superadmin_id'));
        $this->assertOperationResponseMatchesOpenApi($cancel, '/pembelians/{id}/pembatalan', 'post');
        $this->assertDatabaseHas('pembelian_koreksis', ['pembelian_id' => $purchaseId, 'user_id' => null, 'superadmin_id' => $superadmin->id]);
        $this->assertSame(2, DB::table('pembelian_koreksis')->where('pembelian_id', $purchaseId)->count());
    }

    public function test_tenant_cannot_inject_target_warung_into_transaction_write(): void
    {
        $warung = Warung::factory()->create();
        $otherWarung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('tenant-warung-injection')->plainTextToken;
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $response = $this->withToken($token)->postJson('/api/v1/penjualans', [
            'warung_id' => (string) $otherWarung->id,
            'tanggal' => '2026-10-08T10:00:00Z',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ], ['Idempotency-Key' => 'tenant-warung-injection-001'])->assertUnprocessable();

        $this->assertArrayHasKey('warung_id', $response->json('errors'));
        $this->assertDatabaseCount('penjualans', 0);

        $nullSelector = $this->withToken($token)->postJson('/api/v1/penjualans', [
            'warung_id' => null,
            'tanggal' => '2026-10-08T10:00:00Z',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ], ['Idempotency-Key' => 'tenant-warung-null-injection-001'])->assertUnprocessable();
        $this->assertArrayHasKey('warung_id', $nullSelector->json('errors'));
        $this->assertDatabaseCount('penjualans', 0);
    }

    public function test_rollback_refuses_to_remove_superadmin_actor_columns_after_a_superadmin_transaction(): void
    {
        $warung = Warung::factory()->create();
        $superadmin = User::factory()->create(['warung_id' => null, 'role' => 'superadmin']);
        DB::table('pembelians')->insert([
            'warung_id' => $warung->id,
            'user_id' => null,
            'created_by_superadmin_id' => $superadmin->id,
            'no_transaksi' => 'PB-SUPERADMIN-ROLLBACK',
            'idempotency_key' => 'superadmin-rollback-key',
            'payload_hash' => str_repeat('a', 64),
            'idempotency_expires_at' => now()->addDays(7),
            'tanggal' => now()->toDateTimeString(),
            'total' => '1.00',
            'status' => 'tercatat',
            'catatan' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_10_08_090000_allow_superadmin_transaction_writes.php');

        try {
            $migration->down();
            $this->fail('Rollback must preserve transactions attributed to superadmin.');
        } catch (\LogicException $exception) {
            $this->assertSame('Rollback ditolak karena masih ada transaksi yang dicatat superadmin.', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('pembelians', 'created_by_superadmin_id'));
        $this->assertDatabaseHas('pembelians', ['created_by_superadmin_id' => $superadmin->id]);
    }
}
