# Rencana Integrasi Larissama–UDEV: Pelanggan, Langganan, dan Pembayaran

Status: **MASTER PLAN BERTAHAP — customer sync, katalog, invoice/checkout/status billing, dan aturan akses lokal disetujui; transaksi pembayaran Sandbox belum diuji**
Tanggal pembaruan: 2026-10-26
Scope master: pendaftaran warung, customer sync, trial, billing UDEV, dan aturan akses Larissama. Tahap tetap diimplementasikan terpisah; persetujuan implementasi billing tidak menyatakan pembayaran Sandbox atau produksi sudah diuji.

Implementasi lokal customer sync dan katalog paket sudah dibuat; live UAT customer masih perlu review UDEV. Pada 2026-10-26 user menyetujui implementasi API referral, invoice/checkout/status dengan Global API Token bersama dan base URL sementara `https://apiudev.udumbara.my.id`. Owner memeriksa status tiap 30 detik selama pembayaran pending. Belum ada webhook yang dipakai. Transaksi pembayaran Sandbox belum diuji. Masa tenggang 30 tanggal lokal memberi owner akses menu pembayaran; akses ditolak mulai hari ke-31.

## Tujuan

Master plan ini menghubungkan pendaftaran customer dengan billing dan pengaturan akses secara bertahap. UDEV menjadi sumber kebenaran untuk paket/harga, invoice, pembayaran terverifikasi, settlement, dan subscription. Larissama menerapkan aturan trial/grace lokal dan memperbarui tanggal masa aktif dari subscription UDEV setelah settlement selesai.

## Kondisi saat ini

- Setelah revisi 2026-10-24, pendaftaran publik langsung membuat warung aktif selama trial tiga tanggal lokal. Provisioning manual superadmin tetap menunggu persetujuan 30 hari; aktivasi trial tiga hari tersedia sebagai aksi terpisah.
- Login dan request tenant bergantung pada status user, status warung, persetujuan, dan tanggal langganan.
- Dokumen customer UDEV yang tersedia mengusulkan upsert `POST /api/v1/integrations/customers` dengan ID eksternal stabil. Dokumen tersebut tidak mencakup invoice atau pembayaran.
- Larissama sudah memiliki customer sync, katalog paket, validasi/penguncian referral, dan implementasi lokal preview/invoice/checkout/status. Pemanggilan live dan kecocokan token/base URL belum diverifikasi; transaksi pembayaran Sandbox tidak dijalankan pada slice ini.

## Status tahapan dan alur produk

### Tahap 1 — Customer sync pada pendaftaran (disetujui)

1. Owner mendaftar; pendaftaran warung dan akun owner disimpan di Larissama.
2. Setelah transaksi lokal berhasil commit, backend mengirim satu customer UDEV untuk satu warung dan owner pendaftarnya, tanpa menunggu approval admin atau pembayaran.
3. Field awal: `name` dari `warungs.nama`, `owner_name` dari `users.nama` owner pendaftar, email dari akun owner, dan phone dari data warung.
4. Kirim `status=active` sementara mengikuti arahan UDEV untuk mengirim data dulu lalu direview. Status itu tidak boleh dianggap bukti pembayaran; mapping dikoreksi jika review UDEV meminta perubahan.
5. Pengiriman gagal tidak membatalkan pendaftaran Larissama. Retry otomatis terbatas; retry manual/log detail hanya untuk superadmin atau operator internal berwenang.
6. Form pendaftaran memberi pemberitahuan dan meminta persetujuan penggunaan data owner untuk akun Larissama serta pengiriman field kontak yang diperlukan ke UDEV. Simpan versi dan waktu persetujuan; password tidak dikirim ke UDEV.

### Tahap 2 — Billing dan entitlement (implementasi disetujui; pembayaran Sandbox pending)

1. Implementasi menggunakan dokumentasi invoice/checkout/status UDEV, Global API Token yang sama, dan base URL sementara `https://apiudev.udumbara.my.id`. Jika endpoint gagal, user mengonfirmasi URL ke UDEV.
2. Invoice jatuh tempo satu hari setelah dibuat mengikuti contoh kontrak. Idempotency key invoice/checkout disimpan dan digunakan ulang pada retry jaringan; percobaan baru memakai key baru hanya setelah payment attempt terminal.
3. Frontend memeriksa status melalui backend setiap 30 detik selama pending dan mengecek kembali saat halaman pembayaran dibuka. Dokumen saat ini tidak mendefinisikan webhook Larissama.
4. Hanya invoice `paid` dengan settlement `completed` yang memicu pembacaan subscription terbaru UDEV dan pembaruan tanggal masa aktif lokal. Tanggal lokal yang lebih panjang tidak dipendekkan.
5. Kode referral dapat divalidasi lebih dahulu; penguncian ke customer dilakukan setelah konfirmasi eksplisit karena UDEV hanya mengizinkan satu kode referral per customer dan kode terkunci tidak dapat diganti. Preview/invoice sesudahnya memakai snapshot referral UDEV.
6. Implementasi endpoint dan UI tidak menjalankan transaksi pembayaran. UAT endpoint dan transaksi pembayaran Sandbox adalah gate berikutnya.

