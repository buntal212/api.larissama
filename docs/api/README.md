# Panduan API dan Handoff Frontend

Versi kontrak: **0.1.21-draft**, 2026-10-27. [openapi.yaml](openapi.yaml) berisi 56 operasi pada 40 path. Pendaftaran publik memberi trial tiga tanggal lokal; owner dapat membaca katalog UDEV, memvalidasi dan mengunci kode referral, meminta preview harga, memilih 1–12 periode langganan (masing-masing 30 hari), membuat invoice, memilih checkout QRIS (default) atau DANA Wallet, dan memeriksa status. Owner yang masa aktifnya berakhir tetap dapat login selama 30 tanggal lokal dan hanya mengakses katalog/pembayaran; pada hari ke-31 login dan request token lama ditolak. Integrasi billing memakai Global API Token bersama dan base URL `https://apiudev.udumbara.my.id`; pembayaran Sandbox belum diuji. Superadmin dapat memberi aktivasi tambahan tiga hari, memantau/retry sinkronisasi UDEV, dan memulai sync warung lama dengan persetujuan satu kali. Superadmin dapat membaca user, kategori, menu, penjualan, dan pembelian warung yang dipilih melalui `warung_id` di query; superadmin tidak dapat membuat atau mengubah kategori/menu. Mutasi lain yang memang mengizinkan superadmin memakai `warung_id` di body. User tenant memperoleh scope dari token dan dilarang mengirim selector. Pengujian edge-case lanjutan tetap terbuka per operationId. Baseline wire D13: `/api/v1`, ID dan decimal string, envelope `data/meta`, pagination, sort allowlist, serta error `code/message/errors/request_id`. API development: `http://localhost:8010/api/v1`.

`OPENAPI-DOCUMENT-INTEGRITY-001` memeriksa keunikan `operationId`, response map, dan resolusi `$ref` lokal ([hasil run](../backend/test-runs/OPENAPI-DOCUMENT-INTEGRITY-001.md)). Inventori kontrak kini berisi 56 operasi pada 40 path; implementasi billing belum diverifikasi.

## Implementasi backend dan status kontrak

Frontend dapat mulai integrasi alur utama melalui operasi berstatus `READY_FOR_FRONTEND`. Pendaftaran trial, daftar warung dengan ringkasan owner dan sync, endpoint aktivasi trial, endpoint sync UDEV, katalog, invoice, checkout, dan pembayaran UDEV berstatus `DRAFT` sampai acceptance backend dijalankan. Pembayaran Sandbox belum diuji. Test edge-case kompleks yang ditandai di OpenAPI dapat dilengkapi bersama laporan integrasi frontend; schema OpenAPI tetap sumber bentuk payload.

`x-implementation-status: DONE` berarti handler dan fitur backend operationId tersedia di repository. `x-contract-status: READY_FOR_FRONTEND` berarti alur utama dan tenant boundary sudah diuji cukup untuk integrasi bertahap; edge-case kompleks dapat dilanjutkan bersama frontend. Pendaftaran trial dan aktivasi tiga hari masih `DRAFT` sampai acceptance backend dijalankan; `x-deferred-verification` menandai pemeriksaan yang belum selesai.

Daftar handler backend yang terkait fitur utama:

- Auth: `registerWarungOwner`, `login`, `getCurrentUser`, `logout`.
- Warung/admin: `listWarungs`, `createWarung`, `getWarung`, `updateWarung`, `approveWarungRegistration`, `activateWarungForThreeDays`, `extendWarungSubscription`, `getWarungUdevCustomerSync`, `retryWarungUdevCustomerSync`, `getCurrentWarung`, `updateCurrentWarung`, `uploadCurrentWarungLogo`, `deleteCurrentWarungLogo`.
- Langganan/pembayaran: `listUdevSubscriptionPlans`, `previewUdevSubscriptionInvoice`, `createUdevSubscriptionInvoice`, `listUdevSubscriptionInvoices`, `getUdevSubscriptionInvoice`, `startUdevSubscriptionCheckout`, dan `reconcileUdevSubscription` membaca/menulis billing UDEV; operasinya masih DRAFT sampai acceptance. Pembayaran Sandbox belum diuji.
- User: `listUsers`, `createUser`, `getUser`, `updateUser`.
- Katalog: `listKategoriMenus`, `createKategoriMenu`, `getKategoriMenu`, `updateKategoriMenu`, `listMenus`, `createMenu`, `getMenu`, `updateMenu`.
- Penjualan/laporan: `createPenjualan`, `listPenjualans`, `getPenjualan`, `updatePenjualan`, `payPenjualan`, `cancelPenjualan`, `createPenjualanRetur`, `getLaporanPenjualan`, `getLaporanPenjualanHarian`, `getLaporanPenjualanItem`, `getLaporanPesananBelumLunas`.
- Pembelian/laporan: `createPembelian`, `listPembelians`, `getPembelian`, `updatePembelian`, `cancelPembelian`, `getLaporanPembelian`.

Status kontrak per operationId pada OpenAPI menjadi acuan handoff; pendaftaran/langganan, alur pending-payment, dan profil owner memiliki test fokus. Status task/gate serta bukti test ada di [tracker progres](../../IMPLEMENTATION_PROGRESS.md).

## Validasi spesifikasi

`docs/api/openapi.yaml` lulus validasi OpenAPI 3.1 dengan `openapi-spec-validator` 0.9.0 di container Docker khusus: `docker compose -f compose.openapi.yaml run --build --rm openapi-validator`. Setup mengunci digest base image dan versi seluruh paket Python; file kontrak dibaca read-only. Bukti: [OPENAPI-SPEC-VALIDATOR-001](../backend/test-runs/OPENAPI-SPEC-VALIDATOR-001.md). Ini hanya memeriksa validitas spesifikasi; kesiapan integrasi dasar ditentukan per operationId. Pengujian edge-case yang ditunda tercatat di `x-deferred-verification`.

Seluruh contoh inline request/response diuji terhadap schema OpenAPI tiap operasi melalui [OPENAPI-EXAMPLES-CONFORMANCE-001](../backend/test-runs/OPENAPI-EXAMPLES-CONFORMANCE-001.md), dan contoh parameter query `DateFrom`/`DateTo` diperiksa melalui [OPENAPI-PARAMETER-EXAMPLES-CONFORMANCE-001](../backend/test-runs/OPENAPI-PARAMETER-EXAMPLES-CONFORMANCE-001.md). Ini memeriksa contoh dalam dokumen; response server aktual tetap dicakup terpisah oleh feature conformance tests.

