# Panduan API dan Handoff Frontend

Versi kontrak: **0.1.0-draft**, 2026-10-05. [openapi.yaml](openapi.yaml) berisi 28 operasi pada 18 path, beserta request/response schema dan contoh sintetis. User menyetujui baseline wire D13: `/api/v1`, ID/decimal string, response `data/meta`, pagination integer `page/per_page` dengan minimum 1 dan maksimum 100, sort allowlist, dan error `code/message/errors/request_id`. Auth, sebagian administrasi user/warung, katalog, transaksi, dan laporan telah diuji pada MySQL 8.0.40. Suite backend terbaru lulus 295 test / 28238 assertions. `API-ROUTE-INVENTORY-CONFORMANCE-001` mencocokkan dua arah seluruh method/path OpenAPI dengan route Laravel ([bukti](../backend/test-runs/API-ROUTE-INVENTORY-CONFORMANCE-001.md)). `API-PATH-ID-POSITIVE-CONFORMANCE-001` membuktikan seluruh operasi detail/update mengikuti pola ID string positif OpenAPI, termasuk error 404 schema-conformant untuk ID invalid ([bukti](../backend/test-runs/API-PATH-ID-POSITIVE-CONFORMANCE-001.md)). `REPORT-RBAC-CONFORMANCE-001` membuktikan report manager-only: kasir dan superadmin menerima 403; izin owner belum diputuskan D04 dan sementara ditolak ([bukti](../backend/test-runs/REPORT-RBAC-CONFORMANCE-001.md)). `SUPERADMIN-TRANSACTION-RBAC-CONFORMANCE-001` menguji payload schema-valid untuk create sale/purchase: superadmin menerima 403 schema-conformant dan tidak menulis header/rincian ([bukti](../backend/test-runs/SUPERADMIN-TRANSACTION-RBAC-CONFORMANCE-001.md)). Semua 27 operasi yang mewajibkan bearer diuji tanpa token dan mengembalikan 401 `UNAUTHENTICATED` dengan body sesuai schema OpenAPI (`PROTECTED-OPERATIONS-401-CONFORMANCE-001`). Runtime checker membandingkan response terpilih pada operasi auth/admin/katalog/transaksi/laporan, body login auth valid untuk contoh sukses/401, body POST/PATCH terpilih untuk delapan operasi admin/katalog, body/header terpilih untuk create sale/purchase, serta query terpilih untuk list akses/katalog/transaksi dan dua laporan terhadap schema OpenAPI. Untuk keenam endpoint daftar, `page=0`/`per_page=0` dan representasi desimal/pecahan/eksponen serta teks non-integer ditolak, `per_page=100` diterima, `per_page=101` ditolak, serta `sort` di luar enum ditolak, dan seluruh 14 opsi enum yang terdokumentasi diterima dengan HTTP 200 yang cocok schema; tie-breaker `id` mengikuti arah sort pada 14 opsi dan dua halaman per opsi, serta urutan sort naik/turun atas nilai primer berbeda sudah dicocokkan ke fixture pada semua opsi; metadata hasil kosong (`total=0`, `last_page=1`) serta page di atas `last_page` (`total` tetap asli), termasuk page maksimum signed 64-bit tanpa offset overflow, juga cocok schema pada keenam list; request tanpa query memverifikasi default `page=1`, `per_page=20`, dan sort per operasi terhadap schema/runtime. Query/body operasi lain, seluruh status, dan conformance seluruh operasi masih terbuka. Semua operasi tetap `DRAFT` karena keputusan bisnis per operasi, acceptance penuh, serta conformance belum selesai. Gunakan file ini untuk review dan mock berlabel, bukan integrasi live.

## Status implementasi yang tersedia

