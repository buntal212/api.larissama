# Panduan API dan Handoff Frontend

Versi kontrak: **0.1.0-draft**, 2026-10-05. [openapi.yaml](openapi.yaml) berisi 28 operasi pada 18 path, beserta request/response schema dan contoh sintetis. User menyetujui baseline wire D13: `/api/v1`, ID/decimal string, response `data/meta`, pagination integer `page` minimum 1 tanpa batas maksimum dan `per_page` 1–100, sort allowlist, dan error `code/message/errors/request_id`. Auth, sebagian administrasi user/warung, katalog, transaksi, dan laporan telah diuji pada MySQL 8.0.40. Suite backend terbaru lulus 482 test / 68401 assertions pada MySQL 8.0.40; field tambahan ditolak 422 pada login dan sepuluh operasi body lain tanpa write parsial ([login](../backend/test-runs/LOGIN-UNKNOWN-FIELD-CONFORMANCE-001.md), [operasi lain](../backend/test-runs/UNKNOWN-BODY-FIELDS-CONFORMANCE-001.md)). `MALFORMED-JSON-400-CONFORMANCE-001` membuktikan JSON rusak memberi HTTP 400 `BAD_REQUEST` schema-conformant pada 11 operasi request-body, tanpa perubahan data ([bukti](../backend/test-runs/MALFORMED-JSON-400-CONFORMANCE-001.md)). `IDEMPOTENCY-KEY-HEADER-CONFORMANCE-001` menguji header wajib 1–255 karakter: batas 255 diterima, nilai kosong/hilang/256 ditolak tanpa transaksi ([bukti](../backend/test-runs/IDEMPOTENCY-KEY-HEADER-CONFORMANCE-001.md)). `OPENAPI-PARAMETER-EXAMPLES-CONFORMANCE-001` memeriksa contoh tanggal `DateFrom`/`DateTo` terhadap schema parameter ([bukti](../backend/test-runs/OPENAPI-PARAMETER-EXAMPLES-CONFORMANCE-001.md)). `TRANSACTION-500-ERROR-CONFORMANCE-001` memastikan kegagalan insert transaksi memberi 500 Error500 schema-conformant tanpa membocorkan detail SQL dan tetap rollback ([bukti](../backend/test-runs/TRANSACTION-500-ERROR-CONFORMANCE-001.md)). `IDEMPOTENCY-KEY-WHITESPACE-CONFORMANCE-001` menyatakan aturan tanpa whitespace di tepi pada schema `Idempotency-Key` serta mengujinya pada kedua create transaksi ([bukti](../backend/test-runs/IDEMPOTENCY-KEY-WHITESPACE-CONFORMANCE-001.md)). `OWNER-TENANT-ACCESS-CONFORMANCE-001` membuktikan jalur positif owner dalam tenant dan delegasi owner kedua ([bukti](../backend/test-runs/OWNER-TENANT-ACCESS-CONFORMANCE-001.md)). `API-ROUTE-INVENTORY-CONFORMANCE-001` mencocokkan dua arah seluruh method/path OpenAPI dengan route Laravel ([bukti](../backend/test-runs/API-ROUTE-INVENTORY-CONFORMANCE-001.md)). `API-PATH-ID-POSITIVE-CONFORMANCE-001` membuktikan seluruh operasi detail/update mengikuti pola ID string positif OpenAPI, termasuk error 404 schema-conformant untuk ID invalid ([bukti](../backend/test-runs/API-PATH-ID-POSITIVE-CONFORMANCE-001.md)). `API-PATH-ID-OVERFLOW-CONFORMANCE-001` juga menguji 20 request detail/update memakai ID di atas rentang PHP dan MySQL; seluruhnya menghasilkan 404 schema-conformant tanpa write ([bukti](../backend/test-runs/API-PATH-ID-OVERFLOW-CONFORMANCE-001.md)). `REPORT-RBAC-CONFORMANCE-001` membuktikan manager 200 serta kasir/superadmin 403 pada kedua report; owner juga diuji mengakses kedua agregat hanya dari tenant sendiri pada `OWNER-TENANT-ACCESS-CONFORMANCE-001` ([bukti](../backend/test-runs/REPORT-RBAC-CONFORMANCE-001.md)). `SUPERADMIN-TRANSACTION-RBAC-CONFORMANCE-001` menguji payload schema-valid untuk create sale/purchase: superadmin menerima 403 schema-conformant dan tidak menulis header/rincian ([bukti](../backend/test-runs/SUPERADMIN-TRANSACTION-RBAC-CONFORMANCE-001.md)). `ROLE-TRANSACTION-CREATE-RBAC-CONFORMANCE-001` membuktikan manager ditolak saat create sale dan kasir saat create purchase dengan payload valid serta no-write ([bukti](../backend/test-runs/ROLE-TRANSACTION-CREATE-RBAC-CONFORMANCE-001.md)). `TRANSACTION-TENANT-INJECTION-REJECTION-001` membuktikan `warung_id` kiriman ditolak 422 schema-conformant pada POST sale/purchase tanpa write ([bukti](../backend/test-runs/TRANSACTION-TENANT-INJECTION-REJECTION-001.md)). `API-END-TO-END-WORKFLOW-001` membuktikan alur HTTP terpilih dari provisioning sampai laporan dan logout lintas role pada satu warung ([bukti](../backend/test-runs/API-END-TO-END-WORKFLOW-001.md)). `TRANSACTION-LIST-QUERY-REJECTION-001` membuktikan query status/tanggal invalid pada list transaksi menghasilkan 422 schema-conformant ([bukti](../backend/test-runs/TRANSACTION-LIST-QUERY-REJECTION-001.md)). `API-END-TO-END-READBACK-001` menguji GET list/detail sale dan purchase sesudah create pada workflow API ([bukti](../backend/test-runs/API-END-TO-END-READBACK-001.md)). `TRANSACTION-LIST-QUERY-PAIR-REQUIRED-001` membuktikan filter tanggal riwayat harus dikirim sebagai pasangan pada kedua list ([bukti](../backend/test-runs/TRANSACTION-LIST-QUERY-PAIR-REQUIRED-001.md)). `TRANSACTION-LIST-LOCAL-PERIOD-CONFORMANCE-001` membuktikan kedua list memakai awal hari lokal inklusif dan akhir eksklusif untuk `Asia/Jakarta` ([bukti](../backend/test-runs/TRANSACTION-LIST-LOCAL-PERIOD-CONFORMANCE-001.md)). `SUPERADMIN-TRANSACTION-LIST-RBAC-CONFORMANCE-001` membuktikan superadmin mendapat 403 schema-conformant ketika transaksi tenant tersedia ([bukti](../backend/test-runs/SUPERADMIN-TRANSACTION-LIST-RBAC-CONFORMANCE-001.md)). `TRANSACTION-LIST-DST-CONFORMANCE-001` membuktikan batas tanggal list untuk satu hari DST 23 jam di `America/New_York` ([bukti](../backend/test-runs/TRANSACTION-LIST-DST-CONFORMANCE-001.md)). Semua 27 operasi yang mewajibkan bearer diuji tanpa token dan mengembalikan 401 `UNAUTHENTICATED` dengan body sesuai schema OpenAPI (`PROTECTED-OPERATIONS-401-CONFORMANCE-001`). Runtime checker membandingkan response terpilih pada operasi auth/admin/katalog/transaksi/laporan, body login auth valid untuk contoh sukses/401, body POST/PATCH terpilih untuk delapan operasi admin/katalog, body/header terpilih untuk create sale/purchase, serta query terpilih untuk list akses/katalog/transaksi dan dua laporan terhadap schema OpenAPI. Untuk keenam endpoint daftar, `page=0`/`per_page=0` dan representasi desimal/pecahan/eksponen serta teks non-integer ditolak, `per_page=100` diterima, `per_page=101` ditolak, serta `sort` di luar enum ditolak, dan seluruh 14 opsi enum yang terdokumentasi diterima dengan HTTP 200 yang cocok schema; tie-breaker `id` mengikuti arah sort pada 14 opsi dan dua halaman per opsi, serta urutan sort naik/turun atas nilai primer berbeda sudah dicocokkan ke fixture pada semua opsi; metadata hasil kosong (`total=0`, `last_page=1`) serta page di atas `last_page` (`total` tetap asli), termasuk page maksimum signed 64-bit dan satu di atas `PHP_INT_MAX` tanpa offset overflow, juga cocok schema pada keenam list ([ACCESS-PAGINATION-LARGE-PAGE-CONFORMANCE-001](../backend/test-runs/ACCESS-PAGINATION-LARGE-PAGE-CONFORMANCE-001.md), [UNBOUNDED-PAGINATION-PAGE-CONFORMANCE-001](../backend/test-runs/UNBOUNDED-PAGINATION-PAGE-CONFORMANCE-001.md)); `page=-1` dan `per_page=-1` ditolak HTTP 422 schema-conformant pada keenam list ([API-PAGINATION-NEGATIVE-BOUNDARY-CONFORMANCE-001](../backend/test-runs/API-PAGINATION-NEGATIVE-BOUNDARY-CONFORMANCE-001.md)); request tanpa query memverifikasi default `page=1`, `per_page=20`, dan sort per operasi terhadap schema/runtime. Query/body operasi lain, seluruh status, dan conformance seluruh operasi masih terbuka. Semua operasi tetap `DRAFT` karena keputusan bisnis per operasi, acceptance penuh, serta conformance belum selesai. Gunakan file ini untuk review dan mock berlabel, bukan integrasi live.