## Kode Warung dan Menu

`POST /api/v1/admin/warungs` (superadmin) membuat warung serta akun owner secara atomik dalam status menunggu persetujuan. `POST /api/v1/auth/register` (owner publik) langsung mengaktifkan warung selama trial tiga tanggal lokal inklusif. Jangan kirim `kode`, tanggal langganan, atau flag aktif; backend membuat kode `WRG-<ULID>`. Kode warung dikembalikan pada `data.warung.kode` dan unik global.

## Profil warung owner

Owner membaca profil melalui `GET /api/v1/warung`, lalu mengubah profil sendiri dengan `PATCH /api/v1/warung`. Body boleh mengirim satu atau lebih field dari `nama`, `alamat`, `telepon`, dan `timezone`; field yang tidak dikirim tidak berubah, `alamat`/`telepon` menerima `null`, dan body kosong ditolak. Jangan kirim `warung_id`: server selalu memakai tenant pada bearer token. `kode`, `aktif`, status, dan tanggal langganan tidak dapat diubah dari endpoint ini. Mengubah timezone akan mengubah pengelompokan tanggal lokal pada laporan untuk transaksi historis juga.

```http
PATCH /api/v1/warung HTTP/1.1
Authorization: Bearer <token>
Content-Type: application/json

{"nama":"Warung Pagi","alamat":null,"telepon":"08123456789","timezone":"Asia/Jakarta"}
```

Logo dikelola terpisah dengan `POST /api/v1/warung/logo` (multipart field `logo`, PNG/JPEG/WebP maksimal 1 MiB) dan `DELETE /api/v1/warung/logo`. Upload mengganti logo sebelumnya; nama file dibuat server. Setiap operasi merespons envelope `data` berisi profil terbaru. `data.logo` adalah URL publik untuk langsung ditampilkan frontend, atau `null` jika tidak ada. Manager, kasir, dan superadmin tidak dapat mengubah profil melalui endpoint owner.

## Pendaftaran owner dan langganan warung

1. Form pendaftaran frontend mengirim `POST /api/v1/auth/register` dengan profil warung (`nama`, `timezone`, opsional `alamat`/`telepon`), data owner (`nama`, `username`, opsional `email`, `password`), `data_consent: true`, dan `data_notice_version: 2026-10-25.v2`. Pemberitahuan menjelaskan penggunaan data akun/login dan pengiriman nama warung, nama owner, email, serta telepon ke UDEV untuk customer dan invoice paket langganan. Username/email lowercase dan unik global; password minimal 8 karakter.
2. Response `201` memuat `data.warung`, `data.owner`, dan `data.status_pendaftaran: aktif_trial`. Warung aktif selama tiga tanggal lokal inklusif mulai hari pendaftaran; owner dapat login selama trial.
3. Warung yang diprovisikan superadmin dan masih menunggu persetujuan tetap dapat dilihat melalui `GET /api/v1/admin/warungs?status_langganan=menunggu_persetujuan`.
4. Superadmin dapat memberi aktivasi tiga hari melalui `POST /api/v1/admin/warungs/{id}/langganan/aktivasi-tiga-hari`. Jika masa aktif masih berjalan, tiga hari ditambahkan setelah tanggal akhir; jika sudah habis atau belum ada, masa dimulai hari lokal saat aksi. Approval 30 hari tetap tersedia untuk warung yang diprovisikan admin.

UDEV menerima upsert customer segera setelah pendaftaran lokal berhasil. Satu warung dan owner terpilih menjadi satu customer UDEV; ID eksternal stabil disimpan lokal. `status=active` pada payload customer UDEV bersifat sementara untuk review UDEV dan bukan bukti pembayaran. Daftar warung superadmin menampilkan ringkasan sync. Untuk warung lama tanpa sync row, superadmin memilih owner dan memulai proses melalui `POST /api/v1/admin/warungs/{id}/udev-customer-sync`; aksi mencatat bahwa pemberitahuan/persetujuan telah disampaikan (verbal diperbolehkan), versi pemberitahuan, waktu, dan aktor. Persetujuan satu kali mencakup pendaftaran customer serta pembuatan invoice paket langganan mendatang. Detail audit tersedia melalui `GET /api/v1/admin/warungs/{id}/udev-customer-sync`; retry untuk status `failed`, `blocked`, atau `retry_wait` melalui `POST /api/v1/admin/warungs/{id}/udev-customer-sync/retry`. Kontrak operasi sinkronisasi tetap `DRAFT` sampai conformance lengkap.
5. Aksi tambah 30 hari tetap memanggil `POST /api/v1/admin/warungs/{id}/langganan/perpanjangan`. Jika belum kedaluwarsa, tanggal akhir bertambah 30 hari setelah tanggal akhir lama. Jika sudah lewat, langganan mulai hari lokal saat aksi dan berlaku 30 tanggal inklusif.
6. Backend menghitung `status_langganan` otomatis saat response dibentuk. Setelah tanggal akhir lokal, owner dapat login sampai 30 tanggal lokal dengan akses hanya ke halaman Paket langganan; request tenant lainnya ditolak. Mulai tanggal lokal ke-31, login dan request token lama ditolak. Manager/kasir tidak dapat login dalam masa tenggang. `PATCH /admin/warungs/{id}` tetap boleh mengatur `aktif: false` untuk menonaktifkan manual, tetapi aktivasi dan tanggal langganan harus melalui endpoint khusus.

## Pembayaran dan katalog paket UDEV

Owner warung aktif atau dalam masa tenggang dapat memakai menu Pembayaran. Backend Larissama membaca katalog dari `GET /api/v1/integrations/subscription-plans` dan billing dari endpoint customer/invoice UDEV memakai Global API Token bersama pada `UDEV_CUSTOMERS_TOKEN`, dengan base URL `UDEV_BASE_URL` sementara `https://apiudev.udumbara.my.id`. Harga dan nominal tidak dipercaya dari browser; backend meminta preview harga UDEV sebelum membuat invoice.

Alur API lokal: `POST /subscription-invoices/preview` dan `POST /subscription-invoices` menerima `subscription_plan_code` serta `period_count` opsional 1–12 (default 1); satu periode tetap 30 hari. Harga/durasi final dikalkulasi UDEV. Kunci idempotensi invoice dibedakan menurut paket dan jumlah periode. Checkout memakai `POST /subscription-invoices/{invoiceNumber}/checkout` dengan key terpisah; owner memilih `qris` (default) atau `dana_wallet`. Frontend membuat gambar QR secara lokal dari `qr_content`/`payment_code`, atau membuka `checkout_url` untuk DANA Wallet. Mengganti metode membatalkan attempt aktif sebelumnya di UDEV. `GET /subscription-invoices/{invoiceNumber}` dan `GET /subscription-invoices` digunakan untuk status/riwayat. Invoice jatuh tempo satu hari setelah penerbitan sesuai contoh UDEV. Checkout pending diperiksa tiap 30 detik melalui backend; status dicek kembali saat halaman dibuka. Gangguan jaringan memakai checkout key yang sama untuk metode yang sama. Setelah metode diganti atau payment attempt terminal, checkout baru memakai key baru.

