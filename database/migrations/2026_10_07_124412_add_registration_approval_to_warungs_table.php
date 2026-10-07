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
            $table->boolean('pendaftaran_disetujui')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('warungs')->where('pendaftaran_disetujui', false)->exists()) {
            throw new RuntimeException(
                'Rollback persetujuan pendaftaran ditolak karena ada warung yang masih menunggu persetujuan.'
            );
        }

        Schema::table('warungs', function (Blueprint $table) {
            $table->dropColumn('pendaftaran_disetujui');
        });
    }
};
