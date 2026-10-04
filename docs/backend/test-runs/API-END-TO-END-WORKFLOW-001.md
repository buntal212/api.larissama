# API-END-TO-END-WORKFLOW-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Test commit: `17029c776670b726c0bd4f671829a3a0a1f3e5c8`
- SHA-256 `tests/Feature/ApiEndToEndWorkflowTest.php`: `bf59901d8bee164a85aedc26fb3d3579d7afbd26b5c95147aa80ecd4c2595d8b`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `ApiEndToEndWorkflowTest`, 1 test / 2209 assertions — PASS
- Suite penuh: 298 test / 30611 assertions — PASS
- Cleanup: container MySQL dan network Compose disposable dihapus

Satu skenario memakai HTTP API untuk seluruh lifecycle. Superadmin login dan membuat warung `Asia/Jakarta` beserta owner. Owner membuat manager dan kasir. Manager login, membuat kategori dan menu seharga `15000.00`; kasir login dan menjual dua menu dengan total `30000.00`. Manager membuat satu pembelian ringkas `150000.00` dan satu pembelian rinci `95000.00`. Laporan penjualan/pembelian untuk 2026-10-04 menghasilkan masing-masing `30000.00` dan `245000.00`. Logout kasir memberi 204 tanpa body, dan token yang sama sesudah logout mendapat 401.

Test membandingkan request, query dan response terpilih dengan OpenAPI, memastikan ID wire berbentuk string, serta memeriksa warung/user/resource relation dan jumlah record/nominal di database. Sale, pembelian ringkas, pembelian rinci, dan laporan terkait memakai tanggal lokal warung yang sama.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/ApiEndToEndWorkflowTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

## Batas bukti

Run ini membuktikan satu happy path lintas provisioning, katalog, sale, dua bentuk purchase, laporan lokal, dan logout. Ia tidak menguji matriks semua role/status, seluruh request/query OpenAPI, skenario gagal lintas modul, diskon, pembayaran non-tunai, pembatalan/koreksi, izin report owner, atau keputusan bisnis yang masih terbuka. Ini bukan conformance API penuh dan tidak mengubah operasi dari `DRAFT`. BE-501/T-E2E-01 masih `IN_PROGRESS` hingga regression dan gate G1–G4 terpenuhi.