Referral dapat divalidasi lewat `POST /subscription-referrals/validate`, yang menampilkan diskon/harga UDEV tanpa mengunci kode. Owner mengonfirmasi secara eksplisit untuk mengunci referral lewat `POST /subscription-referrals` dengan `Idempotency-Key`; UDEV hanya mengizinkan satu referral per customer dan kode yang terkunci tidak dapat diganti. Setelah penguncian, preview invoice menampilkan harga final dari UDEV.

Setelah invoice berstatus `paid` dan `tracking.settlement_status=completed`, owner memanggil `POST /subscription/reconcile`. Backend membaca subscription terbaru dari UDEV dan memperbarui tanggal masa aktif warung dalam timezone warung, tanpa memperpendek tanggal lokal yang lebih panjang. Selama masa tenggang, seluruh operasi pembayaran di atas menjadi akses owner yang diizinkan; route lain tetap ditolak. Seluruh operasi tetap `DRAFT` sampai verifikasi runtime. Pembayaran Sandbox dan transaksi belum diuji.

Contoh minimum body create:

```json
{
  "nama": "Warung A",
  "timezone": "Asia/Jakarta",
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

Status handoff ditetapkan per operationId; tidak semua operasi sudah `READY_FOR_FRONTEND`. Katalog dan endpoint billing UDEV memiliki handler dan halaman owner, tetapi tetap `DRAFT` sampai verifikasi runtime. Integrasi pembayaran Sandbox dan transaksi nyata belum diuji. Akses masa tenggang lokal mengikuti D17 dan pemilik dapat memakai menu pembayaran. Field `x-deferred-verification` mencatat pemeriksaan yang tertunda. Integrasi berjalan dengan [tracker implementasi](../../IMPLEMENTATION_PROGRESS.md).

API dev Docker yang sedang tersedia memakai base URL `http://localhost:8010/api/v1`; status app: `http://localhost:8010/up`; MySQL development: `localhost:33309`. Ketiga migration yang tertunda sudah diterapkan pada database dev dan seluruh 17 migration berstatus `Ran`. Ini environment development lokal, bukan environment integrasi atau production.

Untuk request API, kirim `Accept: application/json`; untuk request ber-body, kirim `Content-Type: application/json`. Endpoint terlindungi memakai `Authorization: Bearer <token>`; token berlaku 30 hari. Request yang mewajibkan retry aman harus mengirim `Idempotency-Key` sesuai operasi di OpenAPI. Header ini dipakai create transaksi dan operasi koreksi/pembatalan/retur sesuai OpenAPI. API mengembalikan envelope D13 untuk 401 tanpa bergantung pada header `Accept` setelah perbaikan `API-UNAUTHENTICATED-NO-ACCEPT-CONFORMANCE-001`.

Baseline backend sebelum endpoint katalog paket ditambahkan terakhir terverifikasi dengan suite 534/81.620 pada MySQL 8.0.40; 32 route terlindungi pada baseline itu diuji menghasilkan 401 schema-conformant tanpa token. Route katalog baru belum tercakup pengujian. Bukti 401 tanpa `Accept` ada di [artefak run](../backend/test-runs/API-UNAUTHENTICATED-NO-ACCEPT-CONFORMANCE-001.md); koreksi/retur sale terbaru di [artefak idempotency](../backend/test-runs/D06-SALE-CANCEL-IDEMPOTENCY-CONFORMANCE-001.md). Base URL tersebut untuk Docker development lokal. Jalankan `docker compose exec app php artisan app:bootstrap-superadmin` pada database dev kosong, lalu gunakan API admin untuk membuat warung dan owner pertama sebelum uji alur tenant. Jangan gunakan kredensial development sebagai kredensial production.

## Urutan baca untuk AI frontend

1. Baca panduan ini untuk istilah, bentuk data, alur, dan batas integrasi.
2. Cari operationId pada OpenAPI. Periksa `x-contract-status`, `x-implementation-status`, `x-candidate-roles`, dan `x-blocked-by`.
3. Periksa status handoff, environment, versi kontrak, dan bukti test pada [IMPLEMENTATION_PROGRESS.md](../../IMPLEMENTATION_PROGRESS.md).
4. Gunakan status terkini per operationId pada OpenAPI dan ikuti `x-deferred-verification` saat memprioritaskan pengujian tambahan.
5. Jika field/perilaku belum jelas, lihat keputusan Dxx pada [DECISIONS.md](../backend/DECISIONS.md); laporkan gap kontrak pada task backend terkait.

### Koordinasi agar pekerjaan tidak tumpang tindih

Sebelum mengambil slice baru, catat di tracker repo frontend: fitur/halaman yang selesai atau sedang dikerjakan, file dan commit terkait, status mock atau live, `operationId` yang dipakai, serta gap kontrak yang ditemukan. Periksa catatan itu sebelum membuat ulang area yang sama. Saat melaporkan masalah backend, sertakan method/path, langkah reproduksi, status dan body response yang sudah disamarkan, serta `request_id`; jangan kirim bearer token atau data pengguna nyata.

Kolom database bukan payload API otomatis. Semua contoh ID, warung, bahan, token, dan transaksi adalah data sintetis. Rincian pembelian minimal harus selalu didukung, tanpa menambahkan syarat master bahan atau qty pada formulir ringkas.

## UI frontend yang aman dikerjakan sekarang

Status kontrak dan handoff tiap operasi mengikuti `x-contract-status` di OpenAPI. Pendaftaran publik dan layar persetujuan/perpanjangan admin kini memiliki endpoint backend dan dapat diintegrasikan; gunakan `x-deferred-verification` untuk memilih edge-case yang masih perlu dilengkapi.

