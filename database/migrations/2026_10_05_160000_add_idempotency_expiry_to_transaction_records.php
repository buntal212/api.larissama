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
        foreach (['penjualans', 'pembelians', 'pembelian_koreksis'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('idempotency_key', 255)->nullable()->change();
                $table->char('payload_hash', 64)->nullable()->change();
                $table->dateTime('idempotency_expires_at', 6)->nullable()->after('payload_hash');
            });

            DB::table($tableName)
                ->whereNotNull('idempotency_key')
                ->update(['idempotency_expires_at' => DB::raw('DATE_ADD(created_at, INTERVAL 7 DAY)')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['penjualans', 'pembelians', 'pembelian_koreksis'] as $tableName) {
            if (DB::table($tableName)->whereNull('idempotency_key')->exists()
                || DB::table($tableName)->whereNull('payload_hash')->exists()) {
                throw new RuntimeException("Cannot roll back idempotency expiry while {$tableName} contains expired key metadata.");
            }
        }

        foreach (['penjualans', 'pembelians', 'pembelian_koreksis'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('idempotency_key', 255)->nullable(false)->change();
                $table->char('payload_hash', 64)->nullable(false)->change();
                $table->dropColumn('idempotency_expires_at');
            });
        }
    }
};
