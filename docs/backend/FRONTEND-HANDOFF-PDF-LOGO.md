# Handoff Frontend: Logo pada Cetak dan PDF

## Kebutuhan

Frontend menampilkan kop warung pada cetak dan unduh PDF laporan, riwayat penjualan, dan riwayat pembelian. Tombol Cetak menggunakan PDF yang sama dengan tombol Download PDF. Nota POS/kasir tidak termasuk perubahan ini.

Kop membutuhkan gambar logo dari URL `warung.logo`, nama warung, alamat, dan telepon. Logo tampil di elemen `<img>` frontend, tetapi belum dapat disematkan ke PDF hasil unduh.

## Temuan

Resource warung membentuk URL logo dari public storage. PDF frontend memuat gambar dengan mode CORS lalu mengubahnya ke data URL agar dapat dimasukkan ke PDF. Jika respons gambar lintas origin tidak memiliki header `Access-Control-Allow-Origin`, browser dapat menampilkan gambar pada `<img>` tetapi menolak serialisasi canvas (`toDataURL`). Akibatnya logo tidak ikut ke PDF.

Konfigurasi CORS yang diperiksa saat handoff hanya mencakup `api/*` dan `sanctum/csrf-cookie`; URL file `/storage/...` tidak tercakup. Karena file storage bisa disajikan langsung oleh web server, pastikan header ditambahkan pada lapisan yang benar, bukan hanya pada middleware Laravel.

## Permintaan untuk backend

- Periksa respons `GET` untuk URL `warung.logo` dari origin frontend yang sedang dipakai.
- Izinkan origin frontend development dan production yang benar pada respons file logo, dengan `Access-Control-Allow-Origin` yang cocok.
- Pastikan header CORS berlaku pada URL public storage sebenarnya, termasuk saat ada redirect.
- Tidak perlu mengubah payload API atau menambah endpoint selama URL logo yang sekarang tetap dapat digunakan.

## Acceptance

- Dari origin frontend, gambar logo dapat dimuat dengan `crossOrigin = 'anonymous'` dan diserialisasi ke canvas tanpa error CORS.
- Download PDF laporan, riwayat penjualan, dan riwayat pembelian menyertakan logo warung.
- Cetak dan download menghasilkan isi dokumen yang sama; total tetap di bagian bawah.
- Cetak nota POS/kasir tidak berubah.

Frontend tidak mengubah kode backend. Setelah konfigurasi respons logo siap, integrasi dapat diverifikasi dari browser.
