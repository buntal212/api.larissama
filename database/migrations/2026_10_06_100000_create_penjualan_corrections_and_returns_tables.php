<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penjualan_koreksis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warung_id');
            $table->unsignedBigInteger('penjualan_id');
            $table->unsignedBigInteger('user_id');
            $table->string('jenis', 20);
            $table->string('alasan', 1000);
            $table->json('sebelum');
            $table->json('sesudah');
            $table->string('idempotency_key', 255)->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->dateTime('idempotency_expires_at', 6)->nullable();
            $table->timestamps();

            $table->unique(['warung_id', 'user_id', 'jenis', 'idempotency_key'], 'penjualan_koreksi_idempotency_unique');
            $table->index(['warung_id', 'penjualan_id', 'id'], 'penjualan_koreksi_riwayat_idx');
            $table->foreign(['warung_id', 'penjualan_id'], 'penjualan_koreksi_header_fk')
                ->references(['warung_id', 'id'])
                ->on('penjualans')
                ->restrictOnDelete();
            $table->foreign(['warung_id', 'user_id'], 'penjualan_koreksi_user_fk')
                ->references(['warung_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::create('penjualan_returs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warung_id');
            $table->unsignedBigInteger('penjualan_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('nominal', 15, 2);
            $table->string('alasan', 1000);
            $table->string('idempotency_key', 255)->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->dateTime('idempotency_expires_at', 6)->nullable();
            $table->timestamps();

            $table->unique(['warung_id', 'user_id', 'idempotency_key'], 'penjualan_retur_idempotency_unique');
            $table->index(['warung_id', 'created_at', 'id'], 'penjualan_retur_warung_created_idx');
            $table->index(['warung_id', 'penjualan_id', 'id'], 'penjualan_retur_sale_idx');
            $table->foreign(['warung_id', 'penjualan_id'], 'penjualan_retur_header_fk')
                ->references(['warung_id', 'id'])
                ->on('penjualans')
                ->restrictOnDelete();
            $table->foreign(['warung_id', 'user_id'], 'penjualan_retur_user_fk')
                ->references(['warung_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan_returs');
        Schema::dropIfExists('penjualan_koreksis');
    }
};