| Slice | Commit | Pemeriksaan lokal | Handoff |
| --- | --- | --- | --- |
| Auth dan akses | `2cafc46`, `4def071`, `e89c2f0`, `ec43d1b` | Pint; `AuthApiTest` 20 test / 3069 assertions pada run request conformance (`AUTH-REQUEST-CONFORMANCE-001`); suite penuh terbaru 295 / 28238 pada MySQL 8.0.40. Response schema diuji untuk login 200/401/403/422/429, me 200/401/403, dan logout 204 kosong; body login valid untuk contoh sukses/401 cocok `LoginRequest` | Login/me, role-context, inactive, date/NULL, expiry, logout, secret/log, limiter per IP, dan request login positif terpilih sudah punya bukti; payload/status lain, conformance penuh, keputusan D08/D12, base URL masih terbuka; DRAFT |
| Administrasi warung dan user tenant | `0f7e39c`, `7fed9ae`, `d554d5a`, `278e27c`, `bad42b6`, `0c15ff5`, `bd8e83e`, `226b332`, `46f0e3b` | Pint; acceptance create/provision/user 11 / 139 (`ADMIN-API-ACCEPTANCE-001`), admin warung 4 / 113 (`ADMIN-WARUNG-MANAGEMENT-ACCEPTANCE-001`), admin schema 5 / 976 (`ADMIN-WARUNG-CONFORMANCE-001`), provisioning/user conformance 17 / 1902 (`ADMIN-USER-CONFORMANCE-001`), query 28 / 3808 (`ACCESS-CATALOG-QUERY-CONFORMANCE-001`), request 34 / 4307 (`ACCESS-CATALOG-WRITE-REQUEST-CONFORMANCE-001`); suite penuh terbaru 295 / 28238 | Provisioning/user CRUD, response schema dan query terpilih serta request body sukses POST/PATCH admin/user sudah diuji; pagination integer 1–100 diuji, nilai di luar batas/tipe dan sort di luar allowlist ditolak dan seluruh enum warung/user diterima; profil `/warung` manager/kasir/superadmin/anonim diuji pada status terpilih; email exact duplicate lintas warung ditolak saat create/update dan null/diabaikan diterima (`USER-EMAIL-UNIQUENESS-001`, 4/242); variasi case/normalisasi D12, akses owner D04, payload/status lain dan conformance penuh masih terbuka; DRAFT |
| Kategori dan menu | `eb5ea04`, `e0e32b8`, `1dc9cf3`, `9075c35`, `0c15ff5`, `bd8e83e`, `226b332` | Pint; acceptance 17 / 286 (`CATALOG-API-ACCEPTANCE-001`), response conformance 17 / 1573 (`CATALOG-CONFORMANCE-001`), query 28 / 3808 (`ACCESS-CATALOG-QUERY-CONFORMANCE-001`), request 34 / 4307 (`ACCESS-CATALOG-WRITE-REQUEST-CONFORMANCE-001`); suite penuh terbaru 295 / 28238 pada MySQL 8.0.40. Schema response diuji untuk list 200/422, create 201/403/422, detail 200/404, update 200/403/404/422 | Create/list/detail/update, request body sukses POST/PATCH terpilih, price string, tenant boundary, manager write/cashier active-only, serta query page/per_page/sort/q/aktif/kategori_menu_id punya bukti parsial; tipe pagination integer, rentang 1–100, dan sort di luar enum ditolak dan enum sort kategori/menu diterima; payload/status lain, D06 untuk transaksi atas menu nonaktif, role/contract matrix penuh dan G2 masih terbuka; DRAFT |
| Penjualan, pembelian, laporan | `0ff1d08`, `4b8f106`, `a7b5ffa`, `89c74de`, `1ae3439`, `3e411fb`, `bc96a7b`, `a9b4637`, `c54d2d3`, `a6f1ff3`, `9545300`, `7a6919a`, `1423242`, `c62953c`, `a2dcf2d`, `931dff1` | Pint/PHP lint, 8 route, OpenAPI parse/ref/contoh, migration MySQL, feature HTTP untuk transaksi, report DST, periode kosong/invalid, envelope D13 parsial, presisi `DATETIME`, aggregate capacity, paging independence, response conformance delapan operasi, request body/header POST terpilih, query GET list/report terpilih, tipe pagination integer, batas 1–100, seluruh enum sort sah, tie-breaker, urutan nilai primer berbeda, default list, serta empty-page metadata enam list (`ACCESS-SORT-ORDERING-CONFORMANCE-001`, `ACCESS-PAGINATION-DEFAULT-CONFORMANCE-001`, `ACCESS-PAGINATION-MAX-CONFORMANCE-001`, `ACCESS-PAGINATION-OVERFLOW-CONFORMANCE-001`, `ACCESS-PAGINATION-LOWER-BOUND-CONFORMANCE-001`, `ACCESS-PAGINATION-TYPE-CONFORMANCE-001`, `ACCESS-PAGINATION-NUMERIC-TYPE-CONFORMANCE-001`, `ACCESS-PAGINATION-EMPTY-PAGE-CONFORMANCE-001`, `ACCESS-SORT-ALLOWLIST-CONFORMANCE-001`, `ACCESS-SORT-VALID-ENUM-CONFORMANCE-001`, `ACCESS-SORT-TIE-BREAKER-CONFORMANCE-001`); retry identik, payload conflict, konteks scope, dan nomor unik concurrent sale/purchase (`IDEMPOTENCY-CONCURRENCY-001`, `IDEMPOTENCY-CONFLICT-RACE-001`, `IDEMPOTENCY-SCOPE-NUMBER-001`, `IDEMPOTENCY-CRASH-RESTART-001`, focused 13/155); conformance transaksi (`TRANSACTION-API-001`, `TRANSACTION-FEATURE-001`, `TRANSACTION-READ-001`, `TRANSACTION-TIMEZONE-001`, `TRANSACTION-REPORT-VALIDATION-001`, `TRANSACTION-REPORT-CONFORMANCE-001`, `TRANSACTION-REPORT-PRECISION-001`, `TRANSACTION-REPORT-AGGREGATE-001`, `TRANSACTION-CONFORMANCE-001`, `TRANSACTION-REQUEST-CONFORMANCE-001`, `TRANSACTION-QUERY-CONFORMANCE-001`); suite penuh terbaru 295 / 28238 pada MySQL 8.0.40 | Response runtime cocok untuk sale list 200, create 201/403/409/422, detail 200/404; purchase list 200/403, create 201/403/409/422, detail 200/404; kedua laporan 200/422. Body/header create valid dan query pilihan diuji; pagination integer 1–100 diterima serta nilai di luar batas/tipe ditolak sesuai schema pada kedua list transaksi. `SUPERADMIN-TRANSACTION-RBAC-CONFORMANCE-001` membuktikan kedua POST menolak superadmin dengan payload valid dan no-write; akses owner belum diputuskan. Untuk retry, payload identik bersamaan teruji pada kedua endpoint dan payload berbeda serentak menghasilkan satu 201/satu 409. Query/status lain, body invalid lain, acceptance penuh dan keputusan D04/D05/D06/D08/D09/D10/D11 tetap terbuka; DRAFT |

