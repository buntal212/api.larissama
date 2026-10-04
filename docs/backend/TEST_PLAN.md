# Rancangan Test dan Kriteria Lulus

Status awal dokumen ini adalah seluruh test aplikasi **NOT_RUN**. Sejak itu, sebelas run transaksi/report serta run auth, admin/user, pengelolaan warung, conformance admin warung, conformance auth, auth request, katalog, conformance katalog, query akses/katalog, request admin/katalog, boundary pagination, dan sort allowlist menjalankan sebagian feature test pada MySQL 8.0.40; suite terbaru lulus 250 test / 26452 assertions. Acceptance profil `/warung` lulus 4/238; audit fresh schema delapan tabel lulus 4/210 dan audit constraint tenant langsung lulus 10/13; guard migrasi user existing lulus 1/4; seluruh test concurrency idempotency sale/purchase lulus 13/155 untuk retry identik, conflict payload, konteks scope, nomor unik, dan pemulihan worker. Auth acceptance menguji 20 kasus/230 assertions, response conformance auth 20/2985, dan request conformance auth 20/3069; admin/user tenant 11/139, admin warung acceptance 4/113, schema conformance admin 5/976, provisioning/user conformance 17/1902, katalog acceptance 17/286 dan conformance 17/1573, akses/katalog query conformance 28/3808, request admin/katalog terarah 34/4307, transaksi response conformance 22/2591, request body/header conformance 22/3270, query transaksi/report 22/3439, overflow pagination 7/1093, lower-bound pagination 19/1597, tipe pagination 31/2101, sort allowlist 37/2395, semua enum sort 51/3381, tie-breaker sort 14/3514, halaman kosong pagination 6/758, serta sort menurut nilai primer berbeda 14/3492, default list 6/1718, numeric pagination 36/1512, page maksimum 64-bit 6/358, constraint tenant langsung 10/13. Bukti dan batasnya ada di [tracker](../../IMPLEMENTATION_PROGRESS.md) serta artefak run terkait. Zona waktu NULL/invalid, keputusan D12, conformance OpenAPI penuh, milestone dan handoff API masih terbuka.

Kebutuhan berasal dari K01–K08 pada [DECISIONS.md](DECISIONS.md), invariant INV01–INV11 pada [DESIGN.md](DESIGN.md), dan [OpenAPI](../api/openapi.yaml). Expected result yang bergantung Dxx adalah kandidat: finalkan keputusan dan sesuaikan test sebelum test tersebut menjadi gate.

## Strategi pelaksanaan

| Lapisan | Lokasi usulan | Yang dibuktikan |
| --- | --- | --- |
| Unit | tests/Unit/ | Decimal, rumus, periode, batas nilai; tidak menggandakan detail implementasi controller. |
| Feature | tests/Feature/ | HTTP, auth, policy, validasi, tenant, response dan perubahan DB. |
| Integration | tests/Integration/ | Migration fresh/upgrade, FK/unique/decimal, atomic rollback, transaksi dan concurrency pada engine produksi. |
| Contract | tests/Contract/ | Request/response runtime cocok dengan OpenAPI, termasuk tipe, nullability, kode status, errors. |
| Alur lintas fitur | tests/Feature/ atau runner HTTP | Provisioning sampai laporan dan logout melalui API yang sebenarnya. |

M0 memilih validator OpenAPI 3.1 yang sesuai lalu mencatat versi/command di tracker. Jangan mengunci package hanya karena dipakai App POS. PHPUnit sudah ada di composer.json; struktur folder baru belum dibuat pada tahap rancangan.

## Conformance request login

`AUTH-REQUEST-CONFORMANCE-001` memakai `assertOperationRequestMatchesOpenApi()` untuk mencocokkan body JSON login yang sama dengan map pada feature test `POST /auth/login` terhadap `LoginRequest`. Tujuh contoh body valid yang diperiksa mencakup login sukses untuk empat role, username dikenal dengan password salah, dan username tidak dikenal; semua map yang dicocokkan juga dipakai langsung oleh `postJson()`.

Run lulus: Pint; `AuthApiTest` 20 test / 3069 assertions; `composer test` penuh 82 test / 10865 assertions pada MySQL 8.0.40 Compose disposable. Body kosong untuk 422 sengaja tidak disebut schema-conformant. Helper menilai map fixture, bukan mengintersep HTTP dan bukan validator OpenAPI umum. Tidak ada perubahan auth/token/limiter/controller/schema DB. Operasi auth tetap DRAFT; body/status lain, D08/D12, dan gate penuh masih terbuka. Detail ada pada [artefak run](test-runs/AUTH-REQUEST-CONFORMANCE-001.md).

## Slice conformance response transaksi dan laporan

`TRANSACTION-CONFORMANCE-001` membandingkan response HTTP feature test dengan schema response di `docs/api/openapi.yaml` untuk list/create/detail penjualan, list/create/detail pembelian, dan kedua laporan. Status tercakup: sale list 200; sale create 201/403/409/422; sale detail 200/404; purchase list 200/403; purchase create 201/403/409/422; purchase detail 200/404; kedua laporan 200/422. GET detail sukses pembelian berjalan pada header/detail fixture test create ringkas.

`assertOperationResponseMatchesOpenApi()` memeriksa subset schema yang didukung helper, bukan validator OpenAPI umum. Hasil run: Pint lulus, terarah 22 test/2591 assertions, suite penuh 81 test/9069 assertions pada MySQL 8.0.40 disposable; checker tidak menemukan mismatch. Request schema, status lain, aturan uang/retry/authorization di luar skenario, dan keputusan D04/D05/D06/D08/D09/D10/D11 belum tertutup. Semua operasi tetap `DRAFT`; run ini tidak menyelesaikan T-API-02/G3/G4.

## Slice request create transaksi

`TRANSACTION-REQUEST-CONFORMANCE-001` memvalidasi payload JSON dan parameter header yang dikirim oleh feature test untuk `POST /penjualans` dan `POST /pembelians` terhadap `requestBody` serta parameter operasi di OpenAPI. Cakupan positif: request create ringkas/rinci, replay, konflik 409 dengan body schema-valid, dan validasi tenant-crossing sale dengan body schema-valid. ID `menu_id` pada request sale dikirim sebagai string sesuai D13. Request purchase yang sengaja melanggar oneOf D10 tetap diuji sebagai 422, tetapi tidak dinyatakan cocok schema.

Helper bersama mendukung `oneOf` dengan tepat satu cabang cocok, `$ref` lokal, tipe dan batas yang sudah dipakai schema ini, serta header wajib `Idempotency-Key`. Hasil run: Pint lulus, tiga feature test transaksi/laporan 22 test / 3270 assertions, suite penuh 81 test / 9748 assertions pada MySQL 8.0.40 disposable; checker tidak menemukan mismatch pada request terpilih. Checker hanya memeriksa bentuk request dari fixture test, bukan membuktikan seluruh kemungkinan runtime. Tidak ada perubahan controller/business rule, status, skema DB, retry, atau keputusan D10. Semua operasi tetap `DRAFT`; query schema, body/status lain, dan gate penuh tetap terbuka. Detail command/environment ada pada artefak [TRANSACTION-REQUEST-CONFORMANCE-001](test-runs/TRANSACTION-REQUEST-CONFORMANCE-001.md).

## Slice conformance query transaksi dan laporan

`TRANSACTION-QUERY-CONFORMANCE-001` menambahkan `assertOperationQueryMatchesOpenApi()` untuk memeriksa fixture query sukses terhadap parameter operasi di `docs/api/openapi.yaml`, termasuk nama yang dideklarasikan, parameter required, parsing tipe scalar hasil serialisasi HTTP, schema, enum/rentang, dan `$ref` lokal. Fixture map yang diperiksa juga dipakai untuk membangun URL yang dikirim oleh feature test.

Cakupan: `GET /penjualans` memakai page, per_page, sort, dan status; `GET /pembelians` memakai page/per_page/sort pada dua halaman; kedua endpoint report mengirim date_from/date_to required dengan format Date. D13 page/per_page maksimum 100 dan sort allowlist sudah disetujui. Run: Pint lulus, tiga feature test transaksi/laporan 22 test / 3439 assertions, suite penuh 81 test / 9917 assertions pada MySQL 8.0.40 disposable; tidak ditemukan mismatch pada nilai query terpilih.

Checker menilai map fixture yang dipakai membangun URL, bukan request object Laravel aktual, dan bukan validator OpenAPI umum. Nilai invalid/unknown, seluruh kombinasi filter/status/operasi, serta batas per_page 100 tidak diperiksa di slice ini. Tidak ada perubahan DB, controller, business rule, tenant/authorization, retry, atau transaksi. Semua operasi tetap `DRAFT`; T-API-02/03 penuh dan gate G3/G4 tetap terbuka. Rincian ada di artefak [TRANSACTION-QUERY-CONFORMANCE-001](test-runs/TRANSACTION-QUERY-CONFORMANCE-001.md).

## Conformance query administrasi dan katalog

`ACCESS-CATALOG-QUERY-CONFORMANCE-001` menambahkan assertion query pada GET list `admin/warungs`, `users`, `kategori-menus`, dan `menus` menggunakan parameter OpenAPI. Query terpilih meliputi page/per_page/sort pada list yang sesuai; q dan aktif pada admin warung/kategori; q, kategori_menu_id, per_page/sort, beberapa halaman, serta filter aktif pada menu. Nilai fixture yang dicek adalah map yang sama dengan yang membangun URL feature test.

Run: Pint lulus; empat feature test area akses/katalog lulus 28 test / 3808 assertions; suite penuh lulus 81 test / 10554 assertions pada MySQL 8.0.40 Compose disposable. Tidak ada mismatch untuk nilai terpilih. Checker menguji map fixture yang membangun URL, bukan request object Laravel aktual, dan bukan validator OpenAPI umum. Query invalid/unknown, batas maksimum per_page=100, seluruh kombinasi/role/status, dan semua operasi belum dicakup. Slice hanya membaca data dan tidak mengubah schema, controller, role/tenant/filter behavior atau operasi tulis. Semua operasi tetap `DRAFT`; T-API-02/03 penuh, G1, dan G2 tetap terbuka. Rincian ada di artefak [ACCESS-CATALOG-QUERY-CONFORMANCE-001](test-runs/ACCESS-CATALOG-QUERY-CONFORMANCE-001.md).

## Conformance batas pagination

`ACCESS-PAGINATION-MAX-CONFORMANCE-001` menggunakan `assertOperationQueryMatchesOpenApi()` untuk `per_page=100`, lalu mengirim map yang sama melalui feature HTTP pada enam endpoint daftar: `/admin/warungs`, `/users`, `/kategori-menus`, `/menus`, `/penjualans`, dan `/pembelians`. Setiap response aktual bernilai 200, cocok dengan schema operasi dan memuat `meta.per_page=100`, `meta.page=1`.

