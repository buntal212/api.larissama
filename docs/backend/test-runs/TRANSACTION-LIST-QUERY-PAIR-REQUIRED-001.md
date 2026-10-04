# TRANSACTION-LIST-QUERY-PAIR-REQUIRED-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit: `b685165a1e33dd7987a24c02fabfc1b2c838f514`
- SHA-256 `PenjualanIndexRequest.php`: `405a03401123c6c68f77f7988067fcb2531aec7d5b30d02d5336a175af36047a`
- SHA-256 `PembelianIndexRequest.php`: `b12d5a1888a5e469dd5ead5a225136d871070477247b85f8965d92132da91bc6`
- SHA-256 `PenjualanApiTest.php`: `9760212c7d238cf7a6d729fc8be5dc9b437f7870e569930663ff3e8fab9c132b`
- SHA-256 `PembelianApiTest.php`: `c5a3bbaeb91256da58e640f57279be2d3485f85e55dc1527af5a9c55af945718`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `PenjualanApiTest` + `PembelianApiTest`, 25 test / 3441 assertions — PASS
- Suite penuh: 302 test / 31773 assertions — PASS
- Cleanup: container MySQL dan network Compose disposable dihapus

Probe pertama menunjukkan GET kedua list menerima HTTP 200 untuk query yang hanya mengirim `date_from` atau hanya `date_to`, bertentangan dengan deskripsi kontrak bahwa keduanya dikirim berpasangan. Penyebabnya aturan `sometimes` melewati validasi field yang hilang sebelum `required_with` dapat mewajibkan pasangannya.

Sesudah `sometimes` dilepas dari dua rules tanggal, empat query parsial (dua arah × dua endpoint) menghasilkan HTTP 422 `VALIDATION_ERROR`, Error422 schema-conformant, dan menunjuk parameter pasangan yang hilang. Tidak ada header/detail baru. Query list tanpa parameter tanggal tetap diterima, dibuktikan oleh E2E workflow. Perubahan hanya di validator dan test; tidak mengubah schema DB, dependencies, timezone atau makna rentang.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

## Batas bukti

Run hanya memastikan kedua parameter tanggal dikirim bersama atau sama-sama absen. Ini tidak menetapkan timezone NULL/invalid, inclusive boundaries, perilaku status `batal`, atau seluruh nilai/query/status; semua operasi tetap `DRAFT`.
