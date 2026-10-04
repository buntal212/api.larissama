# DATABASE-SCHEMA-MIGRATION-CONFORMANCE-001

## Rencana dan lingkup

- Tanggal: 2026-10-05 (Asia/Jakarta).
- Task: BE-101/201/301/401; T-DB-01; INV01–INV11; D01/D07/D08/D09/D16.
- Sumber kebenaran: migrations bisnis yang ada dan target MySQL 8.0.40.
- Commit test terakhir: `d0578bac8044a882bd2344a1992d920d8023ada6` (`test: verify business schema foreign key metadata`).
- File test: `tests/Feature/BusinessSchemaMigrationConformanceTest.php`.
- SHA-256 file: `16f2924326265352a303004fae08c8e2384630e7bbc3dd45065fd569ed66b56b`.
- Database: MySQL 8.0.40 `larissama_test`, Compose disposable, migration dan fixture melalui `RefreshDatabase`.
- Perubahan migration/schema/API: tidak ada.

## Kasus yang diaudit

Test memastikan delapan tabel bisnis tersedia dengan primary key `id`, lalu memeriksa:

1. Kolom penting untuk timezone dan masa aktif warung, identitas user dan FK tenant, nominal menu, header transaksi, rincian penjualan, serta nullable fields pada rincian pembelian. Ini termasuk `penjualan_rincis.menu_id` wajib, `jenis_item` tidak ada, dan `DATETIME_PRECISION=0` pada kedua header transaksi.
2. Susunan kolom pada indeks tenant, unique, idempotency, dan query untuk kategori/menu/penjualan/pembelian.
3. Semua 11 FK bisnis menurut nama, kolom lokal, tabel/kolom referensi, dan urutan pasangan composite. Set FK aktual per tabel harus persis sama dengan set yang diharapkan, dan seluruh `DELETE_RULE` harus `RESTRICT`.
4. Zona sesi MySQL `+00:00` dan round-trip timestamp UTC yang sama saat disimpan langsung maupun dimuat melalui model.

## Hasil

- Pint: lulus.
- Focused `docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings --filter=BusinessSchemaMigrationConformanceTest`: **4 passed / 210 assertions**.
- Suite penuh `docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings`: **229 passed / 25821 assertions**.
- Environment suite: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40.
- Compose cleanup `docker compose -f compose.test.yaml down --remove-orphans`: lulus; container dan network test dihapus.
- Tidak ada perubahan migration, runtime, API, atau data bersama.

## Batas bukti

Hasil ini lulus untuk metadata dan invariant yang disebut pada audit T-DB-01 terhadap fresh migration MySQL 8.0.40. Test tidak mencakup upgrade/backfill database lama T-DB-02, rollback migration, atau pemulihan data. Audit menguji kolom/index yang ditetapkan pada test ini, bukan membandingkan seluruh metadata setiap kolom secara otomatis dengan dokumen rancangan. D16 juga memiliki bukti insert langsung tersendiri di [`TENANT-COMPOSITE-FK-CONSTRAINT-001`](TENANT-COMPOSITE-FK-CONSTRAINT-001.md). Operasi API tetap DRAFT dan gate milestone belum ditutup.