Run lulus: Pint; satu test / 817 assertions; suite penuh 83 test / 11682 assertions pada MySQL 8.0.40 Compose disposable. Percobaan awal 403 pada request kedua terjadi karena auth guard di-cache ketika test harness mengganti bearer identity; test mereset guard sebelum request identity baru. Tidak ada perubahan controller, request validator, schema DB, policy, atau business behavior. Checker query memvalidasi map fixture yang juga membangun URL; response schema diperiksa pada response HTTP aktual. Nilai query lain, kombinasi filter/status/role, dan conformance seluruh operasi belum diuji. Semua operasi tetap DRAFT; T-API-02/03 penuh, G1, dan G2 masih terbuka. Detail ada di artefak [ACCESS-PAGINATION-MAX-CONFORMANCE-001](test-runs/ACCESS-PAGINATION-MAX-CONFORMANCE-001.md).

## Conformance penolakan batas pagination

Task BE-003/104/202/203/303/403, subset T-API-02/03 dan D13: untuk masing-masing dari enam GET list yang sama, kirim `per_page=101` sebagai satu kasus HTTP terpisah. Pastikan parameter operasi OpenAPI memakai batas maksimum 100 dan checker schema menolak 101; request aktual harus menghasilkan 422, response harus cocok schema 422, dan error envelope harus menyebut `per_page`. Gunakan data provider untuk menjalankan satu request per test case dan role yang sesuai: superadmin, owner, atau manager.

Run lulus: Pint; `ApiPaginationQueryConformanceTest` 7 test / 1093 assertions; suite penuh `php artisan test --display-warnings` 89 test / 11958 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Keenam endpoint memverifikasi maksimum OpenAPI 100, schema menolak nilai 101, request HTTP menghasilkan 422 yang cocok schema, dan errors menyebut `per_page` dengan batas 100. Tidak mengubah controller, validasi, schema database, tenant, authorization, atau business behavior. Compose dibersihkan. Lock Composer yang sudah memiliki marker konflik tidak diperbaiki dan `composer install` tidak dijalankan; test memakai vendor yang sudah tersedia. Semua operasi tetap DRAFT; invalid query lain, status/filter lain, dan gate T-API-02/03 penuh tetap terbuka. Detail ada di artefak [ACCESS-PAGINATION-OVERFLOW-CONFORMANCE-001](test-runs/ACCESS-PAGINATION-OVERFLOW-CONFORMANCE-001.md).

## Conformance batas bawah pagination

Task BE-003/104/202/203/303/403, subset T-API-02/03 dan D13: pada enam GET list yang sama, kirim `page=0` atau `per_page=0` sebagai satu kasus HTTP terpisah. Pastikan parameter OpenAPI masing-masing memiliki `minimum: 1` dan schema menolak nol; request aktual harus menghasilkan 422 yang cocok dengan response schema dan errors menyebut field yang diuji. Gunakan role endpoint yang sesuai dan map query yang sama untuk request aktual.

Run lulus: Pint targeted; `ApiPaginationQueryConformanceTest` 19 test / 1597 assertions; suite penuh `php artisan test --display-warnings` 101 test / 12462 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Dua belas request (dua parameter pada enam list) cocok dengan `minimum: 1` di OpenAPI, ditolak oleh checker schema saat bernilai 0, lalu menghasilkan response 422 yang cocok schema dan error field yang diuji. Scope hanya feature conformance/read; tidak mengubah controller, validasi, schema database, tenant, authorization, sort/filter, atau dependencies. Compose dibersihkan. Lock Composer dengan marker konflik tidak diubah dan `composer install` tidak dijalankan; runner memakai vendor yang tersedia. Semua operasi tetap DRAFT, query invalid lain serta gate T-API tetap terbuka. Detail ada di artefak [ACCESS-PAGINATION-LOWER-BOUND-CONFORMANCE-001](test-runs/ACCESS-PAGINATION-LOWER-BOUND-CONFORMANCE-001.md).

## Conformance tipe pagination

Task BE-003/104/202/203/303/403, subset T-API-02/03 dan D13: keenam GET list menerima masing-masing `page=abc` dan `per_page=abc` sebagai request terpisah. Parameter OpenAPI bertipe integer, schema menolak string `abc`, request aktual merespons 422 yang cocok schema, dan error field menyebut parameter yang diuji.

Run lulus: Pint; `ApiPaginationQueryConformanceTest` 31 test / 2101 assertions; suite penuh `php artisan test --display-warnings` 113 test / 12966 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Tidak mengubah controller, validator, schema database, tenant/authorization, sort/filter, atau business behavior. Compose dibersihkan. Lock Composer yang berisi marker konflik tidak diubah dan `composer install` tidak dijalankan. Semua operasi tetap DRAFT; numeric string lain, query invalid lain, status/role lain, serta conformance penuh masih terbuka. Detail ada di artefak [ACCESS-PAGINATION-TYPE-CONFORMANCE-001](test-runs/ACCESS-PAGINATION-TYPE-CONFORMANCE-001.md).

## Rencana conformance tipe pagination

Task BE-003/104/202/203/303/403, subset T-API-02/03 dan D13. Pada setiap enam GET list, kirim `page=abc` dan `per_page=abc` sebagai request terpisah. Pastikan OpenAPI mendefinisikan kedua parameter bertipe integer dan menolak string tersebut; request aktual harus menghasilkan 422 sesuai schema response dengan error pada parameter yang dikirim. Pakai data provider dan role endpoint yang sesuai.

Scope hanya feature conformance/read pada query pagination. Tidak mengubah endpoint, validator, business behavior, tenant/role, schema database, filter/sort, atau dependencies. Acceptance: 12 kasus HTTP dan schema lulus, Pint serta suite penuh lulus pada MySQL 8.0.40 disposable; seluruh operasi tetap DRAFT dan conformance lain masih terbuka.

## Rencana conformance allowlist sort

Task BE-003/104/202/203/303/403, subset T-API-02/03 dan D13. Untuk keenam GET list (`/admin/warungs`, `/users`, `/kategori-menus`, `/menus`, `/penjualans`, `/pembelians`), kirim `sort=created_at` yang bukan anggota enum operasi. Pastikan OpenAPI checker menolak enum value dan request aktual merespons 422 sesuai response schema dengan error field `sort`; gunakan role yang sah bagi tiap endpoint.

Scope hanya feature conformance/read terhadap allowlist yang sudah didefinisikan. Tidak mengubah endpoint, validator, sort fields, tenant/role, database, filter behavior, atau dependencies. Acceptance: enam kasus HTTP/schema lulus, Pint dan suite penuh lulus di MySQL 8.0.40 Compose disposable; operasi tetap DRAFT dan gate lain masih terbuka.

## Hasil conformance allowlist sort

`ACCESS-SORT-ALLOWLIST-CONFORMANCE-001` menjalankan enam request `sort=created_at` pada list warung, user, kategori, menu, penjualan, dan pembelian. Tiap kasus memastikan nilai berada di luar enum `sort` operasi pada OpenAPI, lalu request runtime menghasilkan HTTP 422 yang cocok schema error serta menyebut field `sort`.

Run lulus: Pint; `ApiPaginationQueryConformanceTest` 37 test / 2395 assertions; suite penuh `php artisan test --display-warnings` 119 test / 13260 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Compose dibersihkan. Cakupan hanya satu nilai invalid pada enam operasi dan response 422; sort valid tiap enum, kombinasi filter, seluruh role/status, serta conformance umum belum ditutup. Tidak mengubah API, DB, atau behavior; semua operasi tetap DRAFT. Bukti lengkap ada di artefak [ACCESS-SORT-ALLOWLIST-CONFORMANCE-001](test-runs/ACCESS-SORT-ALLOWLIST-CONFORMANCE-001.md).

## Rencana penerimaan seluruh enum sort

Task BE-003/104/202/203/303/403, subset T-API-02/03 dan D13. Jalankan satu feature case untuk tiap opsi enum pada enam GET list, sebanyak 14 pasangan operasi/nilai: `nama`/`-nama` pada warung, user, menu; `urutan`/`-urutan`/`nama`/`-nama` pada kategori; dan `-tanggal`/`tanggal` pada penjualan serta pembelian. Untuk setiap case, cocokkan query fixture dengan parameter OpenAPI, kirim request memakai role sah, pastikan HTTP 200 dan response cocok schema.

Scope hanya pembuktian penerimaan nilai sort yang didokumentasikan; tidak menguji urutan row atau tie-breaker dan tidak mengubah runtime behavior, request/controller, database, tenant/role, atau dependencies. Acceptance: seluruh 14 opsi diterima dan cocok schema, Pint serta suite penuh lulus pada MySQL 8.0.40 Compose disposable, lalu stack dibersihkan. Semua operasi tetap DRAFT dan gate lain terbuka.

## Hasil conformance seluruh enum sort

`ACCESS-SORT-VALID-ENUM-CONFORMANCE-001` memeriksa semua 14 pasangan opsi/operasi yang didefinisikan OpenAPI pada enam GET list. Query fixture yang sama dikirim melalui HTTP memakai superadmin, owner, atau manager yang sesuai; setiap request menghasilkan 200 dan response aktual cocok schema operasi.

Run lulus: Pint; `ApiPaginationQueryConformanceTest` 51 test / 3381 assertions; suite penuh `php artisan test --display-warnings` 133 test / 14246 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Compose dibersihkan. Ini membuktikan semua nilai sort yang tercantum diterima, tanpa assertion atas urutan row atau tie-breaker. Tidak mengubah API, DB, atau behavior; semua operasi tetap DRAFT. Bukti lengkap ada di artefak [ACCESS-SORT-VALID-ENUM-CONFORMANCE-001](test-runs/ACCESS-SORT-VALID-ENUM-CONFORMANCE-001.md).

## Rencana conformance tie-breaker sort

Task BE-003/104/202/203/303/403, subset T-API-02/03 dan D13. Untuk tiap 14 nilai sort yang didokumentasikan pada keenam GET list, buat dua row dengan nilai kolom sort yang sama. Minta halaman 1 dan 2 menggunakan `per_page=1`; pastikan `id` naik untuk arah ascending dan turun untuk opsi `-...`, setiap transaksi hanya muncul pada satu halaman, metadata total/last_page tepat, serta kedua response cocok schema OpenAPI. Gunakan superadmin untuk warung, owner untuk user, dan manager untuk kategori/menu/penjualan/pembelian; filter `q` menjaga fixture list warung/user tetap tepat.

Scope hanya feature test read dengan data sintetis; tidak mengubah controller, query, DB, tenant/policy, atau dependencies. Acceptance: 28 response HTTP dari 14 opsi sort cocok dengan aturan secondary ID dan schema, Pint serta suite penuh lulus pada MySQL 8.0.40 Compose disposable, lalu stack dibersihkan. Semua operasi tetap DRAFT dan conformance/gate lain terbuka.

## Hasil conformance tie-breaker sort

`ACCESS-SORT-TIE-BREAKER-CONFORMANCE-001` menjalankan 14 case pada enam GET list: dua record dengan nilai primary sort sama, `per_page=1`, lalu meminta page 1 dan page 2. Keempat belas opsi mencakup ascending dan descending; kedua page harus mengembalikan ID berurutan sesuai arah dan metadata total/last_page sama-sama 2. Setiap response juga dicocokkan dengan schema OpenAPI.

