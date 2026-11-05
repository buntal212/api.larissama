# Spesifikasi Teknis Integrasi UIH — Larissama sebagai Aplikasi Sumber Udumbara (ASU)

> **Status Dokumen:** Baseline Spesifikasi Teknis Resmi (Approved Integration Baseline v1.0)  
> **Versi:** 1.0.0  
> **Tanggal Pembaruan:** 5 November 2026  
> **Target Aplikasi:** Larissama Backend (`api.larissama`)  
> **Konsumen Integrasi:** Udumbara Internal Hub / UIH (`api.uih`) & Temuka (`api.temuka` / `web.temuka`)  
> **Dokumen Acuan:** `UIH_CANONICAL_CATALOG_CONTRACT_V1.md` (di repositori UIH)  
> **Tujuan:** Menjadi pedoman teknis definitif dan siap pakai bagi developer Larissama untuk mengimplementasikan endpoint Server-to-Server (S2S) katalog kuliner publik.

---

## 1. Konteks dan Arsitektur Integrasi

Dalam ekosistem Udumbara, **Temuka** bertindak sebagai platform penemuan (*discovery platform*) multi-sumber yang menyajikan penawaran produk/jasa kepada publik secara bertahap (*progressive delivery*) melalui **Server-Sent Events (SSE)**.

Alur integrasi berjalan secara berjenjang:
```
Browser Pengguna (Temuka Web)
        │
        ▼ (HTTP / SSE)
Temuka Backend (api.temuka)
        │
        ▼ (SSE Aggregator Stream)
Udumbara Internal Hub / UIH (api.uih)
        │
        ▼ (HTTP REST S2S Paralel)
Aplikasi Sumber Udumbara (ASU): [Larissama, Sumber Lain...]
```

### Prinsip Utama Integrasi:
1. **Zero Direct Database Access:** UIH **tidak pernah** mengakses database MySQL Larissama secara langsung. Komunikasi murni melalui gerbang API HTTP REST S2S.
2. **Kanonisasi Data:** UIH menormalisasi data dari berbagai ASU ke dalam satu kontrak kanonik yang seragam (*UIH Canonical Catalog Contract v1*). Larissama bertugas menyediakan data katalog sesuai kontrak S2S yang ditentukan dalam dokumen ini.
3. **Penyedia Kuliner Pertama:** Larissama bertindak sebagai ASU kuliner pertama yang menyediakan katalog menu makanan/minuman dari warung-warung makan terverifikasi.

---

## 2. Identitas dan Routing ASU

Larissama adalah sistem *multi-tenant* berbasis *single database* di mana setiap tenant merepresentasikan sebuah warung makan (`warungs`). Identitas data dibagi menjadi 3 hierarki:

```
+-------------------------------------------------------------+
| ASU (Aplikasi Sumber Udumbara)                             |
| Kode: "larissama" (Dikelola di Registry UIH)                |
+-------------------------------------------------------------+
                              │
                              ▼
+-------------------------------------------------------------+
| Provider (Tenant / Warung)                                  |
| ID: warungs.id (BigInt PK, unik global)                     |
| Business Code: warungs.kode (String, unik global)           |
+-------------------------------------------------------------+
                              │
                              ▼
+-------------------------------------------------------------+
| Offer / Menu Item                                           |
| ID: menus.id (BigInt PK, unik global di Larissama)          |
| Business Code: menus.kode (String, unik HANYA per warung)   |
+-------------------------------------------------------------+
```

### 2.1. ASU Identity (`source: "larissama"`)
- Larissama **tidak memerlukan** kolom `application_id` pada database internal. Seluruh instance Larissama secara utuh adalah satu domain aplikasi sumber.
- UIH mendaftarkan Larissama pada registry internalnya dengan kode kanonis string: `'larissama'`.

### 2.2. Provider Identity (`provider_id`)
- Mengidentifikasi warung penjual di Larissama.
- **Identitas Kanonik:** `warungs.id` (integer atau string numerik unik global).
- **Business Code:** `warungs.kode` (contoh: `WRG-00001`), ramah pengguna dan bersifat unik global.

