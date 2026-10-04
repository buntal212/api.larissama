# AUTH-API-ACCEPTANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-102

Test ID: T-AUTH-01–05; subset T-API-02/04

Commit yang diuji: `4def07131976fd268bd0df9d463f25702ecad06a`

Status run: **PASS untuk cakupan auth yang dicatat di bawah**

## Environment

- Docker Compose project `larissama-backend-test`, file `compose.test.yaml`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Database `larissama_test` disposable; tidak memakai database development atau production.
- Semua fixture dibuat oleh factory dan dibersihkan dengan `RefreshDatabase`.
- Container DB dan Compose network dihapus setelah run.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner sh -lc 'vendor/bin/pint --dirty --format agent'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/AuthApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer test'
docker compose -f compose.test.yaml down --remove-orphans
```

| Check | Hasil |
| --- | --- |
| Pint | PASS |
| `AuthApiTest.php` | PASS, 20 kasus / 230 assertions; tanpa warning |
| Suite `composer test` | PASS, 47 test / 443 assertions; tanpa warning |
| Pembersihan stack | PASS, container DB dan network disposable dihapus |

Satu targeted run awal menampilkan dua kegagalan ketika test mencoba memeriksa expiry/logout melalui beberapa request dalam satu instance aplikasi Laravel. Guard auth yang telah memuat user/token tertahan di antara request simulasi. Test diperbaiki dengan `Auth::forgetGuards()` pada batas request itu; rerun terfokus dan suite penuh lulus. Tidak ada perubahan source auth untuk mengatasi temuan test-harness ini.

## Bukti auth

- Login berhasil mengembalikan token bearer, expiry tepat 30 hari, user, dan warung. `/auth/me` mengembalikan identitas/scope dari bearer saat ini tanpa mengirim ulang token.
- Login diuji pada role superadmin, owner, manager, dan kasir. Superadmin mempunyai `warung_id: null` dan `warung: null`; user tenant mendapat warung yang terkait.
- Username tak dikenal dan password salah memberi pesan 401 generik yang sama. Payload kosong memberi 422 dengan field error; bentuk envelope error dan UUID `request_id` diperiksa.
- User atau warung yang dinonaktifkan setelah token terbit mendapat 403 saat login, `/auth/me`, dan `GET /api/v1/warung`. Login yang ditolak tidak menerbitkan token baru.
- Pada instant UTC yang sama, tanggal akses dihitung menurut hari lokal Jakarta atau New York. Batas mulai/akhir inklusif, masa aktif yang belum mulai/sudah berakhir, kedua tanggal NULL, serta NULL satu sisi diperiksa.
- Token berhasil dipakai sesaat sebelum usia 30 hari dan mendapat 401 tepat pada expiry. Logout memberi 204 tanpa body, menghapus bearer yang dikirim, dan membiarkan token sesi kedua tetap berlaku.
- Password tersimpan sebagai hash dan tidak muncul pada response/log yang ditangkap. Rate limiter menerima lima percobaan untuk username+IP yang sama; percobaan keenam memakai bentuk lowercase-equivalent mendapat 429 `RATE_LIMITED`, sedangkan IP berbeda memulai bucket baru.

## Batas bukti

- Pemeriksaan D13 membandingkan field set response login/me/error secara manual dengan schema terkait; belum memakai validator OpenAPI terhadap setiap response runtime.
- Kebijakan akses ketika `warungs.timezone` NULL/invalid masih provisional D08 dan sengaja tidak dijadikan keputusan final oleh test ini.
- D12 untuk normalisasi identitas/login dan migrasi data user lama masih terbuka.
- Ketiga operasi Auth tetap DRAFT. Run ini tidak menutup T-API-01/02/03/04 penuh, G1, deployment CORS/HTTPS, atau base URL handoff.

SHA-256 `tests/Feature/AuthApiTest.php`: `0e4e4363d968b71509e47fe82232d5d7e2632ff1a0a9bc363591a1aff002ffcc`.
