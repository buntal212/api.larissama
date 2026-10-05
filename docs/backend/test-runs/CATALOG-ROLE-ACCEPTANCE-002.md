# CATALOG-ROLE-ACCEPTANCE-002

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit source: `4951bb9` (tidak ada perubahan runtime sejak full suite `BACKEND-FULL-SUITE-001`)
- Runtime: Docker Compose disposable, PHP 8.3, Laravel 13, MySQL 8.0.40
- Feature tests: PASS, 21 test / 5.135 assertions
- Pint: PASS, 173 file
- OpenAPI validator: PASS, `docs/api/openapi.yaml: OK`
- Cleanup: service database dan network test dihapus

Command feature:

```sh
docker compose -f compose.test.yaml run --build --rm test-runner php artisan test --display-warnings tests/Feature/KategoriMenuApiTest.php tests/Feature/MenuApiTest.php tests/Feature/SaleInactiveCatalogConformanceTest.php tests/Feature/OwnerTenantAccessConformanceTest.php tests/Feature/CatalogServerErrorConformanceTest.php
```

## Cakupan yang lulus

- Manager membuat, membaca, memfilter, dan memperbarui kategori serta menu.
- Owner dapat mengelola katalog dalam tenant sendiri; resource tenant lain tidak tampil dan detail lintas tenant menghasilkan 404.
- Kasir hanya melihat kategori/menu aktif dan tidak dapat menulis katalog; owner/kasir/superadmin ditolak pada hak tulis manager-only.
- Superadmin ditolak pada pembacaan dan penulisan katalog tenant dengan 403 tanpa perubahan data.
- Kategori/menu lintas tenant, injeksi `warung_id`, field yang tidak didukung, kode duplikat dalam tenant, dan harga tidak valid ditangani sesuai test.
- Penjualan baru atas menu atau kategori nonaktif ditolak tanpa write; katalog aktif tetap dapat dijual.
- Kegagalan database yang dipicu pada INSERT/UPDATE kategori/menu menghasilkan 500 sesuai OpenAPI, tidak membocorkan pesan SQL, dan tidak membuat atau mengubah row.
- Request, query, dan response terpilih dicocokkan dengan OpenAPI melalui assertion pada test fitur.

## Batas hasil

Run ini bersama `CATALOG-CONFORMANCE-001`, `CATALOG-API-ACCEPTANCE-001`, pagination/filter runs, dan D06 run menutup implementasi fitur katalog BE-202/203. Ini bukan matriks semua kombinasi status/request OpenAPI; BE-204/G2 dan status operasi katalog tetap menunggu conformance kontrak penuh. Tidak ada perubahan runtime atau schema pada run ini.
