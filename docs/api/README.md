# Panduan API dan Handoff Frontend

Versi kontrak: **0.1.2-draft**, 2026-10-06. [openapi.yaml](openapi.yaml) berisi 33 operasi pada 21 path, termasuk koreksi/pembatalan pembelian dan koreksi/pembatalan/retur penjualan. Baseline wire D13 yang disetujui: `/api/v1`, ID dan decimal berupa string, response `data/meta`, `page` integer minimum 1 tanpa batas maksimum, `per_page` 1–100, sort allowlist, dan error `code/message/errors/request_id`. Suite backend terakhir lulus 534 test / 81.620 assertions dalam 49,45 detik pada MySQL 8.0.40; OpenAPI 3.1 validator lulus. Handoff: **33/33 operasi `READY_FOR_FRONTEND`** untuk integrasi bertahap pada server lokal. Pengujian edge-case tambahan tetap dicatat per operationId dan dapat dilanjutkan bersama frontend. Base URL lokal `http://localhost:8010/api/v1`.

`OPENAPI-DOCUMENT-INTEGRITY-001` memeriksa bahwa inventaris operasi memiliki `operationId` unik dan response map, serta semua `$ref` JSON Pointer lokal dapat di-resolve ([hasil run](../backend/test-runs/OPENAPI-DOCUMENT-INTEGRITY-001.md)). Baseline awal berisi 28 operasi; D11 menambah dua operasi pembelian dan D06 menambah tiga operasi penjualan, sehingga OpenAPI kini berisi 33 operasi pada 21 path. Pemeriksaan ini bersifat struktural dan tidak menggantikan test runtime; status integrasi ditandai per operationId. Semua operasi telah dibuka untuk alur utama; edge-case tertunda ditulis pada `x-deferred-verification`.

## Implementasi backend dan status kontrak

Frontend dapat mulai live integration untuk seluruh 33 operasi: auth/admin, katalog, transaksi dan laporan, termasuk koreksi/pembatalan/retur penjualan serta koreksi/pembatalan pembelian. Test edge-case kompleks yang ditandai di OpenAPI dapat dilengkapi bersama laporan integrasi frontend; schema OpenAPI tetap sumber bentuk payload.

`x-implementation-status: DONE` berarti handler dan fitur backend operationId tersedia di repository. `x-contract-status: READY_FOR_FRONTEND` berarti alur utama dan tenant boundary sudah diuji cukup untuk integrasi bertahap; edge-case kompleks dapat dilanjutkan bersama frontend. Semua operasi saat ini `READY_FOR_FRONTEND`; `x-deferred-verification` menandai pemeriksaan edge-case yang masih dapat dilanjutkan.

Implementasi handler tersedia untuk seluruh 33 operationId:

- Auth: `login`, `getCurrentUser`, `logout`.
- Warung/admin: `listWarungs`, `createWarung`, `getWarung`, `updateWarung`, `getCurrentWarung`.
- User: `listUsers`, `createUser`, `getUser`, `updateUser`.
- Katalog: `listKategoriMenus`, `createKategoriMenu`, `getKategoriMenu`, `updateKategoriMenu`, `listMenus`, `createMenu`, `getMenu`, `updateMenu`.
- Penjualan/laporan: `createPenjualan`, `listPenjualans`, `getPenjualan`, `updatePenjualan`, `cancelPenjualan`, `createPenjualanRetur`, `getLaporanPenjualan`.
- Pembelian/laporan: `createPembelian`, `listPembelians`, `getPembelian`, `updatePembelian`, `cancelPembelian`, `getLaporanPembelian`.

Semua 33 `x-implementation-status` bernilai `DONE`. Seluruh 33 operasi berstatus `READY_FOR_FRONTEND` untuk alur utama pada server dev; pemeriksaan edge-case lanjutan tetap terbuka. Status task/gate lanjutan ada di [tracker progres](../../IMPLEMENTATION_PROGRESS.md); urutan milestone dan dependency backend ada di [IMPLEMENTATION_PLAN.md](../../IMPLEMENTATION_PLAN.md).

## Validasi spesifikasi