`OPENAPI-DOCUMENT-INTEGRITY-001` memeriksa bahwa 28 operasi memiliki `operationId` unik dan response map, serta semua `$ref` JSON Pointer lokal dapat di-resolve ([hasil run](../backend/test-runs/OPENAPI-DOCUMENT-INTEGRITY-001.md)). Pemeriksaan ini bersifat struktural; kontrak tetap DRAFT dan belum menggantikan validator OpenAPI 3.1 atau conformance runtime menyeluruh.

## Validasi spesifikasi

`docs/api/openapi.yaml` lulus validasi OpenAPI 3.1 dengan `openapi-spec-validator` 0.9.0 di container Docker khusus: `docker compose -f compose.openapi.yaml run --build --rm openapi-validator`. Setup mengunci digest base image dan versi seluruh paket Python; file kontrak dibaca read-only. Bukti: [OPENAPI-SPEC-VALIDATOR-001](../backend/test-runs/OPENAPI-SPEC-VALIDATOR-001.md). Ini hanya memeriksa validitas spesifikasi; conformance runtime dan keputusan yang belum final masih menjadi gate. Semua operasi tetap DRAFT.

Seluruh contoh inline request/response diuji terhadap schema OpenAPI tiap operasi melalui [OPENAPI-EXAMPLES-CONFORMANCE-001](../backend/test-runs/OPENAPI-EXAMPLES-CONFORMANCE-001.md), dan contoh parameter query `DateFrom`/`DateTo` diperiksa melalui [OPENAPI-PARAMETER-EXAMPLES-CONFORMANCE-001](../backend/test-runs/OPENAPI-PARAMETER-EXAMPLES-CONFORMANCE-001.md). Ini memeriksa contoh dalam dokumen; response server aktual tetap dicakup terpisah oleh feature conformance tests.

## Bentuk request pembelian

`POST /api/v1/pembelians` menerima `rincian` yang berbentuk salah satu dari dua cara berikut:

- Ringkas: `nama_item` dan `subtotal`. `qty`, `satuan`, dan `harga_satuan` boleh tidak dikirim. Ini memenuhi kebutuhan minimal K05, misalnya `Belanja di pasar` beserta total.
- Dengan hitungan: `nama_item`, `qty`, dan `harga_satuan`; `satuan` opsional. Backend menghitung `subtotal` per baris dan `total` header. Client boleh mengirim `subtotal` sebagai pembanding; nilainya harus sama dengan hasil backend.

Qty tanpa harga satuan, harga satuan tanpa qty, atau baris ringkas tanpa subtotal ditolak dengan `422 VALIDATION_ERROR`; bentuk tersebut juga gagal pada `oneOf` OpenAPI. Baris dengan qty/harga berpasangan tetapi subtotal berbeda memiliki bentuk schema yang sah, namun ditolak validasi bisnis dengan 422 pada `rincian.N.subtotal`. Aturan D10 ini telah diputuskan user dan bentuk request/runtime diuji pada [PURCHASE-REQUEST-SHAPE-CONFORMANCE-001](../backend/test-runs/PURCHASE-REQUEST-SHAPE-CONFORMANCE-001.md). Operasi tetap `DRAFT` sampai seluruh gate kontrak dan runtime lulus.

## Status implementasi yang tersedia

