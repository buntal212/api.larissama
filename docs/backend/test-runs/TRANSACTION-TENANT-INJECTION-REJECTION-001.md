# TRANSACTION-TENANT-INJECTION-REJECTION-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test: `12251ac741cd8979995b1313690b75672b36ce4c`
- SHA-256 `PenjualanApiTest.php`: `aebd398886c60f8586d46642fc4afb41269adf9498bbea2cc5707add00348df1`
- SHA-256 `PembelianApiTest.php`: `a7926c4ab732803f0ecf1874901f9131883f38e0b5c66415ab9f3182291e4f93`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `PenjualanApiTest` + `PembelianApiTest`, 21 test / 3213 assertions — PASS
- Suite penuh: 297 test / 28402 assertions — PASS
- Cleanup: container MySQL dan network Compose disposable dihapus

Kasir dengan menu pada warung A mengirim payload sale valid selain tambahan `warung_id` warung B. Manager mengirim payload purchase nominal valid selain tambahan `warung_id` warung B. Kedua endpoint mengembalikan HTTP 422 `VALIDATION_ERROR`, error menunjuk field `warung_id`, dan response cocok dengan `Error422`. Tidak ada header atau rincian yang ditulis pada warung A maupun B.

OpenAPI request schema `PenjualanCreate` dan `PembelianCreate` memiliki `additionalProperties: false`; `warung_id` tidak boleh dikirim oleh client. Atribut Laravel `FailOnUnknownFields` menolak field tambahan sebelum action transaksi, jadi tenant tetap berasal dari user bearer. Run ini tidak mengubah runtime, database, atau dependencies.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

## Batas bukti

Run hanya memeriksa injeksi `warung_id` pada POST create sale/purchase. Tidak menguji semua field asing, seluruh kombinasi lintas tenant, atau integrasi seluruh kontrak. Operasi tetap `DRAFT`.