`docs/api/openapi.yaml` lulus validasi OpenAPI 3.1 dengan `openapi-spec-validator` 0.9.0 di container Docker khusus: `docker compose -f compose.openapi.yaml run --build --rm openapi-validator`. Setup mengunci digest base image dan versi seluruh paket Python; file kontrak dibaca read-only. Bukti: [OPENAPI-SPEC-VALIDATOR-001](../backend/test-runs/OPENAPI-SPEC-VALIDATOR-001.md). Ini hanya memeriksa validitas spesifikasi; kesiapan integrasi dasar ditentukan per operationId. Pengujian edge-case yang ditunda tercatat di `x-deferred-verification`.

Seluruh contoh inline request/response diuji terhadap schema OpenAPI tiap operasi melalui [OPENAPI-EXAMPLES-CONFORMANCE-001](../backend/test-runs/OPENAPI-EXAMPLES-CONFORMANCE-001.md), dan contoh parameter query `DateFrom`/`DateTo` diperiksa melalui [OPENAPI-PARAMETER-EXAMPLES-CONFORMANCE-001](../backend/test-runs/OPENAPI-PARAMETER-EXAMPLES-CONFORMANCE-001.md). Ini memeriksa contoh dalam dokumen; response server aktual tetap dicakup terpisah oleh feature conformance tests.

## Kode Warung dan Menu

`POST /api/v1/admin/warungs` hanya menerima data profil warung dan owner awal. Jangan kirim `kode`: properti itu bukan bagian dari request create, dan server akan menolak field tambahan dengan `422 VALIDATION_ERROR`. Backend membuat kode format `WRG-` diikuti ULID 26 karakter, menyimpannya sebagai unik global, lalu mengembalikannya sebagai `data.warung.kode` pada response `201`. Gunakan nilai response tersebut untuk menampilkan atau mencari warung. Superadmin masih dapat mengubah kode melalui `PATCH /api/v1/admin/warungs/{id}`; pemeriksaan unik global tetap dilakukan backend.

Contoh minimum body create:

```json
{
  "nama": "Warung A",
  "timezone": "Asia/Jakarta",
  "tanggal_mulai": null,
  "tanggal_berakhir": null,
  "owner": {
    "nama": "Pemilik A",
    "username": "owner_a",
    "password": "contoh-password"
  }
}
```

Untuk `POST /api/v1/menus`, perlakuannya sama: jangan kirim `kode`; backend mengisi kode berformat `MNL-` + ULID 26 karakter. Response menu menaruhnya di `data.kode`. Kolom tersebut tetap dibatasi unique bersama `warung_id` di database, dan owner/manager masih dapat mengubah kode melalui `PATCH /api/v1/menus/{id}`; pemeriksaan unik per warung tetap dilakukan backend. Kode warung unik global; kode menu unik per warung.

Pada tabel aplikasi, kolom literal `kode` hanya ada di `warungs` dan `menus`. `penjualans.no_transaksi` (`PJ-<ULID>`) dan `pembelians.no_transaksi` (`PB-<ULID>`) juga sudah dibuat backend, bukan dikirim client.

Contoh minimum body create menu:

```json
{
  "kategori_menu_id": "101",
  "nama": "Nasi",
  "harga": "15000.00"
}
```

## Bentuk request pembelian

`POST /api/v1/pembelians` menerima `rincian` yang berbentuk salah satu dari dua cara berikut:

- Ringkas: `nama_item` dan `subtotal`. `qty`, `satuan`, dan `harga_satuan` boleh tidak dikirim. Ini memenuhi kebutuhan minimal K05, misalnya `Belanja di pasar` beserta total.
- Dengan hitungan: `nama_item`, `qty`, dan `harga_satuan`; `satuan` opsional. Backend menghitung `subtotal` per baris dan `total` header. Client boleh mengirim `subtotal` sebagai pembanding; nilainya harus sama dengan hasil backend.

Qty tanpa harga satuan, harga satuan tanpa qty, atau baris ringkas tanpa subtotal ditolak dengan `422 VALIDATION_ERROR`; bentuk tersebut juga gagal pada `oneOf` OpenAPI. Baris dengan qty/harga berpasangan tetapi subtotal berbeda memiliki bentuk schema yang sah, namun ditolak validasi bisnis dengan 422 pada `rincian.N.subtotal`. Aturan D10 ini telah diputuskan user dan bentuk request/runtime diuji pada [PURCHASE-REQUEST-SHAPE-CONFORMANCE-001](../backend/test-runs/PURCHASE-REQUEST-SHAPE-CONFORMANCE-001.md). Operasi pembelian create/list/detail READY; test bentuk request pembelian sudah lulus.