| Slice | Commit | Pemeriksaan lokal | Handoff |
| --- | --- | --- | --- |
| Auth dan akses | `2cafc46`, `4def071`, `e89c2f0`, `ec43d1b` | Pint; `AuthApiTest` 20 test / 3069 assertions pada run request conformance (`AUTH-REQUEST-CONFORMANCE-001`); suite penuh terbaru 482 / 68401 pada MySQL 8.0.40. Response schema diuji untuk login 200/401/403/422/429, me 200/401/403, dan logout 204 kosong; body login valid untuk contoh sukses/401 cocok `LoginRequest` | Login/me, role-context, inactive, date/NULL, expiry, logout, secret/log, limiter per IP, dan request login positif terpilih sudah punya bukti; payload/status lain, migrasi identitas legacy D12, conformance penuh, dan base URL masih terbuka; DRAFT |
| Administrasi warung dan user tenant | `0f7e39c`, `7fed9ae`, `d554d5a`, `278e27c`, `bad42b6`, `0c15ff5`, `bd8e83e`, `226b332`, `46f0e3b` | Pint; acceptance create/provision/user 11 / 139 (`ADMIN-API-ACCEPTANCE-001`), admin warung 4 / 113 (`ADMIN-WARUNG-MANAGEMENT-ACCEPTANCE-001`), admin schema 5 / 976 (`ADMIN-WARUNG-CONFORMANCE-001`), provisioning/user conformance 17 / 1902 (`ADMIN-USER-CONFORMANCE-001`), query 28 / 3808 (`ACCESS-CATALOG-QUERY-CONFORMANCE-001`), request 34 / 4307 (`ACCESS-CATALOG-WRITE-REQUEST-CONFORMANCE-001`); suite penuh terbaru 482 / 68401 | Provisioning/user CRUD, response schema dan query terpilih serta request body sukses POST/PATCH admin/user sudah diuji; `per_page` 1–100 serta `page` positif tanpa batas maksimum diuji; input invalid/tipe dan sort di luar allowlist ditolak, seluruh enum warung/user diterima; profil `/warung` manager/kasir/superadmin/anonim diuji pada status terpilih; owner profile dan delegasi role owner diperiksa pada `OWNER-TENANT-ACCESS-CONFORMANCE-001`; email exact duplicate lintas warung ditolak saat create/update dan null/diabaikan diterima (`USER-EMAIL-UNIQUENESS-001`, 4/242); aturan lowercase D12 kini diuji: uppercase ditolak dan username lowercase dengan angka diterima; migrasi identitas legacy, conformance status/payload lain, matriks role penuh dan conformance penuh masih terbuka; DRAFT |
| Kategori dan menu | `eb5ea04`, `e0e32b8`, `1dc9cf3`, `9075c35`, `0c15ff5`, `bd8e83e`, `226b332` | Pint; acceptance 17 / 286 (`CATALOG-API-ACCEPTANCE-001`), response conformance 17 / 1573 (`CATALOG-CONFORMANCE-001`), query 28 / 3808 (`ACCESS-CATALOG-QUERY-CONFORMANCE-001`), request 34 / 4307 (`ACCESS-CATALOG-WRITE-REQUEST-CONFORMANCE-001`); suite penuh terbaru 482 / 68401 pada MySQL 8.0.40. Schema response diuji untuk list 200/422, create 201/403/422, detail 200/404, update 200/403/404/422 | Create/list/detail/update, request body sukses POST/PATCH terpilih, price string, tenant boundary, manager write/cashier active-only, serta query page/per_page/sort/q/aktif/kategori_menu_id punya bukti parsial; tipe pagination integer, batas `per_page` 1–100, `page` positif tanpa batas maksimum termasuk di atas PHP_INT_MAX pada keenam list, dan sort di luar enum ditolak serta enum sort kategori/menu diterima; D06 penolakan sale baru atas menu/kategori nonaktif kini teruji; harga menu wajib positif telah diuji. Detail sukses, status/payload lain, matriks role penuh dan G2 juga masih terbuka; DRAFT |
| Penjualan, pembelian, laporan | `0ff1d08`, `4b8f106`, `a7b5ffa`, `89c74de`, `1ae3439`, `3e411fb`, `bc96a7b`, `a9b4637`, `c54d2d3`, `a6f1ff3`, `9545300`, `7a6919a`, `1423242`, `c62953c`, `a2dcf2d`, `931dff1`, `d8c2613`, `12251ac`, `17029c7`, `e35c67b`, `48c45d2`, `b685165`, `319125f`, `03f9235`, `c12ffd1`, `4654272` | Pint/PHP lint, 8 route, OpenAPI parse/ref/contoh, migration MySQL, feature HTTP untuk transaksi, report DST, periode kosong/invalid, envelope D13 parsial, presisi `DATETIME`, aggregate capacity, paging independence, response conformance delapan operasi, request body/header POST terpilih, query GET list/report terpilih, tipe pagination integer, batas `per_page` 1–100, page positif tanpa batas maksimum, seluruh enum sort sah, tie-breaker, urutan nilai primer berbeda, default list, serta empty-page metadata enam list (`ACCESS-SORT-ORDERING-CONFORMANCE-001`, `ACCESS-PAGINATION-DEFAULT-CONFORMANCE-001`, `ACCESS-PAGINATION-MAX-CONFORMANCE-001`, `ACCESS-PAGINATION-OVERFLOW-CONFORMANCE-001`, `ACCESS-PAGINATION-LOWER-BOUND-CONFORMANCE-001`, `ACCESS-PAGINATION-TYPE-CONFORMANCE-001`, `ACCESS-PAGINATION-NUMERIC-TYPE-CONFORMANCE-001`, `ACCESS-PAGINATION-EMPTY-PAGE-CONFORMANCE-001`, `ACCESS-SORT-ALLOWLIST-CONFORMANCE-001`, `ACCESS-SORT-VALID-ENUM-CONFORMANCE-001`, `ACCESS-SORT-TIE-BREAKER-CONFORMANCE-001`); retry identik, payload conflict, konteks scope, dan nomor unik concurrent sale/purchase (`IDEMPOTENCY-CONCURRENCY-001`, `IDEMPOTENCY-CONFLICT-RACE-001`, `IDEMPOTENCY-SCOPE-NUMBER-001`, `IDEMPOTENCY-CRASH-RESTART-001`, focused 13/155); conformance transaksi (`TRANSACTION-API-001`, `TRANSACTION-FEATURE-001`, `TRANSACTION-READ-001`, `TRANSACTION-TIMEZONE-001`, `TRANSACTION-REPORT-VALIDATION-001`, `TRANSACTION-REPORT-CONFORMANCE-001`, `TRANSACTION-REPORT-PRECISION-001`, `TRANSACTION-REPORT-AGGREGATE-001`, `TRANSACTION-CONFORMANCE-001`, `TRANSACTION-REQUEST-CONFORMANCE-001`, `TRANSACTION-QUERY-CONFORMANCE-001`, `PURCHASE-REQUEST-SHAPE-CONFORMANCE-001`); suite penuh terbaru 482 / 68401 pada MySQL 8.0.40 | Response runtime cocok untuk sale list 200, create 201/403/409/422, detail 200/404; purchase list 200/403, create 201/403/409/422, detail 200/404; kedua laporan 200/422. Body/header create valid dan query pilihan diuji; `per_page` 1–100 diterima dan `page` tanpa batas maksimum diuji; nilai invalid/tipe ditolak sesuai schema pada kedua list transaksi. `SUPERADMIN-TRANSACTION-RBAC-CONFORMANCE-001` membuktikan kedua POST menolak superadmin dengan payload valid dan no-write; `ROLE-TRANSACTION-CREATE-RBAC-CONFORMANCE-001` membuktikan manager tidak membuat sale dan kasir tidak membuat purchase memakai payload valid; `TRANSACTION-TENANT-INJECTION-REJECTION-001` menolak `warung_id` dari client dengan 422 dan no-write; `TRANSACTION-LIST-QUERY-REJECTION-001` menolak status sale di luar enum dan tanggal list invalid dengan 422; `TRANSACTION-LIST-QUERY-PAIR-REQUIRED-001` menolak parameter tanggal tunggal dengan 422; `TRANSACTION-LIST-LOCAL-PERIOD-CONFORMANCE-001` menguji batas hari lokal awal inklusif dan akhir eksklusif untuk `Asia/Jakarta`; `SUPERADMIN-TRANSACTION-LIST-RBAC-CONFORMANCE-001` menguji 403 schema-conformant untuk superadmin pada kedua GET list; `TRANSACTION-LIST-DST-CONFORMANCE-001` menguji batas awal/akhir list pada hari DST 23 jam `America/New_York`; `API-END-TO-END-WORKFLOW-001` melewati happy path API provisioning→katalog→sale/purchase→laporan→logout pada satu tenant; `API-END-TO-END-READBACK-001` memeriksa manager membaca list/detail seluruh transaksi yang dibuat; keputusan D04 memberi owner operasi tenant; `OWNER-TENANT-ACCESS-CONFORMANCE-001` membuktikan create/list/detail dan tenant boundary transaksi serta akses laporan owner. Untuk retry, payload identik bersamaan teruji pada kedua endpoint dan payload berbeda serentak menghasilkan satu 201/satu 409. Query/status lain, body invalid lain, dan acceptance penuh masih terbuka. D05, D08, dan D10 rincian subtotal pembelian telah diputuskan dan diuji; D09 retensi idempotensi tetap terbuka. D11 koreksi pembelian telah diputuskan, tetapi schema/API tambahan dan perubahan agregat laporan belum dikerjakan. Pembatalan penjualan D06 tetap terbuka; DRAFT |

