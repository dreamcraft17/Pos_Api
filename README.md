# POS API

Backend REST API untuk aplikasi **Point of Sale (POS)** — kasir, manajemen menu, stok, transaksi, shift kasir, dan sinkronisasi data ke client (biasanya aplikasi mobile/desktop).

Dibangun dengan **Laravel 12** dan **PHP 8.2+**. Semua endpoint API berada di prefix `/api`.

---

## Untuk apa project ini?

API ini menjadi **sumber data pusat** antara database dan aplikasi kasir. Client memanggil API untuk:

| Kebutuhan bisnis | Contoh endpoint |
|------------------|-----------------|
| Login & identitas kasir | `POST /api/auth/login`, `GET /api/auth/me` |
| Master produk & stok | `GET/POST /api/products`, `POST /api/products/{sku}/stock` |
| Menu jual (dengan komponen & varian) | `GET/POST /api/menus` |
| Paket / bundle menu | `GET/POST /api/bundle-menus` |
| Transaksi penjualan | `POST /api/orders`, `GET /api/orders` |
| Refund parsial / penuh | `POST /api/orders/{id}/refund` |
| Bill belum dibayar (open bill) | `GET/POST /api/open-bills` |
| Metode bayar, diskon, tipe order | `/api/payment-methods`, `/api/discounts`, `/api/order-types` |
| Riwayat pergerakan stok | `GET /api/stock-moves` |
| Permintaan stok antar outlet | `/api/stock-requests` |
| Shift kasir & ringkasan harian | `/api/shifts`, `/api/shifts/active-summary` |
| Health check deploy | `GET /api/health`, `GET /api/db-check` |

**Alur tipikal:** client sync master data (produk, menu) → kasir buat order → stok berkurang otomatis dari SKU atau resep menu → pembayaran tercatat → shift ditutup dengan ringkasan penjualan.

---

## Arsitektur singkat

```
[Aplikasi POS Client]
        │
        │  HTTP JSON (/api/...)
        ▼
[Laravel POS API]  ──►  MySQL / MariaDB
        │
        ├── Models (Order, Product, Menu, Shift, …)
        ├── Controllers di app/Http/Controllers/Api/
        └── Middleware CookieAuth (session user opsional)
```

- Harga disimpan dalam **sen** (`*_cents`) untuk menghindari floating point.
- Order menyimpan snapshot payload JSON + baris `order_items` / `payments`.
- Menu bisa punya **komponen** (`menu_items` → `product_sku` + qty): saat order, stok produk komponen dikurangi otomatis.
- Multi-user: banyak entitas punya `created_by` agar data per kasir/outlet tidak bentrok.

---

## Persyaratan

- PHP ≥ 8.2 (extension: `pdo_mysql`, `mbstring`, `openssl`, …)
- Composer
- MySQL / MariaDB (atau SQLite untuk development)
- Web server: Laragon, `php artisan serve`, atau Nginx/Apache

---

## Instalasi

```bash
# Clone / masuk ke folder project
cd pos-api

# Dependensi
composer install

# Environment
cp .env.example .env
php artisan key:generate

# Sesuaikan .env — contoh MySQL (Laragon)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=pos
# DB_USERNAME=root
# DB_PASSWORD=

# Migrasi database (termasuk index performa)
php artisan migrate

# Jalankan server development
php artisan serve
# API: http://127.0.0.1:8000/api/health
```

### Production (disarankan)

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

---

## Autentikasi

- Registrasi / login: `POST /api/auth/register`, `POST /api/auth/login`
- Beberapa route shift memakai middleware `auth:sanctum`
- Middleware `CookieAuth` di grup `api` mendukung client yang mengirim cookie/session
- Helper `currentUser()` di `BaseApiController` mengambil user dari attribute request, Sanctum, atau `auth()`

---

## Optimasi query & performa

Bagian ini menjelaskan **apa** yang dioptimasi dan **untuk apa**, agar API tetap responsif saat banyak transaksi atau sync data besar.

### Masalah yang diselesaikan

Tanpa optimasi, operasi seperti **buat order** bisa menjalankan puluhan query (satu query per item untuk produk, komponen menu, insert baris). Itu memperlambat kasir dan membebani database.

### Yang sudah diterapkan

