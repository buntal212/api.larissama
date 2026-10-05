# Runbook Operasi Backend LarisSama

Status: **rancangan operasional, belum dijalankan pada environment production**. MySQL 8.0.40 sudah dipilih sebagai target DB. Host, domain/TLS termination, image/runtime production, jadwal backup, serta target RPO/RTO belum ditetapkan; ganti placeholder berikut dengan nilai dari operator sebelum deployment.

## Batas environment

- [`docker-compose.yml`](../../docker-compose.yml) adalah development lokal: binding hanya ke loopback, kredensial default lokal, source di-mount, `APP_DEBUG=true`, dan proses memakai `php artisan serve`. Jangan gunakan file ini sebagai konfigurasi production.
- `compose.test.yaml` membuat database disposable untuk test. Jangan arahkan ke database development atau production.
- Tim yang tidak memakai Docker dapat menjalankan Laravel memakai PHP 8.3+, Composer, serta MySQL 8.0.40 tersendiri. Docker Desktop Windows dipakai melalui terminal WSL 2 bila dipilih.
- Production membutuhkan web server/runtime PHP yang dikelola operator. Image production saat ini belum disediakan repository.

## Konfigurasi production

Sediakan konfigurasi melalui secret manager/environment deployment, jangan commit `.env` atau menaruh secret pada command line, CI log, image, maupun source.

Wajib ditetapkan sebelum service menerima trafik:

| Variabel/area | Aturan |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Secret acak stabil yang sama pada seluruh instance/release; simpan di secret manager dan jangan generate ulang saat deploy rutin. |
| `APP_URL` | URL HTTPS API production. |
| `DB_CONNECTION` | `mysql`; target MySQL 8.0.40. Kredensial aplikasi memakai user DB dengan privilege minimum yang dibutuhkan runtime. |
| `LARISSAMA_CORS_ALLOWED_ORIGINS` | Daftar origin frontend HTTPS yang tepat, dipisahkan koma; tanpa wildcard. |
| `LOG_LEVEL` | Pilih level production dan pastikan log tidak merekam bearer token, password, atau payload sensitif. |
| HTTPS/proxy | TLS wajib. Jika TLS selesai pada load balancer/proxy, konfigurasi trusted proxy dan forwarded headers secara eksplisit pada environment tersebut. |

Token Sanctum berlaku 30 hari. Pastikan jam host tersinkronisasi UTC; database/runtime menyimpan timestamp UTC, sedangkan laporan memakai timezone IANA milik warung.

## Prosedur rilis dan migration

1. Pilih commit/tag immutable yang sudah lulus workflow `Backend CI`. Catat versi aplikasi, commit, versi PHP, dan versi MySQL.
2. Tinjau migration baru dan status migration pada database target. Dilarang memakai `migrate:fresh`, `db:wipe`, atau seeding fixture untuk deployment.
3. Buat backup database mengikuti bagian Backup. Pastikan file ada, ukurannya masuk akal, checksum dicatat, dan hasil restore terakhir pada database terisolasi tersedia.
4. Siapkan release baru secara terpisah. Pasang dependency dari lock file: `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`.
5. Pasang environment production melalui mekanisme rahasia deployment. Pastikan `APP_DEBUG=false`, `APP_KEY` stabil, koneksi DB benar, CORS origin tepat, dan direktori `storage` serta `bootstrap/cache` dapat ditulis oleh user runtime.
6. Untuk deployment yang membutuhkan downtime, aktifkan maintenance mode pada release aktif dengan `php artisan down --retry=60` dan pastikan load balancer tidak mengirim write baru. Untuk rolling deployment, jalankan hanya migration yang kompatibel dengan versi aplikasi lama dan baru; repository belum menetapkan strategi zero-downtime khusus.
7. Jalankan `php artisan migrate --force` satu kali dari release yang dipilih, memakai satu deployment job. Hentikan rilis bila migration gagal; jangan jalankan rollback otomatis.
8. Buat cache konfigurasi/rute yang didukung aplikasi: `php artisan optimize`. Restart/reload PHP workers dan service web sesuai supervisor/platform deployment.
9. Verifikasi `GET /up`, status container/service, koneksi DB dari proses aplikasi, serta smoke request API melalui HTTPS. Pastikan login, bearer auth, operasi tenant, dan laporan dapat dipanggil pada tenant sintetis yang disetujui tanpa mencatat token.
10. Nonaktifkan maintenance mode dengan `php artisan up` hanya setelah pemeriksaan lulus. Catat waktu, commit, migration, hasil smoke, dan operator pada deployment log.

Laravel `/up` hanya membuktikan aplikasi berhasil boot secara normal. Tambahkan pemeriksaan DB pada platform monitoring bila readiness DB diperlukan; jangan menganggap HTTP 200 `/up` sebagai bukti backup, koneksi DB, atau seluruh dependency sehat.

## Bootstrap superadmin pertama

Pada instalasi fresh, jalankan `php artisan app:bootstrap-superadmin` sesudah migration berhasil. Command meminta nama, username, email opsional, dan password melalui prompt interaktif; password tidak diberikan sebagai argumen atau dicatat pada output. Username/email harus lowercase, password minimal 8 karakter dan dikonfirmasi.

Command hanya berjalan jika tabel `users` kosong, membuat akun aktif `superadmin` dengan `warung_id = NULL`, dan memakai MySQL advisory lock agar dua proses bootstrap tidak membuat akun bersamaan. Jika database sudah berisi user apa pun, command berhenti tanpa perubahan. Jangan pakai `db:seed` untuk membuat akun akses; DatabaseSeeder tidak membuat user default. Simpan credential melalui prosedur secret organisasi setelah akun dibuat.

