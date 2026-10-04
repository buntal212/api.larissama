# Panduan API dan Handoff Frontend

Versi kandidat: **0.1.0-draft**, 2026-10-04. [openapi.yaml](openapi.yaml) berisi 28 operasi pada 18 path, beserta request/response schema dan contoh sintetis. Auth, administrasi, dan katalog kategori/menu sudah memiliki implementasi awal, tetapi masih `DRAFT` karena test aplikasi dan contract test belum dijalankan. Penjualan, pembelian, serta laporan belum tersedia. File ini dapat dipakai untuk review dan mock yang diberi label, bukan bukti integrasi live sudah dapat berjalan.

## Status implementasi yang tersedia

| Slice | Commit | Pemeriksaan lokal | Handoff |
| --- | --- | --- | --- |
| Auth dan akses | `2cafc46` | Pint/PHP lint, 3 route, timezone sesi MySQL, migration timezone (`AUTH-API-001`, `DB-MIGRATION-003`) | Belum ada HTTP/app/contract test; DRAFT |
| Administrasi warung dan user tenant | `0f7e39c` | Pint/PHP lint, 9 route, YAML parse (`ADMIN-API-001`) | Belum ada HTTP/app/contract test; DRAFT |
| Kategori dan menu | `eb5ea04` | Pint/PHP lint, 8 route, YAML parse, dua migration dan `db:table` pada MySQL 8.0.40 (`CATALOG-API-001`, `DB-MIGRATION-004`) | Belum ada HTTP/app/contract test; DRAFT, rincian D02/D04/D05/D06/D13/D16 masih perlu ditutup |

Rincian hasil dan batas pemeriksaan ada di [tracker implementasi](../../IMPLEMENTATION_PROGRESS.md). Jangan arahkan frontend ke server live sampai kontrak operasi berstatus `READY_FOR_FRONTEND`.

## Urutan baca untuk AI frontend

1. Baca panduan ini untuk istilah, bentuk data, alur, dan batas integrasi.
2. Cari operationId pada OpenAPI. Periksa `x-contract-status`, `x-implementation-status`, `x-candidate-roles`, dan `x-blocked-by`.
3. Periksa status handoff, environment, versi kontrak, dan bukti test pada [IMPLEMENTATION_PROGRESS.md](../../IMPLEMENTATION_PROGRESS.md).
4. Jika operasi belum READY_FOR_FRONTEND, kerjakan UI/mock hanya bila ditugaskan dan tandai datanya sebagai mock. Jangan menebak route, header retry, lifecycle token, status, atau field yang belum tersedia.
5. Jika field/perilaku belum jelas, lihat keputusan Dxx pada [DECISIONS.md](../backend/DECISIONS.md); laporkan gap kontrak pada task backend terkait.

Kolom database bukan payload API otomatis. Semua contoh ID, warung, bahan, token, dan transaksi adalah data sintetis. Rincian pembelian minimal harus selalu didukung, tanpa menambahkan syarat master bahan atau qty pada formulir ringkas.

## Kandidat konvensi umum (D02/D05/D08/D13)

| Aspek | Kontrak kandidat |
| --- | --- |
| Base URL | Diserahkan per environment saat handoff. Prefix `/api/v1` sudah ada pada `servers.url`; jangan menggandakannya. |
| Media | Request/response JSON; kirim `Accept: application/json`, body dengan `Content-Type: application/json`. |
| Auth | User memilih Sanctum bearer melalui `Authorization: Bearer ...`; token berlaku 30 hari lalu user login ulang. Logout mencabut token aktif. Login dibatasi 5 percobaan per menit per username dan IP. Sanctum personal access token bersifat opaque; jangan parsing isinya sebagai JWT. Konfigurasi CORS dan HTTPS masih perlu ditetapkan sebelum deployment. |
| Tenant | User biasa tidak mengirim pemilih warung. Backend menggunakan identitas user; path admin warung hanya untuk superadmin. `warung.timezone` memakai identifier IANA dan wajib diisi sebelum tenant dapat login. `tanggal_mulai` NULL berarti tanpa batas mulai; `tanggal_berakhir` NULL berarti tanpa batas akhir; tanggal terisi berlaku inklusif. |
| ID | String digit, misalnya `"1001"`; jangan konversi BIGINT menjadi Number. |
| Nominal dan qty | String decimal dua angka pecahan, tanpa pemisah ribuan; contoh `"150000.00"`, `"0.50"`. Format lokal hanya untuk tampilan. Money transaksi mengikuti batas kolom; AggregateMoney laporan dapat melebihi kapasitas satu transaksi dan tetap string eksak. |
| Tanggal | Timestamp disimpan dan dikirim dalam UTC. Tanggal tampilan dan filter periode mengikuti `warung.timezone`. `tanggal` request memakai RFC3339 ber-offset. Zona NULL/invalid menolak akses tenant. |
| Null | JSON `null` berarti tidak diisi/tidak berlaku sesuai schema. `0.00` adalah nominal nol yang diketahui, bukan pengganti null/error. |
| Field input | `additionalProperties: false`: field server seperti total header, warung_id, user_id, nomor, dan status tidak dikirim pada create transaksi. |
| Detail | Endpoint detail/transaksi baru mengembalikan `rincian`. Endpoint daftar hanya header; fetch detail untuk membuka transaksi. |
| Pagination | `page` >= 1, `per_page` 1–100, default 20. Response `meta` memuat page/per_page/total/last_page. Total adalah hasil filter seluruh halaman; hasil kosong memakai data=[], total=0, last_page=1. Halaman di atas last_page memberi data kosong dengan total asli. |
| Sort | Hanya enum pada operasi; arah diikuti id sebagai tie-breaker. Default transaksi `-tanggal` dengan id menurun saat tanggal sama. Nilai tak didukung menghasilkan 422. |
| Periode | `date_from` dan `date_to` wajib untuk laporan. Pada daftar transaksi boleh keduanya kosong; bila salah satu diisi harus berpasangan. Awal <= akhir. |
| Patch | Hanya field yang berubah. Field nullable dikosongkan dengan null; field dihilangkan berarti tidak diubah. Body kosong ditolak. |
| Retry | Mekanisme durable D09 belum ditentukan. Jangan mengarang Idempotency-Key atau retry create otomatis; timeout belum membuktikan transaksi gagal tersimpan. |

