<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('penjualans', function (Blueprint $table): void {
            $table->string('nama_pelanggan', 150)->nullable()->after('no_transaksi');
            $table->string('status_pembayaran', 20)->default('lunas')->after('status');
            $table->dateTime('dibayar_pada')->nullable()->after('metode_pembayaran');
            $table->unsignedBigInteger('pembayaran_user_id')->nullable()->after('dibayar_pada');
            $table->string('pembayaran_idempotency_key', 255)->nullable()->after('pembayaran_user_id');
            $table->char('pembayaran_payload_hash', 64)->nullable()->after('pembayaran_idempotency_key');
            $table->dateTime('pembayaran_idempotency_expires_at', 6)->nullable()->after('pembayaran_payload_hash');
            $table->string('metode_pembayaran', 30)->nullable()->change();
            $table->unique(['warung_id', 'pembayaran_user_id', 'pembayaran_idempotency_key'], 'penjualans_payment_idempotency_unique');
            $table->index(['warung_id', 'status_pembayaran', 'tanggal', 'id'], 'penjualans_warung_payment_status_idx');
            $table->index(['warung_id', 'status_pembayaran', 'dibayar_pada', 'id'], 'penjualans_warung_paid_at_idx');
            $table->foreign(['warung_id', 'pembayaran_user_id'], 'penjualans_warung_payment_user_fk')
                ->references(['warung_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });

        DB::table('penjualans')
            ->where('status_pembayaran', 'lunas')
            ->whereNull('dibayar_pada')
            ->update([
                'dibayar_pada' => DB::raw('created_at'),
                'pembayaran_user_id' => DB::raw('user_id'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('penjualans')->where('status_pembayaran', 'belum_lunas')->exists()) {
            throw new LogicException('Migration rollback ditolak karena terdapat pesanan yang belum lunas.');
        }

        Schema::table('penjualans', function (Blueprint $table): void {
            $table->dropForeign('penjualans_warung_payment_user_fk');
            $table->dropUnique('penjualans_payment_idempotency_unique');
            $table->dropIndex('penjualans_warung_payment_status_idx');
            $table->dropIndex('penjualans_warung_paid_at_idx');
            $table->dropColumn([
                'nama_pelanggan',
                'status_pembayaran',
                'dibayar_pada',
                'pembayaran_user_id',
                'pembayaran_idempotency_key',
                'pembayaran_payload_hash',
                'pembayaran_idempotency_expires_at',
            ]);
            $table->string('metode_pembayaran', 30)->nullable(false)->change();
        });
    }
};
