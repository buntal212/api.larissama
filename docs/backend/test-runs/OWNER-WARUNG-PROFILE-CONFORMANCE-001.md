# OWNER-WARUNG-PROFILE-CONFORMANCE-001

Tanggal: 2026-10-12
Status: **PASS**
OpenAPI: **0.1.10-draft**

## Cakupan

- Owner mengubah nama/alamat/telepon/timezone tanpa `warung_id`; ID warung diambil dari token, dan field kode/aktif/langganan tetap tidak berubah.
- Body kosong dan field yang tidak diizinkan ditolak 422.
- Manager tidak dapat mengubah profil atau menghapus logo; policy matrix juga menolak kasir dan superadmin.
- Upload logo PNG, penggantian, URL response, penghapusan file lama, dan penghapusan logo diuji pada `Storage::fake('public')`.
- File non-image dan file lebih dari 1 MiB ditolak tanpa mengubah row atau menyisakan file.
- Runtime response terpilih dicocokkan dengan schema OpenAPI.

## Hasil

| Check | Hasil |
| --- | --- |
| `CurrentWarungApiTest.php` di MySQL 8.0.40 Compose disposable | PASS: 9 tests / 762 assertions |
| Endpoints warung/admin/profil terkait | PASS: 32 tests / 3.168 assertions setelah pemeriksaan akhir URL logo |
| Policy, OpenAPI document integrity, dan route/OpenAPI inventory | PASS: 28 tests / 1.537 assertions; 40 operationId / 26 paths cocok |
| Full suite MySQL 8.0.40 Compose disposable | PASS: 577 tests / 98.202 assertions in 61.03 seconds |
| Pint | PASS |
| OpenAPI 3.1 validator | PASS |
| `php artisan route:cache` lalu `route:clear` pada Docker PHP 8.3 | PASS |
| PHP `fileinfo`, upload limits 2M/8M, dan `php artisan storage:link --force` pada Docker runtime | PASS |
| YAML dan bash syntax seluruh workflow deploy | PASS: 17 script |

## Catatan deploy

Tidak ada migration atau dependency baru. Deployment menolak runtime PHP tanpa `fileinfo` atau limit upload CLI kurang dari 2M/8M, melakukan preflight `public/storage` sebelum rsync, membuat ulang symlink, lalu memverifikasinya. Logo memakai `public` disk: operator harus menjaga `storage/app/public` persisten, memasukkannya ke backup/restore bersama DB, dan mengonfigurasi `APP_URL` sebagai URL publik HTTPS. Production host/PHP-FPM belum diuji oleh artefak ini.
