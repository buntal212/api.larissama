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
        Schema::create('kategori_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warung_id')->constrained()->restrictOnDelete();
            $table->string('nama', 100);
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['warung_id', 'aktif', 'urutan', 'id'], 'kategori_warung_aktif_urutan_idx');
            $table->index(['warung_id', 'nama'], 'kategori_warung_nama_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kategori_menus');
    }
};
