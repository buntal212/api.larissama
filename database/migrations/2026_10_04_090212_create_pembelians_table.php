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
        Schema::create('pembelians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warung_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('no_transaksi', 50);
            $table->string('idempotency_key', 255);
            $table->char('payload_hash', 64);
            $table->dateTime('tanggal');
            $table->decimal('total', 15, 2);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['warung_id', 'id'], 'pembelians_warung_id_id_unique');
            $table->unique(['warung_id', 'no_transaksi'], 'pembelians_warung_nomor_unique');
            $table->unique(['warung_id', 'user_id', 'idempotency_key'], 'pembelians_idempotency_unique');
            $table->index(['warung_id', 'tanggal', 'id'], 'pembelians_warung_tanggal_idx');
            $table->foreign(['warung_id', 'user_id'], 'pembelians_warung_user_fk')
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
        Schema::dropIfExists('pembelians');
    }
};