Run lulus: Pint; `ApiSortTieBreakerTest` 14 test / 3514 assertions; suite penuh `php artisan test --display-warnings` 147 test / 17760 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Compose dibersihkan. Tidak ada perubahan controller, query, database, policy, tenant, atau role. Scope membuktikan pagination stabil untuk primary key sort yang sama; operasi tetap DRAFT dan gate conformance lain terbuka. Bukti lengkap ada di artefak [ACCESS-SORT-TIE-BREAKER-CONFORMANCE-001](test-runs/ACCESS-SORT-TIE-BREAKER-CONFORMANCE-001.md).

## Rencana conformance urutan nilai sort

Task BE-003/104/202/203/303/403, subset T-API-02/03 dan D13. Untuk seluruh 14 pasangan nilai sort pada enam GET list, buat tiga row fixture dengan nilai kolom sort utama berbeda dan terurut jelas. Minta satu halaman dengan `per_page=3`; pastikan urutan ID/data naik menurut nilai utama untuk sort ascending dan turun untuk `-...`. Filter `q` membatasi fixture di list warung/user; endpoint lain memakai tenant fixture. Query dicocokkan ke parameter OpenAPI, response aktual ke schema, dan urutan data dinilai dari ID yang terkait dengan nilai primer berbeda. Role: superadmin warung, owner user, manager kategori/menu/penjualan/pembelian.

Scope hanya feature conformance/read; tidak mengubah controller, query, paginator, API, DB schema, filter, tenant/role/policy, atau dependencies. Acceptance: semua 14 opsi menghasilkan urutan sesuai nilai sort utama dan respons cocok schema; Pint serta suite penuh lulus pada MySQL 8.0.40 Compose disposable lalu stack dibersihkan. Semua operasi tetap DRAFT.

Pra-implementasi dicatat; hasil belum dijalankan. Setelah test, perbarui tracker, README API/OpenAPI, DECISIONS, TEST_PLAN, dan artefak run. File kode yang direncanakan: `tests/Feature/ApiSortOrderingConformanceTest.php`.

## Hasil conformance urutan nilai sort

Run lulus: Pint; `ApiSortOrderingConformanceTest` 14 test / 3492 assertions; suite penuh `php artisan test --display-warnings` 167 test / 22010 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Seluruh 14 opsi sort menghasilkan urutan sesuai nilai kolom primer yang berbeda; fixture dibuat dalam urutan C/A/B sehingga sort ID saja tidak memenuhi ekspektasi. Query dan seluruh response dicocokkan ke OpenAPI. Compose dibersihkan. Tidak ada perubahan controller, query, database, filter, tenant, atau role. Semua operasi tetap DRAFT dan gate conformance lain terbuka. Bukti lengkap ada di artefak [ACCESS-SORT-ORDERING-CONFORMANCE-001](test-runs/ACCESS-SORT-ORDERING-CONFORMANCE-001.md).

## Rencana conformance default list

Task BE-003/104/202/203/303/403, subset T-API-02/03 dan D13. Untuk enam GET list warung, user, kategori, menu, penjualan, dan pembelian, kirim request tanpa query string. Pastikan OpenAPI mendeklarasikan default `page=1`, `per_page=20`, serta `sort` sesuai operasi (`nama`, `urutan`, atau `-tanggal`); response aktual harus HTTP 200, cocok schema, memiliki metadata default tersebut, dan mengurutkan fixture menurut sort default. Gunakan fixture nilai primer berbeda dengan urutan insert yang sengaja tidak berhubungan dengan sort. Role: superadmin warung, owner user, manager sisanya.

Scope hanya feature conformance/read; tidak mengubah controller, query, paginator, API, DB schema, tenant/role/policy atau dependencies. Acceptance: keenam endpoint memberi metadata dan urutan sesuai default OpenAPI/runtime, Pint serta suite penuh lulus pada MySQL 8.0.40 Compose disposable, lalu stack dibersihkan. Semua operasi tetap DRAFT.

Pra-implementasi dicatat; hasil belum dijalankan. Setelah test, perbarui tracker, README API/OpenAPI, DECISIONS, TEST_PLAN dan artefak run. File kode yang direncanakan: `tests/Feature/ApiListDefaultsConformanceTest.php`.

## Hasil conformance default list

Run lulus pada 2026-10-05: Pint; `ApiListDefaultsConformanceTest` 6 test / 1718 assertions; suite penuh `php artisan test --display-warnings` 173 test / 23728 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Keenam request tanpa query string memakai `page=1`, `per_page=20`, serta sort default sesuai operasi. Urutan fixture dengan nilai berbeda dan metadata cocok dengan OpenAPI/runtime; response keenam route cocok schema. Compose dibersihkan. Tidak ada perubahan runtime/API/DB/tenant/role. Semua operasi tetap DRAFT dan gate conformance lain masih terbuka. Bukti ada di artefak [ACCESS-PAGINATION-DEFAULT-CONFORMANCE-001](test-runs/ACCESS-PAGINATION-DEFAULT-CONFORMANCE-001.md).

## Rencana conformance bentuk numerik pagination

Task BE-003/104/202/203/303/403, subset T-API-02/03 dan D13. Pada keenam GET list, kirim `page` dan `per_page` secara terpisah dengan representasi string desimal `1.0`, pecahan `1.5`, dan eksponen `1e2` (36 kombinasi). Pastikan OpenAPI parameter integer menolak tiap representasi, HTTP aktual merespons 422 dengan response schema sesuai dan error pada field yang diuji. Gunakan role sah setiap endpoint.

Scope hanya feature conformance/input validation; tidak mengubah API, validator, paginator, database, tenant/role, atau dependencies. Acceptance: seluruh 36 request cocok hasil schema/HTTP 422; Pint dan suite penuh lulus pada MySQL 8.0.40 Compose disposable lalu stack dibersihkan. Operasi tetap DRAFT.

Pra-implementasi dicatat; hasil belum dijalankan. Setelah test, perbarui tracker, README API/OpenAPI, DECISIONS, TEST_PLAN, dan artefak run. File kode direncanakan: `tests/Feature/ApiPaginationNumericTypeConformanceTest.php`.

## Hasil conformance bentuk numerik pagination

Run lulus pada 2026-10-05: Pint; `ApiPaginationNumericTypeConformanceTest` 36 test / 1512 assertions; suite penuh `php artisan test --display-warnings` 209 test / 25240 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Desimal `1.0`, pecahan `1.5`, dan eksponen `1e2` diuji terpisah untuk `page`/`per_page` pada enam GET list; semua request ditolak HTTP/schema 422 dengan error field yang diuji. Compose dibersihkan. Tidak ada perubahan validator, API, DB, tenant/role atau dependencies. Semua operasi tetap DRAFT; gate conformance lain terbuka. Bukti ada di artefak [ACCESS-PAGINATION-NUMERIC-TYPE-CONFORMANCE-001](test-runs/ACCESS-PAGINATION-NUMERIC-TYPE-CONFORMANCE-001.md).

## Rencana conformance page integer sangat besar

Task BE-003/104/202/203/303/403, subset T-API-02/03 dan D13. Pada keenam GET list, kirim `page=9223372036854775807` dan `per_page=100` setelah tersedia satu row cocok. Nilai `page` adalah integer maksimum 64-bit dan schema OpenAPI hanya membatasi minimum 1. Pastikan response HTTP 200/schema-valid mengembalikan `data=[]`, page yang diminta, total row asli, dan `last_page=1`; jangan sampai offset besar menghasilkan error database. Gunakan role sah tiap operasi dan fixture sesuai tenant/scope list.

Scope feature conformance/read; jika runtime gagal, perbaiki hanya path pagination terkait agar memenuhi hasil di atas. Jangan mengubah kontrak OpenAPI menjadi punya batas maksimum baru tanpa keputusan. Acceptance: enam response dan metadata sesuai schema/runtime; Pint serta suite penuh lulus pada MySQL 8.0.40 Compose disposable lalu stack dibersihkan. Semua operasi tetap DRAFT.

Pra-implementasi dicatat; hasil run ada di bawah. File kode `tests/Feature/ApiPaginationLargePageConformanceTest.php`; tracker, README API/OpenAPI, DECISIONS, TEST_PLAN dan artefak telah diperbarui.

## Hasil conformance page integer sangat besar

Probe awal gagal pada keenam list: `meta.page`, `per_page`, `total`, dan `last_page` sesuai harapan, tetapi `data` berisi row pertama. Offset SQL meluap saat Laravel menjalankan pagination biasa untuk nilai page maksimum 64-bit. Implementasi memindahkan keenam list ke `ApiPagination`, yang menghitung total terfilter dan tidak menjalankan query item ketika page melewati `last_page`; kontrak tidak diberi batas page maksimum baru.

Run lulus pada 2026-10-05: Pint; `ApiPaginationLargePageConformanceTest` 6 test / 358 assertions; suite penuh `php artisan test --display-warnings` 215 test / 25598 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Keenam GET list mengembalikan `data=[]`, `page=9223372036854775807`, `per_page=100`, `total=1`, dan `last_page=1`, cocok dengan schema response OpenAPI. Compose dibersihkan. Bukti mencatat probe gagal sebelum perbaikan dan rerun sukses di [ACCESS-PAGINATION-LARGE-PAGE-CONFORMANCE-001](test-runs/ACCESS-PAGINATION-LARGE-PAGE-CONFORMANCE-001.md). Cakupan runtime hanya page maksimum signed 64-bit dengan satu row cocok; operasi tetap DRAFT dan conformance/gate lain terbuka.

## Rencana conformance halaman kosong pagination

Task BE-003/104/202/203/303/403, subset T-API-02/03 dan D13. Pada masing-masing enam GET list, kirim request yang tidak mempunyai row cocok dan pastikan response HTTP 200 sesuai schema dengan `data=[]`, `total=0`, dan `last_page=1`. Sesudah membuat dua row yang cocok untuk endpoint tersebut, minta `page=3&per_page=1` dan pastikan `data=[]` tanpa menghilangkan total asli (`total=2`, `last_page=2`, `page=3`). Untuk list warung/user gunakan filter `q` tanpa kecocokan pada kasus kosong karena aktor/tenant test diperlukan; fixture seed kemudian dicocokkan dengan q. Validasi query terhadap OpenAPI dan response aktual terhadap schema.

Scope hanya feature conformance/read. Tidak mengubah paginator, API, DB schema, filter, tenant/role/policy, atau dependencies. Acceptance: 12 response pada enam route cocok dengan schema dan metadata; Pint serta suite penuh lulus pada MySQL 8.0.40 Compose disposable lalu stack dibersihkan. Semua operasi tetap DRAFT.

## Hasil conformance halaman kosong pagination

Run lulus: Pint; `ApiPaginationEmptyPageConformanceTest` 6 test / 758 assertions; suite penuh `php artisan test --display-warnings` 153 test / 18518 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Keenam GET list memberi `data=[]`, `total=0`, `last_page=1` untuk hasil kosong; setelah dua row dibuat, `page=3&per_page=1` memberi `data=[]`, `total=2`, `last_page=2`. Kedua response pada tiap operasi cocok schema OpenAPI. Compose dibersihkan. Tidak ada perubahan paginator, API, DB, filter, tenant/role/policy, atau dependencies. Semua operasi tetap DRAFT dan gate conformance lain terbuka. Bukti ada di artefak [ACCESS-PAGINATION-EMPTY-PAGE-CONFORMANCE-001](test-runs/ACCESS-PAGINATION-EMPTY-PAGE-CONFORMANCE-001.md).

