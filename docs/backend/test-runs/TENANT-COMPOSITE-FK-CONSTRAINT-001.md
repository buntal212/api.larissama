# TENANT-COMPOSITE-FK-CONSTRAINT-001

## Rencana dan lingkup

- Tanggal: 2026-10-05 (Asia/Jakarta).
- Task: BE-101/201/301/401; T-DB-03; INV01; D16.
- Sumber kebenaran: migration FK gabungan/unique index yang sudah diterapkan; tidak mengubah migration.
- Commit test: `5718f87b3d4c689200c25a0c7f1996f9020328de` (`test: verify tenant composite database constraints`).
- File test: `tests/Feature/TenantCompositeForeignKeyTest.php`.
- SHA-256 file test: `e5d933b4c3f2185fae49ef0971990bdf18af71db846c61910a92200102ab8382`.
- Database: MySQL 8.0.40 `larissama_test`, Compose disposable, fixture melalui `RefreshDatabase`.

## Kasus

1. Enam FK gabungan menolak relasi lintas warung: menu-kategori, penjualan-user, detail penjualan-header, detail penjualan-menu, pembelian-user, detail pembelian-header.
2. FK kategori menolak menu dengan ID kategori orphan.
3. Unique `(warung_id, kode)` menerima kode sama antar-warung dan menolak duplikat pada warung yang sama.
4. Unique `(warung_id, no_transaksi)` pada penjualan dan pembelian menerima nomor sama antar-warung dan menolak duplikat dalam warung yang sama.

Untuk setiap penolakan, assertion memeriksa nama constraint di `QueryException`; error dari penyebab lain tidak dihitung lulus. Kasus duplikasi lintas tenant membuktikan insert kedua berhasil dan tepat dua row tersimpan.

## Hasil

- Pint: lulus.
- Focused `docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings --filter=TenantCompositeForeignKeyTest`: **10 passed / 13 assertions**.
- Suite penuh `docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings`: **225 passed / 25611 assertions**.
- Environment suite: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40.
- Compose cleanup `docker compose -f compose.test.yaml down --remove-orphans`: lulus; container/network test dihapus.
- Tidak ada perubahan schema, migration, runtime API, tenant scope, atau data selain fixture test.

## Batas bukti

Ini membuktikan constraint MySQL yang diuji pada schema saat ini dan melengkapi HTTP validation/tenant cases di feature tests API. Test tidak mencakup semua kemungkinan orphan pada tiap FK, full eight-table migration audit T-DB-01, upgrade data users lama T-DB-02, atau rollback migration. BE-101/201/301/401 dan gate G1–G4 tetap berjalan; tidak ada operasi API yang berubah status.
