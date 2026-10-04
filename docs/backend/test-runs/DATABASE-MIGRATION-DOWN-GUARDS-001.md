# DATABASE-MIGRATION-DOWN-GUARDS-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test: `0015672e83f1fccbc5fb2b8ea21906081048622d`
- SHA-256 `LegacyUserMigrationSafetyTest.php`: `cfd260bd060b8371c2e9bd63f5f5954e616d359f4668418502acca03412dffc0`
- SHA-256 `WarungTimezoneMigrationSafetyTest.php`: `70455c2ffbf74ddac6bf05769aa1f6e5db3f0aabc8b1dc607097547efae43acf`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: dua file migration safety, 4 test / 18 assertions — PASS
- Suite penuh: 254 test / 26474 assertions — PASS
- MySQL trigger tersisa: tidak ada; Compose/network dibersihkan

Test membuktikan tiga guard pada `down()` dan pemeriksaan migrasi `up()` yang sudah ada:

- Migration users menolak rollback ketika row user masih ada; daftar kolom dan row user tetap sama.
- Migration users menolak rollback ketika tabel users kosong tetapi token masih tersimpan; daftar kolom tetap sama dan row token tidak berubah.
- Migration timezone menolak rollback saat warung menyimpan `Asia/Jakarta`; daftar kolom serta seluruh row warung tetap sama.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/LegacyUserMigrationSafetyTest.php tests/Feature/WarungTimezoneMigrationSafetyTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml exec -T test-db mysql -N -ularissama_test -plarissama-test-only larissama_test -e "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()"
docker compose -f compose.test.yaml down --remove-orphans
```

## Batas bukti

Test memanggil guard `down()` langsung pada fixture tersimpan dan memastikan guard berhenti sebelum DDL. Test tidak menjalankan rollback batch penuh pada schema berisi data. T-DB-04 hanya membuktikan reversibilitas batch pada database disposable kosong; pemulihan legacy users/backfill T-DB-02 tetap menunggu pemetaan identitas user ke warung.