### 2.3. Offer / Menu Identity (`offer_id`)
- Mengidentifikasi item menu yang ditawarkan oleh warung.
- **Identitas Kanonik:** `menus.id` (integer atau string numerik unik global).
- **Peringatan Penting mengenai `menus.kode`:** Kolom `menus.kode` (contoh: `MNU-00001`) di Larissama **hanya unik per warung** (`menus_warung_kode_unique`), bukan unik global lintas warung. Oleh karena itu, UIH dan Temuka **wajib menggunakan `menus.id`** sebagai `offer_id` kanonis global. `menus.kode` hanya berfungsi sebagai atribut metadata bisnis opsional.

### 2.4. Mekanisme Routing di UIH
- **Pencarian Katalog:** UIH menjalankan adapter terdaftar (`LarissamaCatalogAdapter`) secara paralel saat pengguna Temuka mencari menu makanan.
- **Routing Permintaan Masa Depan:** Setiap item yang berasal dari Larissama dilabeli dengan tuple:
  ```json
  {
    "source": "larissama",
    "provider_id": "12",
    "item_id": "105"
  }
  ```
  Ketika pengguna Temuka memilih menu tersebut (misalnya untuk melihat detail atau membuat pesanan di kemudian hari), UIH membaca atribut `source` untuk merutekan permintaan langsung ke service endpoint Larissama.

---

## 3. Kebutuhan API Katalog S2S

Untuk melayani pencarian katalog dari UIH, Larissama wajib menyediakan satu endpoint API Server-to-Server (S2S) inbound khusus publik katalog.

### 3.1. Spesifikasi Endpoint & Metode HTTP
- **Metode:** `GET` (idempotent dan ramah performa)
- **URL Path:** `/api/v1/s2s/catalog/search`
- **Tipe Konten:** `application/json`

### 3.2. Parameter Pencarian (Query Parameters)
| Parameter | Tipe Data | Wajib | Keterangan |
|---|---|---|---|
| `q` atau `query` | `string` | Tidak (Nullable) | Kata kunci pencarian nama atau deskripsi menu (min: 1 karakter, max: 100 karakter). Jika kosong atau tidak disertakan, kembalikan daftar menu aktif terpopuler/terbaru hingga batas `limit`. |
| `limit` | `integer` | Tidak | Batas jumlah item yang dikembalikan. Default: `20`, minimum: `1`, maksimum: `50`. Nilai di atas 50 wajib ditolak dengan HTTP 422. |
| `provider_id` | `integer` | Tidak | Filter opsional untuk mencari menu dari satu warung tertentu (`warungs.id`). |

### 3.3. Request Headers dari UIH
```http
GET /api/v1/s2s/catalog/search?q=nasi+goreng&limit=20 HTTP/1.1
Host: api.larissama.internal
Accept: application/json
Authorization: Bearer <UIH_SERVER_TOKEN>
X-Correlation-ID: 7b36f1c2-9014-411a-824c-b17f8a92345e
```

### 3.4. Format JSON Response Resmi (Canonical v1 Compliant)
Format respons menggunakan standar JSON envelope yang selaras 100% dengan Canonical Catalog Contract v1:

```json
{
  "success": true,
  "data": [
    {
      "id": "105",
      "code": "MNU-00012",
      "name": "Nasi Goreng Spesial",
      "category": "Makanan Utama",
      "summary": "Nasi goreng dengan telur mata sapi, ayam suwir, dan acar segar.",
      "price": {
        "amount": 25000,
        "currency": "IDR"
      },
      "image": {
        "url": "https://storage.larissama.id/menus/nasi-goreng.jpg"
      },
      "availability": "available",
      "provider": {
        "id": "12",
        "code": "WRG-00003",
        "name": "Warung Berkah Jaya",
        "image": null,
        "contact": {
          "phone": "081234567890",
          "whatsapp": null
        },
        "location": {
          "address": "Jl. Kaliurang KM 5, Sleman",
          "latitude": null,
          "longitude": null
        }
      }
    }
  ],
  "meta": {
    "total": 1,
    "limit": 20
  }
}
```

