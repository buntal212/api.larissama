# LEGACY-USER-MIGRATION-SAFETY-001

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Task/test: BE-101, T-DB-02, D01/D12
- Commit yang diuji: `74da0d6e578d8bd808a08c3dbf797eccf666a5ef`
- File test: `tests/Feature/LegacyUserMigrationSafetyTest.php`
- SHA-256: `3e9632c54865d459147b92c400694bb214faf8116ba8cb11fd8c8211f0b97ff4`
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 di Compose test disposable, DB `larissama_test`

## Hasil

**PASS untuk pemeriksaan guard.** Pint lulus; test terarah 1 test / 4 assertions; suite penuh 238 test / 26305 assertions.

Command:

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/LegacyUserMigrationSafetyTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

Test menyimpan snapshot daftar kolom dan row user mentah lengkap, lalu memanggil method `up()` dari migration adaptasi `users` secara langsung. Dengan row user ada, migration mengeluarkan pesan preflight yang ditetapkan. Daftar kolom serta seluruh nilai user sesudah percobaan sama persis dengan snapshot, mencakup ID, nama, email, password hash, dan timestamps. Karena guard berhenti sebelum DDL, tidak ada schema/data yang diubah. Database/network Compose disposable telah dihapus.

## Batas bukti

Ini membuktikan migrasi menolak perubahan saat tabel berisi user, bukan migrasi berhasil terhadap database dengan schema legacy. Test tidak memetakan user ke warung atau menentukan bagaimana data lama dipertahankan setelah pemetaan disediakan. T-DB-02 upgrade/backfill dan keputusan D12 tetap terbuka; migration sengaja tidak diubah.