**Yang masih menunggu:** transaksi pembayaran Sandbox, simulasi sukses/gagal/expired, hasil konfirmasi URL bila diperlukan, aturan refund/chargeback, dan cutover produksi. Trial dan grace lokal sudah ditetapkan.

## Sumber kebenaran billing dan akses

| Fakta | Otoritas | Peran sistem lain |
| --- | --- | --- |
| Identitas warung, akun owner, kontak utama | Larissama | UDEV menerima salinan customer dan menghubungkannya ke billing. |
| ID customer UDEV | UDEV menerbitkan ID internal; Larissama memiliki ID eksternal stabil | Larissama menyimpan pasangan ID. |
| Paket/harga, invoice, payment attempt, settlement, `paid_at`, refund/chargeback | UDEV | Larissama menggunakan status terverifikasi untuk akses. |
| Periode berbayar dan `paid_until` | UDEV setelah settlement terverifikasi | Larissama menyimpan projection, ID/version event, dan waktu rekonsiliasi. |
| Durasi trial, masa tenggang, pembatasan route, penguncian/pemulihan | Larissama | Backend menerapkan kebijakan lokal dan memakai tanggal UDEV setelah settlement. |
| Hak login/API dan isolasi tenant | Backend Larissama | Frontend hanya mencerminkan keputusan backend. |

Untuk Tahap 1, `status=active` dikirim sementara untuk customer baru sesuai arahan UDEV dan menunggu review. Status customer UDEV bukan bukti lunas. Status customer, invoice, payment, subscription, trial, dan application access adalah fakta terpisah. Semantik final customer status masih perlu dicatat dari hasil review UDEV.

## Identitas dan pemetaan customer

- `external_customer_id`: ID acak stabil per warung; jangan memakai `warungs.kode` yang dapat berubah.
- `name`/`phone`: dari profil `Warung`.
- `owner_name`/`email`: dari akun owner pendaftar sebagai kontak utama; relasinya harus eksplisit.
- `status`: `active` sementara untuk data pendaftaran baru; review UDEV dapat mengubah mapping.
- `application_id`: ditentukan UDEV dari token, tidak dikirim Larissama.
- Referensi invoice dan event: gunakan ID eksternal yang stabil; jangan percaya ID tenant dari browser.
- Jangan kirim password, token, transaksi penjualan, atau payload billing sensitif sebagai customer data.

## Permintaan kontrak kepada UDEV

### Customer sync

1. Konfirmasi API upsert, read, dan update customer yang terikat pada application token serta idempoten pada pasangan aplikasi + `external_customer_id`.
2. Tetapkan schema field, nullable fields, makna status `active`/`inactive`, response/error codes, dan penanganan konflik dengan customer manual.
3. Konfirmasi customer dapat dibuat saat registrasi sebelum ada pembayaran.
4. Sediakan base URL dan Global API Token server-to-server, rate limit, serta prosedur rotasi/revoke. Konfirmasi beda dokumentasi customer-sync yang masih menjelaskan token app-scoped: route UDEV pada kode saat ini mewajibkan Global API Token.

### Paket, invoice, pembayaran

1. Kontrak terbaru mendokumentasikan `GET /api/v1/integrations/subscription-plans` dengan scope `subscription-plans:read` untuk membaca paket aktif milik aplikasi dari token. Konfirmasi ketersediaannya di Sandbox UDEV untuk aplikasi Larissama.
2. Tetapkan proses pemilihan paket, lalu sediakan endpoint membuat/membaca invoice, membuat checkout URL, dan membaca status invoice/payment/subscription.
3. Definisikan ID/reference, masa berlaku invoice, nominal/mata uang, fee/pajak, periode yang dibeli, renewal, dan idempotency.
4. Jelaskan perilaku pembayaran ganda, invoice expired, refund, chargeback, dan pembayaran terlambat.
5. Konfirmasi apakah UDEV menyediakan entitlement trial/grace; Larissama sementara menerapkan trial tiga tanggal lokal. Sepakati grace 30 hari dan siapa pemilik aturan entitlement berbayar.

### Webhook dan rekonsiliasi

1. Sediakan event untuk invoice dibuat, payment pending/sukses/gagal/kedaluwarsa/refund/chargeback, dan subscription aktif/diperpanjang/berakhir.
2. Dokumentasikan event ID unik, timestamp, signature/HMAC, pencegahan replay, urutan/versi event, retry dan prosedur replay.
3. Sertakan customer/app reference, invoice/payment ID, nominal/mata uang, status final, `paid_at`, dan `paid_until`/periode layanan.
4. Sediakan API status kanonis untuk rekonsiliasi saat webhook terlambat/gagal/tidak berurutan.
5. Jelaskan respons endpoint webhook Larissama, Sandbox event sender, SLA, rate limit, maintenance, dan kontak eskalasi.