Rincian hasil dan batas pemeriksaan ada di [tracker implementasi](../../IMPLEMENTATION_PROGRESS.md); bukti sort allowlist serta penerimaan semua enum tercatat pada `ACCESS-SORT-ALLOWLIST-CONFORMANCE-001` dan `ACCESS-SORT-VALID-ENUM-CONFORMANCE-001`, tie-breaker lintas halaman pada `ACCESS-SORT-TIE-BREAKER-CONFORMANCE-001` dan urutan nilai sort pada `ACCESS-SORT-ORDERING-CONFORMANCE-001`; metadata hasil kosong/di luar jangkauan pada `ACCESS-PAGINATION-EMPTY-PAGE-CONFORMANCE-001` dan default page/per_page/sort pada `ACCESS-PAGINATION-DEFAULT-CONFORMANCE-001`; representasi numerik non-integer pada `ACCESS-PAGINATION-NUMERIC-TYPE-CONFORMANCE-001`, dan page maksimum signed 64-bit pada `ACCESS-PAGINATION-LARGE-PAGE-CONFORMANCE-001`. Jangan arahkan frontend ke server live sampai kontrak operasi berstatus `READY_FOR_FRONTEND`.

Bukti D05 sekarang mencakup aturan nominal sale/purchase pada `ApprovedTransactionRulesConformanceTest`: diskon berlebih, cash kurang, QRIS/transfer tidak pas, jumlah nol/negatif/lebih dari dua desimal, pembayaran sah, dan half-up per baris. Test terarah 12/2549; suite penuh 482/68401 di MySQL 8.0.40. Harga `17.25` × qty `3.00` juga tetap dicakup oleh `SALE-DECIMAL-EXACT-CONFORMANCE-001` ([artefak run](../backend/test-runs/SALE-DECIMAL-EXACT-CONFORMANCE-001.md)).

Bukti riwayat kasir: `CASHIER-SALE-HISTORY-RBAC-CONFORMANCE-001` menunjukkan list hanya memuat penjualan kasir yang login, detail miliknya 200, dan detail kasir lain pada warung sama 404 ([artefak run](../backend/test-runs/CASHIER-SALE-HISTORY-RBAC-CONFORMANCE-001.md)). Akses owner diuji untuk profil, katalog, transaksi, laporan, serta delegasi owner kedua pada `OWNER-TENANT-ACCESS-CONFORMANCE-001`. `NONOWNER-USER-ADMIN-RBAC-CONFORMANCE-001` membuktikan manager, kasir, dan superadmin ditolak schema-conformant pada GET list, POST create, GET detail, dan PATCH user tanpa write; matriks role penuh masih terbuka. `SUPERADMIN-TENANT-CATALOG-WRITE-RBAC-CONFORMANCE-001` membuktikan superadmin ditolak 403 schema-conformant pada POST/PATCH kategori dan menu tenant dengan payload valid, tanpa mengubah row. `MANAGER-PURCHASE-TENANT-READ-CONFORMANCE-001` membuktikan manager bisa membaca purchase manager lain dalam warung tokennya, dengan list/detail tenant lain tetap tersembunyi ([artefak run](../backend/test-runs/MANAGER-PURCHASE-TENANT-READ-CONFORMANCE-001.md)).

`MANAGER-SALE-TENANT-READ-CONFORMANCE-001` membuktikan manager dapat membaca sale kasir lain di warung yang sama dan mendapat 404 schema-conformant untuk sale tenant lain ([bukti](../backend/test-runs/MANAGER-SALE-TENANT-READ-CONFORMANCE-001.md)). `OWNER-PURCHASE-TENANT-READ-CONFORMANCE-001` membuktikan owner dapat membaca purchase manager lain di warungnya dan tidak dapat membuka purchase tenant lain ([bukti](../backend/test-runs/OWNER-PURCHASE-TENANT-READ-CONFORMANCE-001.md)). Ini bukti subset D04; semua operasi OpenAPI tetap `DRAFT` dan matriks role penuh belum selesai.

`SUPERADMIN-TENANT-CATALOG-READ-RBAC-CONFORMANCE-001` membuktikan superadmin mendapat 403 schema-conformant pada list dan detail kategori/menu tenant, sementara fixture tetap tersimpan ([bukti](../backend/test-runs/SUPERADMIN-TENANT-CATALOG-READ-RBAC-CONFORMANCE-001.md)). Semua operasi tetap `DRAFT` sampai seluruh contract gate lulus.

`SUPERADMIN-TRANSACTION-DETAIL-RBAC-CONFORMANCE-001` membuktikan superadmin juga mendapat 403 schema-conformant pada detail sale dan purchase tenant yang sudah ada; GET list 403 sudah diuji terpisah ([bukti](../backend/test-runs/SUPERADMIN-TRANSACTION-DETAIL-RBAC-CONFORMANCE-001.md)).

`CASHIER-PURCHASE-DETAIL-RBAC-CONFORMANCE-001` membuktikan kasir mendapat 403 schema-conformant pada list/detail purchase dan tidak dapat membuat purchase ([bukti](../backend/test-runs/CASHIER-PURCHASE-DETAIL-RBAC-CONFORMANCE-001.md)); row pembelian milik manager tetap utuh.

## Urutan baca untuk AI frontend

1. Baca panduan ini untuk istilah, bentuk data, alur, dan batas integrasi.
2. Cari operationId pada OpenAPI. Periksa `x-contract-status`, `x-implementation-status`, `x-candidate-roles`, dan `x-blocked-by`.
3. Periksa status handoff, environment, versi kontrak, dan bukti test pada [IMPLEMENTATION_PROGRESS.md](../../IMPLEMENTATION_PROGRESS.md).
4. Jika operasi belum READY_FOR_FRONTEND, kerjakan UI/mock hanya bila ditugaskan dan tandai datanya sebagai mock. Jangan menebak route, header retry, lifecycle token, status, atau field yang belum tersedia.
5. Jika field/perilaku belum jelas, lihat keputusan Dxx pada [DECISIONS.md](../backend/DECISIONS.md); laporkan gap kontrak pada task backend terkait.