| Slice UI | Bisa dimulai | Batas yang perlu diikuti |
| --- | --- | --- |
| App shell dan navigasi | Layout responsif, menu per role, halaman login dan profil memakai identitas mock | Hak akses di UI hanya untuk tampilan; backend tetap otoritatif. Jangan membuat pemilih warung untuk owner/manager/kasir. |
| Warung dan user | Integrasikan daftar/provisioning/edit warung superadmin, profil warung, dan CRUD user owner melalui API dev | Bootstrapping superadmin pertama memakai Artisan pada DB lokal kosong. Owner hanya mengelola tenantnya dan boleh menetapkan owner/manager/kasir. |
| Kategori dan menu | Integrasikan list/filter, form tambah/edit kategori dan menu melalui API dev | Kasir melihat item aktif saja. Harga dikirim/ditampilkan sebagai decimal string; `harga_modal` dan gambar bukan bagian MVP. |
| Kasir dan riwayat penjualan | Integrasikan catat, list/detail, laporan, koreksi, pembatalan, dan retur penjualan melalui API dev. | Total sukses berasal dari response backend. D05 menetapkan harga menu > 0, qty > 0 sampai dua desimal, diskon nominal maksimal subtotal, cash boleh lebih, QRIS/transfer harus pas, dan half-up per baris. |
| Pembelian | Integrasikan create ringkas/rinci, list/detail, laporan, koreksi, dan pembatalan pembelian melalui API dev. | Koreksi/pembatalan memerlukan alasan dan menyimpan audit snapshot; retry memakai `Idempotency-Key` selama tujuh hari. |
| Pembayaran | Owner melihat paket/harga UDEV, memvalidasi dan mengunci kode referral, meminta preview harga final, membuat invoice, membuka checkout, dan melihat riwayat/status. Saat masa tenggang, halaman pembayaran adalah satu-satunya halaman aplikasi yang dapat diakses owner. | UDEV menjadi sumber kebenaran invoice, pembayaran, settlement, dan subscription. Pembayaran Sandbox belum diuji; settlement completed direkonsiliasi ke masa aktif lokal. |
| Laporan | Integrasikan dua tampilan periode: pendapatan penjualan dan total pembelian, termasuk keadaan periode kosong | Filter tanggal memakai hari lokal warung (`YYYY-MM-DD`). Jangan hitung atau beri label laba dari selisih kedua total. |
| Komponen lintas fitur | Gunakan response server untuk status yang ditemui; gunakan fixture mock untuk variasi edge-case yang belum diuji | Pertahankan input saat 422 dan jangan mengubah kegagalan request menjadi angka nol. Kirim temuan dengan method/path dan `request_id`. |

Gunakan schema, contoh, `x-contract-status`, dan `x-deferred-verification` di `openapi.yaml`. Hubungkan operasi READY ke server dev `http://localhost:8010/api/v1`. Gunakan fixture mock untuk variasi edge-case yang ditunda. Catat gap runtime yang ditemukan dengan method/path dan `request_id`.

## Konvensi umum dan batas kontrak (D02/D05/D08/D13)

Konvensi wire D13 pada baris terkait sudah disetujui user. Kontrak operation-level masih berstatus READY_FOR_FRONTEND untuk flow utama; test lanjutan request/response runtime diuji terhadap OpenAPI.

| Aspek | Aturan dan status |
| --- | --- |
| Base URL | Prefix `/api/v1` disetujui D13 dan sudah ada pada `servers.url`; Docker backend lokal memakai `http://localhost:8010`, lalu frontend menambahkan `/api/v1`. Quasar dev lokal dapat berjalan pada origin `http://localhost:9000` atau `http://localhost:9201`. |
| CORS | Kedua origin frontend lokal tersebut diizinkan untuk API bearer. Sesuaikan `LARISSAMA_CORS_ALLOWED_ORIGINS` dengan daftar origin frontend yang dipisahkan koma; jangan gunakan `*` untuk deployment. Cookies/credential browser tidak diaktifkan. |
| Media | Request/response JSON; kirim `Accept: application/json`, body dengan `Content-Type: application/json`. |
| Auth | User memilih Sanctum bearer melalui `Authorization: Bearer ...`; token berlaku 30 hari lalu user login ulang. Logout mencabut hanya token bearer aktif. Login dibatasi 5 percobaan per menit per username dan IP. Username/email wajib dikirim huruf kecil; angka diperbolehkan pada username, huruf besar ditolak dan tidak dinormalisasi otomatis. Feature test memeriksa 429 setelah percobaan kelima habis pada username+IP yang sama dan bucket terpisah pada IP lain. Sanctum personal access token bersifat opaque; jangan parsing isinya sebagai JWT. HTTPS tetap wajib ditetapkan sebelum deployment. |
| Tenant | User biasa tidak mengirim pemilih warung; backend memakai identitas user. Superadmin wajib mengirim `warung_id` pada setiap GET data tenant dan pada body setiap mutasi tenant. Path admin warung tetap menjadi jalur platform. `warung.timezone` memakai identifier IANA dan wajib diisi sebelum tenant dapat login. `tanggal_mulai` NULL berarti tanpa batas mulai; `tanggal_berakhir` NULL berarti tanpa batas akhir; tanggal terisi berlaku inklusif. |
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

Keputusan D04/D18: untuk setiap GET data tenant, superadmin wajib mengirim query `warung_id`, misalnya `GET /api/v1/menus?warung_id=123`. Superadmin hanya memakai selector tersebut untuk membaca kategori dan menu; pembuatan maupun perubahan katalog tetap khusus owner/manager di warung token. Untuk mutasi tenant yang memang diizinkan bagi superadmin, gunakan `warung_id` target dalam JSON body sesuai operationId. Ambil ID dari daftar warung admin. Backend mengembalikan 404 jika detail tidak berada di warung pilihan. Selector dilarang untuk user tenant biasa; tenant memakai warung pada bearer token. Form user superadmin dapat menetapkan role owner/manager/kasir, tetapi tidak dapat membuat atau mengubah akun superadmin. Kolom `created_by_superadmin_id`/`pembayaran_superadmin_id` atau `superadmin_id` menunjukkan pelaku superadmin untuk transaksi. Owner memakai akses seluruhnya dalam warung token; manager/kasir dapat mencatat pesanan pending, mengedit dengan alasan, lalu melunasi. Filter penjualan `status_pembayaran=belum_lunas|lunas` tersedia. Koreksi penjualan lunas tunduk batas 72 jam sejak pembayaran.

## Daftar operasi

Path berikut relatif terhadap `/api/v1`. Role mengikuti keputusan D04. Status integrasi ada per operationId pada OpenAPI; endpoint trial baru berstatus `DRAFT` sampai acceptance backend dijalankan.