## Conformance request administrasi dan katalog

`ACCESS-CATALOG-WRITE-REQUEST-CONFORMANCE-001` mencocokkan payload sukses yang sama-sama dipakai untuk request feature dengan requestBody OpenAPI pada delapan operasi: `POST /admin/warungs`, `PATCH /admin/warungs/{id}`, `POST/PATCH /users`, `POST/PATCH /kategori-menus`, dan `POST/PATCH /menus`. Create menu mengirim `kategori_menu_id` sebagai string sesuai D13.

Run awal pada checker sebelum perbaikan menghasilkan 28 lulus dan 5 gagal karena helper belum mendukung `writeOnly` (anotasi pada password) serta `minProperties` (batas body PATCH). Helper kini mengabaikan anotasi `writeOnly`, memeriksa `minProperties` untuk object, dan regression test membuktikan body update kosong ditolak checker. Run final pada HEAD `88a442bce492da0e202128995d98669d03eac3ef` lulus: Pint; lima feature file 34 test / 4307 assertions; suite penuh 82 test / 10781 assertions; cleanup Compose PASS. Tidak ada perubahan endpoint, schema, database, role/tenant behavior, atau keputusan bisnis.

Checker memvalidasi map fixture yang juga dikirim sebagai JSON body; ia tidak mengintersep request HTTP dan bukan validator OpenAPI umum. Cakupan hanya payload sukses terpilih dan regression schema checker untuk body kosong; field opsional/variasi penuh, payload invalid, status lain, dan seluruh operasi tetap di luar run. Semua operasi tetap `DRAFT`; T-API-02 penuh dan G1/G2 masih terbuka. Detail ada di artefak [ACCESS-CATALOG-WRITE-REQUEST-CONFORMANCE-001](test-runs/ACCESS-CATALOG-WRITE-REQUEST-CONFORMANCE-001.md).

Sebelum test DB, pastikan APP_ENV=testing, koneksi dan nama database adalah target test terisolasi, serta bukan data bersama/produksi. Jangan menjalankan refresh/wipe pada koneksi yang belum diketahui. `phpunit.xml` memakai SQLite memory sebagai default, tetapi gate FK/concurrency/migration memerlukan engine target D01. `compose.test.yaml` menyediakan project tersendiri dengan MySQL 8.0.40, DB `larissama_test`, tanpa port host dan tanpa volume data persisten; jangan mengganti host/database dengan konfigurasi development. Fake clock, dua tenant, dan koneksi terpisah membuat skenario dapat diulang.

## Fixture sintetis dan expected result

Dataset angka berikut terpisah dari data produksi. Contoh ini belum berasal dari database berjalan.

- Warung A dan B aktif, masing-masing punya owner, manager, kasir, kategori, dan menu. Tambahkan user nonaktif, warung nonaktif, warung belum mulai, dan warung kedaluwarsa untuk test akses.
- Warung A: Nasi harga `15000.00`, Teh `5000.00`. Warung B punya kode/nama sama tetapi harga berbeda untuk mendeteksi lookup lintas tenant.
- S1, A, 2026-10-04: Nasi 2 + Teh 1, diskon header `2000.00`, bayar cash `50000.00`. Subtotal `35000.00`, total `33000.00`, kembalian `17000.00` menurut kandidat D05.
- S2, A, tanggal sama: transaksi batal total `10000.00` untuk fixture laporan (jangan mengasumsikan endpoint cancel sudah ada). S3, B: penjualan selesai `99000.00`. S4, A: penjualan September `25000.00`.
- P1, A, 2026-10-04: satu rincian `Belanja di pasar`, nominal `150000.00`, qty/satuan/harga NULL.
- P2, A, tanggal sama: Beras 5 × `15000.00` dan Cabai 0.50 × `40000.00`; subtotal `75000.00` dan `20000.00`; total `95000.00`.
- P3, B: pembelian `900000.00`. P4, A, September: pembelian `40000.00`.
- Expected laporan Oktober A: jumlah penjualan selesai 1, pendapatan `33000.00`; jumlah pembelian 2, total `245000.00`. Tiga rincian pembelian tidak boleh mengubah count header 2 menjadi 3 atau menggandakan nominal.

Fixture batas waktu/rounding harus dibuat pada dataset terpisah agar tidak mengubah expected agregasi di atas. Angka pecahan dibandingkan secara eksak, bukan tolerance float.

## Matriks test

Kolom lulus menjelaskan observable result, bukan sekadar `assertStatus(200)`. Semua variasi tenant/role/operasi yang relevan harus diparameterisasi; satu endpoint yang lolos tidak membuktikan endpoint lain aman.

