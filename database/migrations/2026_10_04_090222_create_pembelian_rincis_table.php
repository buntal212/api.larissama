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
        Schema::create('pembelian_rincis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warung_id');
            $table->unsignedBigInteger('pembelian_id');
            $table->string('nama_item', 150);
            $table->decimal('qty', 10, 2)->nullable();
            $table->string('satuan', 30)->nullable();
            $table->decimal('harga_satuan', 15, 2)->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->index(['warung_id', 'pembelian_id'], 'pembelian_rincis_warung_header_idx');
            $table->foreign(['warung_id', 'pembelian_id'], 'pembelian_rincis_warung_header_fk')
                ->references(['warung_id', 'id'])
                ->on('pembelians')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembelian_rincis');
    }
};
