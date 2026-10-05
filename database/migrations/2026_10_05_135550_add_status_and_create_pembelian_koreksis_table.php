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
        Schema::table('pembelians', function (Blueprint $table) {
            $table->string('status', 20)->default('tercatat')->after('total');
            $table->index(['warung_id', 'status', 'tanggal', 'id'], 'pembelians_warung_status_tanggal_idx');
        });

        Schema::create('pembelian_koreksis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warung_id');
            $table->unsignedBigInteger('pembelian_id');
            $table->unsignedBigInteger('user_id');
            $table->string('jenis', 20);
            $table->string('alasan', 1000);
            $table->json('sebelum');
            $table->json('sesudah');
            $table->string('idempotency_key', 255);
            $table->char('payload_hash', 64);
            $table->timestamps();

            $table->unique(['warung_id', 'user_id', 'jenis', 'idempotency_key'], 'pembelian_koreksi_idempotency_unique');
            $table->index(['warung_id', 'pembelian_id', 'id'], 'pembelian_koreksi_riwayat_idx');
            $table->foreign(['warung_id', 'pembelian_id'], 'pembelian_koreksi_header_fk')
                ->references(['warung_id', 'id'])
                ->on('pembelians')
                ->restrictOnDelete();
            $table->foreign(['warung_id', 'user_id'], 'pembelian_koreksi_user_fk')
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
        Schema::dropIfExists('pembelian_koreksis');

        Schema::table('pembelians', function (Blueprint $table) {
            $table->dropIndex('pembelians_warung_status_tanggal_idx');
            $table->dropColumn('status');
        });
    }
};