## Status implementasi backend dan lingkungan lokal

Semua 33 handler dan operasi berstatus `READY_FOR_FRONTEND` untuk integrasi bertahap dari server dev, termasuk koreksi/pembatalan/retur. Field `x-deferred-verification` mencatat pengujian lanjutan yang ditunda. Integrasi berjalan dengan [tracker implementasi](../../IMPLEMENTATION_PROGRESS.md).

API dev Docker yang sedang tersedia memakai base URL `http://localhost:8010/api/v1`; status app: `http://localhost:8010/up`; MySQL development: `localhost:33309`. Ketiga migration yang tertunda sudah diterapkan pada database dev dan seluruh 17 migration berstatus `Ran`. Ini environment development lokal, bukan environment integrasi atau production.

Untuk request API, kirim `Accept: application/json`; untuk request ber-body, kirim `Content-Type: application/json`. Endpoint terlindungi memakai `Authorization: Bearer <token>`; token berlaku 30 hari. Request yang mewajibkan retry aman harus mengirim `Idempotency-Key` sesuai operasi di OpenAPI. Header ini dipakai create transaksi dan operasi koreksi/pembatalan/retur sesuai OpenAPI. API mengembalikan envelope D13 untuk 401 tanpa bergantung pada header `Accept` setelah perbaikan `API-UNAUTHENTICATED-NO-ACCEPT-CONFORMANCE-001`.

Baseline backend terakhir terverifikasi dengan suite 534/81.620 pada MySQL 8.0.40; seluruh 32 route terlindungi diuji menghasilkan 401 schema-conformant tanpa token. Bukti 401 tanpa `Accept` ada di [artefak run](../backend/test-runs/API-UNAUTHENTICATED-NO-ACCEPT-CONFORMANCE-001.md); koreksi/retur sale terbaru di [artefak idempotency](../backend/test-runs/D06-SALE-CANCEL-IDEMPOTENCY-CONFORMANCE-001.md). Base URL tersebut untuk Docker development lokal. Jalankan `docker compose exec app php artisan app:bootstrap-superadmin` pada database dev kosong, lalu gunakan API admin untuk membuat warung dan owner pertama sebelum uji alur tenant. Jangan gunakan kredensial development sebagai kredensial production.

## Urutan baca untuk AI frontend

1. Baca panduan ini untuk istilah, bentuk data, alur, dan batas integrasi.
2. Cari operationId pada OpenAPI. Periksa `x-contract-status`, `x-implementation-status`, `x-candidate-roles`, dan `x-blocked-by`.
3. Periksa status handoff, environment, versi kontrak, dan bukti test pada [IMPLEMENTATION_PROGRESS.md](../../IMPLEMENTATION_PROGRESS.md).
4. Seluruh 33 operationId berstatus READY_FOR_FRONTEND untuk alur utama; gunakan API dev lokal dan ikuti `x-deferred-verification` saat memprioritaskan pengujian tambahan.
5. Jika field/perilaku belum jelas, lihat keputusan Dxx pada [DECISIONS.md](../backend/DECISIONS.md); laporkan gap kontrak pada task backend terkait.

### Koordinasi agar pekerjaan tidak tumpang tindih

Sebelum mengambil slice baru, catat di tracker repo frontend: fitur/halaman yang selesai atau sedang dikerjakan, file dan commit terkait, status mock atau live, `operationId` yang dipakai, serta gap kontrak yang ditemukan. Periksa catatan itu sebelum membuat ulang area yang sama. Saat melaporkan masalah backend, sertakan method/path, langkah reproduksi, status dan body response yang sudah disamarkan, serta `request_id`; jangan kirim bearer token atau data pengguna nyata.

Kolom database bukan payload API otomatis. Semua contoh ID, warung, bahan, token, dan transaksi adalah data sintetis. Rincian pembelian minimal harus selalu didukung, tanpa menambahkan syarat master bahan atau qty pada formulir ringkas.

## UI frontend yang aman dikerjakan sekarang

