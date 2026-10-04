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
        if (DB::table('users')->exists()) {
            throw new RuntimeException(
                'Migration users dihentikan: isi username, role, dan warung_id untuk setiap user lama sebelum migrasi.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'nama');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('nama', 150)->change();
            $table->string('username', 100)->unique();
            $table->foreignId('warung_id')->nullable()->constrained('warungs')->restrictOnDelete();
            $table->string('role', 30);
            $table->boolean('aktif')->default(true);
            $table->string('email', 150)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('users')->exists() || DB::table('personal_access_tokens')->exists()) {
            throw new RuntimeException(
                'Rollback users ditolak karena ada data user atau token; gunakan migration maju untuk perubahan schema.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['warung_id']);
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'warung_id', 'role', 'aktif']);
            $table->renameColumn('nama', 'name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
