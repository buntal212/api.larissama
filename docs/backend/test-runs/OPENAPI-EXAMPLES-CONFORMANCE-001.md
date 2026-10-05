# OPENAPI-EXAMPLES-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Status: PASS
- Commit: `525ef35`
- Baseline sebelum test: `accaef8`
- Test: `tests/Feature/ApiOpenApiExamplesConformanceTest.php`
- SHA-256 test: `0b741103d7e01c713cd6c7d69fc84b752350e98ffffd83542ba7366b9b5b9339`
- Lingkungan: Docker Compose test terisolasi, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40
- Pint: PASS, 141 file
- Focused: 1 test / 12497 assertions — PASS
- Suite penuh: 326 test / 50870 assertions dalam 32.64 detik — PASS
- Cleanup: service database test dan network Compose dibersihkan; pemeriksaan `ps -a` kosong
- Runtime API/schema database/dependency: tidak diubah

## Cakupan

Test memindai setiap operasi OpenAPI dan mencocokkan seluruh `example` serta `examples.*.value` inline pada content requestBody dan response dengan schema medianya. `$ref` lokal pada body, response, contoh, dan schema di-resolve. Contoh object kosong dinormalisasi sebagai object menurut tipe schema agar tidak keliru dibaca sebagai array PHP.

Probe pertama menemukan perbedaan parsing tersebut pada contoh error `errors: {}`. Normalisasi berbasis schema memperbaikinya; seluruh contoh request/response pada dokumen kini lulus. Pemeriksaan menggunakan subset schema checker proyek, bukan validator JSON Schema independen.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --no-deps --rm test-runner php artisan test tests/Feature/ApiOpenApiExamplesConformanceTest.php
docker compose -f compose.test.yaml --project-name larissama-backend-test run --no-deps --rm test-runner vendor/bin/pint --test
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --no-progress
docker compose -f compose.test.yaml --project-name larissama-backend-test down --remove-orphans
```

## Batas bukti

Run ini membuktikan contoh inline di request/response cocok dengan schema dokumen sesuai keyword yang didukung checker. Ia tidak menguji contoh parameter, contoh yang mengambil nilai eksternal, kecocokan runtime HTTP, atau keputusan bisnis. Gabungkan dengan `OPENAPI-SPEC-VALIDATOR-001` untuk validasi spesifikasi OpenAPI 3.1. T-API-01 tetap menunggu review/pedoman integrasi; semua operasi tetap `DRAFT`.
