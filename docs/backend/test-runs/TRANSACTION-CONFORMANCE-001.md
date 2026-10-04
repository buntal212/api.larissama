# TRANSACTION-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-303/403/305/405; subset T-API-02/04, T-TEN/T-RBAC, D13

Commit yang diuji: `c54d2d36282a2cead42171b82fe81a9421fa617d`

Status run: **PASS untuk response dan status yang diuji di bawah**

## Environment

- Docker Compose test-only, project `larissama-backend-test`, file `compose.test.yaml`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Database `larissama_test` disposable; feature test memakai `RefreshDatabase`.
- Container database dan network dihapus setelah run.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php tests/Feature/LaporanApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner composer test
docker compose -f compose.test.yaml down --remove-orphans
```

| Check | Hasil |
| --- | --- |
| Pint | PASS |
| Tiga feature test transaksi/laporan | PASS, 22 test / 2591 assertions; tanpa warning |
| Suite `composer test` | PASS, 81 test / 9069 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti conformance response runtime

`tests/TestCase.php` membaca operasi, status, schema response, dan `$ref` lokal dari `docs/api/openapi.yaml`. Assertion membandingkan response HTTP aktual yang dipakai feature acceptance dengan schema operasi berikut:

| Operasi | Status yang response-nya dicocokkan |
| --- | --- |
| `GET /penjualans` | 200 |
| `POST /penjualans` | 201, 403, 409, 422 |
| `GET /penjualans/{id}` | 200, 404 |
| `GET /pembelians` | 200, 403 |
| `POST /pembelians` | 201, 403, 409, 422 |
| `GET /pembelians/{id}` | 200, 404 |
| `GET /laporan/penjualan` dan `GET /laporan/pembelian` | 200, 422 |

Detail pembelian 200 memakai header/detail yang sama dengan test create bentuk ringkas. Semua response pada tabel cocok dengan schema draft dan tidak ditemukan mismatch response API.

## Batas bukti

- Checker memvalidasi subset keyword JSON Schema yang dipakai oleh response terpilih dan gagal eksplisit untuk keyword lain yang belum didukung. Ini bukan validator OpenAPI 3.1 umum.
- Request body, query, dan header schema belum dibandingkan otomatis. Status yang tidak tercantum di atas, termasuk 400, 401, 429, dan 500, belum dicocokkan untuk transaksi/laporan.
- Test terfokus memakai skenario yang sudah ada; run ini tidak menutup semua matrix role, validasi uang, retry race/crash, keputusan D04/D05/D06/D08/D09/D10/D11, atau seluruh T-API-02/03/04.
- Seluruh operasi tetap `DRAFT`; run ini tidak menyerahkan API transaksi/laporan untuk integrasi frontend dan tidak menutup G3/G4.

## File dan hash

- `tests/Feature/PenjualanApiTest.php`: `206445189db60abf370391d64803311dcaf768126f6502bdd04fbd579e6d4e46`
- `tests/Feature/PembelianApiTest.php`: `35123e6d5a289ee65d07e75720c2f11e83a5f970ce810b4d4b42cb13788831b6`
- `tests/Feature/LaporanApiTest.php`: `6cb8445c44c07f0ec2e6e62120d3b2fc807e110a7f14ed5bfab72e30ed50b230`
