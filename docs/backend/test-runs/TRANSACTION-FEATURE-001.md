# TRANSACTION-FEATURE-001

Tanggal: 2026-10-04 (Asia/Jakarta)
Task: BE-001, BE-301–BE-305, BE-401–BE-405
Test IDs: T-ENV-01, sebagian T-SAL/T-BUY/T-RET/T-REP/T-TEN/T-RBAC/T-API-02/04
Commit yang diuji: `4b8f106`
Status run: **PASS**

## Environment

- Docker Compose file: `compose.test.yaml`, project `larissama-backend-test`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Identitas DB: service `test-db`, database `larissama_test`, user `larissama_test`.
- DB ini hanya untuk test: tidak ada host port dan tidak ada volume persisten. Konfigurasi koneksi runner mengarah ke `test-db` / `larissama_test`; koneksi diverifikasi sebelum menjalankan migration/test. Tidak ada DB development atau produksi yang dipakai.
- Konfigurasi Docker test mengizinkan trigger sementara pada MySQL disposable; test menghapus trigger serta fixture-nya.
- Sesudah run, container test DB dan network Compose dihentikan serta dihapus; volume persisten memang tidak dibuat.

## Command dan hasil

Validasi Compose dijalankan dari shell repo. Dua perintah test dijalankan pada service `test-runner` dari `compose.test.yaml`:

```sh
docker compose -f compose.test.yaml config --quiet
php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php tests/Feature/TransactionAtomicityTest.php tests/Feature/IdempotencyConcurrencyTest.php
composer test
```

| Cakupan | Hasil aktual |
| --- | --- |
| Konfigurasi Compose | PASS, exit code 0 |
| Empat file feature transaksi | PASS, 15 tests / 74 assertions, tanpa warning, 2.95 detik |
| Suite `composer test` | PASS, 17 tests / 76 assertions, tanpa warning, 2.98 detik |

Expected: seluruh test selesai tanpa failure/error/warning; test database terisolasi; dua worker concurrency benar-benar overlap dan menghasilkan satu header/detail. Actual memenuhi seluruh expected itu. Pada concurrency test terdapat dua file yang menandai kedua worker melewati barrier, kedua response 201 membawa ID transaksi sama, dan hanya satu pembelian beserta satu rincian tersimpan.

## Status skenario yang disentuh

Status **PARTIAL** berarti assertion nyata dijalankan, tetapi belum memenuhi seluruh kriteria acceptance pada `TEST_PLAN.md`.