Rincian hasil dan batas pemeriksaan ada di [tracker implementasi](../../IMPLEMENTATION_PROGRESS.md); bukti sort allowlist serta penerimaan semua enum tercatat pada `ACCESS-SORT-ALLOWLIST-CONFORMANCE-001` dan `ACCESS-SORT-VALID-ENUM-CONFORMANCE-001`, tie-breaker lintas halaman pada `ACCESS-SORT-TIE-BREAKER-CONFORMANCE-001` dan urutan nilai sort pada `ACCESS-SORT-ORDERING-CONFORMANCE-001`; metadata hasil kosong/di luar jangkauan pada `ACCESS-PAGINATION-EMPTY-PAGE-CONFORMANCE-001` dan default page/per_page/sort pada `ACCESS-PAGINATION-DEFAULT-CONFORMANCE-001`; representasi numerik non-integer pada `ACCESS-PAGINATION-NUMERIC-TYPE-CONFORMANCE-001`, dan page maksimum signed 64-bit pada `ACCESS-PAGINATION-LARGE-PAGE-CONFORMANCE-001`. Jangan arahkan frontend ke server live sampai kontrak operasi berstatus `READY_FOR_FRONTEND`.

## Urutan baca untuk AI frontend

1. Baca panduan ini untuk istilah, bentuk data, alur, dan batas integrasi.
2. Cari operationId pada OpenAPI. Periksa `x-contract-status`, `x-implementation-status`, `x-candidate-roles`, dan `x-blocked-by`.
3. Periksa status handoff, environment, versi kontrak, dan bukti test pada [IMPLEMENTATION_PROGRESS.md](../../IMPLEMENTATION_PROGRESS.md).
4. Jika operasi belum READY_FOR_FRONTEND, kerjakan UI/mock hanya bila ditugaskan dan tandai datanya sebagai mock. Jangan menebak route, header retry, lifecycle token, status, atau field yang belum tersedia.
5. Jika field/perilaku belum jelas, lihat keputusan Dxx pada [DECISIONS.md](../backend/DECISIONS.md); laporkan gap kontrak pada task backend terkait.