### Koordinasi agar pekerjaan tidak tumpang tindih

Sebelum mengambil slice baru, catat di tracker repo frontend: fitur/halaman yang selesai atau sedang dikerjakan, file dan commit terkait, status mock atau live, `operationId` yang dipakai, serta gap kontrak yang ditemukan. Periksa catatan itu sebelum membuat ulang area yang sama. Saat melaporkan masalah backend, sertakan method/path, langkah reproduksi, status dan body response yang sudah disamarkan, serta `request_id`; jangan kirim bearer token atau data pengguna nyata.

Kolom database bukan payload API otomatis. Semua contoh ID, warung, bahan, token, dan transaksi adalah data sintetis. Rincian pembelian minimal harus selalu didukung, tanpa menambahkan syarat master bahan atau qty pada formulir ringkas.

## UI frontend yang aman dikerjakan sekarang

Status kontrak backend saat ini masih `DRAFT` untuk seluruh 28 operasi. AI frontend boleh mulai membangun layar, navigasi, state, dan interaksi dengan repository/mock data yang ditandai sebagai mock; integrasi ke API live menunggu operasi terkait berubah ke `READY_FOR_FRONTEND`.

| Slice UI | Bisa dimulai | Batas yang perlu diikuti |
| --- | --- | --- |
| App shell dan navigasi | Layout responsif, menu per role, halaman login dan profil memakai identitas mock | Hak akses di UI hanya untuk tampilan; backend tetap otoritatif. Jangan membuat pemilih warung untuk owner/manager/kasir. |
| Warung dan user | Form/list admin warung superadmin, profil warung, serta CRUD user untuk owner memakai mock | Owner hanya mengelola user tenant sendiri dan boleh memberi role owner/manager/kasir. Aksi membuat superadmin hanya milik jalur platform. |
| Kategori dan menu | List, pencarian, filter aktif, form tambah/edit kategori dan menu | Kasir melihat item aktif saja. Harga dikirim/ditampilkan dari API sebagai decimal string; `harga_modal` dan gambar bukan bagian MVP. |
| Kasir dan riwayat penjualan | Keranjang, pratinjau subtotal, pembayaran mock, list/detail riwayat per role | Total sukses berasal dari response backend. D05 telah menetapkan harga menu > 0, qty > 0 sampai dua desimal, diskon nominal maksimal subtotal, cash boleh lebih, QRIS/transfer harus pas, dan half-up per baris. |
| Pembelian | Form ringkas “Belanja di pasar” dengan tanggal dan total; list/detail memakai mock | Bentuk minimal K05 berisi `nama_item` dan `subtotal`. User juga menetapkan edit dan pembatalan pembelian harus memiliki alasan audit. Endpoint koreksi adalah tambahan di luar 28 operasi awal dan belum siap untuk integrasi live. |
| Laporan | Dua tampilan periode terpisah: pendapatan penjualan dan total pembelian, termasuk keadaan periode kosong | Filter tanggal memakai hari lokal warung (`YYYY-MM-DD`). Jangan hitung atau beri label laba dari selisih kedua total. |
| Komponen lintas fitur | Loading, empty state, validation field/baris, 401/403/404/409/422/429/500 dan kegagalan jaringan memakai response mock | Pertahankan input saat 422 dan jangan mengubah kegagalan request menjadi angka nol. |

Gunakan schema dan contoh di `openapi.yaml` sebagai bentuk data kandidat, bukan kontrak live. Catat asumsi mock dan jangan menghubungkan layar ke server sebelum entry operasi menyebut `READY_FOR_FRONTEND`, versi spec, commit yang diuji, serta base URL environment.

## Konvensi umum dan batas kontrak (D02/D05/D08/D13)

Konvensi wire D13 pada baris terkait sudah disetujui user. Kontrak operation-level masih berstatus DRAFT sampai seluruh request/response runtime diuji terhadap OpenAPI.

