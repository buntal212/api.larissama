# UNBOUNDED-PAGINATION-PAGE-CONFORMANCE-001

## Lingkup

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Task: BE-003/104/202/203/303/403, T-API-05, D13.
- Commit yang diuji: `f8b4977e4313d0b634b00eee503813af4ffc8abf` (`feat: support unbounded page integers`).
- SHA-256 `tests/Feature/ApiPaginationLargePageConformanceTest.php`: `f86e4a4a23b643b5e2c57d0d34c3316771f02ed3adbdb9eb82eb2496b5f0a8a4`.
- SHA-256 `tests/TestCase.php`: `63dab011b3df367dfe380b98a2d8171fe2adf7cdd0b8bb24bd9db982624f4086`.
- Input di atas rentang PHP 64-bit: `page=9223372036854775808&per_page=100`.
- Keenam list diuji dengan satu row cocok dan role yang sah untuk masing-masing endpoint.

## Hasil

Pint lulus untuk 138 file. `ApiPaginationLargePageConformanceTest` lulus 12 test / 728 assertions: page maksimum signed 64-bit dan nilai di atas `PHP_INT_MAX` pada keenam list. Suite penuh lulus 316 test / 33969 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 / `larissama_test` melalui Docker Compose test-only.

Semua enam GET menerima nilai page tersebut, mengembalikan HTTP 200, `data=[]`, `per_page=100`, `total=1`, serta `last_page=1`. Body mempertahankan angka yang diminta sebagai token integer JSON tanpa tanda kutip dan tanpa pembulatan. Query dan response yang diuji cocok dengan schema OpenAPI. Tidak ada SQL offset untuk page setelah `last_page`; page diproses sebagai digit desimal untuk membandingkannya dengan jumlah halaman, bukan di-cast ke integer PHP. Compose dibersihkan.

Percobaan pertama pada test menemukan body response terbungkus sebagai JSON string karena argumen raw JSON berbeda urutan pada wrapper Laravel. Implementasi diganti ke `JsonResponse::fromJsonString()`; rerun focused dan suite penuh lulus.

## Batas bukti

Test menguji nilai tepat satu di atas `PHP_INT_MAX`, bukan string digit sepanjang batas URL/proxy. Kontrak tidak menentukan maksimum numerik; batas transport/request-line server tetap berlaku secara fisik. `meta.page` tetap angka JSON integer sesuai D13. JavaScript `JSON.parse` tidak mempertahankan ketepatan angka di atas `Number.MAX_SAFE_INTEGER`; frontend perlu menyimpan query page sebagai string dan tidak mengandalkan aritmetika `meta.page` untuk nilai sebesar itu. D13 tetap PARTIAL dan semua operasi tetap `DRAFT`.
