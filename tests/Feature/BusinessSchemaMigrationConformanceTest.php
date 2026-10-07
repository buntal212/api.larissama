<?php

namespace Tests\Feature;

use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BusinessSchemaMigrationConformanceTest extends TestCase
{
    use RefreshDatabase;

    private const BUSINESS_TABLES = [
        'warungs',
        'users',
        'kategori_menus',
        'menus',
        'penjualans',
        'penjualan_rincis',
        'pembelians',
        'pembelian_rincis',
        'pembelian_koreksis',
        'penjualan_koreksis',
        'penjualan_returs',
    ];

    public function test_eleven_business_tables_have_expected_column_metadata(): void
    {
        foreach (self::BUSINESS_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected business table {$table} to exist.");
            $this->assertSame(['id'], $this->indexColumns($table, 'PRIMARY'));
        }

        $this->assertColumn('warungs', 'timezone', 'varchar', 64, null, null, true);
        $this->assertColumn('warungs', 'tanggal_mulai', 'date', null, null, null, true);
        $this->assertColumn('warungs', 'tanggal_berakhir', 'date', null, null, null, true);
        $this->assertColumn('warungs', 'pendaftaran_disetujui', 'tinyint', null, 3, 0, false);
        $this->assertColumn('users', 'username', 'varchar', 100, null, null, false);
        $this->assertColumn('users', 'email', 'varchar', 150, null, null, true);
        $this->assertColumn('users', 'warung_id', 'bigint', null, 20, 0, true);
        $this->assertColumn('menus', 'harga', 'decimal', null, 15, 2, false);
        $this->assertColumn('menus', 'harga_modal', 'decimal', null, 15, 2, true);
        $this->assertColumn('penjualans', 'tanggal', 'datetime', null, null, null, false, 0);
        $this->assertColumn('penjualans', 'total', 'decimal', null, 15, 2, false);
        $this->assertColumn('penjualans', 'idempotency_key', 'varchar', 255, null, null, true);
        $this->assertColumn('penjualans', 'idempotency_expires_at', 'datetime', null, null, null, true, 6);
        $this->assertColumn('penjualan_rincis', 'menu_id', 'bigint', null, 20, 0, false);
        $this->assertColumn('penjualan_rincis', 'qty', 'decimal', null, 10, 2, false);
        $this->assertColumn('penjualan_rincis', 'subtotal', 'decimal', null, 15, 2, false);
        $this->assertFalse(Schema::hasColumn('penjualan_rincis', 'jenis_item'));
        $this->assertColumn('pembelians', 'tanggal', 'datetime', null, null, null, false, 0);
        $this->assertColumn('pembelians', 'total', 'decimal', null, 15, 2, false);
        $this->assertColumn('pembelians', 'status', 'varchar', 20, null, null, false);
        $this->assertColumn('pembelian_rincis', 'qty', 'decimal', null, 10, 2, true);
        $this->assertColumn('pembelian_rincis', 'satuan', 'varchar', 30, null, null, true);
        $this->assertColumn('pembelian_rincis', 'harga_satuan', 'decimal', null, 15, 2, true);
        $this->assertColumn('pembelian_rincis', 'subtotal', 'decimal', null, 15, 2, false);
        $this->assertColumn('penjualan_koreksis', 'alasan', 'varchar', 1000, null, null, false);
        $this->assertColumn('penjualan_koreksis', 'sebelum', 'json', null, null, null, false);
        $this->assertColumn('penjualan_koreksis', 'idempotency_expires_at', 'datetime', null, null, null, true, 6);
        $this->assertColumn('penjualan_returs', 'nominal', 'decimal', null, 15, 2, false);
        $this->assertColumn('penjualan_returs', 'idempotency_expires_at', 'datetime', null, null, null, true, 6);
    }

    public function test_all_business_columns_match_the_expected_mysql_metadata(): void
    {
        $expectedColumns = [
            'warungs' => [
                'id' => 'bigint unsigned|NO|<NULL>|auto_increment|<NULL>',
                'kode' => 'varchar(30)|NO|<NULL>|<NONE>|<NULL>',
                'nama' => 'varchar(150)|NO|<NULL>|<NONE>|<NULL>',
                'alamat' => 'text|YES|<NULL>|<NONE>|<NULL>',
                'telepon' => 'varchar(30)|YES|<NULL>|<NONE>|<NULL>',
                'logo' => 'varchar(255)|YES|<NULL>|<NONE>|<NULL>',
                'tanggal_mulai' => 'date|YES|<NULL>|<NONE>|<NULL>',
                'tanggal_berakhir' => 'date|YES|<NULL>|<NONE>|<NULL>',
                'aktif' => 'tinyint(1)|NO|1|<NONE>|<NULL>',
                'created_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'updated_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'timezone' => 'varchar(64)|YES|<NULL>|<NONE>|<NULL>',
                'pendaftaran_disetujui' => 'tinyint(1)|NO|1|<NONE>|<NULL>',
            ],
            'users' => [
                'id' => 'bigint unsigned|NO|<NULL>|auto_increment|<NULL>',
                'nama' => 'varchar(150)|NO|<NULL>|<NONE>|<NULL>',
                'email' => 'varchar(150)|YES|<NULL>|<NONE>|<NULL>',
                'email_verified_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'password' => 'varchar(255)|NO|<NULL>|<NONE>|<NULL>',
                'remember_token' => 'varchar(100)|YES|<NULL>|<NONE>|<NULL>',
                'created_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'updated_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'username' => 'varchar(100)|NO|<NULL>|<NONE>|<NULL>',
                'warung_id' => 'bigint unsigned|YES|<NULL>|<NONE>|<NULL>',
                'role' => 'varchar(30)|NO|<NULL>|<NONE>|<NULL>',
                'aktif' => 'tinyint(1)|NO|1|<NONE>|<NULL>',
            ],
            'kategori_menus' => [
                'id' => 'bigint unsigned|NO|<NULL>|auto_increment|<NULL>',
                'warung_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'nama' => 'varchar(100)|NO|<NULL>|<NONE>|<NULL>',
                'urutan' => 'int unsigned|NO|0|<NONE>|<NULL>',
                'aktif' => 'tinyint(1)|NO|1|<NONE>|<NULL>',
                'created_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'updated_at' => 'timestamp|YES|<NULL>|<NONE>|0',
            ],
            'menus' => [
                'id' => 'bigint unsigned|NO|<NULL>|auto_increment|<NULL>',
                'warung_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'kategori_menu_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'kode' => 'varchar(30)|NO|<NULL>|<NONE>|<NULL>',
                'nama' => 'varchar(150)|NO|<NULL>|<NONE>|<NULL>',
                'harga' => 'decimal(15,2)|NO|<NULL>|<NONE>|<NULL>',
                'harga_modal' => 'decimal(15,2)|YES|<NULL>|<NONE>|<NULL>',
                'gambar' => 'varchar(255)|YES|<NULL>|<NONE>|<NULL>',
                'deskripsi' => 'text|YES|<NULL>|<NONE>|<NULL>',
                'aktif' => 'tinyint(1)|NO|1|<NONE>|<NULL>',
                'created_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'updated_at' => 'timestamp|YES|<NULL>|<NONE>|0',
            ],
            'penjualans' => [
                'id' => 'bigint unsigned|NO|<NULL>|auto_increment|<NULL>',
                'warung_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'user_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'no_transaksi' => 'varchar(50)|NO|<NULL>|<NONE>|<NULL>',
                'idempotency_key' => 'varchar(255)|YES|<NULL>|<NONE>|<NULL>',
                'payload_hash' => 'char(64)|YES|<NULL>|<NONE>|<NULL>',
                'idempotency_expires_at' => 'datetime(6)|YES|<NULL>|<NONE>|6',
                'tanggal' => 'datetime|NO|<NULL>|<NONE>|0',
                'subtotal' => 'decimal(15,2)|NO|<NULL>|<NONE>|<NULL>',
                'diskon' => 'decimal(15,2)|NO|0.00|<NONE>|<NULL>',
                'total' => 'decimal(15,2)|NO|<NULL>|<NONE>|<NULL>',
                'bayar' => 'decimal(15,2)|NO|<NULL>|<NONE>|<NULL>',
                'kembalian' => 'decimal(15,2)|NO|0.00|<NONE>|<NULL>',
                'metode_pembayaran' => 'varchar(30)|NO|<NULL>|<NONE>|<NULL>',
                'status' => 'varchar(20)|NO|selesai|<NONE>|<NULL>',
                'catatan' => 'text|YES|<NULL>|<NONE>|<NULL>',
                'created_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'updated_at' => 'timestamp|YES|<NULL>|<NONE>|0',
            ],
            'penjualan_rincis' => [
                'id' => 'bigint unsigned|NO|<NULL>|auto_increment|<NULL>',
                'warung_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'penjualan_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'menu_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'nama_menu' => 'varchar(150)|NO|<NULL>|<NONE>|<NULL>',
                'harga' => 'decimal(15,2)|NO|<NULL>|<NONE>|<NULL>',
                'qty' => 'decimal(10,2)|NO|<NULL>|<NONE>|<NULL>',
                'diskon' => 'decimal(15,2)|NO|0.00|<NONE>|<NULL>',
                'subtotal' => 'decimal(15,2)|NO|<NULL>|<NONE>|<NULL>',
                'catatan' => 'text|YES|<NULL>|<NONE>|<NULL>',
                'created_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'updated_at' => 'timestamp|YES|<NULL>|<NONE>|0',
            ],
            'pembelians' => [
                'id' => 'bigint unsigned|NO|<NULL>|auto_increment|<NULL>',
                'warung_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'user_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'no_transaksi' => 'varchar(50)|NO|<NULL>|<NONE>|<NULL>',
                'idempotency_key' => 'varchar(255)|YES|<NULL>|<NONE>|<NULL>',
                'payload_hash' => 'char(64)|YES|<NULL>|<NONE>|<NULL>',
                'idempotency_expires_at' => 'datetime(6)|YES|<NULL>|<NONE>|6',
                'tanggal' => 'datetime|NO|<NULL>|<NONE>|0',
                'total' => 'decimal(15,2)|NO|<NULL>|<NONE>|<NULL>',
                'status' => 'varchar(20)|NO|tercatat|<NONE>|<NULL>',
                'catatan' => 'text|YES|<NULL>|<NONE>|<NULL>',
                'created_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'updated_at' => 'timestamp|YES|<NULL>|<NONE>|0',
            ],
            'pembelian_rincis' => [
                'id' => 'bigint unsigned|NO|<NULL>|auto_increment|<NULL>',
                'warung_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'pembelian_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'nama_item' => 'varchar(150)|NO|<NULL>|<NONE>|<NULL>',
                'qty' => 'decimal(10,2)|YES|<NULL>|<NONE>|<NULL>',
                'satuan' => 'varchar(30)|YES|<NULL>|<NONE>|<NULL>',
                'harga_satuan' => 'decimal(15,2)|YES|<NULL>|<NONE>|<NULL>',
                'subtotal' => 'decimal(15,2)|NO|<NULL>|<NONE>|<NULL>',
                'created_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'updated_at' => 'timestamp|YES|<NULL>|<NONE>|0',
            ],
            'pembelian_koreksis' => [
                'id' => 'bigint unsigned|NO|<NULL>|auto_increment|<NULL>',
                'warung_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'pembelian_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'user_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'jenis' => 'varchar(20)|NO|<NULL>|<NONE>|<NULL>',
                'alasan' => 'varchar(1000)|NO|<NULL>|<NONE>|<NULL>',
                'sebelum' => 'json|NO|<NULL>|<NONE>|<NULL>',
                'sesudah' => 'json|NO|<NULL>|<NONE>|<NULL>',
                'idempotency_key' => 'varchar(255)|YES|<NULL>|<NONE>|<NULL>',
                'payload_hash' => 'char(64)|YES|<NULL>|<NONE>|<NULL>',
                'idempotency_expires_at' => 'datetime(6)|YES|<NULL>|<NONE>|6',
                'created_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'updated_at' => 'timestamp|YES|<NULL>|<NONE>|0',
            ],
            'penjualan_koreksis' => [
                'id' => 'bigint unsigned|NO|<NULL>|auto_increment|<NULL>',
                'warung_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'penjualan_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'user_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'jenis' => 'varchar(20)|NO|<NULL>|<NONE>|<NULL>',
                'alasan' => 'varchar(1000)|NO|<NULL>|<NONE>|<NULL>',
                'sebelum' => 'json|NO|<NULL>|<NONE>|<NULL>',
                'sesudah' => 'json|NO|<NULL>|<NONE>|<NULL>',
                'idempotency_key' => 'varchar(255)|YES|<NULL>|<NONE>|<NULL>',
                'payload_hash' => 'char(64)|YES|<NULL>|<NONE>|<NULL>',
                'idempotency_expires_at' => 'datetime(6)|YES|<NULL>|<NONE>|6',
                'created_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'updated_at' => 'timestamp|YES|<NULL>|<NONE>|0',
            ],
            'penjualan_returs' => [
                'id' => 'bigint unsigned|NO|<NULL>|auto_increment|<NULL>',
                'warung_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'penjualan_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'user_id' => 'bigint unsigned|NO|<NULL>|<NONE>|<NULL>',
                'nominal' => 'decimal(15,2)|NO|<NULL>|<NONE>|<NULL>',
                'alasan' => 'varchar(1000)|NO|<NULL>|<NONE>|<NULL>',
                'idempotency_key' => 'varchar(255)|YES|<NULL>|<NONE>|<NULL>',
                'payload_hash' => 'char(64)|YES|<NULL>|<NONE>|<NULL>',
                'idempotency_expires_at' => 'datetime(6)|YES|<NULL>|<NONE>|6',
                'created_at' => 'timestamp|YES|<NULL>|<NONE>|0',
                'updated_at' => 'timestamp|YES|<NULL>|<NONE>|0',
            ],
        ];

        foreach (self::BUSINESS_TABLES as $table) {
            $expected = collect($expectedColumns[$table])
                ->map(fn (string $metadata, string $column): string => $column.'|'.$metadata)
                ->values()
                ->all();

            $this->assertSame(
                $expected,
                $this->businessColumnSignatures($table),
                "All column metadata must match for {$table}.",
            );
        }
    }

    public function test_business_indexes_have_expected_tenant_and_idempotency_columns(): void
    {
        $indexes = [
            ['warungs', 'warungs_kode_unique', ['kode']],
            ['users', 'users_email_unique', ['email']],
            ['users', 'users_username_unique', ['username']],
            ['users', 'users_warung_id_id_unique', ['warung_id', 'id']],
            ['kategori_menus', 'kategori_warung_id_id_unique', ['warung_id', 'id']],
            ['kategori_menus', 'kategori_warung_aktif_urutan_idx', ['warung_id', 'aktif', 'urutan', 'id']],
            ['kategori_menus', 'kategori_warung_nama_idx', ['warung_id', 'nama']],
            ['menus', 'menus_warung_id_id_unique', ['warung_id', 'id']],
            ['menus', 'menus_warung_kode_unique', ['warung_id', 'kode']],
            ['menus', 'menus_warung_aktif_nama_idx', ['warung_id', 'aktif', 'nama', 'id']],
            ['menus', 'menus_warung_kategori_aktif_idx', ['warung_id', 'kategori_menu_id', 'aktif']],
            ['penjualans', 'penjualans_warung_id_id_unique', ['warung_id', 'id']],
            ['penjualans', 'penjualans_warung_nomor_unique', ['warung_id', 'no_transaksi']],
            ['penjualans', 'penjualans_idempotency_unique', ['warung_id', 'user_id', 'idempotency_key']],
            ['penjualans', 'penjualans_warung_tanggal_idx', ['warung_id', 'tanggal', 'id']],
            ['penjualans', 'penjualans_warung_status_tanggal_idx', ['warung_id', 'status', 'tanggal']],
            ['penjualan_rincis', 'penjualan_rincis_warung_header_idx', ['warung_id', 'penjualan_id']],
            ['penjualan_rincis', 'penjualan_rincis_warung_menu_idx', ['warung_id', 'menu_id']],
            ['pembelians', 'pembelians_warung_id_id_unique', ['warung_id', 'id']],
            ['pembelians', 'pembelians_warung_nomor_unique', ['warung_id', 'no_transaksi']],
            ['pembelians', 'pembelians_idempotency_unique', ['warung_id', 'user_id', 'idempotency_key']],
            ['pembelians', 'pembelians_warung_tanggal_idx', ['warung_id', 'tanggal', 'id']],
            ['pembelians', 'pembelians_warung_status_tanggal_idx', ['warung_id', 'status', 'tanggal', 'id']],
            ['pembelian_rincis', 'pembelian_rincis_warung_header_idx', ['warung_id', 'pembelian_id']],
            ['pembelian_koreksis', 'pembelian_koreksi_idempotency_unique', ['warung_id', 'user_id', 'jenis', 'idempotency_key']],
            ['pembelian_koreksis', 'pembelian_koreksi_riwayat_idx', ['warung_id', 'pembelian_id', 'id']],
            ['penjualan_koreksis', 'penjualan_koreksi_idempotency_unique', ['warung_id', 'user_id', 'jenis', 'idempotency_key']],
            ['penjualan_koreksis', 'penjualan_koreksi_riwayat_idx', ['warung_id', 'penjualan_id', 'id']],
            ['penjualan_returs', 'penjualan_retur_idempotency_unique', ['warung_id', 'user_id', 'idempotency_key']],
            ['penjualan_returs', 'penjualan_retur_warung_created_idx', ['warung_id', 'created_at', 'id']],
            ['penjualan_returs', 'penjualan_retur_sale_idx', ['warung_id', 'penjualan_id', 'id']],
        ];

        foreach ($indexes as [$table, $name, $columns]) {
            $this->assertSame($columns, $this->indexColumns($table, $name), "Unexpected index {$table}.{$name}.");
        }
    }

    public function test_foreign_keys_match_tenant_safe_columns_and_restrict_deletes(): void
    {
        $foreignKeys = [
            ['users', 'users_warung_id_foreign', ['warung_id'], 'warungs', ['id']],
            ['kategori_menus', 'kategori_menus_warung_id_foreign', ['warung_id'], 'warungs', ['id']],
            ['menus', 'menus_warung_id_foreign', ['warung_id'], 'warungs', ['id']],
            ['menus', 'menus_warung_kategori_fk', ['warung_id', 'kategori_menu_id'], 'kategori_menus', ['warung_id', 'id']],
            ['penjualans', 'penjualans_warung_id_foreign', ['warung_id'], 'warungs', ['id']],
            ['penjualans', 'penjualans_warung_user_fk', ['warung_id', 'user_id'], 'users', ['warung_id', 'id']],
            ['penjualan_rincis', 'penjualan_rincis_warung_header_fk', ['warung_id', 'penjualan_id'], 'penjualans', ['warung_id', 'id']],
            ['penjualan_rincis', 'penjualan_rincis_warung_menu_fk', ['warung_id', 'menu_id'], 'menus', ['warung_id', 'id']],
            ['pembelians', 'pembelians_warung_id_foreign', ['warung_id'], 'warungs', ['id']],
            ['pembelians', 'pembelians_warung_user_fk', ['warung_id', 'user_id'], 'users', ['warung_id', 'id']],
            ['pembelian_rincis', 'pembelian_rincis_warung_header_fk', ['warung_id', 'pembelian_id'], 'pembelians', ['warung_id', 'id']],
            ['pembelian_koreksis', 'pembelian_koreksi_header_fk', ['warung_id', 'pembelian_id'], 'pembelians', ['warung_id', 'id']],
            ['pembelian_koreksis', 'pembelian_koreksi_user_fk', ['warung_id', 'user_id'], 'users', ['warung_id', 'id']],
            ['penjualan_koreksis', 'penjualan_koreksi_header_fk', ['warung_id', 'penjualan_id'], 'penjualans', ['warung_id', 'id']],
            ['penjualan_koreksis', 'penjualan_koreksi_user_fk', ['warung_id', 'user_id'], 'users', ['warung_id', 'id']],
            ['penjualan_returs', 'penjualan_retur_header_fk', ['warung_id', 'penjualan_id'], 'penjualans', ['warung_id', 'id']],
            ['penjualan_returs', 'penjualan_retur_user_fk', ['warung_id', 'user_id'], 'users', ['warung_id', 'id']],
        ];
        $expectedNames = [];

        foreach ($foreignKeys as [$table, $name, $columns, $referencedTable, $referencedColumns]) {
            $expectedNames[$table][] = $name;
            $this->assertForeignKey($table, $name, $columns, $referencedTable, $referencedColumns);
        }

        $actualNames = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->select('TABLE_NAME', 'CONSTRAINT_NAME')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->whereIn('TABLE_NAME', self::BUSINESS_TABLES)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->distinct()
            ->get()
            ->groupBy('TABLE_NAME')
            ->map(fn ($constraints) => $constraints->pluck('CONSTRAINT_NAME')->sort()->values()->all())
            ->all();
        ksort($expectedNames);
        ksort($actualNames);

        foreach ($expectedNames as &$names) {
            sort($names);
        }
        unset($names);

        $this->assertSame($expectedNames, $actualNames);
    }

    public function test_mysql_connection_uses_utc_and_preserves_a_utc_datetime_round_trip(): void
    {
        $timezone = DB::selectOne('SELECT @@session.time_zone AS timezone')->timezone;
        $this->assertSame('+00:00', $timezone);

        $warung = Warung::factory()->create();
        $kasir = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $expected = CarbonImmutable::parse('2026-10-05 12:34:56', 'UTC');
        $sale = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $kasir->id,
            'tanggal' => $expected,
        ]);

        $stored = DB::table('penjualans')->where('id', $sale->id)->value('tanggal');
        $loaded = Penjualan::query()->findOrFail($sale->id);

        $this->assertSame('2026-10-05 12:34:56', $stored);
        $this->assertSame('2026-10-05 12:34:56', $loaded->tanggal->utc()->format('Y-m-d H:i:s'));
    }

    private function assertColumn(
        string $table,
        string $column,
        string $dataType,
        ?int $length,
        ?int $precision,
        ?int $scale,
        bool $nullable,
        ?int $datetimePrecision = null,
    ): void {
        $definition = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->first();

        $this->assertNotNull($definition, "Expected column {$table}.{$column} to exist.");
        $this->assertSame($dataType, strtolower($definition->DATA_TYPE), "Unexpected type for {$table}.{$column}.");
        $this->assertSame($length, $definition->CHARACTER_MAXIMUM_LENGTH === null
            ? null
            : (int) $definition->CHARACTER_MAXIMUM_LENGTH);
        $this->assertSame($precision, $definition->NUMERIC_PRECISION === null
            ? null
            : (int) $definition->NUMERIC_PRECISION);
        $this->assertSame($scale, $definition->NUMERIC_SCALE === null
            ? null
            : (int) $definition->NUMERIC_SCALE);
        $this->assertSame($nullable, $definition->IS_NULLABLE === 'YES');

        if ($datetimePrecision !== null) {
            $this->assertSame($datetimePrecision, (int) $definition->DATETIME_PRECISION);
        }
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $referencedColumns
     */
    private function assertForeignKey(
        string $table,
        string $name,
        array $columns,
        string $referencedTable,
        array $referencedColumns,
    ): void {
        $definition = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->selectRaw('COLUMN_NAME AS column_name, REFERENCED_TABLE_NAME AS referenced_table, REFERENCED_COLUMN_NAME AS referenced_column')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->orderBy('ORDINAL_POSITION')
            ->get();

        $this->assertSame($columns, $definition->pluck('column_name')->all(), "Unexpected local columns for {$table}.{$name}.");
        $this->assertSame([$referencedTable], $definition->pluck('referenced_table')->unique()->values()->all());
        $this->assertSame($referencedColumns, $definition->pluck('referenced_column')->all());

        $deleteRule = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('CONSTRAINT_NAME', $name)
            ->value('DELETE_RULE');

        $this->assertSame('RESTRICT', $deleteRule, "Unexpected delete rule for {$table}.{$name}.");
    }

    /**
     * @return list<string>
     */
    private function indexColumns(string $table, string $index): array
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->orderBy('SEQ_IN_INDEX')
            ->pluck('COLUMN_NAME')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function businessColumnSignatures(string $table): array
    {
        return DB::table('information_schema.COLUMNS')
            ->select('COLUMN_NAME', 'COLUMN_TYPE', 'IS_NULLABLE', 'COLUMN_DEFAULT', 'EXTRA', 'DATETIME_PRECISION')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->orderBy('ORDINAL_POSITION')
            ->get()
            ->map(fn ($column): string => implode('|', [
                $column->COLUMN_NAME,
                strtolower($column->COLUMN_TYPE),
                $column->IS_NULLABLE,
                $column->COLUMN_DEFAULT === null ? '<NULL>' : (string) $column->COLUMN_DEFAULT,
                $column->EXTRA === '' ? '<NONE>' : strtolower($column->EXTRA),
                $column->DATETIME_PRECISION === null ? '<NULL>' : (string) $column->DATETIME_PRECISION,
            ]))
            ->all();
    }
}
