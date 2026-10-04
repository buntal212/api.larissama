<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('penjualans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warung_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('no_transaksi', 50);
            $table->string('idempotency_key', 255);
            $table->char('payload_hash', 64);
            $table->dateTime('tanggal');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('diskon', 15, 2)->default('0.00');
            $table->decimal('total', 15, 2);
            $table->decimal('bayar', 15, 2);
            $table->decimal('kembalian', 15, 2)->default('0.00');
            $table->string('metode_pembayaran', 30);
            $table->string('status', 20)->default('selesai');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['warung_id', 'id'], 'penjualans_warung_id_id_unique');
            $table->unique(['warung_id', 'no_transaksi'], 'penjualans_warung_nomor_unique');
            $table->unique(['warung_id', 'user_id', 'idempotency_key'], 'penjualans_idempotency_unique');
            $table->index(['warung_id', 'tanggal', 'id'], 'penjualans_warung_tanggal_idx');
            $table->index(['warung_id', 'status', 'tanggal'], 'penjualans_warung_status_tanggal_idx');
            $table->foreign(['warung_id', 'user_id'], 'penjualans_warung_user_fk')
                ->references(['warung_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penjualans');
    }
};