| ID | Skenario dan lapisan | Kriteria lulus | Prasyarat |
| --- | --- | --- | --- |
| T-ENV-01 | Runtime, dependencies, harness dan DB test terisolasi | Versi memenuhi composer constraints; harness jalan; identitas DB test terbukti; tidak menyentuh DB bersama. | D01, BE-001 |
| T-DB-01 | Migration baru pada engine target | Delapan tabel bisnis terbentuk sesuai rancangan; PK/FK gabungan/unique/index/nullability/decimal benar. Sesi MySQL melaporkan `@@session.time_zone = '+00:00'`, dan timestamp round-trip konsisten UTC. Detail membawa `warung_id`; `penjualan_rincis.menu_id` wajib; `jenis_item` tidak ada; idempotency key unik per tenant/user/endpoint (constraint terpisah pada tabel header). Jalankan subset tabel per milestone; full delapan pada M4/M5. | D01,D07,D08,D09,D16 |
| T-DB-02 | Upgrade users awal dan data lama | Data/ID/password hash yang sah tetap utuh; migration tidak menebak warung; hasil pemetaan sesuai sumber; tanpa edit migration bersama. | D01,D12 |
| T-DB-03 | Constraint dan relasi tenant | Kode/nomor boleh sama pada warung berbeda bila unique composite; duplikat dalam warung ditolak; orphan ditolak; FK gabungan D16 dan scope/validasi aplikasi sama-sama menolak relasi silang tenant. | D01,D12,D16 |
| T-AUTH-01 | Login benar/salah, me | Login sah hanya mengeluarkan token dan identitas user/warung aktual; username/password salah sama-sama 401 dengan pesan generik; validasi field kosong 422; `/auth/me` memuat identitas dari bearer saat ini. Response login/me dan error mengikuti field set D13 tanpa password, remember token, atau token pada `/me`. | D02,D03,D12,D13 |
| T-AUTH-02 | Nonaktif setelah token/sesi terbit | Login user/warung nonaktif mendapat 403 tanpa menerbitkan token; token yang terbit sebelum status dinonaktifkan mendapat 403 pada `/auth/me` dan satu route terlindungi (`GET /api/v1/warung`). User atau warung yang dinonaktifkan tidak membuat perubahan bisnis. | D02,D03 |
| T-AUTH-03 | Masa aktif, timezone, dan NULL | Pada instant UTC yang sama, akses mengikuti tanggal lokal masing-masing timezone; `tanggal_mulai` dan `tanggal_berakhir` berlaku inklusif. Buktikan rentang sebelum/di dalam/sesudah masa aktif, kedua tanggal NULL, dan masing-masing satu tanggal NULL. Penanganan timezone NULL/invalid masih keputusan sementara D08 dan belum menjadi kriteria final. | D03,D08 |
| T-AUTH-04 | Logout dan sesi kadaluwarsa | Token berlaku sesaat sebelum genap 30 hari dan ditolak tepat saat expiry 30 hari. Logout sukses 204 tanpa body, mencabut hanya token bearer yang dikirim, dan token kedua milik user tetap dapat dipakai. | D02,D13 |
| T-AUTH-05 | Password, log, rate limit | Password tersimpan sebagai hash dan tidak keluar di login/me; lima percobaan per menit pada username lowercase-equivalent+IP sama diproses dan percobaan keenam mendapat 429 dengan error envelope D13. IP berbeda memulai bucket terpisah. Password/token tidak boleh masuk log; fixture bukan kredensial nyata. | D02,D12,D13 |
| T-TEN-01 | Daftar/laporan A dengan data B | Tidak ada row, count, atau nominal B pada seluruh endpoint tenant, termasuk filter, search, pagination, dan summary. | D04 |
| T-TEN-02 | Detail/update ID milik B | User A mendapat 404 sesuai D13; data B tidak berubah; lookup rincian selalu melalui header tenant. | D04,D13 |
| T-TEN-03 | Injeksi warung/user/menu/kategori | Field pemilih tenant/user tidak didukung ditolak 422; referensi kategori/menu B ditolak; tetap tidak ada write parsial. | D04,D13 |
| T-RBAC-01 | Seluruh role × tindakan | Aksi yang diizinkan berhasil; semua kombinasi terlarang 403; superadmin tidak otomatis memakai jalur transaksi tenant; hak kasir atas riwayat mengikuti matriks final. | D04 |
| T-ADM-01 | Provision warung+owner, gagal owner | Superadmin menerima 201 dengan satu warung dan owner role `owner` yang terikat ke warung itu; password owner tersimpan hash. Bila penyimpanan owner gagal setelah warung dibuat, transaksi me-rollback keduanya tanpa memaparkan detail DB. | D04,D12,D13 |
| T-ADM-02 | Pengelolaan user, identitas dan eskalasi role | Owner dapat membuat manager/kasir dalam tenant sendiri, lalu list/detail/update hanya dalam tenant itu. Email nullable dan unique global bila terisi: nilai email identik yang sudah dipakai tenant lain ditolak 422 saat create/update, tanpa perubahan; email `NULL`/tidak dikirim tetap diterima. Role selain manager/kasir dan field `warung_id` pada create/patch mendapat 422; ID user tenant lain mendapat 404 dan datanya tidak berubah. Manager, kasir, dan superadmin tidak dapat memakai user administration owner. | D04,D12,D13 |
| T-ADM-03 | Daftar, detail, dan update warung oleh superadmin | Superadmin melihat semua warung dengan pencarian, filter aktif, sort allowlist, pagination, dan resource ID string; dapat membuka/mengubah warung termasuk status dan metadata. ID tidak ada mendapat 404, role selain superadmin mendapat 403, input tak dikenal/invalid ditolak 422, dan update gagal tidak mengubah row. | D04,D13 |
| T-ADM-04 | Profil warung user aktif | Manager dan kasir membaca profil dari `warung_id` autentikasi dan mendapat `WarungResponse` D13; superadmin mendapat 403 dan request tanpa token mendapat 401 dengan error envelope D13. Owner belum masuk acceptance karena cakupan profil owner belum ditetapkan pada D04. | D02,D04,D13 |
| T-CAT-01 | Kategori/menu create/read/update/filter | Data dan response sesuai input sah; kode unique per warung; kategori harus satu warung; tenant lain tidak terbaca/terubah; pagination/search/sort/aktif konsisten; kasir selalu hanya melihat menu/kategori aktif. | D04,D05,D06,D13 |
| T-CAT-02 | Nonaktifkan kategori/menu | Manager dapat mengubah status; kasir tidak menerima kategori/menu nonaktif (termasuk detail dan filter `aktif=false`); FK tetap menolak hard delete; aturan transaksi terhadap katalog nonaktif mengikuti D06. | D04,D06 |
| T-SAL-01 | Simpan S1 melalui API | Tepat 1 header dan 2 detail; subtotal 35000.00, total 33000.00, bayar 50000.00, kembalian 17000.00; response sama dengan nilai DB. | D05 |
| T-SAL-02 | Otoritas dan scope menu pada rincian | Setiap baris wajib menunjuk menu aktif pada warung sama; nama/harga dari client tidak dipercaya dan snapshot datang dari master. Menu salah tenant, nonaktif, ID tak ada, rincian kosong, atau field `jenis_item`/item bebas ditolak; tidak ada write parsial. | D05,D06,D07 |
| T-SAL-03 | Exception pada detail kedua | Header, detail pertama, dan efek nomor/retry rollback bersama; tidak ada orphan/penjualan setengah jadi. | D09 |
| T-SAL-04 | Nama/harga menu berubah setelah S1 | Read detail/riwayat S1 tetap nama/harga/total awal; transaksi baru mengikuti kebijakan harga final. | D05 |
| T-SAL-05 | Nilai negatif/nol/overflow/rounding/diskon/bayar | Uji decimal string eksak dan round half-up per rincian, termasuk 0.01 × 0.50 = 0.01 bila fractional qty dikonfirmasi. Validasi batas schema DECIMAL(15,2), diskon berlebih, dan cash kurang. Perilaku harga nol/fractional qty/QRIS-transfer mengikuti D05 final; asumsi implementasi sekarang bukan PASS sebelum disetujui. Semua input invalid 422 tanpa write. | D05 |
| T-SAL-06 | Cancel/koreksi penjualan | Bila masuk scope final: status/izin/alasan/audit sesuai keputusan, snapshot tetap; laporan mengeluarkan batal; pengulangan tidak menggandakan efek. Jika ditunda, keputusan defer dicatat, bukan PASS. | D06 |
| T-BUY-01 | Input P1 hanya nama_item+subtotal | 201, 1 header+1 detail, total 150000.00, qty/satuan/harga_satuan NULL; tidak menuntut master bahan. | K05,D05 |
| T-BUY-02 | Input P2 rinci | 201, 1 header+2 detail; subtotal `75000.00` dan `20000.00`, total `95000.00`; backend menghitung dengan decimal eksak dan round half-up per rincian. | D05,D10 |
| T-BUY-03 | Rincian kosong/nama kosong/mismatch/pasangan sebagian | Array kosong/nama kosong ditolak 422; implementasi kandidat juga menolak qty/harga_satuan yang tidak berpasangan dan subtotal mismatch. Bentuk P1 tetap diterima tanpa qty/unit/price; detail lain mengikuti keputusan final D10. | D10 |
| T-BUY-04 | Kegagalan detail terakhir | Tidak ada header/detail/total/efek retry parsial setelah rollback. | D09 |
| T-BUY-05 | Independensi pembelian | Setelah P1/P2, jumlah/nilai penjualan, menu, dan snapshot tidak berubah; tidak ada syarat maupun efek stok/resep. | K03 |
| T-BUY-06 | Koreksi pembelian dan laporan | Jika fitur disetujui: metadata/fakta original terjaga dan agregasi mengikuti D11. Jika ditunda, tidak membuat field status fiktif atau endpoint sendiri. | D11 |
| T-RET-01 | Retry intent yang sama setelah respons hilang | Key sama untuk tenant/user/endpoint dan payload identik menghasilkan HTTP 201 dengan id/header/detail yang sama; tepat satu header. Uji sale dan purchase. | D09 |
| T-RET-02 | Intent sama dengan payload berbeda | Key sama dengan payload body berbeda menghasilkan HTTP 409 `IDEMPOTENCY_KEY_REUSED`; tidak ada transaksi kedua/perubahan diam-diam. | D09 |
| T-RET-03 | Dua koneksi bersamaan | Request duplikat benar-benar overlap dan menghasilkan satu transaksi; key scope tenant/user/endpoint tidak bertabrakan antar user/warung/endpoint; nomor berbeda tetap unik. Gunakan barrier pada MySQL. | D01,D09 |
| T-RET-04 | Crash sebelum/sesudah commit dan restart worker | Key/hash/transaksi tetap konsisten setelah restart karena tersimpan pada header; sebelum commit tidak ada header, sesudah commit retry me-replay. Key tidak kedaluwarsa selama header dipertahankan. | D09 |
| T-REP-01 | Pendapatan Oktober A | Count 1 dan 33000.00; tidak memasukkan batal, B, September, bayar 50000.00, atau pembelian. | D06,D08 |
| T-REP-02 | Total pembelian Oktober A dan kapasitas agregasi | Count 2 dan 245000.00; 3 detail tidak menggandakan total/count. Report yang sama sebelum/sesudah baca halaman list berbeda tidak berubah. Dataset batas: dua header masing-masing 6000000000000.00 menghasilkan AggregateMoney 12000000000000.00 secara eksak, di atas batas satu header DECIMAL(15,2). | D05,D08,D11 |
| T-REP-03 | Batas hari dan timezone per warung | Asia/Jakarta 2026-10-04: UTC mulai 2026-10-03T17:00:00Z masuk; sebelum itu keluar; tepat 2026-10-04T17:00:00Z keluar. Kasus `America/New_York` pada 2026-03-08 menguji hari DST 23 jam dengan jendela `[2026-03-08T05:00:00Z, 2026-03-09T04:00:00Z)`: sebelum awal dan tepat akhir keluar, tepat awal dan detik terakhir masuk. Verifikasi `DATETIME_PRECISION` MySQL untuk kolom tanggal dan bahwa resolusi satu detik tercakup oleh record 23:59:59 serta batas eksklusif 00:00:00. | D08 |
| T-REP-04 | Periode kosong dan filter invalid | Pada kedua report, periode sah tanpa header memberi count=0 dan field total `0.00`. Untuk kedua report, format tanggal cacat, `date_to` hilang, atau `date_from > date_to` mendapat 422 dan bukan total nol palsu. | D08,D13 |
| T-API-01 | Lint spec/ref/contoh | OpenAPI 3.1 valid di validator M0; semua ref resolve, operationId unik, contoh request/response cocok schema; tidak mengklaim DRAFT sebagai live. | D13 |
| T-API-02 | Conformance terhadap HTTP aktual | Setiap operasi yang diserahkan diuji request/response sukses dan gagal; ID/nominal string, nullability, required, pagination, status HTTP dan tanpa field rahasia sesuai spec. Field set success/error mengikuti OpenAPI dan D13 (`data/meta`, `code/message/errors/request_id`). | Implementasi slice,D13 |
| T-API-03 | Pagination/search/sort/aktif | Batas 1–100, sort allowlist, tie-breaker stabil, empty page dan metadata benar; pencarian tetap scoped; input sort berbahaya tidak menjadi SQL bebas. | D13 |
| T-API-04 | Errors dan 204 | Error shape stabil dengan dotted field paths dan request_id; tanpa SQL/stack/secret; logout 204 tidak berisi JSON; 401/403/404 tidak tertukar. | D02,D13 |
| T-OPS-01 | Upgrade, backup/restore, rollback aplikasi | Pada environment uji, data fixture terjaga sesudah upgrade/recovery; instruksi runbook dapat diulang; tidak bergantung migrate:fresh produksi. | D01, runbook |
| T-OPS-02 | Config/CORS/log/secret/health | Origins/credential policy sesuai D02, env rahasia tidak di-commit/dilog, health aman; environment integrasi terdokumentasi dan dapat diakses penerima. | D02, deployment |
| T-E2E-01 | Alur API dari provisioning sampai laporan/logout | Warung/user/katalog/sale/P1/P2 terbentuk lewat API sah; angka fixture dan isolasi tenant benar; logout memutus akses. | M1–M4 |
| T-E2E-02 | Handoff frontend | Penerima mencatat versi spec/commit/base URL, mencoba contoh sukses/error dan kedua bentuk pembelian; hasil tercatat. Jika environment/penerima belum tersedia, tetap BLOCKED/PENDING. | Endpoint READY kandidat |

## Pra-implementasi verifikasi constraint tenant T-DB-03

Task BE-101/201/301/401, T-DB-03, INV01 dan D16. Sumber kebenaran: migrations tenant composite FK dan unique indexes; D16 disetujui user. Tambahkan `tests/Feature/TenantCompositeForeignKeyTest.php` menggunakan MySQL Compose disposable dan `RefreshDatabase`.

Cakupan langsung pada DB: keenam FK gabungan (menu-kategori, header penjualan-user, detail penjualan-header/menu, header pembelian-user, detail pembelian-header) menolak kombinasi lintas tenant; relasi orphan ditolak; kode menu serta nomor transaksi boleh sama antar-warung tetapi duplikat di warung yang sama ditolak oleh unique index. Setiap assertion mengidentifikasi constraint yang gagal agar error lain tidak dianggap lulus. API tenant/validasi sudah punya cakupan terpisah dan tidak diubah di slice ini.

Tidak mengubah migration, schema, model, data selain fixture test, auth/policy, API atau keputusan Dxx. Acceptance: seluruh constraint yang diuji menghasilkan constraint DB yang tepat, duplikasi lintas tenant diterima, target dan suite penuh lulus di MySQL 8.0.40, Pint lulus, Compose dibersihkan; bukti dan status task diperbarui. T-DB-03 penuh tetap parsial bila constraint yang belum diuji atau jalur aplikasi belum punya bukti.

## Hasil verifikasi constraint tenant T-DB-03

`TENANT-COMPOSITE-FK-CONSTRAINT-001` menguji enam FK gabungan langsung pada MySQL: menu-kategori, penjualan-user, detail penjualan-header/menu, pembelian-user, dan detail pembelian-header. Semua menolak relasi lintas warung dengan nama constraint yang diharapkan. FK kategori juga menolak ID kategori orphan. Unique index menerima kode menu/nomor sale/nomor purchase yang sama di dua warung dan menolak duplikat dalam warung yang sama.

Run lulus pada 2026-10-05: Pint; `TenantCompositeForeignKeyTest` 10 test / 13 assertions; suite penuh `php artisan test --display-warnings` 225 test / 25611 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. `RefreshDatabase` mengisolasi fixture dan Compose dibersihkan. Tidak mengubah migration atau runtime. Ini menutup kasus constraint database yang tercantum pada rencana; T-DB-01 full, upgrade users lama T-DB-02, rollback migration, dan gate milestone tetap terbuka. Rincian ada di [TENANT-COMPOSITE-FK-CONSTRAINT-001](test-runs/TENANT-COMPOSITE-FK-CONSTRAINT-001.md).