Status kontrak: seluruh 33 operasi READY_FOR_FRONTEND untuk integrasi bertahap di server development. Frontend dapat menghubungkan semua slice, termasuk koreksi/pembatalan/retur. Gunakan mock untuk variasi edge-case yang belum tercakup, sesuai catatan OpenAPI.

| Slice UI | Bisa dimulai | Batas yang perlu diikuti |
| --- | --- | --- |
| App shell dan navigasi | Layout responsif, menu per role, halaman login dan profil memakai identitas mock | Hak akses di UI hanya untuk tampilan; backend tetap otoritatif. Jangan membuat pemilih warung untuk owner/manager/kasir. |
| Warung dan user | Integrasikan daftar/provisioning/edit warung superadmin, profil warung, dan CRUD user owner melalui API dev | Bootstrapping superadmin pertama memakai Artisan pada DB lokal kosong. Owner hanya mengelola tenantnya dan boleh menetapkan owner/manager/kasir. |
| Kategori dan menu | Integrasikan list/filter, form tambah/edit kategori dan menu melalui API dev | Kasir melihat item aktif saja. Harga dikirim/ditampilkan sebagai decimal string; `harga_modal` dan gambar bukan bagian MVP. |
| Kasir dan riwayat penjualan | Integrasikan catat, list/detail, laporan, koreksi, pembatalan, dan retur penjualan melalui API dev. | Total sukses berasal dari response backend. D05 menetapkan harga menu > 0, qty > 0 sampai dua desimal, diskon nominal maksimal subtotal, cash boleh lebih, QRIS/transfer harus pas, dan half-up per baris. |
| Pembelian | Integrasikan create ringkas/rinci, list/detail, laporan, koreksi, dan pembatalan pembelian melalui API dev. | Koreksi/pembatalan memerlukan alasan dan menyimpan audit snapshot; retry memakai `Idempotency-Key` selama tujuh hari. |
| Laporan | Integrasikan dua tampilan periode: pendapatan penjualan dan total pembelian, termasuk keadaan periode kosong | Filter tanggal memakai hari lokal warung (`YYYY-MM-DD`). Jangan hitung atau beri label laba dari selisih kedua total. |
| Komponen lintas fitur | Gunakan response server untuk status yang ditemui; gunakan fixture mock untuk variasi edge-case yang belum diuji | Pertahankan input saat 422 dan jangan mengubah kegagalan request menjadi angka nol. Kirim temuan dengan method/path dan `request_id`. |

Gunakan schema, contoh, `x-contract-status`, dan `x-deferred-verification` di `openapi.yaml`. Hubungkan operasi READY ke server dev `http://localhost:8010/api/v1`. Gunakan fixture mock untuk variasi edge-case yang ditunda. Catat gap runtime yang ditemukan dengan method/path dan `request_id`.

## Konvensi umum dan batas kontrak (D02/D05/D08/D13)

Konvensi wire D13 pada baris terkait sudah disetujui user. Kontrak operation-level masih berstatus READY_FOR_FRONTEND untuk flow utama; test lanjutan request/response runtime diuji terhadap OpenAPI.

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
| Retry | Seluruh operasi create transaksi serta koreksi/pembatalan/retur memakai `Idempotency-Key` dengan window 7 hari sejak request pertama. Selama window, payload kanonis identik me-replay hasil awal dan key sama dengan payload berbeda memberi 409 `IDEMPOTENCY_KEY_REUSED`. Sesudah expiry, key lama tidak me-replay respons dan pemakaian ulang diproses sebagai request baru. Metadata retry lama dilepas saat transaksi baru commit; bila request ditolak validasi bisnis dan rollback, metadata expired dapat tetap tersimpan secara fisik tetapi tetap tidak berlaku untuk replay. Tidak ada cleanup terjadwal. Header transaksi dan audit tetap tersimpan. Batas 7 hari sale, purchase, koreksi, dan pembatalan diuji oleh [IDEMPOTENCY-7-DAY-EXPIRY-CONFORMANCE-001](../backend/test-runs/IDEMPOTENCY-7-DAY-EXPIRY-CONFORMANCE-001.md) dan [IDEMPOTENCY-CANCEL-EXPIRY-CONFORMANCE-001](../backend/test-runs/IDEMPOTENCY-CANCEL-EXPIRY-CONFORMANCE-001.md); concurrency/crash-restart tercatat di artefak D09 terkait. Retry dan expiry untuk operasi koreksi/pembatalan D06/D11 memiliki bukti terarah pada artefak terkait; uji edge-case lanjutan dicatat per operasi. |