| Area / operasi | Method dan path | operationId | Akses kandidat | Status handoff |
| --- | --- | --- | --- | --- |
| Login | POST /auth/login | login | Publik, rate limited | READY_FOR_FRONTEND |
| Katalog paket langganan UDEV | GET /subscription-plans | listUdevSubscriptionPlans | Owner warung | DRAFT |
| Validasi kode referral | POST /subscription-referrals/validate | validateUdevSubscriptionReferral | Owner warung | DRAFT |
| Kunci kode referral | POST /subscription-referrals | attachUdevSubscriptionReferral | Owner warung; Idempotency-Key wajib | DRAFT |
| Preview harga paket | POST /subscription-invoices/preview | previewUdevSubscriptionInvoice | Owner warung | DRAFT |
| Buat invoice | POST /subscription-invoices | createUdevSubscriptionInvoice | Owner warung; Idempotency-Key wajib | DRAFT |
| Riwayat invoice | GET /subscription-invoices | listUdevSubscriptionInvoices | Owner warung | DRAFT |
| Status invoice | GET /subscription-invoices/{invoiceNumber} | getUdevSubscriptionInvoice | Owner warung; invoice milik customer token | DRAFT |
| Mulai checkout | POST /subscription-invoices/{invoiceNumber}/checkout | startUdevSubscriptionCheckout | Owner warung; Idempotency-Key wajib | DRAFT |
| Sinkronkan subscription terverifikasi | POST /subscription/reconcile | reconcileUdevSubscription | Owner warung | DRAFT |
| Daftar owner/warung | POST /auth/register | registerWarungOwner | Publik, rate limited | DRAFT |
| Profil dan warung aktif | GET /auth/me | getCurrentUser | User aktif | READY_FOR_FRONTEND |
| Logout | POST /auth/logout | logout | Bearer token user aktif; mencabut token aktif saja | READY_FOR_FRONTEND |
| Daftar warung | GET /admin/warungs | listWarungs | superadmin | DRAFT |
| Warung + owner awal | POST /admin/warungs | createWarung | superadmin | READY_FOR_FRONTEND |
| Detail warung | GET /admin/warungs/{id} | getWarung | superadmin | READY_FOR_FRONTEND |
| Ubah warung | PATCH /admin/warungs/{id} | updateWarung | superadmin | READY_FOR_FRONTEND |
| Setujui pendaftaran dan mulai 30 hari | POST /admin/warungs/{id}/persetujuan | approveWarungRegistration | superadmin | READY_FOR_FRONTEND |
| Beri aktivasi tiga hari | POST /admin/warungs/{id}/langganan/aktivasi-tiga-hari | activateWarungForThreeDays | superadmin | DRAFT |
| Tambah masa aktif 30 hari | POST /admin/warungs/{id}/langganan/perpanjangan | extendWarungSubscription | superadmin | READY_FOR_FRONTEND |
| Catat persetujuan dan mulai sync customer UDEV | POST /admin/warungs/{id}/udev-customer-sync | initiateWarungUdevCustomerSync | superadmin; owner dipilih eksplisit | DRAFT |
| Status sinkronisasi customer UDEV | GET /admin/warungs/{id}/udev-customer-sync | getWarungUdevCustomerSync | superadmin | DRAFT |
| Retry sinkronisasi customer UDEV | POST /admin/warungs/{id}/udev-customer-sync/retry | retryWarungUdevCustomerSync | superadmin | DRAFT |
| Profil warung sendiri | GET /warung | getCurrentWarung | owner/manager/kasir dalam tenant token; superadmin memakai GET /admin/warungs/{id} untuk profil platform | READY_FOR_FRONTEND |
| Ubah profil warung sendiri | PATCH /warung | updateCurrentWarung | owner dalam tenant token; tanpa warung_id; field nama, alamat, telepon, timezone | READY_FOR_FRONTEND |
| Unggah/ganti logo sendiri | POST /warung/logo | uploadCurrentWarungLogo | owner dalam tenant token; multipart `logo`, PNG/JPEG/WebP maksimal 1 MiB | READY_FOR_FRONTEND |
| Hapus logo sendiri | DELETE /warung/logo | deleteCurrentWarungLogo | owner dalam tenant token | READY_FOR_FRONTEND |
| Daftar user | GET /users | listUsers | owner; superadmin dengan `warung_id`, baca saja | READY_FOR_FRONTEND |
| Tambah user | POST /users | createUser | owner | READY_FOR_FRONTEND |
| Detail user | GET /users/{id} | getUser | owner; superadmin dengan `warung_id`, baca saja | READY_FOR_FRONTEND |
| Ubah user | PATCH /users/{id} | updateUser | owner | READY_FOR_FRONTEND |
| Daftar kategori | GET /kategori-menus | listKategoriMenus | owner/manager semua dalam tenant; kasir hanya kategori aktif; superadmin dengan `warung_id`, baca saja | READY_FOR_FRONTEND |
| Tambah kategori | POST /kategori-menus | createKategoriMenu | owner/manager dalam tenant | READY_FOR_FRONTEND |
| Detail kategori | GET /kategori-menus/{id} | getKategoriMenu | owner/manager semua; kasir hanya kategori aktif; superadmin dengan `warung_id`, baca saja | READY_FOR_FRONTEND |
| Ubah kategori | PATCH /kategori-menus/{id} | updateKategoriMenu | owner/manager dalam tenant | READY_FOR_FRONTEND |
| Daftar menu | GET /menus | listMenus | owner/manager semua; kasir hanya menu aktif dari kategori aktif; superadmin dengan `warung_id`, baca saja | READY_FOR_FRONTEND |
| Tambah menu | POST /menus | createMenu | owner/manager dalam tenant | READY_FOR_FRONTEND |
| Detail menu | GET /menus/{id} | getMenu | owner/manager semua; kasir hanya menu aktif dari kategori aktif; superadmin dengan `warung_id`, baca saja | READY_FOR_FRONTEND |
| Ubah menu | PATCH /menus/{id} | updateMenu | owner/manager dalam tenant | READY_FOR_FRONTEND |
| Daftar penjualan | GET /penjualans | listPenjualans | owner/manager/kasir seluruh transaksi tenant; filter `status_pembayaran=belum_lunas|lunas`; superadmin dengan `warung_id`, baca saja | READY_FOR_FRONTEND |
| Catat pesanan | POST /penjualans | createPenjualan | owner/manager/kasir; superadmin dengan `warung_id` body; tanpa `bayar` dan metode membuat pesanan belum lunas | READY_FOR_FRONTEND |
| Detail penjualan | GET /penjualans/{id} | getPenjualan | owner/manager/kasir seluruh transaksi tenant; superadmin dengan `warung_id`, baca saja | READY_FOR_FRONTEND |
| Koreksi/pesan ulang pesanan | PATCH /penjualans/{id} | updatePenjualan | owner/manager/kasir untuk pending; owner/manager untuk lunas sampai 72 jam sejak pembayaran; superadmin dengan `warung_id` body | READY_FOR_FRONTEND |
| Catat pembayaran | POST /penjualans/{id}/pembayaran | payPenjualan | owner/manager/kasir; superadmin dengan `warung_id` body; pembayaran penuh, tunai boleh lebih untuk kembalian | READY_FOR_FRONTEND |
| Batalkan penjualan | POST /penjualans/{id}/pembatalan | cancelPenjualan | role tenant mengikuti batas state D06; superadmin dengan `warung_id` body | READY_FOR_FRONTEND |
| Catat retur | POST /penjualans/{id}/retur | createPenjualanRetur | owner/manager dalam tenant; superadmin dengan `warung_id` body selama masih ada saldo | READY_FOR_FRONTEND |
| Daftar pembelian | GET /pembelians | listPembelians | owner/manager dalam tenant; superadmin dengan `warung_id`, baca saja | READY_FOR_FRONTEND |
| Catat pembelian | POST /pembelians | createPembelian | owner/manager dalam tenant; superadmin dengan `warung_id` di body | READY_FOR_FRONTEND |
| Detail pembelian | GET /pembelians/{id} | getPembelian | owner/manager dalam tenant; superadmin dengan `warung_id`, baca saja | READY_FOR_FRONTEND |
| Koreksi pembelian | PATCH /pembelians/{id} | updatePembelian | owner/manager dalam tenant; superadmin dengan `warung_id` di body, dengan alasan | READY_FOR_FRONTEND |
| Batalkan pembelian | POST /pembelians/{id}/pembatalan | cancelPembelian | owner/manager dalam tenant; superadmin dengan `warung_id` di body, dengan alasan | READY_FOR_FRONTEND |
| Pendapatan periode | GET /laporan/penjualan | getLaporanPenjualan | owner/manager dalam tenant; superadmin wajib `warung_id`, baca saja | READY_FOR_FRONTEND |
| Total pembelian periode | GET /laporan/pembelian | getLaporanPembelian | owner/manager dalam tenant; superadmin wajib `warung_id`, baca saja | READY_FOR_FRONTEND |
| Antrean nilai belum lunas | GET /laporan/pesanan-belum-lunas | getLaporanPesananBelumLunas | owner/manager dalam tenant; superadmin wajib `warung_id`, baca saja | READY_FOR_FRONTEND |
| Tren penjualan harian | GET /laporan/penjualan-harian | getLaporanPenjualanHarian | owner/manager dalam tenant; superadmin wajib `warung_id`, baca saja | READY_FOR_FRONTEND |
| Penjualan per snapshot item | GET /laporan/penjualan-item | getLaporanPenjualanItem | owner/manager dalam tenant; superadmin wajib `warung_id`, baca saja | READY_FOR_FRONTEND |

