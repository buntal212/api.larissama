# Rencana Integrasi Larissama–UDEV: Pelanggan, Langganan, dan Pembayaran

Status: **MASTER PLAN BERTAHAP — Tahap 1 disetujui; Tahap 2 dan seterusnya pending**
Tanggal: 2026-10-24
Scope master: pendaftaran warung, customer sync, trial, billing UDEV, dan aturan akses Larissama. Pekerjaan awal hanya Tahap 1: customer sync saat pendaftaran.

Implementasi lokal Tahap 1 sudah dibuat pada backend dan form pendaftaran; hasil tes terarah dicatat pada tracker implementasi. Pengiriman live ke Sandbox UDEV masih menunggu base URL/token yang dikelola sebagai secret serta review UDEV. Tahap 2 dan seterusnya tetap pending. Kontrak sumber UDEV yang tersimpan di folder ini menjadi referensi untuk customer sync saja; kontrak tersebut belum mendefinisikan pembuatan invoice, pembayaran, subscription, atau webhook billing.

## Tujuan

Master plan ini menghubungkan pendaftaran customer saat ini dengan rencana billing dan pengaturan akses pada tahap berikutnya. Untuk tahap billing yang masih pending, usulan pembagian otoritasnya adalah UDEV untuk invoice, pembayaran terverifikasi, settlement, dan periode langganan berbayar; Larissama untuk kebijakan trial, masa tenggang, dan hak akses berdasarkan fakta billing terverifikasi.

## Kondisi saat ini

- Pendaftaran sekarang membuat warung `aktif = false`, belum disetujui, dan tanpa tanggal masa aktif. Persetujuan admin mengaktifkan warung selama 30 hari.
- Login dan request tenant bergantung pada status user, status warung, persetujuan, dan tanggal langganan.
- Dokumen customer UDEV yang tersedia mengusulkan upsert `POST /api/v1/integrations/customers` dengan ID eksternal stabil. Dokumen tersebut tidak mencakup invoice atau pembayaran.
- Implementasi UDEV di Larissama belum ditemukan. Endpoint di dokumen perlu dikonfirmasi pada Sandbox dan tidak dianggap sudah tersedia.

## Status tahapan dan alur produk

### Tahap 1 — Customer sync pada pendaftaran (disetujui)

1. Owner mendaftar; pendaftaran warung dan akun owner disimpan di Larissama.
2. Setelah transaksi lokal berhasil commit, backend mengirim satu customer UDEV untuk satu warung dan owner pendaftarnya, tanpa menunggu approval admin atau pembayaran.
3. Field awal: `name` dari `warungs.nama`, `owner_name` dari `users.nama` owner pendaftar, email dari akun owner, dan phone dari data warung.
4. Kirim `status=active` sementara mengikuti arahan UDEV untuk mengirim data dulu lalu direview. Status itu tidak boleh dianggap bukti pembayaran; mapping dikoreksi jika review UDEV meminta perubahan.
5. Pengiriman gagal tidak membatalkan pendaftaran Larissama. Retry otomatis terbatas; retry manual/log detail hanya untuk superadmin atau operator internal berwenang.
6. Form pendaftaran memberi pemberitahuan dan meminta persetujuan penggunaan data owner untuk akun Larissama serta pengiriman field kontak yang diperlukan ke UDEV. Simpan versi dan waktu persetujuan; password tidak dikirim ke UDEV.

### Tahap 2 — Billing dan entitlement (pending)

1. UDEV dan Larissama menetapkan paket, harga, invoice, checkout, pembayaran, settlement, subscription, status final, dan `paid_until`.
2. Tetapkan sumber kebenaran pembayaran dan mekanisme webhook/status API serta rekonsiliasi.
3. Bekukan kebijakan trial tiga hari, akses selama trial, masa tenggang 30 hari, payment-only, dan penguncian/login/pemulihan admin.
4. Implementasikan invoice/payment UI dan access gate hanya setelah kontrak dan keputusan tahap ini disetujui.

**Keputusan tahap 2 yang masih terbuka:** apakah trial memberikan akses aplikasi penuh atau payment-only; batas hitung tiga hari; awal/akhir grace 30 hari; paket dan masa berlangganan; peran UDEV untuk entitlement; serta aturan reaktivasi dan legacy.

## Usulan sumber kebenaran untuk Tahap 2 (pending)

Pembagian berikut adalah rancangan untuk dibahas setelah UDEV menyiapkan alur billing; ini belum menjadi keputusan final.