## Pra-implementasi audit schema database T-DB-01

Task BE-101/201/301/401, T-DB-01, INV01–INV11, D01/D07/D08/D09/D16. Sumber kebenaran: delapan migration bisnis yang sudah ada dan skema target MySQL 8.0.40. Tambahkan `tests/Feature/BusinessSchemaMigrationConformanceTest.php` dengan `RefreshDatabase`.

Audit metadata tabel `warungs`, `users`, `kategori_menus`, `menus`, `penjualans`, `penjualan_rincis`, `pembelians`, `pembelian_rincis`: kolom/tipe penting, PK `id`, indeks tenant/unique/idempotency, nullable, decimal precision/scale, `DATETIME_PRECISION`, wajibnya `penjualan_rincis.menu_id`, tidak adanya `jenis_item`, dan pasangan nullable untuk rincian pembelian. Verifikasi sesi MySQL `@@session.time_zone = '+00:00'` serta round-trip nilai tanggal yang dibuat sebagai UTC.

Scope hanya observasi schema/DB disposable dan insert fixture round-trip; tidak mengubah migration, schema, data bersama, API/business rules, atau dependensi. Acceptance semua delapan tabel dan metadata yang dinyatakan cocok migration, indeks yang diwajibkan punya susunan kolom tepat, sesi/time round-trip UTC terbukti, Pint/focused/full suite MySQL 8.0.40 lulus dan Compose dibersihkan. Upgrade users legacy T-DB-02 serta rollback migration tetap terpisah.

Addendum pra-implementasi: audit metadata T-DB-01 juga harus mencocokkan seluruh FK pada `information_schema.KEY_COLUMN_USAGE` (nama constraint, kolom lokal, tabel/kolom referensi, dan urutan composite) serta `DELETE_RULE=RESTRICT`, termasuk FK `warung_id` induk dan FK gabungan D16. File tetap `BusinessSchemaMigrationConformanceTest.php`; ini hanya memperluas assertion read-only sebelum status T-DB-01 diringkas.

## Hasil audit metadata schema T-DB-01

`DATABASE-SCHEMA-MIGRATION-CONFORMANCE-001` lulus untuk audit fresh migration delapan tabel bisnis pada MySQL 8.0.40. Empat test memeriksa tabel/PK, kolom kunci dan nullability/decimal/presisi, indeks yang ditetapkan, seluruh 11 FK dengan kolom dan urutan yang tepat serta aturan `RESTRICT`, juga sesi dan round-trip UTC.

Run 2026-10-05: Pint lulus; focused `BusinessSchemaMigrationConformanceTest` 4/210; suite penuh `php artisan test --display-warnings` 229/25821 pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Compose dibersihkan. T-DB-01 lulus untuk kriteria fresh-schema yang direncanakan dan diuji di sini. T-DB-02 (upgrade/backfill data lama), rollback migration, serta seluruh gate milestone tetap terbuka. Rincian batas audit ada di [artefak run](test-runs/DATABASE-SCHEMA-MIGRATION-CONFORMANCE-001.md).

## Pra-implementasi cakupan kolom lengkap T-DB-01

Audit yang ada memeriksa kolom penting dan semua tabel/index/FK, tetapi belum membandingkan kolom lain satu per satu. Tambahkan satu test ke `BusinessSchemaMigrationConformanceTest.php` dengan expected metadata seluruh kolom pada delapan tabel bisnis dari migration yang berlaku. Untuk tiap tabel, pastikan himpunan dan urutan `COLUMN_NAME` tepat; untuk tiap kolom cocokkan `COLUMN_TYPE` lengkap (termasuk panjang, precision/scale dan unsigned), `IS_NULLABLE`, `COLUMN_DEFAULT`, serta `EXTRA` (`auto_increment` atau `on update CURRENT_TIMESTAMP`). `DATETIME_PRECISION` untuk tipe temporal ikut dibandingkan. Nilai metadata harus dinormalisasi secara konsisten agar string default angka/capitalization tidak menghasilkan beda semu.

Scope hanya pembacaan `information_schema` sesudah fresh migration MySQL 8.0.40. Jangan mengubah migration, runtime, keputusan, data bersama, atau dependency. Pertahankan pemeriksaan index/FK/UTC yang sudah ada. Pint, focused/full suite, catat jumlah assertion/hash/evidence, dan bersihkan Compose; T-DB-02 mapping legacy tetap terbuka.

## Pra-implementasi koreksi authorization profil warung

Task BE-104/T-ADM-04, D02/D04/D13. Acceptance awal menemukan manager dan kasir mendapat 403 dari `GET /api/v1/warung`. Pemeriksaan Laravel terpasang menunjukkan `Gate::authorize('viewCurrent', $user)` memilih `UserPolicy`, sedangkan ability `viewCurrent` didefinisikan pada `WarungPolicy`. Perbaikan yang direncanakan hanya mengirim `Warung::class` sebagai subject Gate agar policy yang sudah ada dipilih; kondisi role pada policy tidak berubah.

Acceptance tetap memeriksa manager/kasir membaca data warung dari token, body 200 cocok `WarungResponse`, superadmin mendapat 403, dan tanpa token mendapat 401 dengan error envelope D13. Owner tidak termasuk kandidat contract sampai cakupan role diputuskan di D04. Tidak ada perubahan schema/data bisnis. Jalankan focused dan full test di MySQL 8.0.40 Compose disposable, Pint, lalu cleanup.

## Pra-implementasi verifikasi email user unik D12

Task BE-104/T-ADM-02 dan D12. Sumber kebenaran: D12 menyetujui email nullable dan unik global ketika terisi; migration saat ini mempunyai `users_email_unique`, dan `UserStoreRequest`/`UserUpdateRequest` menerapkan validasi unique global. Tambah `tests/Feature/UserEmailUniquenessApiTest.php`.

Acceptance: owner tenant A mencoba membuat dan mengubah user memakai email identik yang sudah dipakai user tenant B; kedua request harus mendapat 422 dengan error field `email` dan tidak mengubah record. Dua user dengan email `NULL` eksplisit atau field email tidak dikirim harus diterima dengan response/database `email = null`. Request/response sukses dan error dicocokkan dengan schema OpenAPI. Jangan menambahkan normalisasi, lowercase, atau aturan case sensitivity; aspek itu tetap terbuka pada D12. Tidak mengubah migration, schema, atau validasi sebelum hasil test menunjukkan masalah.

Verifikasi memakai MySQL 8.0.40 test Compose disposable dan `RefreshDatabase`; jalankan focused test, Pint, suite penuh, bersihkan Compose, lalu catat hasil dan batasnya. Tidak menambah dependency.

## Hasil acceptance profil warung T-ADM-04

`CURRENT-WARUNG-API-ACCEPTANCE-001` lulus. Manager dan kasir membaca row warung miliknya dari token dan query `warung_id` tenant lain tidak mengubah sasaran. Seluruh field sukses cocok dengan `WarungResponse`; superadmin ditolak 403, request anonim 401. Probe awal memperlihatkan dua role yang seharusnya diizinkan justru 403 karena subject Gate memilih `UserPolicy`; controller sekarang memilih `WarungPolicy` lewat `Warung::class`, tanpa mengubah syarat role.

Pint lulus; focused 4/238; suite penuh `php artisan test --display-warnings` 233/26059 pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Compose dibersihkan. D04 belum menetapkan akses owner ke profil, jadi operasi tetap DRAFT; G1 dan conformance lain masih terbuka. Rincian probe gagal dan batas cakupan ada di [artefak run](test-runs/CURRENT-WARUNG-API-ACCEPTANCE-001.md).

## Hasil verifikasi email user T-ADM-02

`USER-EMAIL-UNIQUENESS-001` lulus: Pint, focused 4 test / 242 assertions, dan suite penuh 237 test / 26301 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Email exact duplicate milik user tenant lain ditolak 422 dengan field error `email` saat create maupun update; tidak ada record yang berubah. Email eksplisit `null` dan email tak dikirim diterima sebagai null. Constraint `users_email_unique` diuji langsung di MySQL: duplicate lintas warung ditolak dan beberapa NULL diterima. Request/response skenario terpilih cocok dengan schema OpenAPI. Compose dibersihkan. Variasi huruf, normalisasi, dan mapping identitas lama tidak termasuk; D12 tetap PARTIAL dan operasi tetap DRAFT. Lihat [artefak run](test-runs/USER-EMAIL-UNIQUENESS-001.md).

## Pra-implementasi perlindungan migrasi user lama T-DB-02

Task BE-101/T-DB-02 dan D01/D12. Migration `2026_10_04_065854_adapt_users_for_larissama_tenants.php` saat ini menolak berjalan jika tabel `users` berisi data, sebelum mengganti kolom atau menambah kolom tenant. Tambah `tests/Feature/LegacyUserMigrationSafetyTest.php` untuk menjalankan method `up()` migration secara langsung terhadap database test MySQL disposable yang memiliki satu user tersimpan.

Acceptance: migration melempar pesan preflight yang sudah ditetapkan; seluruh kolom tabel dan seluruh nilai row user sebelum/sesudah identik, termasuk ID, nama, email, password hash dan timestamps. Ini menguji penolakan aman saat data ada, bukan migrasi sukses atas schema legacy atau validitas pemetaan user ke warung. Tidak mengubah migration atau membuat pemetaan. Gunakan `RefreshDatabase`, Pint, focused/full suite MySQL 8.0.40, lalu bersihkan Compose. T-DB-02 untuk upgrade/backfill tetap terbuka.

## Hasil perlindungan migration users existing T-DB-02

`LEGACY-USER-MIGRATION-SAFETY-001` lulus untuk guard migration. Pint lulus, test terarah 1/4, dan suite penuh 238/26305 pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Dengan user tersimpan, pemanggilan migration menghasilkan pesan preflight yang diharapkan; daftar kolom dan row mentah lengkap, termasuk ID, email, password hash dan timestamps, tetap identik. Migration berhenti sebelum DDL. Compose dibersihkan. Cakupan ini tidak membuktikan upgrade schema legacy, pemetaan tenant, backfill, atau rollback; T-DB-02 tetap terbuka. Lihat [artefak run](test-runs/LEGACY-USER-MIGRATION-SAFETY-001.md).

## Pra-implementasi overlap idempotency penjualan T-RET-03

Task BE-304/T-RET-03 dan D09. `IdempotencyConcurrencyTest` sekarang membuktikan dua proses HTTP Kernel menulis satu pembelian untuk key/payload identik, tetapi belum mempunyai skenario penjualan concurrent. Perluas worker test agar secara eksplisit menerima hanya tipe transaksi `penjualan` atau `pembelian` dan payload test terpilih. Tambah case penjualan dengan kasir dan menu fixture, barrier dua proses sebelum request, serta trigger MySQL sementara yang menahan insert header penjualan.