### 3.5. Aturan Lapangan, Sensitivitas, dan Nullability
1. **Harga Jual Menu (`price`):**
   - Format wajib berupa objek: `{"amount": <integer rupiah>, "currency": "IDR"}`.
   - `price.amount`: Bilangan bulat positif $\ge 0$ (contoh: `25000`). Bukan float (bukan `25000.00`), dan bukan string.
   - **KEBIJAKAN ZERO-FALLBACK DI UIH:** UIH **menolak keras item dengan harga tidak valid atau negatif**. UIH **tidak akan mengubah harga invalid menjadi 0**, karena harga `0` dapat disalahartikan sebagai gratis oleh calon pembeli. Larissama wajib memastikan hanya menu dengan `harga >= 0` yang dikembalikan.
   - **KEAMANAN & PRIVASI:** Kolom `menus.harga_modal` (HPP / Harga Pokok Penjualan) **DILARANG KERAS DIEKSPOS**. Margin warung adalah rahasia dapur internal pedagang.
2. **Kategori Menu (`category`):**
   - Diambil dari relasi `kategori_menus.nama`.
   - Wajib hadir secara struktural, tetapi bernilai `string | null` jika kategori belum diisi atau dinonaktifkan.
3. **Format Gambar (`image` & `provider.image`):**
   - Format wajib berupa objek `{ "url": "..." }` atau `null` jika tidak ada foto.
   - **Larangan Substitusi:** Gambar item menu **tidak boleh** diganti dengan logo warung secara otomatis. Logo warung memiliki field tersendiri pada `provider.image`.
   - Di database Larissama, kolom gambar menu bernama `menus.gambar`, dan logo warung bernama `warungs.logo`.
4. **Availability:**
   - Wajib bernilai enum string: `"available"` atau `"unavailable"`.
5. **Kontak dan Lokasi Provider:**
   - Dikelompokkan di bawah objek `provider.contact` dan `provider.location`.
   - `contact.phone`: Diambil dari `warungs.telepon` (atau `null`).
   - `contact.whatsapp`: Saat ini belum ada kolom terpisah di Larissama, sehingga bernilai `null`.
   - `location.address`: Diambil dari `warungs.alamat` (atau `null`).
   - `location.latitude` & `location.longitude`: Saat ini belum ada kolom koordinat di Larissama, sehingga wajib bernilai `null`.
6. **Penegakan Nullability Eksplisit:**
   - Seluruh field yang bernilai kosong (`code`, `category`, `summary`, `image`, `provider.image`, `provider.contact.*`, `provider.location.*`) **wajib hadir eksplisit dalam JSON dengan nilai `null`**, bukan dihilangkan dari objek (*omitted*).

### 3.6. Aturan Kelayakan Publik (Public Eligibility Rules)
Hanya menu dan warung yang memenuhi **seluruh kriteria** berikut yang boleh dikembalikan dalam pencarian katalog publik:

| Entitas | Kriteria Kelayakan Publik di Larissama | Keterangan Teknis |
|---|---|---|
| **Warung** | `warungs.pendaftaran_disetujui == true` | Pendaftaran warung telah disetujui admin. |
| **Warung** | `warungs.aktif == true` | Warung dalam status operasional aktif. |
| **Warung** | `warung->subscriptionStatusAt(now()) === 'aktif'` | Langganan warung aktif (tidak kedaluwarsa, tidak terjadwal, tidak ditangguhkan). |
| **Kategori**| `kategori_menus.aktif == true` | Kategori menu aktif. |
| **Menu** | `menus.aktif == true` | Menu aktif dan dapat dipesan. |
| **Menu** | `menus.harga >= 0` | Harga jual valid dan tidak negatif. |
| **Integritas** | `menus.warung_id == kategori_menus.warung_id` | Integritas composite key tenant terjamin. |

Menu dari warung yang belum disetujui, warung non-aktif, atau warung yang masa langganannya habis **tidak boleh muncul** di hasil pencarian.

