# Quality Assurance Review — Modul Inventory (Stock, Warehouse, Master Product)

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Master Product

### 1.1 Stock field di master_products — denormalized

**Lokasi:** `app/Models/MasterProduct.php`
**Masalah:** Field `stock` di tabel `master_products` adalah denormalized value dari `MasterProductWarehouseStock`. Risk inconsistency antara `stock` (master) vs sum `qty` (warehouse_stocks). Query di stock management menggunakan `ProductStockMovement` untuk deteksi mismatch.
**Risiko:** Sedang — Stock inconsistency.
**Rekomendasi:** Gunakan database event untuk sync, atau periodic reconciliation job.

### 1.2 No company_id di MasterProduct

**Masalah:** Master produk tidak memiliki `company_id`. Di multi-bisnis, produk harus per-company.
**Risiko:** Tinggi — Data leakage antar perusahaan.
**Rekomendasi:** Tambah `company_id` nullable + FK.

---

## 2. Stock Management

### 2.1 N+1 di stock management page

**Lokasi:** `app/Http/Controllers/ERPInventoryController.php:64-92`
```php
$products = $paginator->through(function (MasterProduct $product) use ($selectedWarehouseId) {
    $stockRow = MasterProductWarehouseStock::query()
        ->where('master_product_id', $product->id)
        ->where('warehouse_id', $selectedWarehouseId)
        ->first();
```
**Masalah:** Untuk setiap produk di halaman, query `MasterProductWarehouseStock` di-loop satu per satu (N+1). Jika perPage=250, ada 250+1 query tambahan.
**Risiko:** **Critical** — Performa buruk di page dengan banyak produk.

**Rekomendasi:**
```php
// Load all stocks in 1 query before the loop
$stockIds = $paginator->pluck('id');
$stocks = MasterProductWarehouseStock::query()
    ->whereIn('master_product_id', $stockIds)
    ->where('warehouse_id', $selectedWarehouseId)
    ->get()
    ->keyBy('master_product_id');

$products = $paginator->through(function ($product) use ($stocks) {
    $stockRow = $stocks->get($product->id);
    ...
});
```

### 2.2 Stock movement query untuk setiap page load

**Lokasi:** `ERPInventoryController.php:95-115`
```php
$movementRowsByProduct = $selectedWarehouseId && count($idsOnPage) > 0
    ? ProductStockMovement::query()
        ->where('warehouse_id', $selectedWarehouseId)
        ->whereIn('master_product_id', $idsOnPage)
        ->orderByDesc('movement_date')
        ->orderByDesc('id')
        ->get([...])
```
**Masalah:** Query stock movement untuk semua produk di page, meskipun user tidak membuka accordion movement. Data dikirim ke frontend untuk semua produk.
**Risiko:** Sedang — Network overhead, query overhead.
**Rekomendasi:** Lazy-load movement via API (expand accordion → fetch).

### 2.3 Mismatch query — WarehouseStockRebuildService::mismatchSummary

**Lokasi:** `ERPInventoryController.php:117`
```php
$stockMismatch = app(WarehouseStockRebuildService::class)->mismatchSummary($selectedWarehouseId, $idsOnPage)
```
**Masalah:** Service dipanggil setiap render. Jika slow, ini blocking.
**Risiko:** Sedang — Performa.
**Rekomendasi:** Cache result, refresh periodik via job.

---

## 3. Warehouse

### 3.1 Warehouse — no soft delete

**Lokasi:** `app/ERP/Inventory/Models/Warehouse.php`
**Masalah:** Warehouse dihapus permanen. Transaksi lama yang reference warehouse_id akan null.
**Risiko:** Sedang — Referential integrity.
**Rekomendasi:** Tambah `SoftDeletes`.

### 3.2 Stock Transfer — tidak ada validasi stock cukup

Perlu dicek apakah stock transfer memvalidasi bahwa qty tersedia di source warehouse sebelum transfer.
**Rekomendasi:** Validasi `source_qty >= transfer_qty` dengan `lockForUpdate()`.

---

## Ringkasan Prioritas Inventory

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Critical** | N+1 query warehouse_stocks per produk | `ERPInventoryController.php:68` |
| **High** | MasterProduct tanpa company_id | `MasterProduct.php` |
| **High** | Stock movement eager load untuk semua produk | `ERPInventoryController.php:96` |
| **Medium** | No soft delete warehouse | `Warehouse.php` |
| **Medium** | Denormalized stock field inconsistency | `MasterProduct.php` |
