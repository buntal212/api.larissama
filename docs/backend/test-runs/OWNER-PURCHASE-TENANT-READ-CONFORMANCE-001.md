# OWNER-PURCHASE-TENANT-READ-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Status akhir: PASS
- Commit test/docs: `f815bcea70b8305c7b8a071c4662e1f8e0a0c084`
- Test: `tests/Feature/PembelianApiTest.php`
- Lingkungan: Docker Compose project `larissama-backend-test`, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test` disposable)
- Pint: PASS
- Focused `PembelianApiTest`: 22 test / 3449 assertions — PASS
- Suite penuh: 323 test / 37913 assertions, 35.28 detik — PASS
- Cleanup: container MySQL test dan network Compose test dibersihkan
- Percobaan awal: assertion helper error D13 yang private di kelas test lain tidak tersedia; diganti assertion langsung `code=NOT_FOUND`, dengan schema response tetap diverifikasi

## Cakupan

Owner A membaca `GET /pembelians` dan melihat pembelian yang dicatat manager pada warung A, dengan `meta.total=1` serta `user_id` manager yang benar. Owner juga mendapat 200 schema-conformant pada detail pembelian tersebut beserta rincian.

Pembelian warung B tidak muncul di list dan detailnya memberi 404 `NOT_FOUND` schema-conformant. Row warung B tetap tersimpan. Query list dan response list/detail dicocokkan dengan kontrak OpenAPI.

## Batas bukti

Policy dan query runtime sudah sesuai sehingga tidak ada perubahan runtime, schema, atau dependency. Cakupan ini membuktikan owner membaca purchase lintas pencatat dalam tenant sendiri dan tenant isolation pada list/detail. Ia tidak menutup seluruh matriks D04, gate BE-403/G1, atau kesiapan handoff; semua operasi OpenAPI tetap `DRAFT`.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact
docker compose -f compose.test.yaml --project-name larissama-backend-test down --volumes --remove-orphans
```