## Backup MySQL

Backup memuat data pribadi, hash password, personal access token, dan transaksi. Simpan terenkripsi dengan akses terbatas, retensi yang ditetapkan organisasi, dan salinan terpisah dari host database.

1. Gunakan account backup dengan privilege sesuai MySQL 8.0.40 dan secret file yang hanya dapat dibaca operator. Jangan masukkan password dalam argumen `-p...` atau shell history.
2. Hindari DDL selama dump. `--single-transaction` memberi snapshot konsisten untuk tabel InnoDB; ia tidak menjamin konsistensi tabel non-transaksional dan dapat terganggu DDL konkuren. Repository memakai tabel InnoDB.
3. Contoh berikut mengasumsikan secret file berformat option file MySQL dengan permission `0600`. Ganti nilai contoh host/path/nama database sesuai platform:

```sh
BACKUP_OPTION_FILE=/run/secrets/larissama-mysql-backup.cnf
DB_HOST=mysql.internal.example
DB_NAME=larissama
BACKUP_FILE=/var/backups/larissama-$(date -u +%Y%m%dT%H%M%SZ)
umask 077
set -o pipefail
mysqldump --defaults-extra-file="$BACKUP_OPTION_FILE" \
  --host="$DB_HOST" --single-transaction --skip-lock-tables --no-tablespaces \
  --routines --triggers --events --hex-blob --default-character-set=utf8mb4 \
  "$DB_NAME" | gzip -c > "${BACKUP_FILE}.sql.gz"
sha256sum "${BACKUP_FILE}.sql.gz" > "${BACKUP_FILE}.sql.gz.sha256"
```

4. Periksa exit code pipeline (`set -o pipefail` pada shell Bash), ukuran file, checksum, timestamp, nama DB, commit aplikasi, dan versi MySQL. Enkripsi sebelum mengirim/menyimpan off-host.
5. Tentukan frekuensi serta retensi dari target RPO/RTO yang disetujui. Belum ada kebijakan backup atau binary log point-in-time recovery yang ditetapkan dalam repository.

## Restore drill dan pemulihan

1. Pilih backup dan verifikasi checksum. Siapkan instance MySQL 8.0.40 serta database **baru dan terisolasi**, dengan hak akses terbatas. Jangan berlatih restore ke DB production.
2. Decompress ke stream dan restore ke target kosong memakai credential file yang dibatasi:

```sh
RESTORE_OPTION_FILE=/run/secrets/larissama-mysql-restore.cnf
RESTORE_DB_HOST=mysql-drill.internal.example
EMPTY_RESTORE_DB=larissama_restore_drill
gzip -dc "${BACKUP_FILE}.sql.gz" | mysql --defaults-extra-file="$RESTORE_OPTION_FILE" \
  --host="$RESTORE_DB_HOST" "$EMPTY_RESTORE_DB"
```

3. Periksa tabel, jumlah row transaksi, foreign key, timezone, dan `migrations`. Jalankan aplikasi versi commit sumber backup dengan `APP_DEBUG=false` terhadap DB drill.
4. Lakukan read-only smoke: `/up`, autentikasi dengan akun drill, daftar/detail tenant yang disetujui, dan laporan pada periode diketahui. Jangan membuat transaksi nyata saat latihan.
5. Catat durasi restore, ukuran dump, versi source/target, pemeriksaan yang dilakukan, dan gap. Ulangi berdasarkan frekuensi drill yang ditetapkan operator.

Saat incident production, isolasi write terlebih dahulu dan simpan salinan kondisi DB saat ini sebelum pemulihan. Restore ke DB baru, verifikasi, lalu alihkan konfigurasi aplikasi melalui prosedur change yang disetujui. Restore in-place, penghapusan volume, atau pengembalian backup lama yang menghilangkan transaksi terbaru memerlukan keputusan incident owner setelah dampak dan kehilangan data dipahami. Jangan menjalankan `migrate:rollback` atau memulihkan backup lama otomatis sebagai respons migration gagal.

## Rollback release dan diagnosis

- Rollback kode ke release sebelumnya hanya aman jika migration kompatibel dengan versi aplikasi tersebut. Migration database dan code rollback dinilai terpisah.
- Jangan menjalankan `migrate:rollback` otomatis: migration mungkin tidak dapat mengembalikan data yang telah ditulis atau aplikasi versi lama mungkin tidak kompatibel dengan schema.
- Untuk gagal deploy, catat commit, exception/request ID, status migration, healthcheck, konektivitas MySQL dari runtime, dan waktu UTC. Redaksi authorization header, token, password, data pribadi, serta payload transaksi.
- `/up` gagal: periksa log app dan boot/runtime. `/up` lulus namun request DB gagal: periksa konfigurasi efektif tanpa mencetak secret, DNS/rute ke MySQL, grant user aplikasi, serta status/connection limit database.
- Error yang dilaporkan frontend perlu menyertakan `request_id`, `operationId`, status, waktu UTC, dan payload tersamarkan; jangan kirim bearer token.

## Keputusan operasional yang masih diperlukan

BE-502 belum dapat ditandai DONE sampai pemilik deployment menentukan host/platform dan release runtime, domain/TLS/proxy, secret store, strategi downtime atau rolling, backup schedule/retention/encryption, target RPO/RTO, monitoring/alerting, serta penerima restore drill. Langkah di atas adalah baseline runbook; belum ada deployment atau restore production yang diklaim telah diuji.

## Referensi upstream

- [Laravel 13 deployment](https://laravel.com/docs/13.x/deployment)
- [MySQL 8.0 `mysqldump`](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html)
