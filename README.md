<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Menjalankan backend lokal

Docker adalah pilihan untuk development, bukan syarat semua anggota tim. Untuk menggunakan Docker Desktop yang terpasang di Windows, aktifkan integrasi WSL 2 lalu jalankan perintah dari terminal WSL di direktori repo ini. Compose menyediakan PHP 8.3 dan MySQL 8.0.40 untuk development lokal; versi MySQL ini bukan keputusan versi database produksi.

```bash
docker compose up --build -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan app:bootstrap-superadmin
```

Setelah migrasi, buat superadmin pertama lewat prompt interaktif; command hanya menerima tabel `users` kosong dan tidak menampilkan password. API server lokal tersedia di `http://localhost:8010`; health endpoint-nya `http://localhost:8010/up`. Status app menjadi `healthy` setelah endpoint tersebut berhasil dijawab; gunakan `docker compose ps` untuk melihat status. MySQL dapat diakses dari host pada port `33309`. Compose memasang dependency Composer dan membuat APP_KEY development sementara saat container app mulai jika `LARISSAMA_APP_KEY` tidak diisi. Untuk menghentikan layanan tanpa menghapus data database, jalankan `docker compose down` (hindari opsi `-v` jika volume database ingin dipertahankan). Kredensial default Compose hanya untuk database development lokal. Port dan kredensial dapat diubah lewat variabel `LARISSAMA_API_PORT`, `LARISSAMA_DB_PORT`, `LARISSAMA_DB_DATABASE`, `LARISSAMA_DB_USERNAME`, `LARISSAMA_DB_PASSWORD`, `LARISSAMA_DB_ROOT_PASSWORD`, dan `LARISSAMA_CORS_ALLOWED_ORIGINS`; ID user/group container default `1000` dan dapat diubah dengan `LARISSAMA_UID` serta `LARISSAMA_GID`.

## Test dan pemeriksaan kontrak

Full suite memakai MySQL 8.0.40 disposable karena beberapa test memeriksa constraint,
trigger, transaksi, dan perilaku khusus MySQL. Service database test tidak membuka port
ke host dan tidak memakai volume persisten; project Compose-nya terpisah dari database
development. Fixture dibuat per test menggunakan factory dan `RefreshDatabase`, termasuk
fixture dua warung untuk membuktikan batas tenant. Jangan arahkan test ke database
development atau produksi.

Dengan Docker Desktop Windows dan integrasi WSL 2 aktif, jalankan dari terminal WSL di
direktori repo:

```bash
docker compose -f compose.test.yaml --project-name larissama-backend-test run --build --rm test-runner sh -lc 'composer install --no-interaction --prefer-dist --no-progress && vendor/bin/pint --test && php artisan test --no-progress'
docker compose -f compose.openapi.yaml --project-name larissama-openapi-ci run --build --rm openapi-validator
docker compose -f compose.test.yaml --project-name larissama-backend-test down --remove-orphans
docker compose -f compose.openapi.yaml --project-name larissama-openapi-ci down --remove-orphans
```

Jalankan kedua perintah `down` juga bila validasi/test gagal. Workflow GitHub Actions
`Backend CI` menjalankan pemeriksaan OpenAPI, Pint, dan suite penuh pada setiap push,
pull request, atau pemanggilan manual, lalu membersihkan kedua project Compose meski job
gagal. Ia memakai Compose test yang sama dengan development lokal.

Tanpa Docker, test tetap dapat dijalankan memakai PHP 8.3+, Composer, dan database MySQL
8.0.40 disposable tersendiri. Pasang dependency dengan `composer install`, lalu berikan
`DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
dan `DB_URL=` pada proses `php artisan test`; jangan gunakan koneksi development/produksi.
`phpunit.xml` memakai SQLite secara default untuk test lokal yang tidak membutuhkan
perilaku khusus MySQL, sehingga konfigurasi eksplisit diperlukan untuk full suite.

Rincian hasil test dan status tiap task ada di [tracker implementasi](IMPLEMENTATION_PROGRESS.md).
Rincian operasi kontrak dan status handoff frontend ada di [panduan API](docs/api/README.md)
dan [OpenAPI](docs/api/openapi.yaml); status handoff per operationId tercatat di OpenAPI dan tracker. Saat ini seluruh 33 operasi READY_FOR_FRONTEND untuk alur utama.
Prosedur rilis, migration, backup/restore, serta pemulihan operasional ada di [runbook backend](docs/backend/RUNBOOK.md). Runbook tersebut membedakan Compose development dari production dan belum mengklaim deployment atau restore production pernah dijalankan.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
