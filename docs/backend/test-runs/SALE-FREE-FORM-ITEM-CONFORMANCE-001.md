# SALE-FREE-FORM-ITEM-CONFORMANCE-001

## Keputusan dan kontrak

- Tanggal: 2026-10-09 (Asia/Jakarta).
- Keputusan: D07 direvisi atas persetujuan user. Baris penjualan dapat memilih menu katalog atau item bebas; item bebas menyimpan nama/harga snapshot dan memakai `menu_id = NULL`.
- Jumlah baris item bebas tidak memiliki batas khusus. Baris bebas dan katalog boleh dicampur; qty per baris tetap mengikuti D05.
- API contract: OpenAPI 3.1 `SaleLineInput`, digunakan oleh `PenjualanCreate` dan `PenjualanUpdate`. Response detail dan snapshot koreksi mengirim `menu_id` sebagai ID string atau null.
- Skema maju: `2026_10_09_020520_make_sale_detail_menu_optional`; FK gabungan tetap berlaku untuk menu ID terisi. Rollback menolak jika sudah ada baris item bebas.
- Implementation commit: `5643fb60cfb1094698457e8bd2512b7b0901b91c` (`feat(sales): support free-form sale items`).

## Verifikasi

- MySQL 8.0.40 disposable via `compose.test.yaml`:
  - `PenjualanApiTest`, `PenjualanCorrectionApiTest`, `BusinessSchemaMigrationConformanceTest`, `TenantCompositeForeignKeyTest`: **50 passed, 11.553 assertions**.
  - Mencakup baris katalog + bebas campuran, subtotal/total terhitung server, positive unit price/name validation, koreksi ke item bebas, null serialization, metadata nullability, dan keberlanjutan tenant FK.
- `ApiOpenApiDocumentIntegrityTest` dan `ApiOpenApiExamplesConformanceTest`: **3 passed, 14.765 assertions**.
- Pint: **PASS** (`vendor/bin/pint --dirty --format agent` dalam container test-runner).
- OpenAPI 3.1 validator: **PASS**, `docs/api/openapi.yaml: OK`.

## Batas hasil

Test full G3/G4 tidak diulang. Tidak ada verifikasi migrasi pada database production; hanya MySQL disposable. Rollback ketika sudah ada item bebas memang sengaja gagal agar data snapshot tidak hilang.
