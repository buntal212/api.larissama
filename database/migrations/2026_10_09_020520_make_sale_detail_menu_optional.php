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
        Schema::table('penjualan_rincis', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('penjualan_rincis')->whereNull('menu_id')->exists()) {
            throw new RuntimeException('Cannot restore required menu_id while free-form sale lines exist.');
        }

        Schema::table('penjualan_rincis', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_id')->nullable(false)->change();
        });
    }
};