### Contoh filter hari lokal pada daftar transaksi

```http
GET /api/v1/penjualans?page=1&per_page=20&sort=-tanggal&date_from=2026-10-04&date_to=2026-10-04 HTTP/1.1
Accept: application/json
Authorization: Bearer <token>
```

Untuk timezone warung `Asia/Jakarta`, contoh itu meminta seluruh tanggal lokal 4 Oktober 2026. Rentang database UTC-nya mulai `2026-10-03T17:00:00Z` (inklusif) dan berakhir sebelum `2026-10-04T17:00:00Z`. Kirim `date_from` dan `date_to` sebagai tanggal `YYYY-MM-DD` lokal, tanpa mengonversinya di browser ke UTC. Kedua parameter boleh sama-sama tidak dikirim untuk daftar tanpa filter periode; jika hanya satu dikirim, server memberi 422. Aturan yang sama berlaku untuk `/api/v1/pembelians`.

Keputusan D04: superadmin mengelola warung pada jalur platform dan tidak otomatis bertindak sebagai user tenant. Owner adalah pemilik warung dengan seluruh akses tenant dalam warung tokennya: user dan role, katalog, penjualan, pembelian, serta laporan; beberapa owner per warung diperbolehkan dan owner dapat menetapkan owner/manager/kasir. Manager mengelola katalog, membaca seluruh penjualan, mengelola pembelian, dan laporan. Kasir mencatat penjualan serta hanya membaca transaksinya sendiri. Owner tidak dapat membuat superadmin atau mengakses warung lain. Core API roles dan tenant scope sudah diuji untuk integrasi awal; perluas matriks edge-case setelah frontend melaporkan kebutuhan atau mismatch.

## Daftar operasi

Path berikut relatif terhadap `/api/v1`. Role mengikuti keputusan D04. Status integrasi ada per operationId pada OpenAPI: seluruh 33 operasi READY_FOR_FRONTEND.

