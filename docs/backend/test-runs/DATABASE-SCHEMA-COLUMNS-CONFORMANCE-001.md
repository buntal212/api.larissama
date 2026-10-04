# DATABASE-SCHEMA-COLUMNS-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test: `bd0161699f62cf23bdd3e74b0a60256665ba6cdf`
- SHA-256 test: `843b401b5d084dffeed915c6a146e0fc1a8f1807578019e5c4ca9d673840349a`
- Lingkungan: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Hasil focused: `BusinessSchemaMigrationConformanceTest`, 5 test / 218 assertions — PASS
- Hasil suite: 251 test / 26460 assertions — PASS
- Pint: PASS

Test membangun fresh schema dari migration dan membandingkan metadata 93 kolom pada delapan tabel bisnis. Pemeriksaan mencakup nama dan urutan kolom, `COLUMN_TYPE` lengkap (termasuk panjang, precision/scale, dan signedness), nullability, default, `EXTRA`, serta precision temporal. Test conformance sebelumnya tetap memeriksa primary key, indeks terpilih, semua 11 foreign key beserta urutan kolom dan aturan `RESTRICT`, serta round-trip timestamp UTC.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/BusinessSchemaMigrationConformanceTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml exec -T test-db mysql -N -ularissama_test -plarissama-test-only larissama_test -e "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()"
docker compose -f compose.test.yaml down --remove-orphans
```

Pemeriksaan trigger tidak mengembalikan baris; Compose disposable dihentikan dan dibersihkan.

## Batas bukti

Hasil ini membuktikan conformance fresh install T-DB-01 untuk metadata kolom yang disebut di atas. Pemeriksaan ini tidak membuktikan upgrade/backfill user lama T-DB-02, rollback migration, maupun kesesuaian metadata lain yang tidak disebutkan. T-DB-02 tetap menunggu keputusan pemetaan identitas user lama ke warung.