### 3.7. Penanganan Hasil Kosong dan Error
- **Hasil Tidak Ditemukan:** Jika kata kunci tidak menghasilkan menu yang cocok, kembalikan HTTP `200 OK` dengan payload `"data": []` dan `"total": 0`. **Bukan HTTP 404**.
- **Validasi Gagal (misal limit > 50):** HTTP `422 Unprocessable Content` dengan struktur JSON error Laravel standar.
- **Autentikasi Gagal:** HTTP `401 Unauthorized`.
- **Internal Server Error:** HTTP `500 Internal Server Error` dengan pesan aman tanpa membocorkan trace database atau file paths di environment produksi.

### 3.8. Target Kinerja & SLA
- **Target SLA Latensi:** Larissama diharapkan merespons query pencarian dalam waktu $\le \mathbf{800\text{ ms}}$.
- **Batas Timeout UIH:** UIH membatasi waktu tunggu HTTP client sebesar **2.0 hingga 3.0 detik**. Jika Larissama melewati batas ini, UIH akan memutus koneksi dan memancarkan event SSE `source_failed` untuk Larissama agar stream pencarian pengguna Temuka tidak terhenti.

---

## 4. Autentikasi Server-to-Server (S2S)

Komunikasi antara UIH dan Larissama adalah komunikasi mesin-ke-mesin (*machine-to-machine*) dalam jaringan terpercaya.

### 4.1. Pemisahan Total dari Laravel Sanctum
Larissama saat ini menggunakan Laravel Sanctum untuk otentikasi pengguna web/mobile (kasir/pemilik warung).
- Autentikasi S2S **tidak boleh** menggunakan atau memeriksa tabel `personal_access_tokens` milik Sanctum.
- Autentikasi S2S tidak merepresentasikan user login mana pun dan bersifat *stateless*.
- Diimplementasikan menggunakan dedicated middleware, misalnya: `EnsureServerToServerAuthentication`.

### 4.2. Mekanisme Bearer Token
1. UIH menyertakan Bearer token pada header:
   ```http
   Authorization: Bearer <UIH_SERVER_TOKEN>
   ```
2. Larissama mengonfigurasi secret token melalui file `.env`:
   ```dotenv
   # .env Larissama
   UIH_SERVER_TOKEN=larissama_s2s_secret_secure_token_random
   ```
   Didaftarkan pada konfigurasi: `config('services.uih.server_token')`.
3. Validasi token wajib menggunakan perbandingan string tahan serangan pewaktuan (*timing-attack safe*):
   ```php
   if (! is_string($token) || ! hash_equals($expectedToken, $token)) {
       return response()->json(['message' => 'Invalid S2S credentials.'], 401);
   }
   ```
4. Jika header `Authorization` kosong atau format Bearer tidak sesuai, middleware langsung menolak dengan status HTTP `401 Unauthorized`.

---

## 5. Aturan Data dan Tenant Isolation

Larissama mengelola ribuan menu dari berbagai warung dalam satu database terpusat. Untuk mencegah kebocoran data (*data leakage*):

### 5.1. Perlindungan Data Sensitif (Strict Exclusion List)
Endpoint S2S katalog Larissama **DILARANG KERAS** memuat field-field berikut:
1. `menus.harga_modal` (HPP / margin keuntungan pedagang).
2. Data akun pengguna (`users.id`, `username`, `email`, `password`, `pin`, `role`).
3. Riwayat transaksi kasir dan penjualan (`penjualans`, `penjualan_items`, `penjualan_payments`).
4. Riwayat pembukuan dan belanja modal (`pembelians`, `pembelian_items`).
5. Sinkronisasi data pelanggan UDEV (`warung_customer_syncs`).
6. Informasi rekening bank atau pembayaran biaya langganan warung.

---

## 6. Kesiapan Routing Transaksi Masa Depan

Meskipun integrasi tahap awal difokuskan pada pencarian katalog, struktur identitas kanonik (`source: "larissama"`, `provider_id`, `item_id`) menjamin kesiapan untuk alur transaksi pemesanan di masa depan.