Kolom database bukan payload API otomatis. Semua contoh ID, warung, bahan, token, dan transaksi adalah data sintetis. Rincian pembelian minimal harus selalu didukung, tanpa menambahkan syarat master bahan atau qty pada formulir ringkas.

## Konvensi umum dan batas kontrak (D02/D05/D08/D13)

Konvensi wire D13 pada baris terkait sudah disetujui user. Kontrak operation-level masih berstatus DRAFT sampai seluruh request/response runtime diuji terhadap OpenAPI.

| Aspek | Aturan dan status |
| --- | --- |
| Base URL | Prefix `/api/v1` disetujui D13 dan sudah ada pada `servers.url`; base URL environment diserahkan saat handoff. Jangan menggandakan prefix. |
| Media | Request/response JSON; kirim `Accept: application/json`, body dengan `Content-Type: application/json`. |
| Auth | User memilih Sanctum bearer melalui `Authorization: Bearer ...`; token berlaku 30 hari lalu user login ulang. Logout mencabut hanya token bearer aktif. Login dibatasi 5 percobaan per menit per username ternormalisasi lowercase dan IP; pencocokan identitas username mengikuti keputusan D12 yang masih perlu ditutup. Feature test memeriksa 429 setelah percobaan kelima habis pada username+IP yang sama dan bucket terpisah pada IP lain. Sanctum personal access token bersifat opaque; jangan parsing isinya sebagai JWT. Konfigurasi CORS dan HTTPS masih perlu ditetapkan sebelum deployment. |
| Tenant | User biasa tidak mengirim pemilih warung. Backend menggunakan identitas user; path admin warung hanya untuk superadmin. `warung.timezone` memakai identifier IANA dan wajib diisi sebelum tenant dapat login. `tanggal_mulai` NULL berarti tanpa batas mulai; `tanggal_berakhir` NULL berarti tanpa batas akhir; tanggal terisi berlaku inklusif. |
| ID | D13 disetujui: string digit, misalnya `"1001"`; jangan konversi BIGINT menjadi Number. |
| Nominal dan qty | D13 menyetujui decimal sebagai string. Nominal memakai dua angka pecahan tanpa pemisah ribuan, misalnya `"150000.00"` dan qty `"0.50"`. Format lokal hanya untuk tampilan. Money transaksi mengikuti batas kolom; AggregateMoney laporan dapat melebihi kapasitas satu transaksi dan tetap string eksak. |
| Tanggal | Timestamp disimpan dan dikirim dalam UTC. Tanggal tampilan dan filter periode mengikuti `warung.timezone`. `tanggal` request memakai RFC3339 ber-offset. Zona NULL/invalid menolak akses tenant. |
| Null | JSON `null` berarti tidak diisi/tidak berlaku sesuai schema. `0.00` adalah nominal nol yang diketahui, bukan pengganti null/error. |
| Field input | `additionalProperties: false`: field server seperti total header, warung_id, user_id, nomor, dan status tidak dikirim pada create transaksi. |
| Detail | Endpoint detail/transaksi baru mengembalikan `rincian`. Endpoint daftar hanya header; fetch detail untuk membuka transaksi. |
| Envelope sukses | D13 disetujui: resource berada pada `data`; response berpaginasi menyertakan `meta`. Perilaku spesifik mengikuti schema tiap operasi. |
| Pagination | D13 disetujui: `page` >= 1 dan `per_page` maksimum 100. Default 20; `meta` memuat page/per_page/total/last_page. Total adalah hasil filter seluruh halaman; hasil kosong memakai data=[], total=0, last_page=1. Halaman di atas last_page memberi data kosong dengan total asli. |
| Sort | D13 disetujui: sort memakai allowlist operasi. Arah diikuti id sebagai tie-breaker. Default transaksi `-tanggal` dengan id menurun saat tanggal sama. Nilai tak didukung menghasilkan 422. |
| Error | D13 disetujui: response memakai `code`, `message`, `errors`, dan `request_id`. Kesesuaian error runtime pada semua status masih harus diuji. |
| Periode | `date_from` dan `date_to` wajib untuk laporan. Pada daftar transaksi boleh keduanya kosong; bila salah satu diisi harus berpasangan. Awal <= akhir. |
| Patch | Hanya field yang berubah. Field nullable dikosongkan dengan null; field dihilangkan berarti tidak diubah. Body kosong ditolak. |
| Retry | Create penjualan/pembelian wajib memakai `Idempotency-Key`. Key yang sama dengan payload kanonis identik me-replay respons awal; key sama dengan payload berbeda memberi 409 `IDEMPOTENCY_KEY_REUSED`. Sampai frontend menerima status READY, retry otomatis belum boleh dianggap terverifikasi karena race/crash belum diuji. |

