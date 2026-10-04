# DATABASE-MIGRATION-ROLLBACK-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit yang diuji: `fc992b6930a03967fb17b63d54f7dec3b1f27a3a`
- Lingkungan: Docker Compose `compose.test.yaml`, image MySQL 8.0.40, database `larissama_test` tanpa port host dan tanpa volume persisten
- Fresh migration: 14 migration — PASS
- Rollback batch: seluruh 14 migration — PASS
- Verifikasi segera sesudah rollback: tidak ada tabel aplikasi; hanya tabel `migrations` tersisa
- Migrasi ulang: 14 migration — PASS; kedelapan tabel bisnis hadir dan kosong
- `php artisan migrate:status`: seluruh 14 migration berstatus `Ran`
- Cleanup Compose: PASS

## Command

```sh
docker compose -f compose.test.yaml config --quiet
docker compose -f compose.test.yaml up -d --wait test-db
docker compose -f compose.test.yaml run --rm test-runner php artisan migrate:fresh --force
docker compose -f compose.test.yaml run --rm test-runner php artisan migrate:rollback --force
docker compose -f compose.test.yaml exec -T test-db mysql -N -ularissama_test -plarissama-test-only larissama_test -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('warungs','users','kategori_menus','menus','penjualans','penjualan_rincis','pembelians','pembelian_rincis')"
docker compose -f compose.test.yaml run --rm test-runner php artisan migrate --force
docker compose -f compose.test.yaml run --rm test-runner php artisan migrate:status
docker compose -f compose.test.yaml exec -T test-db mysql -N -ularissama_test -plarissama-test-only larissama_test -e "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('warungs','users','kategori_menus','menus','penjualans','penjualan_rincis','pembelians','pembelian_rincis') ORDER BY TABLE_NAME"
docker compose -f compose.test.yaml down --remove-orphans
```

Sesudah rollback, query hitung tabel bisnis menghasilkan `0` dan daftar tabel hanya `migrations`. Sesudah migrasi ulang, query menemukan delapan tabel bisnis; `TABLE_ROWS` masing-masing `0`. Semua migration kembali berstatus `Ran`. Tidak ada trigger atau fixture bisnis dibuat.

## Batas bukti

Ini membuktikan urutan `down()` pada seluruh batch dan migrasi ulang untuk database disposable kosong. Ini tidak membuktikan rollback aman atau pemulihan data pada database berisi data. Guard yang menolak rollback perubahan users saat ada user/token dan perubahan timezone saat ada nilai timezone tetap berlaku; rollback pada schema berisi data bukan prosedur rilis yang disetujui. T-DB-02 untuk upgrade/backfill users lama tetap menunggu keputusan pemetaan identitas ke warung.
