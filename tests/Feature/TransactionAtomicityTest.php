<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_second_sale_detail_rolls_back_header_and_first_detail(): void
    {
        $warung = Warung::factory()->create();
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menuA = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '15000.00']);
        $menuB = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '5000.00']);
        $token = $cashier->createToken('atomicity-test')->plainTextToken;
        $triggerName = 'test_fail_sale_'.Str::lower(Str::random(16));

        try {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$triggerName} BEFORE INSERT ON penjualan_rincis FOR EACH ROW
                BEGIN
                    IF NEW.menu_id = {$menuB->id} THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test detail insert failure';
                    END IF;
                END
                SQL);

            $response = $this->withToken($token)->postJson('/api/v1/penjualans', [
                'tanggal' => '2026-10-04T10:00:00+07:00',
                'bayar' => '20000.00',
                'metode_pembayaran' => 'cash',
                'rincian' => [
                    ['menu_id' => $menuA->id, 'qty' => '1.00'],
                    ['menu_id' => $menuB->id, 'qty' => '1.00'],
                ],
            ], ['Idempotency-Key' => 'sale-rollback-'.Str::uuid()]);
        } finally {
            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName}");
        }

        $response->assertInternalServerError()->assertDontSee('test detail insert failure');
        self::assertSame(0, Penjualan::query()->where('warung_id', $warung->id)->count());
        self::assertSame(0, DB::table('penjualan_rincis')->where('warung_id', $warung->id)->count());

        DB::table('menus')->where('warung_id', $warung->id)->delete();
        DB::table('kategori_menus')->where('warung_id', $warung->id)->delete();
        $cashier->tokens()->delete();
        $cashier->delete();
        $warung->delete();
    }

    public function test_failed_purchase_detail_rolls_back_header_and_first_detail(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('atomicity-test')->plainTextToken;
        $triggerName = 'test_fail_purchase_'.Str::lower(Str::random(16));

        try {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$triggerName} BEFORE INSERT ON pembelian_rincis FOR EACH ROW
                BEGIN
                    IF NEW.nama_item = 'Gagal uji' THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test detail insert failure';
                    END IF;
                END
                SQL);

            $response = $this->withToken($token)->postJson('/api/v1/pembelians', [
                'tanggal' => '2026-10-04T10:00:00+07:00',
                'rincian' => [
                    ['nama_item' => 'Beras', 'subtotal' => '75000.00'],
                    ['nama_item' => 'Gagal uji', 'subtotal' => '20000.00'],
                ],
            ], ['Idempotency-Key' => 'purchase-rollback-'.Str::uuid()]);
        } finally {
            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName}");
        }

        $response->assertInternalServerError()->assertDontSee('test detail insert failure');
        self::assertSame(0, Pembelian::query()->where('warung_id', $warung->id)->count());
        self::assertSame(0, DB::table('pembelian_rincis')->where('warung_id', $warung->id)->count());

        $manager->tokens()->delete();
        $manager->delete();
        $warung->delete();
    }

    public function beginDatabaseTransaction()
    {
        // Temporary DDL and transaction requests run only on disposable MySQL test storage.
    }
}