| Aspek | Aturan dan status |
| --- | --- |
| Base URL | Prefix `/api/v1` disetujui D13 dan sudah ada pada `servers.url`; Docker backend lokal memakai `http://localhost:8010`, lalu frontend menambahkan `/api/v1`. Quasar dev lokal memakai origin `http://localhost:9000`. |
| CORS | Origin frontend lokal `http://localhost:9000` diizinkan untuk API bearer. Sesuaikan `LARISSAMA_CORS_ALLOWED_ORIGINS` dengan daftar origin frontend yang dipisahkan koma; jangan gunakan `*` untuk deployment. Cookies/credential browser tidak diaktifkan. |
| Media | Request/response JSON; kirim `Accept: application/json`, body dengan `Content-Type: application/json`. |
| Auth | User memilih Sanctum bearer melalui `Authorization: Bearer ...`; token berlaku 30 hari lalu user login ulang. Logout mencabut hanya token bearer aktif. Login dibatasi 5 percobaan per menit per username dan IP. Username/email wajib dikirim huruf kecil; angka diperbolehkan pada username, huruf besar ditolak dan tidak dinormalisasi otomatis. Feature test memeriksa 429 setelah percobaan kelima habis pada username+IP yang sama dan bucket terpisah pada IP lain. Sanctum personal access token bersifat opaque; jangan parsing isinya sebagai JWT. HTTPS tetap wajib ditetapkan sebelum deployment. |
| Tenant | User biasa tidak mengirim pemilih warung. Backend menggunakan identitas user; path admin warung hanya untuk superadmin. `warung.timezone` memakai identifier IANA dan wajib diisi sebelum tenant dapat login. `tanggal_mulai` NULL berarti tanpa batas mulai; `tanggal_berakhir` NULL berarti tanpa batas akhir; tanggal terisi berlaku inklusif. |
| ID | D13 disetujui: string digit, misalnya `"1001"`; jangan konversi BIGINT menjadi Number. ID detail/update positif yang terlalu besar untuk PHP/MySQL tetap ditangani sebagai tidak ditemukan (404), dibuktikan untuk nilai di atas PHP_INT_MAX dan unsigned BIGINT pada [API-PATH-ID-OVERFLOW-CONFORMANCE-001](../backend/test-runs/API-PATH-ID-OVERFLOW-CONFORMANCE-001.md). |
| Nominal dan qty | D13 menyetujui decimal sebagai string. Nominal memakai dua angka pecahan tanpa pemisah ribuan, misalnya `"150000.00"` dan qty `"0.50"`. Format lokal hanya untuk tampilan. Money transaksi mengikuti batas kolom; AggregateMoney laporan dapat melebihi kapasitas satu transaksi dan tetap string eksak. |
| Tanggal | Timestamp disimpan dan dikirim dalam UTC. Tanggal tampilan dan filter periode mengikuti `warung.timezone`. `tanggal` request memakai profil RFC3339 yang didukung: tahun 1000–9999, waktu dengan `T`, zona `Z`/offset legal, jam 00–23, menit/detik 00–59, dan fraksi opsional; pecahan pada request create dipotong ke detik UTC tanpa rounding; leap second tidak didukung. Backdate diperbolehkan; instant transaksi future ditolak. Zona NULL/invalid menolak akses tenant. Konversi instant sale/purchase ber-offset, response UTC, raw MySQL, dan filter hari lokal dibuktikan di [TRANSACTION-UTC-INSTANT-CONFORMANCE-001](../backend/test-runs/TRANSACTION-UTC-INSTANT-CONFORMANCE-001.md); format tanggal/waktu/zona non-RFC3339 ditolak pada kedua POST di [TRANSACTION-RFC3339-DATE-CONFORMANCE-001](../backend/test-runs/TRANSACTION-RFC3339-DATE-CONFORMANCE-001.md); batas offset `Z`/`+23:59` diterima dan `+24:00`/`+00:60` ditolak di [TRANSACTION-RFC3339-OFFSET-BOUNDS-CONFORMANCE-001](../backend/test-runs/TRANSACTION-RFC3339-OFFSET-BOUNDS-CONFORMANCE-001.md); tanggal kalender tak ada ditolak dan hari kabisat diterima di [TRANSACTION-RFC3339-CALENDAR-DATE-CONFORMANCE-001](../backend/test-runs/TRANSACTION-RFC3339-CALENDAR-DATE-CONFORMANCE-001.md); jam 25, menit 60, dan detik 60 ditolak pada kedua POST di [TRANSACTION-TIMESTAMP-CLOCK-RANGE-CONFORMANCE-001](../backend/test-runs/TRANSACTION-TIMESTAMP-CLOCK-RANGE-CONFORMANCE-001.md). Pecahan `.123` dan `.999999999` dengan offset diterima lalu disimpan/dikembalikan pada presisi detik di [TRANSACTION-TIMESTAMP-FRACTION-PRECISION-CONFORMANCE-001](../backend/test-runs/TRANSACTION-TIMESTAMP-FRACTION-PRECISION-CONFORMANCE-001.md). Instant UTC di luar batas MySQL DATETIME memberi 422 setelah konversi offset, sedangkan batas UTC tahun 1000 dan 9999 diterima di [TRANSACTION-TIMESTAMP-MYSQL-RANGE-CONFORMANCE-001](../backend/test-runs/TRANSACTION-TIMESTAMP-MYSQL-RANGE-CONFORMANCE-001.md). |
| Null | JSON `null` berarti tidak diisi/tidak berlaku sesuai schema. `0.00` adalah nominal nol yang diketahui, bukan pengganti null/error. |
| Field input | `additionalProperties: false`: field server seperti total header, warung_id, user_id, nomor, dan status tidak dikirim pada create transaksi. |
| Detail | Endpoint detail/transaksi baru mengembalikan `rincian`. Endpoint daftar hanya header; fetch detail untuk membuka transaksi. |
| Envelope sukses | D13 disetujui: resource berada pada `data`; response berpaginasi menyertakan `meta`. Perilaku spesifik mengikuti schema tiap operasi. |
| Pagination | D13: `page` bilangan bulat >= 1 tanpa batas maksimum; `per_page` bilangan bulat 1–100. Default page=1 dan per_page=20; `meta` memuat page/per_page/total/last_page. Total adalah hasil filter seluruh halaman; hasil kosong memakai data=[], total=0, last_page=1. Halaman di atas last_page memberi data kosong dengan total asli. Page di atas `PHP_INT_MAX` tetap diproses tanpa offset SQL dan dikirim sebagai token integer JSON eksak. `JSON.parse` JavaScript kehilangan presisi di atas `Number.MAX_SAFE_INTEGER`; untuk nilai ekstrem, frontend simpan query page sebagai string dan jangan melakukan aritmetika pada `meta.page`. |
| Sort | D13 disetujui: sort memakai allowlist operasi. Arah diikuti id sebagai tie-breaker. Default transaksi `-tanggal` dengan id menurun saat tanggal sama. Nilai tak didukung menghasilkan 422. |
| Error | D13 disetujui: response memakai `code`, `message`, `errors`, dan `request_id`. Kesesuaian error runtime pada semua status masih harus diuji. |
| Periode | `date_from` dan `date_to` wajib untuk laporan. Pada daftar transaksi boleh keduanya kosong; bila salah satu diisi harus berpasangan. Awal <= akhir. |
| Patch | Hanya field yang berubah. Field nullable dikosongkan dengan null; field dihilangkan berarti tidak diubah. Body kosong ditolak. |
| Retry | Create penjualan/pembelian wajib memakai `Idempotency-Key`. Key yang sama dengan payload kanonis identik me-replay respons awal; key sama dengan payload berbeda memberi 409 `IDEMPOTENCY_KEY_REUSED`. Retry identik, conflict bersamaan, nomor unik, dan pemulihan setelah worker mati sudah memiliki bukti parsial (`IDEMPOTENCY-CRASH-RESTART-001`); retensi setelah record dihapus dan seluruh gate D09 masih terbuka. Jangan gunakan otomatisasi retry live sebelum operasinya berstatus READY. |

### Contoh filter hari lokal pada daftar transaksi

```http
GET /api/v1/penjualans?page=1&per_page=20&sort=-tanggal&date_from=2026-10-04&date_to=2026-10-04 HTTP/1.1
Accept: application/json
Authorization: Bearer <token>
```

Untuk timezone warung `Asia/Jakarta`, contoh itu meminta seluruh tanggal lokal 4 Oktober 2026. Rentang database UTC-nya mulai `2026-10-03T17:00:00Z` (inklusif) dan berakhir sebelum `2026-10-04T17:00:00Z`. Kirim `date_from` dan `date_to` sebagai tanggal `YYYY-MM-DD` lokal, tanpa mengonversinya di browser ke UTC. Kedua parameter boleh sama-sama tidak dikirim untuk daftar tanpa filter periode; jika hanya satu dikirim, server memberi 422. Aturan yang sama berlaku untuk `/api/v1/pembelians`.

Keputusan D04: superadmin mengelola warung pada jalur platform dan tidak otomatis bertindak sebagai user tenant. Owner adalah pemilik warung dengan seluruh akses tenant dalam warung tokennya: user dan role, katalog, penjualan, pembelian, serta laporan; beberapa owner per warung diperbolehkan dan owner dapat menetapkan owner/manager/kasir. Manager mengelola katalog, membaca seluruh penjualan, mengelola pembelian, dan laporan. Kasir mencatat penjualan serta hanya membaca transaksinya sendiri. Owner tidak dapat membuat superadmin atau mengakses warung lain. Policy dan kandidat role OpenAPI selaras; jalur owner tenant diuji sebagian, sementara matriks role dan conformance penuh masih terbuka. Semua operasi tetap DRAFT sampai gate dan bukti tiap operasi lulus.

## Daftar operasi

Path berikut relatif terhadap `/api/v1`. Role mengikuti keputusan D04; semua operasi tetap DRAFT sampai conformance/runtime gate lulus.

