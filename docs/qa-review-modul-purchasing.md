# Quality Assurance Review — Modul Purchasing (PO, GR, Supplier)

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Supplier

### 1.1 Supplier code — race condition di documentNumberService

**Lokasi:** `app/Http/Controllers/ERPPurchasingController.php:85-88`
```php
$code = $this->documentNumberService->next('purchasing', 'supplier_code', [
    'prefix' => 'SUP',
    'padding_length' => 3,
]);
```
**Masalah:** `lockForUpdate()` di DocumentNumberService sudah aman. Tapi validasi unique `code` di database tidak ada — bisa duplicate code jika sequence di-reset.
**Risiko:** Sedang — Duplicate supplier code.
**Rekomendasi:** Tambah `unique` constraint di kolom `code` tabel vendors.

### 1.2 Supplier update — mass assignment via spread

**Lokasi:** `ERPPurchasingController.php:133`
```php
$supplier->update($validated);
```
**Masalah:** Spread `$validated` langsung. Field `is_active` bisa di-update tanpa validasi tambahan. Sudah divalidasi di atas, tapi risk jika ada field baru ditambah tanpa validasi.
**Risiko:** Rendah — Mass assignment.
**Rekomendasi:** Explicit pick fields: `$supplier->update($validated->only(['name', 'email', ...]))`.

### 1.3 Supplier search — no index for `code`, `phone`

**Lokasi:** `ERPPurchasingController.php:46-49`
```php
->where('code', 'like', '%'.$q.'%')
->orWhere('name', 'like', '%'.$q.'%')
->orWhere('phone', 'like', '%'.$q.'%')
```
**Masalah:** LIKE dengan wildcard prefix (`%...`) tidak bisa menggunakan index. Full table scan.
**Risiko:** Sedang — Performa.
**Rekomendasi:** Tambah fulltext index atau gunakan search engine (Meilisearch/Algolia).

---

## 2. Purchase Order

### 2.1 Hard delete lines on update

**Lokasi:** `ERPPurchasingController.php:294`
```php
$purchaseOrder->lines()->delete();
foreach ($lines as $line) {
    $purchaseOrder->lines()->create($line);
}
```
**Masalah:** Semua line dihapus dan dibuat ulang setiap update. Ini menghilangkan histori line ID, dan jika ada error di tengah loop, data hilang.
**Risiko:** Sedang — Data loss on partial failure.
**Rekomendasi:** 
- Wrap di transaction (sudah)
- Update existing lines atau delete hanya yang tidak ada di request baru
- Simpan line ID di request untuk diff

### 2.2 PO status guard hanya 2 status

**Lokasi:** `ERPPurchasingController.php:265`
```php
if (! in_array($purchaseOrder->status->value, [DocumentStatus::Draft->value, DocumentStatus::Submitted->value], true)) {
```
**Masalah:** Guard hanya cek draft/submitted. Jika ada status baru ditambahkan ke enum, guard ini perlu diupdate manual.
**Risiko:** Rendah — Maintainability.
**Rekomendasi:** Gunakan method di model: `$purchaseOrder->isEditable()`.

### 2.3 Supplier + Product query di setiap halaman PO

**Lokasi:** `ERPPurchasingController.php:176-182,254-260`
```php
'suppliers' => Vendor::query()->orderBy('name')->get(['code', 'name']),
'products' => MasterProduct::query()->where('status', 'active')->...->get([...]),
```
**Masalah:** Query supplier dan produk jalan di setiap render halaman PO (index dan show). Data tidak berubah sering.
**Risiko:** Rendah — Performa.
**Rekomendasi:** Cache hasil query dengan TTL pendek (5-10 menit).

---

## 3. Goods Receipt

### 3.1 GRN number generation — potensi duplicate

**Lokasi:** Terkait `DocumentNumberService`
**Masalah:** Sama dengan supplier code — tidak ada unique constraint tambahan.
**Risiko:** Sedang.
**Rekomendasi:** Unique constraint `[number, company_id]`.

### 3.2 Receiving qty > PO qty

Perlu dicek apakah ada validasi bahwa `received_qty` tidak melebihi `qty` di PO line. Jika tidak, risk over-receiving.
**Rekomendasi:** Validasi `received_qty <= qty` per line.

---

## Ringkasan Prioritas Purchasing

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Critical** | Hard delete lines on PO update → data loss risk | `ERPPurchasingController.php:294` |
| **High** | Supplier/PRODUCT query tanpa pagination di PO show | `ERPPurchasingController.php:176` |
| **Medium** | LIKE search tanpa index | `ERPPurchasingController.php:46` |
| **Medium** | No unique constraint supplier code | `Vendor.php` migration |
| **Low** | Over-receiving GR validation | Perlu dicek |
