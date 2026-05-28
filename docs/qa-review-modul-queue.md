# Quality Assurance Review — Queue & Jobs

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Job Classes

**Tidak ada file job di `app/Jobs/`** — seluruh 132 migration dan 28 service files tidak menggunakan queue job sama sekali.

**Masalah:** Beberapa operasi yang seharusnya di-queue:
- Export Excel (bulanan, anggota) → sync GET → timeout risk
- Generate PDF invoice → sync → slow response
- Send email → sync → slow response
- Rebuild inventory stock → sync user nunggu
- Recalculate COGS → sync
- Backfill operations (cash accounts, unit costs, dll)

**Risiko:** **Critical** — Semua operasi berat berjalan synchronously, blocking user request.

## 2. Operasi yang Perlu Di-Queue

### 2.1 Export Excel
**Lokasi:** `routes/web.php:357-358`
**Status:** Sync GET. Untuk ribuan row, bisa timeout.

### 2.2 Generate PDF
**Lokasi:** Invoice download, sales note download, receipt download
**Status:** Sync. PDF di-generate di setiap request.

### 2.3 Rebuild Inventory Stock
**Lokasi:** `ERPAccountingUtilityController::rebuildInventoryStocks`
**Status:** Sync POST. Operasi berat (update banyak row).

### 2.4 Backfill Operations
**Lokasi:** Semua utility backfill endpoints (13 endpoints)
**Status:** Sync POST. Bisa timeout untuk data besar.

## 3. Queue Configuration

**Masalah:** Queue driver tidak jelas dari kode. Tidak ada konfiguran job middleware, retry, atau failed job handling.
**Rekomendasi:**
- Set `QUEUE_CONNECTION=database` di .env
- Buat jobs table: `php artisan queue:table`
- Tambah job retry logic
- Implementasi `ShouldBeUnique` utk backfill jobs

### 3.1 Job batching untuk backfill

**Rekomendasi:** Untuk backfill yang memproses ribuan record, gunakan `Bus::batch()` + chunk:
```php
Bus::batch($chunks->map(fn ($chunk) => new BackfillJob($chunk)))
    ->then(fn () => ...)
    ->catch(fn () => ...)
    ->dispatch();
```

---

## Ringkasan Prioritas Queue

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Critical** | Tidak ada queue job → semua operasi sync | `app/Jobs/` kosong |
| **High** | Export sync → timeout risk | `routes/web.php:357` |
| **High** | PDF generate sync | Controller download method |
| **Medium** | Backfill utility sync | 13 endpoints utility |
| **Low** | No failed job handling | - |