Acceptance: dua request `POST /api/v1/penjualans` benar-benar mencapai barrier (dua file arrival), keduanya mengembalikan 201 untuk ID dan nomor transaksi yang sama, tersimpan tepat satu header dan satu detail, dan waktu run di bawah tiga detik dengan delay satu detik untuk menunjukkan overlap. Worker timeout/503 atau proses serial yang melewati batas gagal. Jalankan ulang test pembelian untuk memastikan worker generik tetap benar. Hanya file test, worker test, dan dokumentasi run yang boleh berubah; tidak mengubah API/runtime/schema bisnis/dependencies. Pint, focused, suite penuh pada MySQL 8.0.40 Compose disposable dan cleanup wajib. D09 scope-key lain dan crash/restart tetap terbuka.

## Pra-implementasi payload conflict concurrent T-RET-03

Task BE-304/BE-404, T-RET-03 dan D09. Retry sequential serta overlap payload identik sale/purchase telah diuji. Belum diuji dua request serentak yang menggunakan key sama tetapi isi payload berbeda, padahal D09 menetapkan HTTP 409 `IDEMPOTENCY_KEY_REUSED`.

Tambah data provider untuk sale dan purchase pada `tests/Feature/IdempotencyConcurrencyTest.php`. Buat dua proses HTTP Kernel yang hadir pada barrier sama; berikan masing-masing payload valid berbeda, key sama, dan trigger MySQL sementara yang menahan insert header. Acceptance: satu response 201 dan satu 409 dengan code `IDEMPOTENCY_KEY_REUSED`, hanya satu header/detail tersimpan, kedua proses benar-benar mencapai barrier, serta elapsed di bawah tiga detik. Pemenang 201 tidak ditentukan. Run harus mempertahankan test overlap payload identik sale/purchase. Hanya test/worker/docs, tanpa source API, migration, schema bisnis, atau dependency. Pint, focused/full MySQL 8.0.40 dan cleanup.

## Hasil overlap idempotency penjualan dan pembelian T-RET-03

`IDEMPOTENCY-CONCURRENCY-001` lulus untuk retry payload identik: Pint, focused `IdempotencyConcurrencyTest` 2/17, dan suite penuh 239/26314 pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Dua proses HTTP Kernel mencapai barrier sebelum request. Trigger MySQL satu detik menahan insert header; kedua request sale dan kedua request purchase mendapat 201 dengan ID/nomor transaksi sama, tepat satu header/detail tersimpan, dan elapsed di bawah tiga detik. Run awal 404 karena helper menyusun route singular; allowlist route plural diperbaiki sebelum rerun yang lulus. Stack Compose dibersihkan. Ini belum menguji konflik payload bersamaan, isolasi key lintas user/warung/endpoint, crash/restart, atau nomor unik untuk request berbeda; D09/T-RET-03 tetap PARTIAL dan transaksi tetap DRAFT. Detail ada pada [artefak run](test-runs/IDEMPOTENCY-CONCURRENCY-001.md).

## Hasil konflik payload idempotency bersamaan T-RET-03

`IDEMPOTENCY-CONFLICT-RACE-001` lulus untuk penjualan dan pembelian: Pint, focused `IdempotencyConcurrencyTest` 4/37, dan suite penuh 241/26334 pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Untuk tiap jenis transaksi, dua proses HTTP Kernel dengan bearer user/tenant/endpoint sama mencapai barrier sebelum request, memakai key sama dan payload berbeda, lalu memperoleh satu 201 serta satu 409 `IDEMPOTENCY_KEY_REUSED`. Hanya satu header/detail tersimpan; ID response 201 cocok dengan row, elapsed kurang dari tiga detik, dan skenario payload identik tetap lulus. Compose dibersihkan. Cakupan ini tidak menguji scope-key lintas user/warung/endpoint, crash/restart, retensi, atau nomor unik untuk request berbeda; D09/T-RET-03 masih PARTIAL dan seluruh operasi transaksi tetap DRAFT. Detail ada pada [artefak run](test-runs/IDEMPOTENCY-CONFLICT-RACE-001.md).

## Pra-implementasi scope key dan nomor unik concurrent T-RET-03

Task BE-304/BE-404, T-RET-03, D09. Migration menetapkan unique key `(warung_id,user_id,idempotency_key)` terpisah di setiap tabel header; endpoint menjadi scope lewat pemisahan tabel/action. Acceptance concurrency yang tersisa: pemakaian key sama pada konteks actor/tenant/endpoint berbeda tetap membuat transaksi mandiri; key berbeda untuk request baru menghasilkan nomor berbeda.

Tambah dua feature test provider pada `tests/Feature/IdempotencyConcurrencyTest.php`. Untuk konteks scope, jalankan dua worker HTTP serentak dengan key dan payload yang sama, tapi konteks valid: (a) dua kasir pada satu tenant dan endpoint penjualan; (b) dua manager pada tenant berbeda dan endpoint pembelian; (c) kasir dan manager pada satu tenant melalui endpoint penjualan/pembelian. Setiap pair harus memberi dua 201, satu header/detail per request, dan masing-masing row berada pada user/tenant yang benar. Untuk pair dalam tabel sama, ID dan nomor harus berbeda; pada pair lintas endpoint, ID boleh kebetulan sama karena tabel memakai auto-increment masing-masing, sehingga periksa row pada tabel/action yang benar dan nomor transaksinya.

Untuk nomor unik, jalankan dua request berbeda secara bersamaan dalam scope yang sama untuk tiap endpoint: user dan tenant sama, key serta catatan berbeda. Acceptance: dua 201, ID/no_transaksi berbeda, tepat dua header/detail, dan nomor tersimpan yang kembali pada response sama dengan dua row. Kedua worker harus telah mencapai barrier bersama sebelum request dilepas. Jangan menambahkan insert trigger pada dua request yang menulis tabel header sama: probe menunjukkan delay trigger tabel tersebut membuat operasi serial dan melampaui batas waktu, sehingga mengukur trigger lock, bukan concurrency request. Untuk pasangan lintas endpoint yang menulis dua tabel berbeda, gunakan trigger MySQL dua detik pada tiap tabel. Ukur overlap dari interval `Kernel::handle()` tiap worker dan assert kedua interval beririsan; jangan memakai total elapsed yang juga menghitung proses boot. Bersihkan trigger/barrier/fixture. Tidak mengubah source API, migration, schema, atau dependency. Pint, focused/full MySQL 8.0.40 Compose disposable dan cleanup wajib. Crash/restart serta retensi key tetap terpisah dan belum dibuktikan.

## Addendum rencana pengukuran overlap HTTP D09

Probe menunjukkan total elapsed tidak cukup stabil untuk acceptance: trigger dua detik pada tabel yang sama menghasilkan 4.14 detik; setelah trigger same-table dihapus, satu skenario conflict lama mencapai 3.32 detik dan melampaui ambang tiga detik, sementara response dan data benar. Angka tersebut mencakup startup/bootstrap kedua PHP Kernel, bukan hanya permintaan HTTP.

Pindahkan barrier worker setelah Laravel Kernel dan Request siap. Catat `microtime(true)` tepat sebelum dan sesudah `$kernel->handle($request)`, kirim nilai itu dalam output worker test, lalu assert `max(started_at) < min(finished_at)` dan dua arrival. Ini mengukur overlap HTTP aktual secara langsung, sehingga tidak bergantung pada overhead startup atau penjadwalan. Pertahankan trigger untuk menahan konflik insert dan trigger dua tabel pada pasangan lintas endpoint; hapus batas total elapsed dari concurrency tests. Worker hanya dipakai test, tidak mengubah response aplikasi.

## Hasil scope key dan nomor transaksi concurrent T-RET-03

`IDEMPOTENCY-SCOPE-NUMBER-001` lulus: Pint; `IdempotencyConcurrencyTest` 9 test / 115 assertions; suite penuh 246/26412 pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 Compose disposable. Dua worker menyiapkan Kernel/Request sebelum barrier; interval `Kernel::handle()` aktual terbukti beririsan pada semua kasus. Key sama/payload sama membuat dua transaksi mandiri (201 masing-masing) untuk dua kasir satu tenant di endpoint sale, dua manager beda tenant di endpoint purchase, dan actor sale/purchase yang sah dalam satu tenant. Setiap row cocok user/tenant/tabel dan memiliki satu detail. Dua key berbeda bersamaan dalam scope sama menghasilkan dua 201, ID/no_transaksi berbeda, tepat dua header/detail, dan nomor di response sama dengan DB pada kedua endpoint. Compose dibersihkan.

`user_id` yang unique global serta FK tenant mengikat tiap user ke satu warung; role juga memisahkan endpoint sale dan purchase. Karena itu tenant dan endpoint diuji melalui kombinasi konteks sah, bukan divariasikan sendiri dengan actor yang sama. D09/T-RET-03 terbukti untuk konteks tersebut; rollback saat worker mati sebelum commit dan replay saat worker baru mengulang key setelah response hilang telah diuji untuk sale/purchase (`IDEMPOTENCY-CRASH-RESTART-001`). Retensi setelah header dihapus masih terbuka, sehingga keputusan D09 serta seluruh operasi transaksi tetap PARTIAL/DRAFT. Detail ada di [artefak run](test-runs/IDEMPOTENCY-SCOPE-NUMBER-001.md).

## Rencana crash/restart idempotency T-RET-04

Tambahkan data provider penjualan/pembelian pada `IdempotencyConcurrencyTest.php`. Untuk crash sebelum commit, pasang trigger MySQL sementara pada insert detail yang mengambil named lock lalu menahan query. Jalankan satu request pada proses PHP terpisah; setelah parent melihat lock diambil (berarti header sudah masuk dan insert detail sedang berjalan di dalam transaksi), kirim SIGKILL ke worker. Tunggu koneksi MySQL melepas lock, hapus trigger, lalu pastikan tidak ada header maupun detail dengan key itu. Kirim ulang payload/key yang sama dari proses PHP baru; harapkan HTTP 201 dan tepat satu header/detail dengan response cocok row.

Untuk simulasi response hilang sesudah commit, jalankan request sukses dalam proses PHP terpisah dan buat worker keluar dengan kode 97 setelah Kernel membentuk respons HTTP 201 tetapi sebelum respons ditulis. Ulangi key/payload identik pada proses PHP baru yang membangun Kernel baru. Harapkan retry HTTP 201 berisi ID/no_transaksi yang sama dan tetap tepat satu header/detail. Periksa kedua proses punya PID berbeda dan seluruh row cocok user/tenant serta endpoint yang diuji. Proses PHP test worker boleh menerima request tanpa barrier untuk skenario berurutan; mode barrier concurrency existing tetap diwajibkan pada test concurrency.

Trigger hanya dipakai DB test MySQL 8.0.40 disposable; hapus dalam `finally`, paksa stop worker bila assertion gagal, dan pastikan tidak ada koneksi/trigger yang tertinggal. Tidak mengubah runtime API, migration, schema, atau dependency. Catat Pint, focused `IdempotencyConcurrencyTest`, suite penuh, hashes, dan cleanup. Uji ini membuktikan rollback transaksi sebelum commit dan replay durable setelah worker baru hidup; tidak menetapkan masa retensi setelah header dihapus, dan semua operasi tetap DRAFT sampai conformance lain lulus.

## Gate milestone