### 6.1. Contoh Konseptual Payload Pemesanan

> [!CAUTION]
> Payload di bawah ini adalah **CONTOH KONSEPTUAL DAN BUKAN KONTRAK PEMESANAN FINAL**. Fitur pemesanan **BELUM diimplementasikan** pada tahap ini.

```json
{
  "source": "larissama",
  "provider_id": "12",
  "order_reference": "TMK-20261105-0089",
  "customer": {
    "name": "Budi Santoso",
    "phone": "08123456789"
  },
  "items": [
    {
      "offer_id": "105",
      "quantity": 2,
      "notes": "Pedas level 1, tanpa kol"
    }
  ]
}
```

### 6.2. Prinsip Validasi Mandiri Larissama
Jika endpoint pemesanan S2S kelak dibangun di Larissama:
1. **Zero-Trust Input Harga:** Larissama **tidak boleh** menerima harga barang yang dikirim oleh client/UIH. Larissama wajib mengambil harga resmi langsung dari database `menus.harga` pada saat pembuatan pesanan.
2. **Validasi Ketersediaan Real-Time:** Larissama wajib memverifikasi ulang apakah warung dan menu bersangkutan masih aktif dan dapat menerima pesanan.
3. **Pencatatan Transaksi:** Pesanan dari luar akan dicatat sesuai alur kasir Larissama dengan referensi nomor order eksternal dari Temuka.

---

## 7. Pemetaan Data Larissama ke Model Kanonik UIH v1

Tabel berikut menunjukkan pemetaan antara field data di Larissama, field kanonik di UIH, dan konsumsinya di Temuka:

| Field Kanonik UIH v1 | Field Sumber Larissama | Tipe Data | Status | Aturan Transformasi & Mapping |
|---|---|---|---|---|
| `id` | `menus.id` | `string` | **Tersedia** | Wajib. Di-cast ke string (`(string) $menu->id`). Menggunakan PK global unik `menus.id`. |
| `code` | `menus.kode` | `string \| null` | **Tersedia** | Opsional. Kode menu bisnis (`MNU-XXXXX`). Unik hanya per-warung. |
| `name` | `menus.nama` | `string` | **Tersedia** | Wajib. Nama publik menu makanan/minuman. |
| `category` | `kategori_menus.nama` | `string \| null` | **Tersedia** | Opsional. Nama kategori publik via relasi `$menu->kategoriMenu->nama`. |
| `summary` | `menus.deskripsi` | `string \| null` | **Tersedia** | Opsional. Deskripsi singkat menu, atau `null` jika tidak diisi warung. |
| `price.amount` | `menus.harga` | `integer` | **Tersedia** | Wajib. Di-cast ke integer rupiah (`(int) round((float) $menu->harga)`). Nilai $\ge 0$. **HPP dilarang diekspos.** |
| `price.currency` | Konstanta `'IDR'` | `string` | **Tersedia** | Wajib. Kode ISO 4217 tetap `'IDR'`. |
| `image` | `menus.gambar` | `object \| null` | **Tersedia** | Opsional. Objek `{"url": Storage::url($menu->gambar)}` jika gambar ada, atau `null` jika kosong. |
| `availability` | Status aktif | `string` | **Tersedia** | Wajib. Enum `"available"` jika menu aktif, selain itu `"unavailable"`. |
| `provider.id` | `warungs.id` | `string` | **Tersedia** | Wajib. Di-cast ke string (`(string) $warung->id`). |
| `provider.code` | `warungs.kode` | `string \| null` | **Tersedia** | Opsional. Format string `WRG-XXXXX`. |
| `provider.name` | `warungs.nama` | `string` | **Tersedia** | Wajib. Nama warung makan. |
| `provider.image` | `warungs.logo` | `object \| null` | **Tersedia** | Opsional. Objek `{"url": Storage::url($warung->logo)}` jika logo ada, atau `null`. |
| `provider.contact.phone` | `warungs.telepon` | `string \| null` | **Tersedia** | Opsional. Nomor telepon publik warung. |
| `provider.contact.whatsapp` | *Belum ada kolom* | `null` | **Belum Tersedia** | Bernilai `null`. |
| `provider.location.address` | `warungs.alamat` | `string \| null` | **Tersedia** | Opsional. Alamat fisik warung. |
| `provider.location.latitude` | *Belum ada kolom* | `null` | **Belum Tersedia** | Bernilai `null`. |
| `provider.location.longitude` | *Belum ada kolom* | `null` | **Belum Tersedia** | Bernilai `null`. |