User menyetujui tanggung jawab inti D04: superadmin mengelola warung dan owner awal lewat admin; owner mengelola user; manager menangani katalog, pembelian, dan laporan; kasir menangani penjualan. Superadmin tidak otomatis bertindak sebagai user tenant. Implementasi least-privilege sementara memberi manager akses daftar/detail penjualan satu warung dan kasir akses transaksi miliknya saja; create sale untuk kasir; pembelian/laporan untuk manager. Hak owner di luar pengelolaan user tetap default deny sampai D04 ditutup. Semua operasi harus menutup keputusan pemblokir dan test sebelum READY_FOR_FRONTEND.

## Daftar operasi

Path berikut relatif terhadap `/api/v1`. Hak akses di tabel adalah kandidat D04.

| Area / operasi | Method dan path | operationId | Akses kandidat |
| --- | --- | --- | --- |
| Login | POST /auth/login | login | Publik, rate limited |
| Profil dan warung aktif | GET /auth/me | getCurrentUser | User aktif |
| Logout | POST /auth/logout | logout | Bearer token user aktif; mencabut token aktif saja |
| Daftar warung | GET /admin/warungs | listWarungs | superadmin |
| Warung + owner awal | POST /admin/warungs | createWarung | superadmin |
| Detail warung | GET /admin/warungs/{id} | getWarung | superadmin |
| Ubah warung | PATCH /admin/warungs/{id} | updateWarung | superadmin |
| Profil warung sendiri | GET /warung | getCurrentWarung | DRAFT; manager/kasir 200, superadmin 403, dan tanpa token 401 cocok schema (`CURRENT-WARUNG-API-ACCEPTANCE-001`); cakupan owner menunggu D04 |
| Daftar user | GET /users | listUsers | owner |
| Tambah user | POST /users | createUser | owner |
| Detail user | GET /users/{id} | getUser | owner |
| Ubah user | PATCH /users/{id} | updateUser | owner |
| Daftar kategori | GET /kategori-menus | listKategoriMenus | manager; kasir hanya kategori aktif |
| Tambah kategori | POST /kategori-menus | createKategoriMenu | manager |
| Detail kategori | GET /kategori-menus/{id} | getKategoriMenu | manager; kasir hanya kategori aktif |
| Ubah kategori | PATCH /kategori-menus/{id} | updateKategoriMenu | manager |
| Daftar menu | GET /menus | listMenus | manager; kasir hanya menu aktif dari kategori aktif |
| Tambah menu | POST /menus | createMenu | manager |
| Detail menu | GET /menus/{id} | getMenu | manager; kasir hanya menu aktif dari kategori aktif |
| Ubah menu | PATCH /menus/{id} | updateMenu | manager |
| Daftar penjualan | GET /penjualans | listPenjualans | Manager semua; kasir miliknya saja |
| Catat penjualan | POST /penjualans | createPenjualan | Kasir |
| Detail penjualan | GET /penjualans/{id} | getPenjualan | Manager semua; kasir miliknya saja |
| Daftar pembelian | GET /pembelians | listPembelians | Manager |
| Catat pembelian | POST /pembelians | createPembelian | Manager |
| Detail pembelian | GET /pembelians/{id} | getPembelian | Manager |
| Pendapatan periode | GET /laporan/penjualan | getLaporanPenjualan | Manager |
| Total pembelian periode | GET /laporan/pembelian | getLaporanPembelian | Manager |

Semua daftar punya pagination dan allowlist sort. Katalog/user/warung juga menyediakan q dan aktif; menu menyediakan kategori_menu_id. Riwayat penjualan menyediakan status. Laporan tidak dipaginasi: hasilnya satu ringkasan periode. Semua transaksi terscope ke warung bearer; FK gabungan juga mencegah relasi lintas warung di database.

### Retry create transaksi

