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
```

API server lokal tersedia di `http://localhost:8010`; health endpoint-nya `http://localhost:8010/up`. MySQL dapat diakses dari host pada port `33309`. Compose memasang dependency Composer dan membuat APP_KEY development sementara saat container app mulai jika `LARISSAMA_APP_KEY` tidak diisi. Untuk menghentikan layanan tanpa menghapus data database, jalankan `docker compose down` (hindari opsi `-v` jika volume database ingin dipertahankan). Kredensial default Compose hanya untuk database development lokal. Port dan kredensial dapat diubah lewat variabel `LARISSAMA_API_PORT`, `LARISSAMA_DB_PORT`, `LARISSAMA_DB_DATABASE`, `LARISSAMA_DB_USERNAME`, `LARISSAMA_DB_PASSWORD`, dan `LARISSAMA_DB_ROOT_PASSWORD`; ID user/group container default `1000` dan dapat diubah dengan `LARISSAMA_UID` serta `LARISSAMA_GID`.

Test Laravel berjalan pada project dan database MySQL 8.0.40 yang terpisah dari development:

```bash
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner sh -lc 'composer install --no-interaction && php artisan test'
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner vendor/bin/pint --test
```

Container database test dapat dihentikan setelahnya dengan `docker compose -f compose.test.yaml --project-name larissama-backend-test down`. Detail cara kerja dan hasil verifikasi ada di [tracker implementasi](IMPLEMENTATION_PROGRESS.md).

Tanpa Docker, gunakan PHP 8.3+ dan Composer yang sesuai dengan `composer.json`, jalankan `composer install`, siapkan `.env` dari `.env.example`, isi koneksi ke MySQL/MariaDB lokal, lalu jalankan `php artisan key:generate`, `php artisan migrate`, dan `php artisan serve`. Rincian operasi kontrak dan status handoff frontend ada di [panduan API](docs/api/README.md) dan [OpenAPI](docs/api/openapi.yaml); keduanya tetap draft sampai status tracker menyatakan siap.

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