## Yang Larissama siapkan untuk UDEV

- Identitas aplikasi dan `external_customer_id` stabil untuk setiap warung.
- Mapping nama/telepon warung dan owner utama, perubahan kontak, serta persetujuan penggunaan data kontak.
- Paket, harga, durasi, mata uang, metode pembayaran, renewal, dan aturan masa aktif yang ditawarkan.
- URL HTTPS webhook staging/produksi; allowlist egress bila UDEV memerlukannya.
- Penyimpanan secret server-side, queue/worker, pemrosesan event idempoten, rekonsiliasi, monitoring dan dukungan operator.
- Satu Global API Token server-to-server pada `UDEV_CUSTOMERS_TOKEN` untuk customer sync, katalog, invoice, checkout, dan status billing; jangan mengirim token ke browser atau menyimpannya bersama frontend. Nilai token tidak dibaca atau dicatat di dokumen.
- Return URL frontend untuk UX saja; status akses hanya berubah setelah konfirmasi backend.

## Kebijakan akses yang direkomendasikan

| Keadaan | Login | Hak akses |
| --- | --- | --- |
| Trial 3 hari, belum membayar | Boleh | Fitur aplikasi penuh selama tiga tanggal lokal inklusif. |
| Trial/periode saat ini berakhir, masih dalam grace 30 hari | Boleh untuk owner | Hanya katalog dan pembayaran dapat diakses; API bisnis lain ditolak backend. |
| Pembayaran belum terverifikasi | Mengikuti status trial/grace yang berlaku | Redirect, screenshot, atau parameter browser tidak membuka akses. |
| Pembayaran terverifikasi dan periode valid | Boleh | Akses sesuai role/tenant. |
| Grace habis tanpa entitlement valid | Ditolak | Tidak bisa login; owner menghubungi admin untuk pemulihan. |
| Penangguhan manual admin | Sesuai tindakan admin | Pembayaran tidak menghapus suspension manual secara otomatis. |

Aturan grace lokal ditetapkan: mulai sehari setelah tanggal akhir, owner dapat login sampai tanggal akhir +30 secara inklusif dan hanya mengakses katalog/pembayaran; setelahnya akses perlu dibuka kembali admin. Batas trial ditetapkan tiga tanggal lokal inklusif agar selaras dengan field tanggal langganan yang ada.

## Rencana pelaksanaan bertahap

1. **Tahap 1 — Pendaftaran/customer sync:** finalisasi customer endpoint dan field/status pada review UDEV; siapkan persetujuan data, ID eksternal, status sync, queue, retry, akses log, dan UAT customer.
2. **Tahap 2 — Katalog dan billing:** implementasi katalog, preview/invoice/checkout/status disetujui; hasil Sandbox dan transaksi pembayaran belum diuji.
3. **Tahap 3 — Trial dan access gate:** trial tiga tanggal lokal, aktivasi superadmin tiga hari, masa tenggang owner 30 tanggal lokal dengan akses katalog/pembayaran saja, serta penolakan login mulai hari ke-31 sudah disetujui. Pemulihan setelah grace tetap lewat admin.
4. **Tahap 4 — UAT dan rilis:** jalankan UAT endpoint lalu transaksi Sandbox setelah arahan user; siapkan worker/monitoring/runbook dan rencanakan cutover produksi dengan persetujuan serta secret produksi tersendiri.

## Keamanan dan penerimaan

- Semua request UDEV berasal dari backend HTTPS; token tidak masuk browser, URL, source, log, atau payload audit.
- Webhook hanya diterima setelah signature dan timestamp diverifikasi. Event disimpan dengan ID unik dan diproses idempoten.
- Jika event ambigu, Larissama membaca status kanonis UDEV; webhook menjadi notifikasi, bukan sumber tak terverifikasi.
- Gangguan UDEV tidak menghapus data Larissama; sinkronisasi dan rekonsiliasi dapat dipulihkan.
- Backend menegakkan role, tenant dan access state; UI payment-only bukan satu-satunya kontrol.
- Gate Tahap 1: customer dibuat satu kali dengan mapping yang disetujui, retry aman, failure log dibatasi, persetujuan data dapat ditelusuri, serta review status/field oleh UDEV.
- Gate billing dan access tetap pending sampai kontrak UDEV serta aturan bisnis tahap 2/3 disepakati dan diuji.

## Batas rencana

Rencana ini tidak mengizinkan secret melalui chat, token di frontend, penghapusan customer UDEV otomatis, atau cutover produksi. Dokumen kontrak Markdown `larissama-customer-sync.md` mengikuti revisi yang diterima 2026-10-25; `customer-integration.yaml` tidak berubah dan tetap hanya mendefinisikan customer API. Kontrak katalog yang tersedia tidak mengubah batas persetujuan Tahap 1.