| Area / operasi | Method dan path | operationId | Akses kandidat | Status handoff |
| --- | --- | --- | --- | --- |
| Login | POST /auth/login | login | Publik, rate limited | READY_FOR_FRONTEND |
| Profil dan warung aktif | GET /auth/me | getCurrentUser | User aktif | READY_FOR_FRONTEND |
| Logout | POST /auth/logout | logout | Bearer token user aktif; mencabut token aktif saja | READY_FOR_FRONTEND |
| Daftar warung | GET /admin/warungs | listWarungs | superadmin | READY_FOR_FRONTEND |
| Warung + owner awal | POST /admin/warungs | createWarung | superadmin | READY_FOR_FRONTEND |
| Detail warung | GET /admin/warungs/{id} | getWarung | superadmin | READY_FOR_FRONTEND |
| Ubah warung | PATCH /admin/warungs/{id} | updateWarung | superadmin | READY_FOR_FRONTEND |
| Profil warung sendiri | GET /warung | getCurrentWarung | owner/manager/kasir dalam tenant token, superadmin 403. Run historis menguji manager/kasir/superadmin/anonim; `OWNER-TENANT-ACCESS-CONFORMANCE-001` juga menguji profil owner | READY_FOR_FRONTEND |
| Daftar user | GET /users | listUsers | owner | READY_FOR_FRONTEND |
| Tambah user | POST /users | createUser | owner | READY_FOR_FRONTEND |
| Detail user | GET /users/{id} | getUser | owner | READY_FOR_FRONTEND |
| Ubah user | PATCH /users/{id} | updateUser | owner | READY_FOR_FRONTEND |
| Daftar kategori | GET /kategori-menus | listKategoriMenus | owner/manager semua dalam tenant; kasir hanya kategori aktif | READY_FOR_FRONTEND |
| Tambah kategori | POST /kategori-menus | createKategoriMenu | owner/manager dalam tenant | READY_FOR_FRONTEND |
| Detail kategori | GET /kategori-menus/{id} | getKategoriMenu | owner/manager semua; kasir hanya kategori aktif | READY_FOR_FRONTEND |
| Ubah kategori | PATCH /kategori-menus/{id} | updateKategoriMenu | owner/manager dalam tenant | READY_FOR_FRONTEND |
| Daftar menu | GET /menus | listMenus | owner/manager semua; kasir hanya menu aktif dari kategori aktif | READY_FOR_FRONTEND |
| Tambah menu | POST /menus | createMenu | owner/manager dalam tenant | READY_FOR_FRONTEND |
| Detail menu | GET /menus/{id} | getMenu | owner/manager semua; kasir hanya menu aktif dari kategori aktif | READY_FOR_FRONTEND |
| Ubah menu | PATCH /menus/{id} | updateMenu | owner/manager dalam tenant | READY_FOR_FRONTEND |
| Daftar penjualan | GET /penjualans | listPenjualans | owner/manager semua dalam tenant; kasir hanya penjualan miliknya | READY_FOR_FRONTEND |
| Catat penjualan | POST /penjualans | createPenjualan | owner/kasir dalam tenant | READY_FOR_FRONTEND |
| Detail penjualan | GET /penjualans/{id} | getPenjualan | owner/manager semua dalam tenant; kasir hanya penjualan miliknya | READY_FOR_FRONTEND |
| Koreksi penjualan | PATCH /penjualans/{id} | updatePenjualan | owner/manager dalam tenant, sampai 72 jam | READY_FOR_FRONTEND |
| Batalkan penjualan | POST /penjualans/{id}/pembatalan | cancelPenjualan | owner/manager dalam tenant, sampai 72 jam | READY_FOR_FRONTEND |
| Catat retur | POST /penjualans/{id}/retur | createPenjualanRetur | owner/manager dalam tenant selama masih ada saldo | READY_FOR_FRONTEND |
| Daftar pembelian | GET /pembelians | listPembelians | owner/manager dalam tenant | READY_FOR_FRONTEND |
| Catat pembelian | POST /pembelians | createPembelian | owner/manager dalam tenant | READY_FOR_FRONTEND |
| Detail pembelian | GET /pembelians/{id} | getPembelian | owner/manager dalam tenant | READY_FOR_FRONTEND |
| Koreksi pembelian | PATCH /pembelians/{id} | updatePembelian | owner/manager dalam tenant, dengan alasan | READY_FOR_FRONTEND |
| Batalkan pembelian | POST /pembelians/{id}/pembatalan | cancelPembelian | owner/manager dalam tenant, dengan alasan | READY_FOR_FRONTEND |
| Pendapatan periode | GET /laporan/penjualan | getLaporanPenjualan | owner/manager dalam tenant | READY_FOR_FRONTEND |
| Total pembelian periode | GET /laporan/pembelian | getLaporanPembelian | owner/manager dalam tenant | READY_FOR_FRONTEND |

Semua daftar punya pagination dan allowlist sort. Katalog/user/warung juga menyediakan q dan aktif; menu menyediakan kategori_menu_id. Riwayat penjualan menyediakan status. Laporan tidak dipaginasi: hasilnya satu ringkasan periode. Semua transaksi terscope ke warung bearer; FK gabungan juga mencegah relasi lintas warung di database. Status per endpoint tertera pada kolom terakhir.

### Retry create transaksi

`POST /penjualans` dan `POST /pembelians` mewajibkan header `Idempotency-Key` 1–255 karakter. Scope unik implementasi adalah `(warung_id, user_id, endpoint)`; key dan hash SHA-256 payload kanonis tersimpan pada header transaksi dan tidak dikirim kembali pada resource. Payload sama me-replay resource transaksi awal dengan HTTP 201; payload berbeda untuk key yang sama menghasilkan HTTP 409 `IDEMPOTENCY_KEY_REUSED`. Urutan rincian ikut diperhitungkan dalam hash. Retry berurutan, race payload identik, dan race payload berbeda telah diuji untuk kedua endpoint (`IDEMPOTENCY-CONCURRENCY-001`, `IDEMPOTENCY-CONFLICT-RACE-001`).

