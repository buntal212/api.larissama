# Rencana Integrasi Larissama–UDEV: Pelanggan, Langganan, dan Pembayaran

Status: **DRAFT — menunggu keputusan bisnis dan kontrak teknis UDEV**
Tanggal: 2026-10-24
Scope: pendaftaran warung Larissama, sinkronisasi pelanggan, trial, invoice/pembayaran UDEV, dan pembatasan akses Larissama.

Dokumen ini adalah rencana, bukan bukti implementasi. Kontrak sumber UDEV yang tersimpan di folder ini menjadi referensi untuk customer sync saja; kontrak tersebut belum mendefinisikan pembuatan invoice, pembayaran, subscription, atau webhook billing.

## Tujuan

Saat owner mendaftarkan warung, Larissama membuat akun dan warung, memulai trial tiga hari, lalu mengirim data customer ke UDEV. UDEV menjadi otoritas atas invoice, pembayaran terverifikasi, settlement, dan periode langganan berbayar. Larissama menerapkan kebijakan trial, masa tenggang, dan hak akses berdasarkan fakta billing yang terverifikasi.

## Kondisi saat ini

- Pendaftaran sekarang membuat warung `aktif = false`, belum disetujui, dan tanpa tanggal masa aktif. Persetujuan admin mengaktifkan warung selama 30 hari.
- Login dan request tenant bergantung pada status user, status warung, persetujuan, dan tanggal langganan.
- Dokumen customer UDEV yang tersedia mengusulkan upsert `POST /api/v1/integrations/customers` dengan ID eksternal stabil. Dokumen tersebut tidak mencakup invoice atau pembayaran.
- Implementasi UDEV di Larissama belum ditemukan. Endpoint di dokumen perlu dikonfirmasi pada Sandbox dan tidak dianggap sudah tersedia.

## Alur produk rancangan

Asumsi untuk dibahas sebelum implementasi:

1. Owner mendaftar; backend membuat akun owner dan warung tanpa menunggu persetujuan admin.
2. Trial tiga hari dimulai pada pendaftaran. Rekomendasi: trial memberi akses fitur aplikasi penuh.
3. Setelah pendaftaran commit, Larissama mengantrekan customer sync ke UDEV dengan ID eksternal stabil.
4. Menu pembayaran di Larissama meminta invoice/checkout melalui backend. Token UDEV tidak pernah dikirim ke browser.
5. Setelah trial atau periode berbayar habis tanpa pembayaran baru, warung masuk masa tenggang 30 hari. Rekomendasi: selama tenggang pengguna boleh login ke halaman/status pembayaran saja; API bisnis lainnya ditolak backend.
6. UDEV memverifikasi pembayaran dan memberi tahu Larissama melalui webhook server-to-server bertanda tangan. Browser redirect bukan bukti pembayaran.
7. Pembayaran terverifikasi memperbarui masa langganan dan memulihkan akses. Jika masa tenggang habis tanpa pembayaran terverifikasi, login ditolak dan owner diminta menghubungi admin.

**Ambiguitas:** kalimat “selama belum membayar hanya menu pembayaran yang aktif” bisa berarti pembatasan mulai sejak pendaftaran. Itu membuat trial tidak memberi akses operasional. Rekomendasi rencana ini: akses penuh selama trial, lalu payment-only selama tenggang. Keputusan final perlu dicatat.

## Sumber kebenaran

| Fakta | Otoritas | Peran sistem lain |
| --- | --- | --- |
| Identitas warung, akun owner, kontak utama | Larissama | UDEV menerima salinan customer dan menghubungkannya ke billing. |
| ID customer UDEV | UDEV menerbitkan ID internal; Larissama memiliki ID eksternal stabil | Larissama menyimpan pasangan ID. |
| Paket/harga, invoice, payment attempt, settlement, `paid_at`, refund/chargeback | UDEV | Larissama menggunakan status terverifikasi untuk akses. |
| Periode berbayar dan `paid_until` | UDEV setelah settlement terverifikasi | Larissama menyimpan projection, ID/version event, dan waktu rekonsiliasi. |
| Durasi trial, masa tenggang, pembatasan route, penguncian/pemulihan | Larissama, kecuali kelak memakai entitlement UDEV yang disepakati | Backend menerapkan kebijakan atas fakta UDEV. |
| Hak login/API dan isolasi tenant | Backend Larissama | Frontend hanya mencerminkan keputusan backend. |

Status customer UDEV `active`/`inactive` tidak boleh dianggap bukti lunas. Status customer, invoice, payment, subscription, dan application access adalah fakta terpisah.

## Identitas dan pemetaan customer

- `external_customer_id`: ID acak stabil per warung; jangan memakai `warungs.kode` yang dapat berubah.
- `name`/`phone`: dari profil `Warung`.
- `owner_name`/`email`: dari owner pendaftar sebagai kontak utama; relasinya harus eksplisit.
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

## Tahapan setelah keputusan dan kontrak siap

1. Bekukan kebijakan trial/grace, paket/harga, source of truth, customer API, invoice/payment API, dan webhook.
2. Inventarisasi warung legacy, status approval/langganan dan owner. Buat dry-run sebelum mengubah data; jangan menebak tanggal/owner.
3. Implementasikan pendaftaran: buat owner/warung/trial secara atomik dan antrekan customer sync setelah commit.
4. Implementasikan invoice/payment vertical slice: backend UDEV client, API Larissama, UI paket/status/checkout tanpa token UDEV di browser.
5. Implementasikan webhook: verifikasi signature, deduplikasi event, projection status, rekonsiliasi, dan audit aman.
6. Implementasikan access gate di login dan semua API tenant; payment-only selama grace; login ditolak setelah grace.
7. Jalankan Sandbox UAT untuk customer, invoice, payment, event duplikat/tidak berurutan, retry, refund, webhook hilang, batas waktu, dan tenant isolation. Cutover produksi perlu secret serta persetujuan produksi tersendiri.

## Keamanan dan penerimaan

- Semua request UDEV berasal dari backend HTTPS; token tidak masuk browser, URL, source, log, atau payload audit.
- Webhook hanya diterima setelah signature dan timestamp diverifikasi. Event disimpan dengan ID unik dan diproses idempoten.
- Jika event ambigu, Larissama membaca status kanonis UDEV; webhook menjadi notifikasi, bukan sumber tak terverifikasi.
- Gangguan UDEV tidak menghapus data Larissama; sinkronisasi dan rekonsiliasi dapat dipulihkan.
- Backend menegakkan role, tenant dan access state; UI payment-only bukan satu-satunya kontrol.
- Penerimaan mensyaratkan satu customer tanpa duplikasi, invoice/payment cocok kontrak Sandbox, pembayaran terverifikasi saja memperpanjang akses, status akses tepat di batas waktu, serta dry-run legacy yang dapat ditinjau.

## Batas rencana

Rencana ini tidak mengizinkan secret melalui chat, token di frontend, penghapusan customer UDEV otomatis, atau cutover produksi. Dokumen kontrak sumber `larissama-customer-sync.md` dan `customer-integration.yaml` tetap dipertahankan tanpa perubahan.
