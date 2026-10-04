# ACCESS-PAGINATION-LARGE-PAGE-CONFORMANCE-001

## Rencana dan lingkup

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Task: BE-003/104/202/203/303/403; subset T-API-02/03; D13.
- Sumber kebenaran: `docs/api/openapi.yaml` untuk schema/query/response, enam controller untuk filter dan scope list.
- File test: `tests/Feature/ApiPaginationLargePageConformanceTest.php`.
- SHA-256 file test: `622c3a7b516af1089ed1aa8b11dc8202ddbb0048cc44438c22d5e50133eeef7b`.
- Query yang diuji: `page=9223372036854775807&per_page=100`, dengan satu row yang cocok di masing-masing endpoint.
- Endpoint: `/admin/warungs` (superadmin), `/users` (owner), `/kategori-menus`, `/menus`, `/penjualans`, `/pembelians` (manager).
- Tidak menambah batas maksimum `page` pada OpenAPI. Respons diharapkan tetap HTTP 200, data kosong, serta metadata page/per_page/total/last_page sesuai kontrak.

## Probe awal sebelum perbaikan

- Run ID: `ACCESS-PAGINATION-LARGE-PAGE-PROBE-001`.
- Status: **FAIL**, 6 kasus.
- Bukti: response memenuhi schema dan metadata berisi page maksimum, per_page 100, total 1, last_page 1. Semua kasus gagal pada assertion `data=[]`; query justru mengembalikan row pertama.
- Sebab: offset `(page - 1) * per_page` meluap sebelum query SQL dijalankan.
- Tindak lanjut: implementasikan helper bersama `App\Support\ApiPagination`, hitung total pada query terfilter, lalu lewati query item apabila page lebih besar dari last page. Keenam controller list menggunakan helper tersebut.

## Rerun setelah perbaikan

- Commit yang diuji: `3d9fc271e88591d4cbc4a466d90299d22676aaf6` (`fix: guard pagination offsets beyond last page`).
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` dalam Compose disposable.
- Pint: lulus.
- Focused: `docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings --filter=ApiPaginationLargePageConformanceTest` — **6 passed / 358 assertions**.
- Suite penuh: `docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings` — **215 passed / 25598 assertions**.
- Hasil tiap endpoint: HTTP 200 dan schema cocok; `data=[]`, `meta.page=9223372036854775807`, `meta.per_page=100`, `meta.total=1`, `meta.last_page=1`.
- Compose: `docker compose -f compose.test.yaml down --remove-orphans` lulus; container dan network test dihapus.
- Pemeriksaan diff: `git diff --check` lulus sebelum commit.

## Batas bukti

Test membuktikan batas maksimum signed 64-bit pada keenam list dengan satu row cocok dan ukuran halaman 100. Ia tidak menetapkan perilaku untuk integer di luar rentang representasi PHP, tidak mengubah kontrak integer OpenAPI menjadi `int64` atau menambahkan `maximum`, dan tidak menyelesaikan T-API-02/03 penuh. Checker query dan response membandingkan fixture/runtime pada subset schema yang didukung test helper; bukan validator OpenAPI umum. Seluruh 28 operasi tetap `DRAFT`, tanpa perubahan status handoff.
