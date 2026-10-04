# USER-EMAIL-UNIQUENESS-001

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Task/test: BE-104, T-ADM-02, D12
- Commit yang diuji: `46f0e3bc38dfa9325603dfcc33aac88570e0c2fc`
- File test: `tests/Feature/UserEmailUniquenessApiTest.php`
- SHA-256: `622c4ccbec0c6edc799f618d7b17e4b686a0a4374c54af19cb485e5630433f69`
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 di Compose test disposable, DB `larissama_test`

## Hasil

**PASS.** Pint lulus. Test terarah menghasilkan 4 test / 242 assertions; suite penuh menghasilkan 237 test / 26301 assertions.

Command test:

```sh
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/UserEmailUniquenessApiTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

Empat test memverifikasi:

1. Owner tidak dapat membuat user tenant A dengan alamat email yang sama persis dengan user tenant B. API mengembalikan 422, field `email` berisi error, dan user baru tidak tersimpan.
2. Owner tidak dapat mengubah email user tenant A menjadi alamat email yang sama persis dengan milik tenant B. API mengembalikan 422, field `email` berisi error, dan nilai semula tidak berubah.
3. Email eksplisit `null` maupun email yang tidak dikirim diterima saat create; response dan database menyimpan `email = null`.
4. MySQL menolak duplikasi email identik lintas warung melalui constraint `users_email_unique` dan menerima lebih dari satu nilai `NULL`.

Request/response API sukses dan error pada skenario terpilih cocok dengan schema OpenAPI yang diperiksa test. Compose test dihentikan dan resource disposable dihapus.

## Batas bukti

Run hanya membuktikan duplikasi dengan ejaan/case identik. Perilaku variasi huruf, normalisasi, lowercase, dan pemetaan field identitas lama tidak diuji; keputusan tersebut tetap terbuka pada D12. Tidak ada perubahan migration, schema, validasi runtime, maupun dependency. Operasi administrasi user tetap DRAFT karena matriks role/contract dan gate M1 lainnya belum selesai.
