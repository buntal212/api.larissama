# SALE-CORRECTION-RETURN-CONFORMANCE-001

- Tanggal: 2026-10-06
- Runtime: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 di Docker Compose test-only
- Status: PASS untuk acceptance D06 yang disebutkan di bawah; belum menjadi gate conformance penuh atau handoff frontend.

## Hasil

- `PenjualanCorrectionApiTest`: 5 test / 2.102 assertions. Koreksi dan pembatalan beralasan dengan snapshot audit; retry idempotent; koreksi tepat 72 jam diterima dan 72 jam + 1 detik ditolak; rincian pengganti memakai harga menu aktif dan total hasil hitung backend; kasir mendapat 403 dan manager tenant lain mendapat 404; retur lebih dari 72 jam tetap diterima, dibatasi sisa total penjualan, serta mengurangi pendapatan pada periode retur.
- `BusinessSchemaMigrationConformanceTest`: 5 test pada 11 tabel / 117 kolom, termasuk dua tabel audit penjualan, seluruh index/FK tenant gabungan, dan UTC MySQL.
- `TenantCompositeForeignKeyTest`: 14 test, termasuk seluruh referensi audit koreksi/retur yang lintas warung ditolak database.
- Suite penuh: 519 test / 77.587 assertions PASS pada MySQL 8.0.40.
- Pint dan `openapi-spec-validator` 0.9.0 PASS (`docs/api/openapi.yaml: OK`).

## Batas hasil

Test ini tidak menutup seluruh kombinasi request/status/error, uji expiry dan race idempotensi untuk event penjualan, seluruh 33 operationId, atau kesiapan environment live. Semua operationId tetap DRAFT. Compose test dan validator memakai project terpisah dan dibersihkan setelah verifikasi.
