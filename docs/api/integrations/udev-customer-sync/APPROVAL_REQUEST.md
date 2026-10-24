# Permintaan Keputusan Integrasi Billing Larissama–UDEV

Status: **menunggu keputusan bisnis dan kontrak teknis UDEV**
Rencana: [INTEGRATION_PLAN.md](INTEGRATION_PLAN.md)

## Mengapa permintaan lama direvisi

Permintaan sebelumnya hanya mencakup sinkronisasi customer setelah persetujuan admin dan secara eksplisit mengecualikan perubahan billing/UI. Alur produk yang baru meminta customer sync segera setelah registrasi, trial tiga hari, invoice/pembayaran melalui UDEV, masa tenggang 30 hari, pembatasan akses, dan penguncian login. Karena itu keputusan serta scope lama tidak cukup untuk mengotorisasi rancangan baru.

Kontrak sumber UDEV yang tersedia hanya mendeskripsikan customer sync. Endpoint invoice, pembayaran, subscription, webhook status, dan rekonsiliasi masih perlu diminta serta disepakati dengan tim UDEV. Tidak ada asumsi bahwa endpoint tersebut sudah tersedia.

## Keputusan bisnis yang perlu disetujui Larissama

1. **Pendaftaran tanpa approval admin:** apakah owner/warung langsung dibuat aktif saat registrasi? Usulan: ya, mulai dari trial.
2. **Akses trial:** apakah tiga hari trial memberi akses penuh, lalu payment-only selama masa tenggang? Usulan: ya. Jika payment-only berlaku sejak mendaftar, trial tidak memberi akses operasional.
3. **Hitungan trial:** apakah tiga hari berarti 72 jam sejak waktu registrasi atau tiga tanggal kalender menurut timezone warung? Pilih satu definisi batas waktu.
4. **Awal masa tenggang:** apakah 30 hari dihitung sejak trial berakhir dan kemudian sejak setiap `paid_until` berakhir? Usulan: ya; selama tenggang, hanya pembayaran yang dapat dibuka.
5. **Setelah tenggang:** apakah login diblokir dan owner harus menghubungi admin untuk pemulihan? Usulan: ya. Tetapkan apakah ada jalur bayar/pemulihan mandiri setelah akun terkunci.
6. **Paket berbayar:** tetapkan harga, durasi layanan, mata uang, metode pembayaran, masa berlaku invoice, renewal otomatis/manual, dan aturan pembayaran setelah periode berakhir.
7. **Pemilik status billing:** apakah UDEV menjadi sumber kebenaran invoice, pembayaran, settlement, refund/chargeback, serta periode berbayar; Larissama menyimpan projection untuk access gate? Usulan: ya.
8. **Pemilik trial dan access gate:** apakah Larissama menjadi sumber kebenaran durasi trial, masa tenggang, role/route yang boleh diakses, dan penguncian? Usulan: ya, kecuali UDEV menawarkan entitlement lengkap yang disepakati.
9. **Customer dan kontak:** apakah satu `Warung` menjadi satu customer UDEV, dibuat ketika registrasi; owner pendaftar menjadi kontak utama? Usulan: ya, dengan relasi owner yang eksplisit.
10. **Identitas dan status customer:** apakah Larissama membuat ID eksternal stabil per warung dan memisahkan status customer `active/inactive` dari status payment/subscription? Usulan: ya.
11. **Data legacy:** tetapkan perlakuan untuk warung lama yang menunggu approval, sedang aktif, kedaluwarsa, atau owner/contact-nya tidak jelas. Usulan: dry-run dan mapping eksplisit sebelum backfill; jangan menebak tanggal atau owner.
12. **Penangguhan dan override admin:** tetapkan apakah suspension manual tetap mengalahkan pembayaran, serta siapa yang boleh memulihkan akun dan bagaimana tindakannya diaudit.

## Permintaan teknis kepada UDEV

Minta UDEV memberikan/menyetujui:

- Sandbox dan spesifikasi OpenAPI terkini untuk customer upsert/read/update, termasuk idempotency dan customer conflict.
- API katalog paket/harga atau ketentuan bahwa paket/nominal disuplai Larissama.
- API create/read invoice, checkout URL, invoice/payment/subscription status, expiry, renewal, refund/chargeback, dan idempotency.
- Arti setiap status dan fakta final yang dikembalikan, termasuk `paid_at`, nominal/mata uang, periode layanan, serta `paid_until`.
- Webhook pembayaran/subscription dengan event ID, signature/HMAC, timestamp, replay protection, urutan/version, retry, dan prosedur replay.
- API status kanonis untuk rekonsiliasi event webhook yang terlambat, hilang, atau tidak berurutan.
- Jawaban apakah trial 3 hari/grace 30 hari didukung UDEV atau tetap menjadi kebijakan access Larissama.
- Token server-to-server dengan scope minimum dan instruksi rotasi/revoke; jangan kirim secret lewat chat atau ke frontend.
- Rate limit, timeout, SLA/maintenance, test data/metode Sandbox, error response, dan kontak eskalasi.

## Yang Larissama siapkan untuk UDEV

- ID aplikasi serta ID customer eksternal stabil per warung.
- Mapping nama/telepon warung dan nama/email owner utama; aturan perubahan kontak dan persetujuan pemrosesan data.
- Keputusan paket, harga, mata uang, durasi, metode pembayaran, renewal, trial dan grace.
- URL HTTPS untuk webhook Sandbox/produksi serta egress allowlist bila dibutuhkan.
- Backend client dan secret storage, queue/worker, pemrosesan webhook idempoten, rekonsiliasi, monitoring, dan operator support.
- Return URL untuk pengalaman pengguna; Larissama tidak akan memperlakukan redirect browser sebagai bukti pembayaran.

## Batas otorisasi dan scope

Persetujuan keputusan bisnis di atas memberi dasar untuk memfinalisasi kontrak dan implementasi Sandbox dalam scope: backend customer sync, invoice/payment API, webhook/reconciliation, migration/status entitlement, access gate, dan UI pembayaran Larissama.

Persetujuan ini **tidak** mengizinkan token dibagikan lewat chat atau frontend, penghapusan customer UDEV otomatis, atau cutover produksi. Cutover produksi memerlukan kredensial produksi, kesiapan worker/webhook/monitoring, hasil UAT, dan persetujuan produksi tersendiri. Integrasi live tidak boleh diklaim lulus hanya dari panel admin internal UDEV.

## Format keputusan

Balas dengan nomor yang disetujui/koreksi, terutama keputusan 2–8 dan 11–12. Jawaban dapat memakai format:

```text
1. SETUJU
2. SETUJU / KOREKSI:
3. 72 jam / tanggal kalender
4. SETUJU / KOREKSI:
5. SETUJU / KOREKSI:
6. Paket:
7. SETUJU / KOREKSI:
8. SETUJU / KOREKSI:
9. SETUJU / KOREKSI:
10. SETUJU / KOREKSI:
11. Kebijakan legacy:
12. Kebijakan admin:
Catatan:
```

Implementasi berhenti pada tahap perencanaan/kontrak sampai keputusan yang memengaruhi uang, masa aktif, akses, dan pemulihan ditetapkan serta kemampuan API UDEV dikonfirmasi.
