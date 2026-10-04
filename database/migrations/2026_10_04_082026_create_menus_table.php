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
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warung_id')->constrained()->restrictOnDelete();
            $table->foreignId('kategori_menu_id')->constrained('kategori_menus')->restrictOnDelete();
            $table->string('kode', 30);
            $table->string('nama', 150);
            $table->decimal('harga', 15, 2);
            $table->decimal('harga_modal', 15, 2)->nullable();
            $table->string('gambar', 255)->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['warung_id', 'kode'], 'menus_warung_kode_unique');
            $table->index(['warung_id', 'aktif', 'nama', 'id'], 'menus_warung_aktif_nama_idx');
            $table->index(['warung_id', 'kategori_menu_id', 'aktif'], 'menus_warung_kategori_aktif_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