| Area / operasi | Method dan path | operationId | Akses kandidat |
| --- | --- | --- | --- |
| Login | POST /auth/login | login | Publik, rate limited |
| Profil dan warung aktif | GET /auth/me | getCurrentUser | User aktif |
| Logout | POST /auth/logout | logout | Bearer token user aktif; mencabut token aktif saja |
| Daftar warung | GET /admin/warungs | listWarungs | superadmin |
| Warung + owner awal | POST /admin/warungs | createWarung | superadmin |
| Detail warung | GET /admin/warungs/{id} | getWarung | superadmin |
| Ubah warung | PATCH /admin/warungs/{id} | updateWarung | superadmin |
| Profil warung sendiri | GET /warung | getCurrentWarung | DRAFT; owner/manager/kasir dalam tenant token, superadmin 403. Run historis menguji manager/kasir/superadmin/anonim; `OWNER-TENANT-ACCESS-CONFORMANCE-001` juga menguji profil owner |
| Daftar user | GET /users | listUsers | owner |
| Tambah user | POST /users | createUser | owner |
| Detail user | GET /users/{id} | getUser | owner |
| Ubah user | PATCH /users/{id} | updateUser | owner |
| Daftar kategori | GET /kategori-menus | listKategoriMenus | owner/manager semua dalam tenant; kasir hanya kategori aktif |
| Tambah kategori | POST /kategori-menus | createKategoriMenu | owner/manager dalam tenant |
| Detail kategori | GET /kategori-menus/{id} | getKategoriMenu | owner/manager semua; kasir hanya kategori aktif |
| Ubah kategori | PATCH /kategori-menus/{id} | updateKategoriMenu | owner/manager dalam tenant |
| Daftar menu | GET /menus | listMenus | owner/manager semua; kasir hanya menu aktif dari kategori aktif |
| Tambah menu | POST /menus | createMenu | owner/manager dalam tenant |
| Detail menu | GET /menus/{id} | getMenu | owner/manager semua; kasir hanya menu aktif dari kategori aktif |
| Ubah menu | PATCH /menus/{id} | updateMenu | owner/manager dalam tenant |
| Daftar penjualan | GET /penjualans | listPenjualans | owner/manager semua dalam tenant; kasir hanya penjualan miliknya |
| Catat penjualan | POST /penjualans | createPenjualan | owner/kasir dalam tenant |
| Detail penjualan | GET /penjualans/{id} | getPenjualan | owner/manager semua dalam tenant; kasir hanya penjualan miliknya |
| Daftar pembelian | GET /pembelians | listPembelians | owner/manager dalam tenant |
| Catat pembelian | POST /pembelians | createPembelian | owner/manager dalam tenant |
| Detail pembelian | GET /pembelians/{id} | getPembelian | owner/manager dalam tenant |
| Pendapatan periode | GET /laporan/penjualan | getLaporanPenjualan | owner/manager dalam tenant |
| Total pembelian periode | GET /laporan/pembelian | getLaporanPembelian | owner/manager dalam tenant |

Semua daftar punya pagination dan allowlist sort. Katalog/user/warung juga menyediakan q dan aktif; menu menyediakan kategori_menu_id. Riwayat penjualan menyediakan status. Laporan tidak dipaginasi: hasilnya satu ringkasan periode. Semua transaksi terscope ke warung bearer; FK gabungan juga mencegah relasi lintas warung di database.

### Retry create transaksi

`POST /penjualans` dan `POST /pembelians` mewajibkan header `Idempotency-Key` 1–255 karakter. Scope unik implementasi adalah `(warung_id, user_id, endpoint)`; key dan hash SHA-256 payload kanonis tersimpan pada header transaksi dan tidak dikirim kembali pada resource. Payload sama me-replay resource transaksi awal dengan HTTP 201; payload berbeda untuk key yang sama menghasilkan HTTP 409 `IDEMPOTENCY_KEY_REUSED`. Urutan rincian ikut diperhitungkan dalam hash. Retry berurutan, race payload identik, dan race payload berbeda telah diuji untuk kedua endpoint (`IDEMPOTENCY-CONCURRENCY-001`, `IDEMPOTENCY-CONFLICT-RACE-001`).

Run `IDEMPOTENCY-SCOPE-NUMBER-001` memakai dua worker yang menunggu barrier setelah Kernel siap dan membuktikan interval request beririsan. Key+payload sama menghasilkan dua transaksi mandiri untuk dua actor pada satu tenant/satu endpoint, actor tenant berbeda, dan kedua endpoint dengan actor berizin; dua key berbeda pada actor/tenant/endpoint yang sama menghasilkan dua ID serta nomor berbeda untuk sale maupun purchase. Setiap response dicocokkan ke row/detail yang tepat. Tenant tidak diuji secara independen dari actor karena user tenant terikat pada satu `warung_id`; endpoint diuji memakai actor sesuai role dan tabel/action terpisah. `IDEMPOTENCY-CRASH-RESTART-001` membuktikan rollback header/detail saat worker mati sebelum commit dan replay ID/no_transaksi sesudah response hilang pada PID baru di kedua endpoint. Kebijakan retensi setelah header dihapus masih belum ditetapkan; transaksi tetap DRAFT dan jangan dipakai sebagai kontrak live.

Nomor transaksi implementasi sementara adalah `PJ-<ULID>` dan `PB-<ULID>`; jangan mengasumsikan format permanen sebelum bukti concurrency D09 lengkap.

Belum ada kontrak endpoint delete, cancel penjualan, koreksi pembelian, upload gambar, atau transaksi atas nama tenant oleh superadmin. D06/D14 serta rancangan schema/API untuk koreksi pembelian masih perlu dituntaskan; frontend belum boleh mengarang route untuk aksi tersebut. Penjualan hanya memilih menu terdaftar; tidak ada input item bebas.

## Alur layar dan contoh

### Login dan menu navigasi

Login mengembalikan identitas serta konteks warung. Ambil ulang `/auth/me` saat memulihkan sesi untuk mengetahui akses terkini. Visibilitas menu mengikuti izin yang disepakati, tetapi backend tetap memeriksa semua request. UI tidak membuat pemilih warung untuk user tenant biasa.

### Katalog kategori dan menu

Manager dapat membuat dan mengubah kategori/menu; manager dan kasir dapat membaca katalog warung sendiri. Kasir selalu menerima kategori dan menu aktif saja, juga bila mengirim `aktif=false`; menu pada kategori nonaktif ikut disembunyikan. Detail item yang tidak terlihat untuk kasir menghasilkan 404. Daftar memakai pagination `page`/`per_page`, pencarian `q`, dan sort allowlist sesuai OpenAPI. `kategori_menu_id` harus berasal dari warung user; jangan kirim `warung_id`. Payload menu MVP memuat harga jual sebagai decimal string dua pecahan; `harga_modal` dan `gambar` tidak dikirim/diterima.

Contoh buat kategori dan menu:

```http
POST /api/v1/kategori-menus
{"nama":"Makanan","urutan":0}

POST /api/v1/menus
{"kategori_menu_id":"101","kode":"NASI","nama":"Nasi","harga":"15000.00"}
```

Menu dapat dinonaktifkan dengan `PATCH /api/v1/menus/{id}` memakai body `{"aktif":false}`; tidak ada endpoint hapus. Harga menu harus positif. Aturan transaksi final tetap mengikuti D05/D06; kontrak keseluruhan masih DRAFT.