`POST /penjualans` dan `POST /pembelians` mewajibkan header `Idempotency-Key` 1–255 karakter. Scope unik implementasi adalah `(warung_id, user_id, endpoint)`; key dan hash SHA-256 payload kanonis tersimpan pada header transaksi dan tidak dikirim kembali pada resource. Payload sama me-replay resource transaksi awal dengan HTTP 201; payload berbeda untuk key yang sama menghasilkan HTTP 409 `IDEMPOTENCY_KEY_REUSED`. Urutan rincian ikut diperhitungkan dalam hash. Retry berurutan, race payload identik, dan race payload berbeda telah diuji untuk kedua endpoint (`IDEMPOTENCY-CONCURRENCY-001`, `IDEMPOTENCY-CONFLICT-RACE-001`).

Run `IDEMPOTENCY-SCOPE-NUMBER-001` memakai dua worker yang menunggu barrier setelah Kernel siap dan membuktikan interval request beririsan. Key+payload sama menghasilkan dua transaksi mandiri untuk dua actor pada satu tenant/satu endpoint, actor tenant berbeda, dan kedua endpoint dengan actor berizin; dua key berbeda pada actor/tenant/endpoint yang sama menghasilkan dua ID serta nomor berbeda untuk sale maupun purchase. Setiap response dicocokkan ke row/detail yang tepat. Tenant tidak diuji secara independen dari actor karena user tenant terikat pada satu `warung_id`; endpoint diuji memakai actor sesuai role dan tabel/action terpisah. `IDEMPOTENCY-CRASH-RESTART-001` membuktikan rollback header/detail saat worker mati sebelum commit dan replay ID/no_transaksi sesudah response hilang pada PID baru di kedua endpoint. Kebijakan retensi setelah header dihapus masih belum ditetapkan; transaksi tetap DRAFT dan jangan dipakai sebagai kontrak live.

Nomor transaksi implementasi sementara adalah `PJ-<ULID>` dan `PB-<ULID>`; jangan mengasumsikan format permanen sebelum bukti concurrency D09 lengkap.

Belum ada kontrak endpoint delete, cancel penjualan, koreksi pembelian, upload gambar, atau transaksi atas nama tenant oleh superadmin. D06/D11/D14 dan schema terkait harus diselesaikan dahulu; kebutuhan frontend untuk aksi tersebut dikembalikan sebagai gap, bukan dibuat route sendiri. Penjualan hanya memilih menu terdaftar; tidak ada input item bebas.

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

Menu dapat dinonaktifkan dengan `PATCH /api/v1/menus/{id}` memakai body `{"aktif":false}`; tidak ada endpoint hapus. Batas uang dan konsekuensi arsip untuk transaksi final masih mengikuti D05/D06, sehingga contoh ini belum menjadi kontrak live.

### Penjualan

Ambil kategori/menu aktif, pilih menu dan qty, lalu kirim request berdasarkan `PenjualanCreate`. Nama/harga menu bukan input yang dipercaya backend. Form dapat membuat pratinjau, tetapi transaksi sukses menampilkan total dan snapshot dari response.

Contoh sintetis: Nasi `15000.00` × `2.00` dan Teh `5000.00` × `1.00`, diskon header `2000.00`, bayar cash `50000.00`. Baseline D05 menetapkan decimal eksak dua angka pecahan dan round half-up per rincian. Implementasi menghitung subtotal dan diskon; cash sementara menerima bayar >= total, sedangkan QRIS/transfer sementara mensyaratkan bayar = total. Aturan pembayaran, diskon, harga nol, batas nilai, dan qty pecahan belum seluruhnya diputuskan sehingga perilaku ini tetap DRAFT. Setiap item harus merujuk menu aktif di warung sama; backend menyimpan snapshot nama/harga jual.

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

Implementasi kandidat D10 menghasilkan subtotal `75000.00` dan `20000.00`, total `95000.00`. Kolom subtotal database selalu terisi; pada request hitungan backend dapat mengisinya. Qty dan harga satuan wajib berpasangan; subtotal yang ikut dikirim harus cocok. Pasangan tidak lengkap menghasilkan 422. Bentuk ringkas K05 tetap diterima tanpa qty/satuan/harga satuan.

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

## Changelog kontrak

| Versi | Status | Perubahan |
| --- | --- | --- |
| 0.1.0-draft | DRAFT | Implementasi awal auth/admin/katalog/transaksi/laporan, nominal/ID string, header-rincian, Idempotency-Key, dua bentuk pembelian, laporan periode, dan daftar keputusan pemblokir. Belum ada operasi live yang diserahkan. |
