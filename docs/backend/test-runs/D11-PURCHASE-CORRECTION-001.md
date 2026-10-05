# D11-PURCHASE-CORRECTION-001

- Tanggal: 2026-10-05
- Task: BE-404, BE-405; keputusan D11
- Commit dasar saat run: `8f25539` (`8f25539` adalah HEAD sebelum kelompok perubahan ini dikomit); pengujian dilakukan pada working tree dengan perubahan D11.
- Lingkungan: Docker Compose test disposable, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40.

## Hasil

| Pemeriksaan | Hasil |
| --- | --- |
| Laravel Pint | PASS (`docker compose -f compose.test.yaml run --rm test-runner vendor/bin/pint --dirty --format agent`) |
| Suite Laravel lengkap | PASS: 490 test, 71.310 assertions, 41.99 detik (`php artisan test --compact`) |
| OpenAPI 3.1 | PASS (`docs/api/openapi.yaml: OK`, `openapi-spec-validator` 0.9.0) |
| Cleanup Compose | PASS; service MySQL test dan network disposable dihentikan/dihapus dengan `docker compose -f compose.test.yaml down -v` |

## Cakupan D11 di suite

`PembelianApiTest` menguji owner mengoreksi tanggal/catatan/rincian, menghitung ulang subtotal dan total, menyimpan audit snapshot/alasan/aktor, serta mengembalikan event yang sama untuk retry idempotent. Test pembatalan menguji manager, penyimpanan status `dibatalkan` tanpa menghapus header atau rincian, retry identik, penolakan pengulangan dengan key baru, filter list/detail, dan pengecualian dari laporan. Test tambahan memeriksa penolakan kasir/superadmin, isolasi tenant (404 untuk tenant lain), serta error payload invalid/no-op tanpa audit write.

Migration conformance memeriksa kolom status pembelian dan tabel `pembelian_koreksis`, termasuk tipe kolom, index unik key, index pembacaan riwayat, dan foreign key tenant. Route/OpenAPI conformance kini memeriksa inventaris 30 operasi pada 19 path, yakni 28 baseline ditambah dua operasi D11.

## Batas bukti

Operasi `updatePembelian` dan `cancelPembelian` masih `DRAFT`; request/response conformance runtime lengkap untuk kedua operasi baru belum dilakukan. Pada tanggal run ini durasi expiry D09 masih belum diputuskan; user kemudian menetapkan tujuh hari, dengan bukti batas waktu di `IDEMPOTENCY-7-DAY-EXPIRY-CONFORMANCE-001`. Run ini tidak mengklaim seluruh kontrak API siap integrasi frontend.