| Area | Teknik | Manfaat |
|------|--------|---------|
| **POST `/orders`** | Preload semua SKU + komponen menu dengan `whereIn`, lalu **satu** query produk | Tidak ada N+1 per item |
| **POST `/orders`** | `OrderItem::insert`, `Payment::insert`, `StockMove::insert` batch | Lebih sedikit round-trip ke DB |
| **GET `/orders/{id}`** | `with(['items','payments'])` + agregat refund satu query | Detail order cepat |
| **POST refund** | Preload stok sama seperti order + batch insert refund items | Refund banyak item tetap ringan |
| **Adjust payment setelah refund** | Pakai relasi `payments` yang sudah di-load, tanpa `fresh()` berulang | Kurangi query sia-sia |
| **GET `/menus`, `/bundle-menus`** | 1 query parent + 2 query anak (`whereIn` + `groupBy`) | Pola list + relasi efisien |
| **Bundle store/update** | Load semua `Menu` sekaligus (`whereIn`) | Tidak query per baris bundle |
| **Schema `hasColumn`** | Trait `CachesSchemaColumns` | Cek kolom DB tidak diulang tiap request |
| **GET `/orders`** | Parameter `limit` (default 500, max 2000) | Hindari load seluruh tabel order |
| **GET `/stock-moves`** | Parameter `limit` (default 500, max 5000) | Riwayat stok terbatas |
| **Shift `active-summary`** | Cache 10 detik + query agregat SQL | Dashboard kasir tidak hammer DB |
| **Index database** | Migration `2026_05_19_000000_add_performance_indexes` | Filter `created_by`, tanggal, SKU lebih cepat |
| **GET `/orders`** | `payload` tidak dikirim default; pakai `?include_payload=1` | Response list jauh lebih kecil |
| **Bundle di order** | `StockReductionService` + `bundle_code` di item | Stok bundle/menu/produk sekali preload |
| **Cache master data** | `MasterDataCache` untuk products, menus, bundles, discounts | Sync POS berulang tidak hit DB tiap kali |
| **Menu/bundle write** | Batch `insert()` untuk anak | Simpan menu/bundle lebih cepat |
| **Stock request WA** | Job `SendStockRequestWhatsApp` (queue) | Request API tidak nunggu Fonnte |
| **Rate limit API** | 300 req/menit per user atau IP | Lindungi dari spam / loop client |
| **CookieAuth** | Verifikasi signature cookie `uid` | Auth tanpa `dd()` debug |

### Parameter query opsional

```http
GET /api/orders?since=2026-05-01T00:00:00&limit=200
GET /api/orders?include_payload=1
GET /api/stock-moves?sku=KOPI-001&limit=100
GET /api/products?updatedSince=2026-05-18T10:00:00
GET /api/open-bills?limit=50
```

### Queue WhatsApp (stock request)

```bash
# .env
QUEUE_CONNECTION=database
FONNTE_TOKEN=...
SUPPLIER_WHATSAPP=628xxxxxxxx

# Jalankan worker
php artisan queue:work
```

Tanpa worker, set `QUEUE_CONNECTION=sync` — pengiriman tetap jalan, hanya blocking seperti sebelumnya.

### Konfigurasi cache (`config/pos.php`)

| Env | Default | Fungsi |
|-----|---------|--------|
| `POS_MASTER_CACHE_TTL` | 300 | TTL cache products/menus/bundles |
| `POS_DISCOUNT_CACHE_TTL` | 300 | TTL cache diskon aktif |
| `POS_SHIFT_SUMMARY_TTL` | 10 | TTL ringkasan shift aktif |

### Development: deteksi lazy loading

Di environment `local`, `Model::preventLazyLoading()` aktif di `AppServiceProvider` — relasi yang belum di-`with()` akan error di dev, membantu mencegah N+1 baru.

---

## Struktur folder penting

```
app/
  Http/
    Controllers/Api/     # Semua handler REST
    Concerns/
      CachesSchemaColumns.php
    Middleware/
      CookieAuth.php
  Jobs/
    SendStockRequestWhatsApp.php
  Services/
    StockReductionService.php
    DiscountResolver.php
    MasterDataCache.php
  Models/                # Order, Product, Menu, Shift, …
config/
  pos.php                # TTL cache, limit, Fonnte
database/
  migrations/            # Skema + index performa
routes/
  api.php                # Definisi route API
```

---

## Endpoint utama (ringkas)

Semua path di bawah ini relatif ke `/api`.

| Method | Path | Keterangan |
|--------|------|------------|
| GET | `/health` | Status API |
| GET | `/products` | Daftar produk (sync: `?updatedSince=`) |
| GET | `/menus` | Menu + components + variants |
| POST | `/orders` | Buat transaksi + kurangi stok |
| GET | `/orders/{id}` | Detail order + refund info per item |
| POST | `/orders/{id}/refund` | Refund + restock (+ opsional adjust payment) |
| GET | `/shifts/active-summary` | Ringkasan shift aktif (cached) |
| GET | `/open-bills` | Bill terbuka |

Lihat `routes/api.php` untuk daftar lengkap.

---

## Catatan pengembangan

- **Uang:** selalu gunakan field `*_cents` (integer).
- **Stok order:** `sku` → produk langsung; `menu_code` → komponen `menu_items`; `bundle_code` → komponen bundle (menu + produk).
- **Order list:** tambahkan `?include_payload=1` hanya jika client butuh JSON penuh per order.
- **Refund:** qty divalidasi agar tidak melebihi yang sudah dibeli minus yang sudah direfund.
- Setelah menambah migration baru, jalankan `php artisan migrate`.

---

## Lisensi

Project ini memakai framework Laravel (MIT). Sesuaikan lisensi aplikasi bisnis Anda jika diperlukan.
