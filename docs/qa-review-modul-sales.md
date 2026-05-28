# Quality Assurance Review — Modul Sales / POS

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. POS (Point of Sale)

### 1.1 POS products query — flatMap + collection in memory

**Lokasi:** `app/Http/Controllers/ERPSalesController.php:47-104`
```php
$products = MasterProduct::query()
    ->where('status', 'active')
    ->whereIn('sales_channel', ['pos', 'both'])
    ->with(['channelPrices', 'uomMappings'])
    ->orderBy('name')
    ->get([...]) // <-- ALL products in memory
    ->flatMap(function (MasterProduct $product) {
        // Complex mapping with nested queries
    })
```
**Masalah:** Semua produk POS di-load ke memory, termasuk UoM mappings dan channel prices. Untuk bisnis dengan >1000 produk, ini memory intensive. Operasi `flatMap` dan `map` membuat collection besar.
**Risiko:** **Critical** — Memory overflow, slow page load.

**Rekomendasi:**
- Pagination produk POS (search + load)
- Atau gunakan `cursor()` / chunk
- Atau cache product list dengan TTL pendek

### 1.2 Stock decrement — race condition

**Lokasi:** Di method `checkoutPos` (perlu dicek)
**Masalah:** POS checkout tanpa `lockForUpdate()` pada stock. Risk overselling di concurrent request.
**Risiko:** Tinggi — Overselling.
**Rekomendasi:** Gunakan `DB::transaction()` + `lockForUpdate()` pada product stock saat checkout.

### 1.3 Refund — stock increment tanpa lock

**Lokasi:** `ERPSalesController.php:232`
```php
$product = MasterProduct::query()->lockForUpdate()->find($item->master_product_id);
```
**Masalah:** Refund menggunakan `lockForUpdate()` — sudah baik. Tapi `increment('stock')` tidak atomic di cluster mode (multiple web servers).
**Risiko:** Rendah — Race condition di cluster.
**Rekomendasi:** Gunakan `DB::raw('stock + ?')` atau queue job untuk stock mutation.

### 1.4 Refund cogs journal — hardcoded account code

**Lokasi:** `ERPSalesController.php:300`
```php
$inventoryAccount = Account::query()->where('code', '1201')->firstOrFail();
```
**Masalah:** Account code `1201` hardcoded. Jika COA diubah, refund akan error.
**Risiko:** Tinggi — Runtime error.
**Rekomendasi:** Gunakan `CoaSettingService::resolveAccountByKey('inventory_account')`.

---

## 2. Project Invoice

### 2.1 PDF download — query di setiap request

**Lokasi:** Method `downloadProjectInvoice`, `downloadProjectSalesNote`
**Masalah:** Query database dan generate PDF di setiap request download. Tidak ada caching.
**Risiko:** Sedang — Performa.
**Rekomendasi:** Cache PDF yang sudah di-generate, atau gunakan queue untuk generate.

### 2.2 Payment receipt download — Storage::path()

**Lokasi:** `ERPSalesController.php` (pattern di line 1268-1269)
```php
$path = Storage::disk('public')->path($setting->app_logo_path);
```
**Masalah:** Menggunakan `Storage::path()` yang return filesystem path, bukan URL. Jika storage menggunakan cloud (S3), `path()` tidak valid.
**Risiko:** Tinggi — Error di cloud deployment.
**Rekomendasi:** Gunakan `Storage::url()` atau `Storage::temporaryUrl()` untuk cloud.

---

## Ringkasan Prioritas Sales / POS

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Critical** | Semua produk POS di-load ke memory | `ERPSalesController.php:47` |
| **High** | Hardcoded account code di refund journal | `ERPSalesController.php:300` |
| **High** | Stock race condition checkout | `ERPSalesController.php` (checkout) |
| **High** | Storage::path() tidak kompatibel cloud | `ERPSalesController.php:1268` |
| **Medium** | PDF download tanpa cache | Method download |
