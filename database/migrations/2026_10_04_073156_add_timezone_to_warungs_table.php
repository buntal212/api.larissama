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
        Schema::table('warungs', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('warungs')->whereNotNull('timezone')->exists()) {
            throw new RuntimeException(
                'Rollback timezone ditolak karena ada pengaturan zona waktu warung; gunakan migration maju.'
            );
        }

        Schema::table('warungs', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
