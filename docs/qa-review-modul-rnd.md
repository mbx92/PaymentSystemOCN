# Quality Assurance Review — Modul R&D (Research & Development)

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. R&D Project

### 1.1 Summary query — separate queries di index

**Lokasi:** `app/Http/Controllers/RndProjectController.php:47-52`
```php
'summary' => [
    'project_count' => RndProject::query()->count(),
    'active_count' => RndProject::query()->whereIn('status', ['idea', 'research', 'development'])->count(),
    'total_estimated_budget' => (float) DB::table('rnd_budget_items')->sum('total_price'),
    'total_actual_spend' => (float) DB::table('rnd_purchases')->sum('total_price'),
],
```
**Masalah:** 4 query terpisah di setiap page load. Untuk sistem besar, ini overhead.
**Risiko:** Rendah — Performa.

**Rekomendasi:**
```php
'summary' => Cache::remember('rnd_summary', 300, function () {
    return [
        'project_count' => RndProject::query()->count(),
        'active_count' => RndProject::query()->whereIn('status', ['idea', 'research', 'development'])->count(),
        'total_estimated_budget' => (float) DB::table('rnd_budget_items')->sum('total_price'),
        'total_actual_spend' => (float) DB::table('rnd_purchases')->sum('total_price'),
    ];
}),
```

### 1.2 Attachment file — storage.serve route

**Lokasi:** `RndProjectController.php:91`
```php
'url' => route('storage.serve', ['path' => $attachment->path]),
```
**Masalah:** Menggunakan custom route `/storage/{path}` yang me-load full Laravel bootstrap. Juga path exposure.
**Risiko:** Tinggi — Security + Performa.
**Rekomendasi:** Gunakan signed URL atau `Storage::url()` dengan symbolic link.

### 1.3 File upload — no validation

Perlu dicek apakah file upload di R&D divalidasi untuk tipe dan ukuran.
**Rekomendasi:** Validasi `mimes:pdf,doc,docx,jpg,png` dan `max:20480`.

---

## 2. R&D Purchase

### 2.1 Receipt path — Storage::delete di controller

**Lokasi:** `RndPurchaseController.php:34,50`
```php
Storage::disk('public')->delete($rndPurchase->receipt_path);
```
**Masalah:** File storage cleanup di controller logic. Jika ada error di tengah proses, file mungkin tidak terhapus atau transaksi tidak rollback storage.
**Risiko:** Rendah — Orphan files.
**Rekomendasi:** Gunakan event `deleted` model untuk cleanup file.

---

## 3. R&D Report

### 3.1 PDF report — sync generation

**Lokasi:** `RndReportController::pdf()`
**Masalah:** PDF report di-generate synchronously.
**Rekomendasi:** Queue job untuk generate PDF.

---

## Ringkasan Prioritas R&D

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **High** | Storage path exposure via route | `RndProjectController.php:91` |
| **Medium** | 4 separate summary queries | `RndProjectController.php:47` |
| **Medium** | File upload validation | `RndResearchNoteController.php` |
| **Low** | Orphan file cleanup | `RndPurchaseController.php:34` |
| **Low** | PDF sync generation | `RndReportController.php` |