User menyetujui tanggung jawab inti D04: superadmin mengelola warung dan owner awal melalui jalur admin; owner mengelola user warungnya; manager mengelola katalog, pembelian, dan laporan; kasir menangani penjualan. Superadmin tidak otomatis bertindak sebagai user tenant. Hak katalog pada implementasi awal: manager dapat membaca dan mengubah; manager dan kasir dapat membaca katalog, dengan kasir hanya melihat kategori/menu aktif. Hak owner di luar pengelolaan user dan hak baca riwayat penjualan masih menunggu rincian D04. Semua batas nominal/rounding tetap kandidat, bukan keputusan produksi. Setiap operasi harus menutup keputusan pemblokir sebelum READY_FOR_FRONTEND.

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
| Profil warung sendiri | GET /warung | getCurrentWarung | DRAFT; akses baca menunggu rincian D04 |
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
| Daftar penjualan | GET /penjualans | listPenjualans | Hak baca dan riwayat kasir menunggu D04 |
| Catat penjualan | POST /penjualans | createPenjualan | kasir inti; hak owner/manager menunggu D04 |
| Detail penjualan | GET /penjualans/{id} | getPenjualan | Hak baca dan riwayat kasir menunggu D04 |
| Daftar pembelian | GET /pembelians | listPembelians | manager inti; hak owner menunggu D04 |
| Catat pembelian | POST /pembelians | createPembelian | manager inti; hak owner menunggu D04 |
| Detail pembelian | GET /pembelians/{id} | getPembelian | manager inti; hak owner menunggu D04 |
| Pendapatan periode | GET /laporan/penjualan | getLaporanPenjualan | manager inti; hak owner menunggu D04 |
| Total pembelian periode | GET /laporan/pembelian | getLaporanPembelian | manager inti; hak owner menunggu D04 |

Semua daftar punya pagination dan allowlist sort. Katalog/user/warung juga menyediakan q dan aktif; menu menyediakan kategori_menu_id. Riwayat penjualan menyediakan status. Laporan tidak dipaginasi: hasilnya satu ringkasan periode.

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

Contoh sintetis: Nasi `15000.00` × `2.00` dan Teh `5000.00` × `1.00`, diskon header `2000.00`, bayar cash `50000.00`. Kandidat D05 menghasilkan subtotal `35000.00`, total `33000.00`, kembalian `17000.00`. Input serta response lengkap ada pada contoh `menu` di OpenAPI. Setiap item harus merujuk ke menu aktif di warung yang sama; backend mengambil nama dan harga jual untuk snapshot.

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

Kandidat D10 menghasilkan subtotal `75000.00` dan `20000.00`, total `95000.00`. Kolom subtotal database selalu terisi; pada request hitungan backend dapat mengisinya. Jika subtotal dikirim bersama qty/harga, kandidat validasi mewajibkan hasil yang sama. Pasangan field yang tidak lengkap menunggu keputusan D10; bentuk ringkas di atas tetap wajib diterima.

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
| 409 CONFLICT | Refresh fakta terkait dan tampilkan konflik; jangan menebak semantik retry D09. |
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
| 0.1.0-draft | DRAFT | Rancangan awal 28 operasi, nominal/ID string, header-rincian, kedua bentuk pembelian, laporan periode, dan daftar keputusan pemblokir. Belum ada operasi live yang diserahkan. |