---

## 8. Panduan Teknis Implementasi untuk Developer Larissama

Bagian ini memuat panduan konkret bagi developer Larissama yang akan mengimplementasikan fitur ini.

### 8.1. Konfigurasi Lingkungan (`.env` dan `config/services.php`)
1. Tambahkan environment variable pada file `.env` dan `.env.example`:
   ```dotenv
   UIH_SERVER_TOKEN=larissama_s2s_secret_random_token_key_here
   ```
2. Tambahkan entri pada `config/services.php`:
   ```php
   'uih' => [
       'server_token' => env('UIH_SERVER_TOKEN'),
   ],
   ```

### 8.2. Pembuatan Middleware S2S Auth
Buat file `app/Http/Middleware/EnsureServerToServerAuthentication.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureServerToServerAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization', '');
        if (! str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Unauthenticated S2S request.'], 401);
        }

        $providedToken = substr($authHeader, 7);
        $expectedToken = config('services.uih.server_token');

        if (! is_string($expectedToken) || $expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json(['message' => 'Invalid S2S credentials.'], 401);
        }

        return $next($request);
    }
}
```

### 8.3. Pendaftaran Route di `routes/api.php`
```php
use App\Http\Controllers\Api\V1\S2S\CatalogSearchController;
use App\Http\Middleware\EnsureServerToServerAuthentication;

Route::prefix('v1/s2s')->middleware([EnsureServerToServerAuthentication::class])->group(function () {
    Route::get('/catalog/search', CatalogSearchController::class);
});
```

### 8.4. Pembuatan Controller & Query Scope
Buat `app/Http/Controllers/Api/V1/S2S/CatalogSearchController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1\S2S;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\S2S\CatalogMenuResource;
use App\Models\Menu;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'provider_id' => ['nullable', 'integer'],
        ]);

        $queryText = trim((string) ($validated['q'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 20);
        $providerId = $validated['provider_id'] ?? null;

        $today = CarbonImmutable::now()->toDateString();

        $menus = Menu::query()
            ->with(['warung', 'kategoriMenu'])
            // Syarat kelayakan menu
            ->where('menus.aktif', true)
            ->where('menus.harga', '>=', 0)
            // Syarat kelayakan kategori
            ->whereHas('kategoriMenu', function ($q) {
                $q->where('aktif', true);
            })
            // Syarat kelayakan warung (disetujui, aktif, subscription aktif)
            ->whereHas('warung', function ($q) use ($today) {
                $q->where('aktif', true)
                    ->where('pendaftaran_disetujui', true)
                    ->where(function ($sq) use ($today) {
                        $sq->whereNull('tanggal_mulai')
                            ->orWhere('tanggal_mulai', '<=', $today);
                    })
                    ->where(function ($sq) use ($today) {
                        $sq->whereNull('tanggal_berakhir')
                            ->orWhere('tanggal_berakhir', '>=', $today);
                    });
            })
            // Filter provider opsional
            ->when($providerId, function ($q, $pId) {
                $q->where('warung_id', $pId);
            })
            // Filter teks pencarian nama atau deskripsi
            ->when($queryText !== '', function ($q) use ($queryText) {
                $q->where(function ($sq) use ($queryText) {
                    $sq->where('nama', 'like', "%{$queryText}%")
                        ->orWhere('deskripsi', 'like', "%{$queryText}%");
                });
            })
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => CatalogMenuResource::collection($menus),
            'meta' => [
                'total' => $menus->count(),
                'limit' => $limit,
            ],
        ]);
    }
}
```

