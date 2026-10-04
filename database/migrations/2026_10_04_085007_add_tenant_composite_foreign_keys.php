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
        Schema::table('users', function (Blueprint $table) {
            $table->unique(['warung_id', 'id'], 'users_warung_id_id_unique');
        });

        Schema::table('kategori_menus', function (Blueprint $table) {
            $table->unique(['warung_id', 'id'], 'kategori_warung_id_id_unique');
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->unique(['warung_id', 'id'], 'menus_warung_id_id_unique');
            $table->dropForeign('menus_kategori_menu_id_foreign');

            $table->foreign(['warung_id', 'kategori_menu_id'], 'menus_warung_kategori_fk')
                ->references(['warung_id', 'id'])
                ->on('kategori_menus')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropForeign('menus_warung_kategori_fk');
            $table->foreign('kategori_menu_id', 'menus_kategori_menu_id_foreign')
                ->references('id')
                ->on('kategori_menus')
                ->restrictOnDelete();
            $table->dropUnique('menus_warung_id_id_unique');
        });

        Schema::table('kategori_menus', function (Blueprint $table) {
            $table->dropUnique('kategori_warung_id_id_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_warung_id_id_unique');
        });
    }
};