Run `IDEMPOTENCY-SCOPE-NUMBER-001` memakai dua worker yang menunggu barrier setelah Kernel siap dan membuktikan interval request beririsan. Key+payload sama menghasilkan dua transaksi mandiri untuk dua actor pada satu tenant/satu endpoint, actor tenant berbeda, dan kedua endpoint dengan actor berizin; dua key berbeda pada actor/tenant/endpoint yang sama menghasilkan dua ID serta nomor berbeda untuk sale maupun purchase. Setiap response dicocokkan ke row/detail yang tepat. Tenant tidak diuji secara independen dari actor karena user tenant terikat pada satu `warung_id`; endpoint diuji memakai actor sesuai role dan tabel/action terpisah. `IDEMPOTENCY-CRASH-RESTART-001` membuktikan rollback header/detail saat worker mati sebelum commit dan replay ID/no_transaksi sesudah response hilang pada PID baru di kedua endpoint. Window key/hash idempotency tujuh hari telah diputuskan dan dibuktikan; metadata retry dilepas saat expiry sementara fakta transaksi dan audit tetap dipertahankan (`IDEMPOTENCY-7-DAY-EXPIRY-CONFORMANCE-001`). Semua endpoint transaksi dan laporan dapat diintegrasikan; mutation membawa aturan alasan/audit dan status yang tertera di OpenAPI.

Nomor transaksi implementasi sementara adalah `PJ-<ULID>` dan `PB-<ULID>`; jangan mengasumsikan format permanen sebelum bukti concurrency D09 lengkap.

