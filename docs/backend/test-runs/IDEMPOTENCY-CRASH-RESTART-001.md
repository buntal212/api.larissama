# IDEMPOTENCY-CRASH-RESTART-001

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Task/test: BE-304/BE-404, T-RET-04, D09
- Commit test yang diuji: `a2dcf2db5ce2cd04ea569da5ba5a419f86e21f89`
- `tests/Feature/IdempotencyConcurrencyTest.php` SHA-256: `baa546855e764d788217dbd7760741a3ef4e95b8a9a90ad8f8d39b6279d90589`
- `tests/Support/concurrent-http-worker.php` SHA-256: `4399a26214f1e492c2a96fda60cb754339ba6c9ba4ef7a30530e8ea84400d3b8`
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 pada Compose test disposable, DB `larissama_test`

## Hasil

**PASS untuk rollback sebelum commit dan replay lintas worker setelah commit pada penjualan serta pembelian.** Pint lulus. `IdempotencyConcurrencyTest` lulus 13 test / 155 assertions; suite penuh lulus 250 test / 26452 assertions.

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/IdempotencyConcurrencyTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml exec -T test-db mysql -N -ularissama_test -plarissama-test-only larissama_test -e "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()"
docker compose -f compose.test.yaml down --remove-orphans
```

### Worker mati sebelum commit

Test memasang trigger MySQL sementara pada insert detail. Trigger memperoleh named lock lalu menahan statement; parent melihat lock setelah header dibuat dan sebelum detail insert selesai. Parent mengirim SIGKILL pada proses PHP. Setelah koneksi worker melepas lock, query memastikan tidak ada header atau detail parsial dengan fixture tenant tersebut. Trigger dilepas, lalu key/payload yang sama dikirim dari worker baru. Kedua endpoint memberi HTTP 201, resource cocok dengan row tersimpan, dan hanya satu header/detail tercatat.

### Respons hilang setelah commit

Worker test menjalankan request normal sampai Laravel Kernel membentuk HTTP 201, lalu keluar kode 97 sebelum menulis respons kepada pemanggil. Test membuktikan transaksi sudah tersimpan, lalu mengulang key/payload sama pada proses PHP dengan PID berbeda. Retry memberi HTTP 201 dengan ID dan nomor transaksi yang sama; jumlah header/detail tetap satu pada tiap endpoint.

Trigger sementara telah dipastikan tidak tersisa dalam schema test dan Compose dibersihkan. Simulasi membuktikan pemulihan transaksi untuk worker/database connection yang putus pada MySQL 8.0.40; bukan uji restart server database atau failover.

## Batas bukti

Replay diuji saat header transaksi masih tersimpan. Masa retensi setelah header dihapus belum ditetapkan; idempotency key unik tetap mengikuti keberadaan row header sesuai implementasi sekarang. Variasi tenant/endpoint yang independen dari actor tidak dapat dibuat lewat role dan FK yang berlaku. Semua operasi transaksi masih `DRAFT` sampai keputusan terkait serta conformance kontrak selesai.