### Penjualan

Ambil kategori/menu aktif, pilih menu dan qty, lalu kirim request berdasarkan `PenjualanCreate`. Nama/harga menu bukan input yang dipercaya backend. Form dapat membuat pratinjau, tetapi transaksi sukses menampilkan total dan snapshot dari response.

Contoh sintetis: Nasi `15000.00` × `2.00` dan Teh `5000.00` × `1.00`, diskon header `2000.00`, bayar cash `50000.00`. D05 menetapkan decimal eksak dua angka pecahan dan round half-up per rincian. Implementasi menghitung subtotal/diskon; cash menerima bayar >= total, sedangkan QRIS/transfer mensyaratkan bayar = total. Diskon tak boleh melebihi subtotal; harga menu dan qty harus positif, qty sampai dua desimal. Setiap item harus merujuk menu aktif di warung sama; backend menyimpan snapshot nama/harga jual.

### Pembelian ringkas

UI boleh menampilkan cukup keterangan, tanggal, dan nominal. Transformasikan menjadi satu rincian:

```json
{
  "tanggal": "2026-10-04T03:00:00Z",
  "rincian": [
    {"nama_item": "Belanja di pasar", "subtotal": "150000.00"}
  ]
}
```

Backend menyimpan satu header, satu detail, total `150000.00`. Response rincian memuat qty/satuan/harga_satuan null. Jangan membuat nama bahan, qty=1, atau harga satuan palsu untuk memenuhi form/schema.

### Pembelian rinci

```json
{
  "tanggal": "2026-10-04T03:00:00Z",
  "rincian": [
    {"nama_item": "Beras", "qty": "5.00", "satuan": "kg", "harga_satuan": "15000.00"},
    {"nama_item": "Cabai", "qty": "0.50", "satuan": "kg", "harga_satuan": "40000.00"}
  ]
}
```

Sesuai D10, backend menghasilkan subtotal `75000.00` dan `20000.00`, total `95000.00`. Kolom subtotal database selalu terisi; pada request hitungan backend menghitungnya. Qty dan harga satuan wajib berpasangan; subtotal yang ikut dikirim harus cocok. Pasangan tidak lengkap menghasilkan 422. Bentuk ringkas K05 tetap diterima tanpa qty/satuan/harga satuan. Bentuk ringkas dan hitungan dapat dicampur dalam satu transaksi.

### Laporan

Kirim tanggal awal dan akhir yang sama ke kedua endpoint laporan untuk menampilkan periode yang sama. Pendapatan menggunakan total penjualan selesai, bukan uang bayar. Contoh fixture: satu penjualan `33000.00`, dua pembelian `150000.00` dan `95000.00`; tampilkan pendapatan `33000.00` serta pembelian `245000.00` secara terpisah. Jangan melabeli selisih sebagai laba.

Jika suatu API gagal, bagian itu berstatus belum diketahui/gagal; jangan menampilkan nol seolah-olah tidak ada transaksi. Periode kosong yang berhasil diproses memang memberi jumlah_transaksi=0 dan nominal `0.00`.

## Error dan respons UI

```json
{
  "code": "VALIDATION_ERROR",
  "message": "Data belum valid.",
  "errors": {"rincian.0.nama_item": ["Wajib diisi."]},
  "request_id": "req-contoh-001"
}
```

| HTTP / code kandidat | Perilaku frontend |
| --- | --- |
| 400 BAD_REQUEST | Laporkan format request salah; jangan retry create otomatis. |
| 401 UNAUTHENTICATED | Tangani login/token sesuai auth final; pada login tampilkan kredensial gagal. |
| 403 FORBIDDEN | Tampilkan akses ditolak/status akun atau warung; jangan otomatis menganggap token tidak valid. |
| 404 NOT_FOUND | Data tidak ditemukan dalam scope; tidak membocorkan apakah ID ada di warung lain. |
| 409 `IDEMPOTENCY_KEY_REUSED` | Jangan mengulang payload berbeda dengan key yang sama. Jika payload memang intent baru, buat key baru; payload dan hasil awal pada key lama tetap menjadi fakta yang tercatat. |
| 422 VALIDATION_ERROR | Tautkan errors berformat dotted path ke field/baris; pertahankan input pengguna. |
| 429 RATE_LIMITED | Beri pesan tunggu; aturan interval/rate limit final mengikuti D02. |
| 500 INTERNAL_ERROR / kegagalan jaringan | Tampilkan kegagalan dan request_id bila ada; jangan mengubah nilai menjadi nol atau mengklaim write pasti gagal. |

Sukses create adalah 201, read/update/login 200, logout 204 tanpa JSON body. Jangan memanggil parser JSON wajib untuk respons 204. Response read/list tidak boleh menyertakan password, hash, atau token.

## Syarat handoff

Backend mengubah operasi menjadi `READY_FOR_FRONTEND` hanya setelah keputusan terkait ditetapkan, endpoint diimplementasikan, test fungsional/tenant/kontrak lulus, dan contoh cocok dengan schema serta perilaku server. Isi tracker dengan commit implementasi, versi spec, base URL environment, auth final, test run ID, serta keterbatasan. AI frontend mencatat versi/commit kontrak yang dipakai; perubahan payload atau semantik yang memutus kompatibilitas memerlukan revisi kontrak dan komunikasi handoff.

Checklist penerima: login/me/logout, permission denied, list/filter/pagination, create sukses, error per baris, detail snapshot, pembelian ringkas, laporan kosong, dan kegagalan jaringan. Pembatalan/correction/retry hanya diuji frontend setelah operasi tersebut resmi diserahkan.

Batas periode lokal pada empat GET daftar/laporan dikonversi ke UTC lalu divalidasi terhadap MySQL `DATETIME` tahun 1000–9999. Batas yang meluap ditolak 422 sebelum query bisnis; batas aman tetap diterima ([TRANSACTION-PERIOD-MYSQL-RANGE-CONFORMANCE-001](../backend/test-runs/TRANSACTION-PERIOD-MYSQL-RANGE-CONFORMANCE-001.md)).

Penjualan baru hanya menerima menu aktif di kategori aktif. Menu/kategori nonaktif mendapat 422 tanpa write; histori lama tetap memakai snapshot ([TRANSACTION-INACTIVE-CATALOG-SALE-CONFORMANCE-001](../backend/test-runs/TRANSACTION-INACTIVE-CATALOG-SALE-CONFORMANCE-001.md)). Pembatalan/koreksi belum masuk API.

## Changelog kontrak

| Versi | Status | Perubahan |
| --- | --- | --- |
| 0.1.0-draft | DRAFT | Implementasi awal auth/admin/katalog/transaksi/laporan, nominal/ID string, header-rincian, Idempotency-Key, dua bentuk pembelian, laporan periode, penolakan periode UTC di luar MySQL DATETIME, keputusan D06 untuk menolak sale baru dari menu/kategori nonaktif, dan daftar keputusan yang masih terbuka. Belum ada operasi live yang diserahkan. |