Tidak ada kontrak endpoint delete transaksi, koreksi pembelian, upload gambar, atau transaksi atas nama tenant oleh superadmin. Backend menyediakan koreksi/pembatalan/retur penjualan sesuai D06. Semua 33 operasi tersedia untuk integrasi bertahap ke server development. Penjualan hanya memilih menu terdaftar; tidak ada input item bebas.

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
{"kategori_menu_id":"101","nama":"Nasi","harga":"15000.00"}
```

Menu dapat dinonaktifkan dengan `PATCH /api/v1/menus/{id}` memakai body `{"aktif":false}`; tidak ada endpoint hapus. Harga menu harus positif. Aturan transaksi final mengikuti D05/D06; endpoint katalog inti berstatus READY_FOR_FRONTEND.

### Penjualan

Ambil kategori/menu aktif, pilih menu dan qty, lalu kirim request berdasarkan `PenjualanCreate`. Nama/harga menu bukan input yang dipercaya backend. Form dapat membuat pratinjau, tetapi transaksi sukses menampilkan total dan snapshot dari response.

Contoh sintetis: Nasi `15000.00` × `2.00` dan Teh `5000.00` × `1.00`, diskon header `2000.00`, bayar cash `50000.00`. D05 menetapkan decimal eksak dua angka pecahan dan round half-up per rincian. Implementasi menghitung subtotal/diskon; cash menerima bayar >= total, sedangkan QRIS/transfer mensyaratkan bayar = total. Diskon tak boleh melebihi subtotal; harga menu dan qty harus positif, qty sampai dua desimal. Setiap item harus merujuk menu aktif di warung sama; backend menyimpan snapshot nama/harga jual.

Owner/manager dapat mengoreksi tanggal, catatan, diskon, pembayaran, atau mengganti seluruh rincian sampai **72 jam sejak transaksi dibuat**. Kirim alasan wajib dan `Idempotency-Key`; koreksi menyimpan snapshot sebelum/sesudah. Harga rincian selalu dibaca ulang dari menu aktif, dan total dihitung backend. Dalam window yang sama, pembatalan memakai `POST /api/v1/penjualans/{id}/pembatalan` beserta alasan. Setelah 72 jam koreksi/pembatalan ditolak, namun retur sebagian/penuh tetap dapat dicatat melalui `POST /api/v1/penjualans/{id}/retur`; alasan wajib, jumlah retur dibatasi sisa nilai, dan retur mengurangi laporan pada hari lokal saat dicatat. Retur tidak mengubah stok. Lihat request/response schema dan contoh di OpenAPI.

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

### Koreksi dan pembatalan pembelian (D11)

Gunakan `PATCH /api/v1/pembelians/{id}` untuk mengubah setidaknya satu dari `tanggal`, `catatan`, atau `rincian`; `alasan` wajib. Jika `rincian` dikirim, seluruh rincian lama diganti dengan daftar baru dan total dihitung ulang. Gunakan `POST /api/v1/pembelians/{id}/pembatalan` dengan `alasan` wajib untuk membatalkan. Kedua operasi memerlukan `Idempotency-Key`. Response merupakan event audit dengan snapshot `sebelum` dan `sesudah`; GET detail memuat `riwayat_koreksi`. List tetap menampilkan pembelian batal dengan `status: dibatalkan`; laporan hanya menghitung `status: tercatat`. Kedua operationId tambahan ini melengkapi 33 operasi pada kontrak. Runtime D11 telah diuji untuk sukses/replay, body/header, 401 tanpa token, role/tenant denial pembatalan, field asing tanpa write, dan expiry idempotency tujuh hari ([role pembatalan deny](../backend/test-runs/D11-CANCEL-RBAC-CONFORMANCE-001.md), [jalur sukses role](../backend/test-runs/D11-PURCHASE-POSITIVE-RBAC-CONFORMANCE-001.md)). Subset hasil dicatat pada [D11-PURCHASE-CONTRACT-CONFORMANCE-001](../backend/test-runs/D11-PURCHASE-CONTRACT-CONFORMANCE-001.md) dan [D11-JSON-BODY-CONFORMANCE-001](../backend/test-runs/D11-JSON-BODY-CONFORMANCE-001.md). Kedua endpoint siap untuk integrasi bertahap; variasi edge-case tambahan mengikuti `x-deferred-verification`.

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

Untuk prioritas kerja saat ini, operasi dapat berstatus `READY_FOR_FRONTEND` setelah keputusan inti final, endpoint dan alur utama diuji secara fungsional/tenant, serta contoh utama cocok dengan schema. Pengujian kombinasi error dan edge-case yang kompleks dapat menyusul setelah frontend mulai integrasi. Tracker tetap mencatat status, versi spec, base URL dev, auth, bukti yang tersedia, dan test yang ditunda.

Checklist penerima: login/me/logout, permission denied, list/filter/pagination, create sukses, error per baris, detail snapshot, koreksi/pembatalan/retur, pembelian ringkas, laporan kosong, dan kegagalan jaringan. Seluruh 33 operasi sudah READY_FOR_FRONTEND untuk alur utama; uji edge-case dapat menyusul.

Batas periode lokal pada empat GET daftar/laporan dikonversi ke UTC lalu divalidasi terhadap MySQL `DATETIME` tahun 1000–9999. Batas yang meluap ditolak 422 sebelum query bisnis; batas aman tetap diterima ([TRANSACTION-PERIOD-MYSQL-RANGE-CONFORMANCE-001](../backend/test-runs/TRANSACTION-PERIOD-MYSQL-RANGE-CONFORMANCE-001.md)).

Penjualan baru dan rincian koreksi hanya menerima menu aktif di kategori aktif. Menu/kategori nonaktif mendapat 422 tanpa write; histori lama tetap memakai snapshot ([TRANSACTION-INACTIVE-CATALOG-SALE-CONFORMANCE-001](../backend/test-runs/TRANSACTION-INACTIVE-CATALOG-SALE-CONFORMANCE-001.md)). Koreksi, pembatalan, dan retur penjualan siap untuk integrasi alur utama. Cakupan uji tersimpan dalam [SALE-CORRECTION-RETURN-CONFORMANCE-001](../backend/test-runs/SALE-CORRECTION-RETURN-CONFORMANCE-001.md), serta artefak request, state, role, audit, dan batas terkait; edge-case lanjutan tercatat di OpenAPI.

## Changelog kontrak

| Versi | Status | Perubahan |
| --- | --- | --- |
| 0.1.2-draft | DRAFT | `POST /menus` tidak menerima `kode`; backend menghasilkan `MNL-<ULID>` dan mengembalikannya pada response. |
| 0.1.1-draft | DRAFT | `POST /admin/warungs` tidak menerima `kode`; backend menghasilkan `WRG-<ULID>` dan mengembalikannya pada response. PATCH warung tetap mengizinkan superadmin mengubah kode. |
| 0.1.0-draft | DRAFT | Seluruh 33 operasi READY_FOR_FRONTEND untuk integrasi bertahap pada server development. Edge-case lanjutan dicatat per operasi dan dilanjutkan setelah integrasi frontend dimulai. |