Semua daftar transaksi punya pagination dan allowlist sort. Katalog/user/warung juga menyediakan q dan aktif; menu menyediakan kategori_menu_id. Riwayat penjualan menyediakan status. Laporan tidak dipaginasi; endpoint item dan tren mengembalikan array hasil di dalam periode. Untuk user tenant, data terscope ke warung bearer; untuk superadmin, GET tenant terscope ke query `warung_id`, sedangkan transaksi ditulis dalam body dengan `warung_id` terpilih. FK gabungan juga mencegah relasi lintas warung di database. Status per endpoint tertera pada kolom terakhir.

### Handoff laporan

Semua laporan menerima `date_from` dan `date_to` (`YYYY-MM-DD`) wajib, memakai hari lokal sesuai timezone warung, dan mengembalikan decimal sebagai string. Owner/manager tidak mengirim `warung_id`; superadmin wajib mengirim `?warung_id=<id>`. Pembelian dan pendapatan tetap dipisah, bukan laba.

- `GET /laporan/penjualan`: jumlah transaksi lunas, total `total` transaksi setelah diskon dan sebelum retur menurut tanggal pembayaran, rata-rata `total` per transaksi lunas, total dan jumlah transaksi per metode pembayaran, serta retur dan pendapatan bersih. Rata-rata dan kelompok metode adalah angka sebelum alokasi retur; total mengikuti nilai transaksi, bukan jumlah tunai yang diserahkan.
- `GET /laporan/pembelian`: jumlah dan total pembelian berstatus `tercatat` menurut tanggal pembelian.
- `GET /laporan/pesanan-belum-lunas`: jumlah dan total order yang saat ini masih `menunggu_pembayaran`/`belum_lunas`, dengan rentang berdasarkan tanggal order. Ini snapshot saat request, bukan pendapatan atau rekonstruksi historis status.
- `GET /laporan/penjualan-harian`: hanya hari yang memiliki transaksi/retur. Nilai penjualan adalah total transaksi setelah diskon dan sebelum retur; dikelompokkan menurut tanggal pembayaran lokal. Retur dikelompokkan menurut tanggal pencatatan lokal. Hari tanpa data tidak dikirim. `total_pendapatan` harian dapat negatif jika retur hari itu melebihi penjualan hari itu.
- `GET /laporan/penjualan-item`: kelompok berdasarkan `menu_id`, nama snapshot, dan harga snapshot. `jenis_item` membedakan `menu` dan `bebas`; untuk item bebas, teks nama serta harga sama digabung karena tidak ada identitas item katalog. `nilai_item` memakai subtotal rincian setelah diskon baris, sebelum diskon header. Retur tidak dapat dibagi ke item karena retur hanya merekam nominal transaksi.

Bentuk response lengkap dan contoh tersedia pada masing-masing operationId di [OpenAPI](openapi.yaml). Frontend dapat menampilkan `rata_rata_transaksi`, `per_metode_pembayaran`, tren harian, antrean belum lunas, dan rincian item memakai kontrak tersebut.

### Retry create transaksi

`POST /penjualans`, `/penjualans/{id}/pembayaran`, dan `POST /pembelians` mewajibkan `Idempotency-Key` 1–255 karakter. Scope penjualan create/pembayaran terpisah: key dibuat per warung, actor (user tenant atau superadmin), dan operasi. Key pembayaran disimpan selama tujuh hari pada header penjualan; payload sama me-replay pembayaran awal dengan HTTP 201, payload berbeda mendapat HTTP 409. Payload pembayaran terikat pada ID penjualan.