### 8.5. Pembuatan API Resource (`CatalogMenuResource`)
Buat `app/Http/Resources/Api/V1/S2S/CatalogMenuResource.php`:

```php
<?php

namespace App\Http\Resources\Api\V1\S2S;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CatalogMenuResource extends JsonResource
{
    /**
     * Transform the resource into an array adhering to Canonical Catalog Contract v1.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $warung = $this->warung;

        return [
            'id' => (string) $this->id,
            'code' => $this->kode ?: null,
            'name' => $this->nama,
            'category' => $this->kategoriMenu?->nama ?: null,
            'summary' => $this->deskripsi ?: null,
            'price' => [
                'amount' => (int) round((float) $this->harga),
                'currency' => 'IDR',
            ],
            'image' => $this->gambar ? ['url' => Storage::url($this->gambar)] : null,
            'availability' => $this->aktif ? 'available' : 'unavailable',
            'provider' => [
                'id' => (string) $warung->id,
                'code' => $warung->kode ?: null,
                'name' => $warung->nama,
                'image' => $warung->logo ? ['url' => Storage::url($warung->logo)] : null,
                'contact' => [
                    'phone' => $warung->telepon ?: null,
                    'whatsapp' => null,
                ],
                'location' => [
                    'address' => $warung->alamat ?: null,
                    'latitude' => null,
                    'longitude' => null,
                ],
            ],
        ];
    }
}
```

### 8.6. Automated Testing Plan (PHPUnit)
Buat feature test di Larissama (`tests/Feature/Api/V1/S2S/CatalogSearchTest.php`) untuk memvalidasi:
1. `test_rejects_unauthenticated_s2s_request()`: Request tanpa header `Authorization` mengembalikan 401.
2. `test_rejects_invalid_s2s_token()`: Request dengan token salah mengembalikan 401.
3. `test_validates_limit_parameter_maximum_50()`: `limit=51` mengembalikan 422.
4. `test_returns_only_approved_and_active_warung_menus()`: Menu dari warung yang belum disetujui atau subscription habis tidak muncul di hasil.
5. `test_returns_only_active_menus_and_categories()`: Menu non-aktif atau kategori non-aktif tidak muncul di hasil.
6. `test_never_exposes_harga_modal_or_user_accounts()`: Memverifikasi JSON response tidak memiliki atribut `harga_modal`, `password`, atau data sensitif lainnya.
7. `test_formats_price_as_integer_rupiah()`: Memastikan `price.amount` adalah integer $\ge 0$.
8. `test_formats_images_as_nullable_objects()`: Memastikan gambar berformat `{ "url": "..." }` atau `null`.
9. `test_empty_results_returns_200_with_empty_array()`: Kata kunci tanpa hasil mengembalikan 200 dengan `data: []`.

---

## 9. Status Keputusan Arsitektur (Finalized Decisions)

Seluruh keputusan teknis penting kini telah **resmi disepakati dan diimplementasikan** pada UIH:

| Keputusan | Status | Ketetapan |
|---|---|---|
| **Metode HTTP Endpoint S2S** | **DISEPAKATI** | Menggunakan `GET /api/v1/s2s/catalog/search`. |
| **Format Harga** | **DISEPAKATI** | Objek kanonik `{"amount": int >= 0, "currency": "IDR"}`. Harga invalid ditolak (tidak diubah ke 0). |
| **Format Gambar** | **DISEPAKATI** | Objek nullable `{"url": string} \| null`. Gambar item dan provider terpisah. |
| **Batas Pencarian (`limit`)** | **DISEPAKATI** | Default 20, batas maksimum 50. Nilai > 50 ditolak (422). |
| **Field Nullable** | **DISEPAKATI** | Wajib hadir eksplisit sebagai `null`, bukan dihilangkan dari JSON. |
| **Kebijakan Caching UIH** | **DISEPAKATI** | UIH bertindak murni *pass-through* tanpa cache pada v1. |
| **Data Sensitif / HPP** | **DISEPAKATI** | HPP (`harga_modal`) dan data internal dilarang keras dikembalikan. |