| Fakta | Otoritas | Peran sistem lain |
| --- | --- | --- |
| Identitas warung, akun owner, kontak utama | Larissama | UDEV menerima salinan customer dan menghubungkannya ke billing. |
| ID customer UDEV | UDEV menerbitkan ID internal; Larissama memiliki ID eksternal stabil | Larissama menyimpan pasangan ID. |
| Paket/harga, invoice, payment attempt, settlement, `paid_at`, refund/chargeback | UDEV | Larissama menggunakan status terverifikasi untuk akses. |
| Periode berbayar dan `paid_until` | UDEV setelah settlement terverifikasi | Larissama menyimpan projection, ID/version event, dan waktu rekonsiliasi. |
| Durasi trial, masa tenggang, pembatasan route, penguncian/pemulihan | Larissama, kecuali kelak memakai entitlement UDEV yang disepakati | Backend menerapkan kebijakan atas fakta UDEV. |
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
4. Sediakan Sandbox, OpenAPI valid, token server-to-server dengan scope minimum, rate limit, dan rotasi/revoke.

### Paket, invoice, pembayaran

1. Jelaskan apakah paket dan harga dikelola UDEV atau dikirim/dipilih Larissama.
2. Sediakan endpoint membuat/membaca invoice, membuat checkout URL, dan membaca status invoice/payment/subscription.
3. Definisikan ID/reference, masa berlaku invoice, nominal/mata uang, fee/pajak, periode yang dibeli, renewal, dan idempotency.
4. Jelaskan perilaku pembayaran ganda, invoice expired, refund, chargeback, dan pembayaran terlambat.
5. Konfirmasi dukungan trial tiga hari dan grace 30 hari; siapa pemilik aturan entitlement jika fitur itu tersedia.

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
- Return URL frontend untuk UX saja; status akses hanya berubah setelah konfirmasi backend.

## Kebijakan akses yang direkomendasikan

| Keadaan | Login | Hak akses |
| --- | --- | --- |
| Trial 3 hari, belum membayar | Boleh | Fitur aplikasi penuh selama trial. |
| Trial/periode berbayar berakhir, masih dalam grace 30 hari | Boleh | Halaman/status pembayaran saja; API bisnis lain ditolak backend. |
| Pembayaran belum terverifikasi | Mengikuti status trial/grace yang berlaku | Redirect, screenshot, atau parameter browser tidak membuka akses. |
| Pembayaran terverifikasi dan periode valid | Boleh | Akses sesuai role/tenant. |
| Grace habis tanpa entitlement valid | Ditolak | Tidak bisa login; owner menghubungi admin untuk pemulihan. |
| Penangguhan manual admin | Sesuai tindakan admin | Pembayaran tidak menghapus suspension manual secara otomatis. |

Keputusan yang perlu ditetapkan: batas waktu dan timezone; apakah trial 72 jam atau tiga tanggal kalender; kapan grace 30 hari dimulai; lama paket; siapa yang boleh membayar; akses superadmin ke tenant terkunci; dan prosedur reaktivasi admin.

## Rencana pelaksanaan bertahap

1. **Tahap 1 — Pendaftaran/customer sync:** finalisasi customer endpoint dan field/status pada review UDEV; siapkan persetujuan data, ID eksternal, status sync, queue, retry, akses log, dan UAT customer. Ini satu-satunya tahap yang disetujui saat ini.
2. **Tahap 2 — Billing:** setelah UDEV mengeluarkan rancangan, sepakati paket/nominal, invoice/checkout, status pembayaran, source of truth, webhook, dan rekonsiliasi. Tahap ini pending.
3. **Tahap 3 — Trial dan access gate:** tetapkan tanggal/aturan trial dan grace, batas akses, lock/login, admin recovery, migrasi data lama, lalu implementasikan backend dan UI. Tahap ini pending.
4. **Tahap 4 — UAT dan rilis:** uji semua slice pada Sandbox, siapkan worker/monitoring/runbook, lalu rencanakan cutover produksi dengan persetujuan dan secret produksi terpisah.

## Keamanan dan penerimaan

- Semua request UDEV berasal dari backend HTTPS; token tidak masuk browser, URL, source, log, atau payload audit.
- Webhook hanya diterima setelah signature dan timestamp diverifikasi. Event disimpan dengan ID unik dan diproses idempoten.
- Jika event ambigu, Larissama membaca status kanonis UDEV; webhook menjadi notifikasi, bukan sumber tak terverifikasi.
- Gangguan UDEV tidak menghapus data Larissama; sinkronisasi dan rekonsiliasi dapat dipulihkan.
- Backend menegakkan role, tenant dan access state; UI payment-only bukan satu-satunya kontrol.
- Gate Tahap 1: customer dibuat satu kali dengan mapping yang disetujui, retry aman, failure log dibatasi, persetujuan data dapat ditelusuri, serta review status/field oleh UDEV.
- Gate billing dan access tetap pending sampai kontrak UDEV serta aturan bisnis tahap 2/3 disepakati dan diuji.

## Batas rencana

Rencana ini tidak mengizinkan secret melalui chat, token di frontend, penghapusan customer UDEV otomatis, atau cutover produksi. Dokumen kontrak sumber `larissama-customer-sync.md` dan `customer-integration.yaml` tetap dipertahankan tanpa perubahan.