Run `IDEMPOTENCY-SCOPE-NUMBER-001` memakai dua worker yang menunggu barrier setelah Kernel siap dan membuktikan interval request beririsan. Key+payload sama menghasilkan dua transaksi mandiri untuk dua actor pada satu tenant/satu endpoint, actor tenant berbeda, dan kedua endpoint dengan actor berizin; dua key berbeda pada actor/tenant/endpoint yang sama menghasilkan dua ID serta nomor berbeda untuk sale maupun purchase. Setiap response dicocokkan ke row/detail yang tepat. Tenant tidak diuji secara independen dari actor karena user tenant terikat pada satu `warung_id`; endpoint diuji memakai actor sesuai role dan tabel/action terpisah. `IDEMPOTENCY-CRASH-RESTART-001` membuktikan rollback header/detail saat worker mati sebelum commit dan replay ID/no_transaksi sesudah response hilang pada PID baru di kedua endpoint. Window key/hash idempotency tujuh hari telah diputuskan dan dibuktikan; metadata retry dilepas saat expiry sementara fakta transaksi dan audit tetap dipertahankan (`IDEMPOTENCY-7-DAY-EXPIRY-CONFORMANCE-001`). Semua endpoint transaksi dan laporan dapat diintegrasikan; mutation membawa aturan alasan/audit dan status yang tertera di OpenAPI.

Nomor transaksi penjualan adalah `PJ-<ULID>` dan pembelian `PB-<ULID>`. API mengembalikan `no_transaksi`; frontend mencetak nomor yang sama pada nota pesanan dan nota pelunasan. Backend belum menyediakan PDF/print endpoint.

Tidak ada kontrak endpoint delete transaksi atau transaksi atas nama tenant oleh superadmin. Backend menyediakan koreksi/pembatalan/retur penjualan sesuai D06 serta pendaftaran/trial/persetujuan/langganan warung sesuai D17. Endpoint logo profil tersedia untuk integrasi bertahap. Semua 44 handler tersedia di repository; status handoff mengikuti `x-contract-status` masing-masing operasi. Rincian penjualan dapat berupa menu katalog atau item bebas dengan snapshot nama dan harga; format tiap baris ada pada OpenAPI `SaleLineInput`.

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

Ambil kategori/menu aktif, pilih menu dan qty, lalu kirim request berdasarkan `PenjualanCreate` tanpa `bayar` dan `metode_pembayaran`. Ini langsung membuat pesanan belum lunas yang dapat diedit. Tampilkan `no_transaksi` pada nota pesanan. Kasir dapat memfilter daftar memakai `status_pembayaran=belum_lunas` atau `lunas`; daftar/detail meliputi pesanan semua pencatat pada warungnya. Nama pelanggan `nama_pelanggan` opsional dan berupa free text.

Contoh pesanan pending:

```http
POST /api/v1/penjualans
Authorization: Bearer <token>
Idempotency-Key: order-20261007-001
Content-Type: application/json

{"tanggal":"2026-10-07T10:00:00+07:00","nama_pelanggan":"Andi","rincian":[{"menu_id":"1001","qty":"2.00"}]}
```

Response berisi `id`, `no_transaksi`, `total`, `status: menunggu_pembayaran`, dan `status_pembayaran: belum_lunas`. Untuk antrean kasir gunakan `GET /api/v1/penjualans?status_pembayaran=belum_lunas`; `lunas` menunjukkan riwayat yang sudah dibayar dan tanpa filter menampilkan semua status. Pesanan pending diedit melalui `PATCH /api/v1/penjualans/{id}` dengan `alasan` dan rincian lengkap pengganti, lalu pelanggan melunasi melalui `POST /api/v1/penjualans/{id}/pembayaran`.

Contoh sintetis: Nasi `15000.00` × `2.00`, Teh `5000.00` × `1.00`, dan item bebas Kerupuk `1500.00` × `2.00`, diskon header `2000.00`, total `36000.00`. Item bebas dikirim dengan `nama_menu`, `harga`, dan `qty`, tanpa `menu_id`; baris katalog hanya mengirim `menu_id` dan `qty`. Dua bentuk boleh dicampur, tidak ada batas khusus jumlah baris, dan backend menyimpan nama/harga snapshot serta menghitung subtotal. Item bebas tidak masuk katalog. Ketika pelanggan siap membayar, panggil `POST /api/v1/penjualans/{id}/pembayaran` dengan `bayar` dan `metode_pembayaran`. Pembayaran melunasi seluruh total: cash menerima bayar >= total dan menghitung kembalian; QRIS/transfer harus sama persis dengan total. Cetak `no_transaksi` yang sama pada nota lunas. D05 memakai decimal eksak dua angka pecahan dan round half-up per rincian. Diskon tak boleh melebihi subtotal; harga dan qty harus positif, qty sampai dua desimal.

Contoh rincian campuran:

```json
[
  {"menu_id":"1001","qty":"2.00"},
  {"nama_menu":"Kerupuk","harga":"1500.00","qty":"2.00"}
]
```

Pesanan belum lunas dapat diedit atau dibatalkan kapan saja selama masih pending; setiap edit/pembatalan wajib membawa alasan dan `Idempotency-Key`, dan edit menyimpan snapshot sebelum/sesudah. Setelah lunas, owner/manager dapat mengoreksi tanggal, catatan, nama pelanggan, diskon, atau mengganti rincian sampai **72 jam sejak waktu pembayaran**. Pembatalan transaksi lunas juga dibatasi window ini. Setelahnya retur sebagian/penuh tetap dapat dicatat melalui `POST /api/v1/penjualans/{id}/retur`; alasan wajib, jumlah retur dibatasi sisa nilai, dan retur mengurangi laporan pada hari lokal saat dicatat. Retur tidak mengubah stok. Lihat request/response schema dan contoh di OpenAPI.

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

Kirim tanggal awal dan akhir yang sama ke kedua endpoint laporan untuk menampilkan periode yang sama. Pendapatan penjualan masuk berdasarkan tanggal lokal `dibayar_pada`: pesanan dibuat di periode A tetapi dibayar di periode B akan dihitung pada periode B. Nilai pendapatan memakai total transaksi, bukan nominal cash diterima. Retur mengurangi periode saat retur dicatat. Contoh fixture: penjualan lunas `33000.00`, dua pembelian `150000.00` dan `95000.00`; tampilkan pendapatan `33000.00` serta pembelian `245000.00` secara terpisah. Jangan melabeli selisih sebagai laba.

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

