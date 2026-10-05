# ROLE-POLICY-MATRIX-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit yang diuji: `185d5cf` (`test: add tenant policy permission matrices`)
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test`
- `composer install --no-interaction --prefer-dist --no-progress`: PASS, tidak ada perubahan dependency
- `vendor/bin/pint --test`: PASS, 156 file
- `php artisan test --compact tests/Unit/Policies`: PASS, 43 test / 313 assertions
- `php artisan test --no-progress`: PASS, 462 test / 65052 assertions dalam 38.37 detik
- Project Compose `larissama-policy-matrix` dihentikan dan network disposable dihapus

Policy yang diuji: `KategoriMenuPolicy`, `MenuPolicy`, `PembelianPolicy`, `PenjualanPolicy`, `UserPolicy`, dan `WarungPolicy`. Matriks memeriksa role owner/manager/kasir/superadmin, aktor tanpa `warung_id`, batas tenant, pembatasan kasir pada penjualan miliknya, larangan write katalog oleh kasir, larangan administrasi user selain owner, akses platform khusus superadmin, serta larangan update/delete/restore/force-delete transaksi; belum ada endpoint cancel.

Tidak ada perubahan runtime, database, dependency, atau OpenAPI. Pengujian policy langsung melengkapi feature tests HTTP yang sudah ada; pengujian ini tidak menutup seluruh kombinasi status/payload HTTP atau gate milestone. Semua operasi OpenAPI tetap `DRAFT`.
