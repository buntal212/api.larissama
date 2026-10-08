<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penjualans', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by_superadmin_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('pembayaran_superadmin_id')->nullable()->after('pembayaran_user_id');
            $table->unique(['warung_id', 'created_by_superadmin_id', 'idempotency_key'], 'penjualans_superadmin_idempotency_unique');
            $table->unique(['warung_id', 'pembayaran_superadmin_id', 'pembayaran_idempotency_key'], 'penjualans_superadmin_payment_idempotency_unique');
            $table->foreign('created_by_superadmin_id', 'penjualans_creator_superadmin_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('pembayaran_superadmin_id', 'penjualans_payment_superadmin_fk')->references('id')->on('users')->restrictOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->unsignedBigInteger('pembayaran_user_id')->nullable()->change();
        });

        Schema::table('pembelians', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by_superadmin_id')->nullable()->after('user_id');
            $table->unique(['warung_id', 'created_by_superadmin_id', 'idempotency_key'], 'pembelians_superadmin_idempotency_unique');
            $table->foreign('created_by_superadmin_id', 'pembelians_creator_superadmin_fk')->references('id')->on('users')->restrictOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        foreach (['penjualan_koreksis', 'penjualan_returs', 'pembelian_koreksis'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->unsignedBigInteger('superadmin_id')->nullable()->after('user_id');
                $table->foreign('superadmin_id', $tableName.'_superadmin_fk')->references('id')->on('users')->restrictOnDelete();
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }

        Schema::table('penjualan_koreksis', function (Blueprint $table): void {
            $table->unique(['warung_id', 'superadmin_id', 'jenis', 'idempotency_key'], 'penjualan_koreksi_superadmin_idempotency_unique');
        });
        Schema::table('penjualan_returs', function (Blueprint $table): void {
            $table->unique(['warung_id', 'superadmin_id', 'idempotency_key'], 'penjualan_retur_superadmin_idempotency_unique');
        });
        Schema::table('pembelian_koreksis', function (Blueprint $table): void {
            $table->unique(['warung_id', 'superadmin_id', 'jenis', 'idempotency_key'], 'pembelian_koreksi_superadmin_idempotency_unique');
        });

        DB::statement('ALTER TABLE penjualans ADD CONSTRAINT penjualans_creator_actor_chk CHECK ((user_id IS NOT NULL AND created_by_superadmin_id IS NULL) OR (user_id IS NULL AND created_by_superadmin_id IS NOT NULL))');
        DB::statement('ALTER TABLE penjualans ADD CONSTRAINT penjualans_payment_actor_chk CHECK ((dibayar_pada IS NULL AND pembayaran_user_id IS NULL AND pembayaran_superadmin_id IS NULL) OR (dibayar_pada IS NOT NULL AND ((pembayaran_user_id IS NOT NULL AND pembayaran_superadmin_id IS NULL) OR (pembayaran_user_id IS NULL AND pembayaran_superadmin_id IS NOT NULL))) )');
        DB::statement('ALTER TABLE pembelians ADD CONSTRAINT pembelians_creator_actor_chk CHECK ((user_id IS NOT NULL AND created_by_superadmin_id IS NULL) OR (user_id IS NULL AND created_by_superadmin_id IS NOT NULL))');
        DB::statement('ALTER TABLE penjualan_koreksis ADD CONSTRAINT penjualan_koreksi_actor_chk CHECK ((user_id IS NOT NULL AND superadmin_id IS NULL) OR (user_id IS NULL AND superadmin_id IS NOT NULL))');
        DB::statement('ALTER TABLE penjualan_returs ADD CONSTRAINT penjualan_retur_actor_chk CHECK ((user_id IS NOT NULL AND superadmin_id IS NULL) OR (user_id IS NULL AND superadmin_id IS NOT NULL))');
        DB::statement('ALTER TABLE pembelian_koreksis ADD CONSTRAINT pembelian_koreksi_actor_chk CHECK ((user_id IS NOT NULL AND superadmin_id IS NULL) OR (user_id IS NULL AND superadmin_id IS NOT NULL))');
    }

    public function down(): void
    {
        $checks = [
            'penjualans' => ['created_by_superadmin_id', 'pembayaran_superadmin_id'],
            'pembelians' => ['created_by_superadmin_id'],
            'penjualan_koreksis' => ['superadmin_id'],
            'penjualan_returs' => ['superadmin_id'],
            'pembelian_koreksis' => ['superadmin_id'],
        ];
        foreach ($checks as $table => $columns) {
            foreach ($columns as $column) {
                if (DB::table($table)->whereNotNull($column)->exists()) {
                    throw new LogicException('Rollback ditolak karena masih ada transaksi yang dicatat superadmin.');
                }
            }
        }

        DB::statement('ALTER TABLE penjualans DROP CHECK penjualans_creator_actor_chk');
        DB::statement('ALTER TABLE penjualans DROP CHECK penjualans_payment_actor_chk');
        DB::statement('ALTER TABLE pembelians DROP CHECK pembelians_creator_actor_chk');
        DB::statement('ALTER TABLE penjualan_koreksis DROP CHECK penjualan_koreksi_actor_chk');
        DB::statement('ALTER TABLE penjualan_returs DROP CHECK penjualan_retur_actor_chk');
        DB::statement('ALTER TABLE pembelian_koreksis DROP CHECK pembelian_koreksi_actor_chk');

        Schema::table('penjualan_koreksis', fn (Blueprint $table) => $table->dropUnique('penjualan_koreksi_superadmin_idempotency_unique'));
        Schema::table('penjualan_returs', fn (Blueprint $table) => $table->dropUnique('penjualan_retur_superadmin_idempotency_unique'));
        Schema::table('pembelian_koreksis', fn (Blueprint $table) => $table->dropUnique('pembelian_koreksi_superadmin_idempotency_unique'));
        foreach (['penjualan_koreksis', 'penjualan_returs', 'pembelian_koreksis'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign($tableName.'_superadmin_fk');
                $table->dropColumn('superadmin_id');
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
            });
        }
        Schema::table('pembelians', function (Blueprint $table): void {
            $table->dropUnique('pembelians_superadmin_idempotency_unique');
            $table->dropForeign('pembelians_creator_superadmin_fk');
            $table->dropColumn('created_by_superadmin_id');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
        Schema::table('penjualans', function (Blueprint $table): void {
            $table->dropUnique('penjualans_superadmin_idempotency_unique');
            $table->dropUnique('penjualans_superadmin_payment_idempotency_unique');
            $table->dropForeign('penjualans_creator_superadmin_fk');
            $table->dropForeign('penjualans_payment_superadmin_fk');
            $table->dropColumn(['created_by_superadmin_id', 'pembayaran_superadmin_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unsignedBigInteger('pembayaran_user_id')->nullable()->change();
        });
    }
};