Checklist penerima: pendaftaran owner mendapat trial tiga hari, aktivasi trial dan perpanjangan admin, login/me/logout, profil warung dan unggah/ganti/hapus logo owner, permission denied, list/filter/pagination, create sukses, error per baris, detail snapshot, pesanan pending yang bisa diedit, pembayaran kasir, nomor transaksi pada nota, koreksi/pembatalan/retur, pembelian ringkas, laporan penjualan/pembelian, antrean belum lunas, tren harian, rata-rata/metode pembayaran, item menu/item bebas, laporan kosong, dan kegagalan jaringan. Handler trial tersedia tetapi conformance backend belum dijalankan.

Batas periode lokal pada empat GET daftar/laporan dikonversi ke UTC lalu divalidasi terhadap MySQL `DATETIME` tahun 1000–9999. Batas yang meluap ditolak 422 sebelum query bisnis; batas aman tetap diterima ([TRANSACTION-PERIOD-MYSQL-RANGE-CONFORMANCE-001](../backend/test-runs/TRANSACTION-PERIOD-MYSQL-RANGE-CONFORMANCE-001.md)).

Baris penjualan katalog hanya menerima menu aktif di kategori aktif. Baris item bebas wajib menyertakan nama dan harga satuan positif; keduanya dapat dicampur tanpa batas jumlah baris khusus dan semua subtotal dihitung backend. Snapshot item bebas memiliki `menu_id: null` dan tidak menambahkan menu katalog. Menu/kategori nonaktif mendapat 422 tanpa write; histori lama tetap memakai snapshot. Koreksi, pembatalan, dan retur penjualan mendukung dua jenis baris. Bukti conformance perubahan D07 dicatat pada [SALE-FREE-FORM-ITEM-CONFORMANCE-001](../backend/test-runs/SALE-FREE-FORM-ITEM-CONFORMANCE-001.md); edge-case lanjutan tetap tercatat di OpenAPI.

## Changelog kontrak

| Versi | Status | Perubahan |
| --- | --- | --- |
| 0.1.21-draft | DRAFT | Owner memilih checkout QRIS (default) atau DANA Wallet; backend meneruskan payment_method sesuai UDEV. |
| 0.1.20-draft | DRAFT | Tambah pemilihan jumlah periode 1–12 mengikuti kontrak UDEV; satu periode 30 hari, harga/durasi dihitung UDEV. |
| 0.1.19-draft | DRAFT | Tambah validasi dan penguncian referral UDEV sebelum preview/invoice; referral hanya dapat dikunci sekali per customer. |
| 0.1.18-draft | DRAFT | Tambah preview/invoice/checkout/status/riwayat billing UDEV dan sinkronisasi subscription ke tanggal aktif lokal. Token Global; due date +1 hari, polling 30 detik, sandbox/payment test belum dijalankan. |
| 0.1.15-draft | DRAFT | Tambah aksi superadmin untuk memulai sync warung lama, memilih owner, dan mencatat pemberitahuan/persetujuan satu kali yang mencakup invoice langganan. |
| 0.1.17-draft | DRAFT | Menjelaskan akses owner selama masa tenggang 30 tanggal lokal dan penolakan login mulai hari ke-31; belum ada invoice/checkout/pembayaran. |
| 0.1.16-draft | DRAFT | Tambah endpoint owner untuk membaca katalog paket dan harga aktif langsung dari UDEV. Invoice/checkout/pembayaran belum termasuk. |
| 0.1.14-draft | DRAFT | Tambah ringkasan sync UDEV pada daftar warung superadmin untuk badge status dan retry UI; acceptance endpoint list/sync belum dijalankan. |
| 0.1.13-draft | DRAFT | Trial tiga tanggal lokal otomatis untuk pendaftaran publik dan endpoint aktivasi tiga hari oleh superadmin. Grace dan pembayaran tetap pending; conformance trial belum dijalankan. |
| 0.1.10-draft | DRAFT | Menambahkan profil owner untuk edit nama/alamat/telepon/timezone serta upload, ganti, dan hapus logo; kode dan masa langganan tetap admin-managed. |
| 0.1.9-draft | DRAFT | D04 dikoreksi: selector `warung_id` superadmin memberi akses baca eksplisit untuk kategori/menu, tetapi tidak memberi hak membuat atau mengubah katalog; operasi tulis tetap owner/manager pada tenant token. |
| 0.1.8-draft | DRAFT | D07 direvisi: rincian penjualan memakai menu katalog atau item bebas dengan snapshot nama/harga, `menu_id` nullable, baris dapat dicampur tanpa batas jumlah khusus; schema request menggunakan `SaleLineInput`. |
| 0.1.7-draft | DRAFT | D04 (revisi 2026-10-09): superadmin memakai query `warung_id` untuk GET tenant dan body `warung_id` untuk mutasi semua form tenant (user, katalog, transaksi). Tenant dilarang mengirim selector; identitas pelaku superadmin dicatat tersendiri untuk transaksi. |
| 0.1.6-draft | DRAFT | D04: superadmin memakai query `warung_id` untuk GET tenant dan body `warung_id` untuk seluruh aksi transaksi. Aturan mutasi katalog/user dari versi ini superseded oleh persetujuan 2026-10-09. |
| 0.1.5-draft | DRAFT | D04: superadmin membaca GET data tenant dengan query `warung_id` wajib; aturan mutasi transaksi pada versi ini superseded oleh persetujuan 2026-10-08. |
| 0.1.4-draft | DRAFT | D18: POST penjualan membuat pesanan pending tanpa pembayaran; tambah POST pembayaran, filter status pembayaran, nama pelanggan opsional, akses kasir lintas pencatat, nomor `no_transaksi` untuk nota, dan laporan menurut `dibayar_pada`. Koreksi lunas 72 jam dari pembayaran. |
| 0.1.3-draft | DRAFT | Menambahkan pendaftaran owner publik, filter antrean persetujuan, aktivasi 30 hari, status langganan turunan, dan endpoint perpanjangan 30 hari. Tanggal langganan hanya dapat diubah lewat aksi admin. |
| 0.1.2-draft | DRAFT | `POST /menus` tidak menerima `kode`; backend menghasilkan `MNL-<ULID>` dan mengembalikannya pada response. |
| 0.1.1-draft | DRAFT | `POST /admin/warungs` tidak menerima `kode`; backend menghasilkan `WRG-<ULID>` dan mengembalikannya pada response. PATCH warung tetap mengizinkan superadmin mengubah kode. |
| 0.1.0-draft | DRAFT | Seluruh 33 operasi READY_FOR_FRONTEND untuk integrasi bertahap pada server development. Edge-case lanjutan dicatat per operasi dan dilanjutkan setelah integrasi frontend dimulai. |
