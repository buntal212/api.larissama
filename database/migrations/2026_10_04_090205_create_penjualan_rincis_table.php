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
        Schema::create('penjualan_rincis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warung_id');
            $table->unsignedBigInteger('penjualan_id');
            $table->unsignedBigInteger('menu_id');
            $table->string('nama_menu', 150);
            $table->decimal('harga', 15, 2);
            $table->decimal('qty', 10, 2);
            $table->decimal('diskon', 15, 2)->default('0.00');
            $table->decimal('subtotal', 15, 2);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['warung_id', 'penjualan_id'], 'penjualan_rincis_warung_header_idx');
            $table->index(['warung_id', 'menu_id'], 'penjualan_rincis_warung_menu_idx');
            $table->foreign(['warung_id', 'penjualan_id'], 'penjualan_rincis_warung_header_fk')
                ->references(['warung_id', 'id'])
                ->on('penjualans')
                ->restrictOnDelete();
            $table->foreign(['warung_id', 'menu_id'], 'penjualan_rincis_warung_menu_fk')
                ->references(['warung_id', 'id'])
                ->on('menus')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penjualan_rincis');
    }
};