| Gate | Test wajib dan hasil yang diterima |
| --- | --- |
| G0 / M0 | T-ENV-01 dan T-API-01 lulus; D01/D02/D03/D04/D08/D12 dan baseline wire D13 ditetapkan; harness dan DB test siap. |
| G1 / M1 | T-DB-01/02/03 untuk warung/users, T-AUTH-01–05, T-TEN-01–03, T-RBAC-01, T-ADM-01–03, T-API-02/04 untuk operasi M1 semuanya lulus. |
| G2 / M2 | T-DB-01/03 untuk katalog, T-CAT-01–02, T-TEN-01–03, T-RBAC-01, T-API-02/03/04 untuk katalog lulus. |
| G3 / M3 | T-SAL-01–05, T-RET-01–04 untuk penjualan, T-REP-01/03/04, test DB/tenant/role/contract slice lulus. T-SAL-06 lulus atau scope defer telah diputuskan eksplisit. |
| G4 / M4 | T-BUY-01–05, T-RET-01–04 untuk pembelian, T-REP-02/03/04, test DB/tenant/role/contract slice lulus. T-BUY-06 lulus atau scope defer eksplisit. |
| G5 / M5 | Semua gate sebelumnya terpenuhi; T-OPS-01–02 dan T-E2E-01–02 lulus; tidak ada kontrak live tanpa bukti conformance; runbook dan handoff lengkap. |

Test tenant, uang, rollback, retry, dan kontrak yang wajib harus 100% lulus dengan nol failure/error. Skipped, blocked, atau not-run tidak dihitung sebagai pass. Defer hanya berlaku pada fitur opsional yang benar-benar diputuskan ditunda, bukan menghilangkan syarat K01–K07. Persentase coverage baris saja tidak menggantikan invariant.

## Rencana command dan bukti

Periksa konfigurasi Compose sebelum menjalankan test, lalu jalankan suite terfokus hanya pada project test. `RefreshDatabase` menjalankan migration pada DB `larissama_test`; jangan mengarahkan koneksi itu ke service development atau DB lain. `IdempotencyConcurrencyTest` menjalankan dua proses Laravel HTTP Kernel dengan koneksi MySQL terpisah, menahan keduanya pada barrier sebelum request, dan memakai trigger delay sementara pada DB disposable.

```sh
docker compose -f compose.test.yaml config --quiet
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer install --no-interaction && php artisan config:clear && php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php tests/Feature/TransactionAtomicityTest.php tests/Feature/IdempotencyConcurrencyTest.php'
docker compose -f compose.test.yaml down --remove-orphans
```

T-API-01 memvalidasi dokumen OpenAPI statis; T-API-02 membandingkan runtime terhadap kontrak per operasi. Sebelas run transaksi/report serta run auth acceptance, response/request conformance, admin/user tenant, admin warung, conformance admin warung, conformance provisioning/user, acceptance katalog, conformance katalog, query/request akses-katalog, pagination, audit schema, email unik dan guard migration users menguji subset aplikasi pada DB MySQL terisolasi. Suite `php artisan test --display-warnings` terbaru lulus 250 test / 26452 assertions pada MySQL 8.0.40 Compose disposable. T-REP-02/03/04 lulus untuk skenario report yang direncanakan. Auth membuktikan login/me untuk role yang disepakati, error D13 subset, nonaktif, timezone tanggal/NULL, masa token/logout, password/log hygiene, limiter 5/6 dengan isolasi IP, dan response schema runtime untuk login 200/401/403/422/429, me 200/401/403, serta logout 204 tanpa body; `AUTH-REQUEST-CONFORMANCE-001` mencocokkan body login positif untuk sukses empat role dan kredensial salah/401 dengan `LoginRequest`; D08 timezone NULL/invalid dan D12 normalisasi identitas belum final. `USER-EMAIL-UNIQUENESS-001` membuktikan email identik unik global saat create/update dan email NULL/diabaikan diterima (focused 4/242); variasi case tidak diuji. `LEGACY-USER-MIGRATION-SAFETY-001` membuktikan migration menolak non-empty users sebelum DDL tanpa mengubah row/schema (focused 1/4); ini bukan migrasi legacy sukses atau backfill. `IDEMPOTENCY-CONCURRENCY-001` membuktikan overlap retry sale/purchase payload identik; `IDEMPOTENCY-CONFLICT-RACE-001` membuktikan untuk kedua endpoint satu 201 dan satu 409 `IDEMPOTENCY_KEY_REUSED` saat payload berbeda; `IDEMPOTENCY-SCOPE-NUMBER-001` membuktikan key sama di konteks actor/tenant/endpoint sah membuat transaksi mandiri serta nomor request concurrent berbeda unik; `IDEMPOTENCY-CRASH-RESTART-001` membuktikan rollback pre-commit saat worker SIGKILL dan replay setelah response hilang pada worker baru (gabungan concurrency terbaru 13/155). Tenant/endpoint tidak diuji independen dari actor karena integritas FK dan role; masa retensi setelah header dihapus belum ditentukan. T-ADM-01/02/03 lulus sesuai skenario pada test plan: provisioning superadmin+owner/hash dan rollback, pengelolaan user tenant, serta list/detail/update warung oleh superadmin dengan role boundary dan validasi. `CURRENT-WARUNG-API-ACCEPTANCE-001` menguji profil manager/kasir, 403 superadmin, 401 anonim dan response schema (4/238); akses owner masih D04. Run `ADMIN-WARUNG-CONFORMANCE-001` memvalidasi response GET list/detail/PATCH admin warung; `ADMIN-USER-CONFORMANCE-001` memvalidasi POST provisioning dan list/create/detail/update user pada status yang tercatat. `CATALOG-CONFORMANCE-001` membandingkan response runtime list 200/422, create 201/403/422, detail 200/404, update 200/403/404/422 untuk kategori dan menu. `ACCESS-CATALOG-QUERY-CONFORMANCE-001` memeriksa query terpilih pada GET list warung/user/kategori/menu; `ACCESS-CATALOG-WRITE-REQUEST-CONFORMANCE-001` memeriksa body sukses terpilih untuk POST/PATCH admin warung, user, kategori, dan menu. Request admin/katalog lulus focused 34/4307; request auth 20/3069; suite penuh pada run conformance saat itu 209/25240. `TRANSACTION-CONFORMANCE-001` membandingkan schema response delapan operasi list/create/detail penjualan/pembelian serta dua laporan pada status terpilih: sale 200/201/403/404/409/422; purchase 200/201/403/404/409/422; laporan 200/422. `TRANSACTION-REQUEST-CONFORMANCE-001` memeriksa request body/header schema-valid terpilih pada POST sale/purchase: create/replay/conflict, sale tenant-crossing 422, dan bentuk purchase ringkas/rinci. `TRANSACTION-QUERY-CONFORMANCE-001` memeriksa query page/per_page/sort/status pada list dan query tanggal wajib untuk report pada nilai sukses terpilih. `ACCESS-PAGINATION-MAX-CONFORMANCE-001` membuktikan nilai batas `per_page=100` pada enam list, dengan response metadata tetap 100; `ACCESS-PAGINATION-OVERFLOW-CONFORMANCE-001` membuktikan nilai 101 ditolak dengan HTTP/schema 422 dan field `per_page`; `ACCESS-PAGINATION-LOWER-BOUND-CONFORMANCE-001` membuktikan `page=0` serta `per_page=0` ditolak dengan HTTP/schema 422 pada enam list; `ACCESS-PAGINATION-TYPE-CONFORMANCE-001` menguji query angka non-integer; `ACCESS-PAGINATION-EMPTY-PAGE-CONFORMANCE-001` memastikan metadata hasil kosong dan halaman di luar jangkauan pada enam list; `ACCESS-SORT-ORDERING-CONFORMANCE-001` memastikan seluruh 14 opsi mengurutkan nilai primer berbeda dengan benar; `ACCESS-PAGINATION-DEFAULT-CONFORMANCE-001` memverifikasi default schema dan runtime pada keenam GET list; `ACCESS-PAGINATION-NUMERIC-TYPE-CONFORMANCE-001` menolak format integer-query desimal, pecahan, dan eksponen di keenam list. Test `TenantCompositeForeignKeyTest` memeriksa enam FK gabungan dan unique kode/nomor tenant langsung di MySQL (10/13 assertions). `BusinessSchemaMigrationConformanceTest` lulus audit fresh schema T-DB-01 untuk metadata terpilih pada delapan tabel (4/210); audit tidak membandingkan seluruh metadata kolom otomatis. Upgrade T-DB-02 dan rollback migration tetap terbuka. Purchase parsial yang melanggar `oneOf` tetap diuji 422, tetapi memang di luar schema. Tidak ditemukan mismatch pada subset akhir yang dicek. Checker mengabaikan anotasi `default` dan `writeOnly`, menegakkan minProperties pada object, dan menolak keyword schema lain yang belum didukung. Semua 28 operasi tetap DRAFT; query/request lain, status lain, seluruh role/milestone, dan G3/G4 tetap terbuka. Rincian hasil serta batas tiap run tercatat pada tracker dan artefak run. Konvensi wire D13 disetujui, tetapi T-API-01/02/03/04 masih harus lengkap sebelum contract handoff. Catat setiap run dengan command, environment, commit, hasil aktual, serta gap. Jangan mengklaim concurrency PASS bila barrier tidak benar-benar dilewati dua request.

Setiap run dicatat dengan format berikut pada [tracker](../../IMPLEMENTATION_PROGRESS.md):

```text
run_id:
tanggal_dan_timezone:
task_ids / test_ids:
commit_yang_diuji:
environment / php / framework / engine_versi / identitas_db_test_tanpa_secret:
command:
expected:
actual / pass / fail / skipped:
status: PASS | FAIL | BLOCKED | NOT_RUN | NOT_APPLICABLE
lokasi_output_atau_artifact:
catatan_gap / keputusan / tindak_lanjut:
```

FAIL tidak dihapus oleh rerun; catat perbaikan dan run baru. NOT_APPLICABLE memerlukan keputusan scope yang ditautkan. Update progress sesudah satu slice diverifikasi, bukan mengklaim seluruh milestone selesai hanya karena migration atau dokumentasinya selesai.

## Hasil crash/restart idempotency T-RET-04

`IDEMPOTENCY-CRASH-RESTART-001` lulus pada kedua endpoint: worker PHP terpisah dimatikan dengan SIGKILL saat trigger MySQL menahan insert detail sesudah header dibuat tetapi sebelum commit; setelah koneksi melepas named lock, tidak ada header/detail parsial, dan retry dari worker baru membuat satu transaksi (HTTP 201). Skenario sesudah commit membuat worker keluar kode 97 setelah Kernel membentuk 201 sebelum respons ditulis; proses baru mengirim ulang key/payload dan menerima 201 dengan ID/no_transaksi yang sama. Pint lulus; focused `IdempotencyConcurrencyTest` 13/155; suite penuh 250/26452 pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40. Trigger telah dipastikan terhapus dan Compose dibersihkan. Bukti ini tidak menetapkan retensi setelah record header dihapus; D09 masih PARTIAL dan API tetap DRAFT. Detail: [artefak run](test-runs/IDEMPOTENCY-CRASH-RESTART-001.md).