| Test ID | Status | Bukti dan batas |
| --- | --- | --- |
| T-ENV-01 | PASS | Versi memenuhi Composer, runner memakai DB test khusus, Compose tervalidasi dan feature/full suite berjalan. |
| T-SAL-01 | PARTIAL | HTTP create menghitung subtotal, diskon, total, bayar/kembalian dan menyimpan snapshot menu; fixture satu rincian, belum seluruh fixture dua rincian canonical. |
| T-SAL-02 | PARTIAL | Menu aktif satu warung diterima dan snapshot diambil dari master; menu tenant lain menghasilkan 422 tanpa header. Menu nonaktif/tidak ada, rincian kosong, field terlarang belum diuji. |
| T-SAL-03 | PARTIAL | Trigger menggagalkan detail kedua; header dan rincian pertama rollback serta pesan SQL tidak bocor. Retry dengan key yang sama sesudah rollback belum diperiksa. |
| T-BUY-01 | PARTIAL | Input `nama_item` + `subtotal` diterima; qty, satuan, dan harga satuan NULL. Pemeriksaan jumlah row secara eksplisit belum lengkap. |
| T-BUY-02 | PARTIAL | Dua rincian rinci dihitung menjadi total 95000.00 dan nilai subtotal tercermin; rounding half-up belum diuji. |
| T-BUY-03 | PARTIAL | Pasangan qty/harga yang tidak lengkap ditolak 422 tanpa write; kasus rincian kosong, nama kosong, dan subtotal mismatch belum diuji. |
| T-BUY-04 | PARTIAL | Trigger menggagalkan rincian kedua; header dan rincian pertama rollback tanpa bocor SQL. Retry pasca-rollback belum diperiksa. |
| T-RET-01 | PASS | Retry berurutan dengan key/payload sama memberi ID yang sama dan satu header, untuk penjualan maupun pembelian. Simulasi koneksi putus sesudah commit tidak dilakukan. |
| T-RET-02 | PASS | Key sama dengan payload berubah menghasilkan 409 `IDEMPOTENCY_KEY_REUSED` tanpa header kedua, pada penjualan dan pembelian. |
| T-RET-03 | PARTIAL | Dua worker HTTP Kernel independen overlap pada pembelian dan hanya membuat satu transaksi. Penjualan, isolasi scope key lintas user/tenant/endpoint, dan keunikan nomor belum diuji. |
| T-REP-01 | PARTIAL | Endpoint pendapatan membatasi data tenant, status selesai dan hari lokal Jakarta; seluruh fixture canonical dan pembeda `total` vs `bayar` belum diuji. |
| T-REP-02 | PARTIAL | Count/sum header 2 / 245000.00 tetap benar dengan tiga rincian dan tenant lain tidak masuk; uji kapasitas agregat besar dan independensi dari pagination belum dilakukan. |
| T-REP-03 | PARTIAL | Batas hari Asia/Jakarta sebelum/tepat/sesudah pergantian hari diuji; timezone IANA lain dan perubahan DST belum diuji. |
| T-TEN-01, T-TEN-03 | PARTIAL | List penjualan dan laporan tenant terisolasi; referensi menu lintas tenant pada create penjualan ditolak. Seluruh endpoint/filter/detail belum diuji. |
| T-RBAC-01 | PARTIAL | Manager tidak dapat create penjualan dan kasir tidak dapat mengakses create/list pembelian. Matriks role penuh belum dijalankan. |
| T-API-02, T-API-04 | PARTIAL | HTTP success, validation/forbidden/conflict, dan error atomik tanpa pesan SQL disentuh; belum ada validator yang membandingkan seluruh response runtime terhadap OpenAPI. |

T-SAL-04/05/06, T-BUY-05/06, T-RET-04, T-REP-04, T-TEN-02, T-API-01/03 dan seluruh test di luar slice ini **NOT_RUN** pada run ini. Tes aplikasi ini tidak menutup keputusan D05/D06/D10/D11, T-DB-01/02/03, seluruh matriks RBAC/tenant, atau API conformance. Seluruh operasi OpenAPI tetap DRAFT.

## Riwayat percobaan selama menyiapkan run

- Run awal menampilkan 11 warning karena `.env.testing` belum tersedia. Ditambahkan file testing kosong/comment-only, lalu slice dan suite penuh dijalankan ulang tanpa warning.
- Percobaan trigger concurrency pertama ditolak konfigurasi binary log MySQL. Flag `--log-bin-trust-function-creators=1` dibatasi pada service DB disposable; test final lulus.
- Percobaan barrier lewat PHP development server tidak membuktikan overlap request secara konsisten. Diganti dua proses PHP independen yang masing-masing menjalankan Laravel HTTP Kernel, file barrier bersama, dan delay trigger MySQL; test final membuktikan keduanya melewati barrier.
- Saat memeriksa rollback migration tenant composite, MySQL menolak penghapusan index yang masih dipakai foreign key. `down()` kini memulihkan index `users.warung_id` sebelum melepas unique composite. Perubahan itu ada pada commit test yang dicatat di atas.

## Tindak lanjut

Lanjutkan keputusan bisnis yang masih DRAFT, perluas validasi/scope/role/report coverage, uji migrasi dan constraint pada MySQL, lalu jalankan conformance OpenAPI atas request/response runtime sebelum mengubah status operasi. G3/G4 dan handoff frontend belum lulus.
