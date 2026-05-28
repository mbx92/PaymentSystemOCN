# Quality Assurance Review — Modul CMS & Landing Sites

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. CMS Dashboard

### 1.1 Stats query — 5 queries per request

**Lokasi:** `app/Http/Controllers/CmsModuleController.php:22-27`
```php
'sites_total' => LandingSite::query()->count(),
'sites_active' => LandingSite::query()->where('is_active', true)->count(),
'pages_published' => LandingSitePage::query()->where('is_published', true)->count(),
'media_total' => CmsMedia::query()->count(),
'media_bytes' => (int) CmsMedia::query()->sum('size_bytes'),
```
**Masalah:** 5 query terpisah untuk statistik yang jarang berubah.
**Risiko:** Rendah — Performa.
**Rekomendasi:** Cache stats dengan TTL 5-10 menit.

### 1.2 Visit analytics — load all logs in memory

**Lokasi:** `CmsModuleController.php:72-80`
```php
$landingLogs = CmsAccessLog::query()
    ->where('kind', CmsAccessLog::KIND_LANDING_PUBLIC)
    ->where('created_at', '>=', $since)
    ->get(['created_at', 'ip_address']);

$adminLogs = CmsAccessLog::query()
    ->where('kind', CmsAccessLog::KIND_CMS_ADMIN)
    ->where('created_at', '>=', $since)
    ->get(['created_at', 'ip_address']);
```
**Masalah:** Semua log 14 hari di-load ke memory untuk dihitung per-hari di PHP. Jika ada 100k+ log entries, memory spike.
**Risiko:** Tinggi — Memory.
**Rekomendasi:**
```php
$landingDaily = CmsAccessLog::query()
    ->where('kind', CmsAccessLog::KIND_LANDING_PUBLIC)
    ->where('created_at', '>=', $since)
    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
    ->groupBy('date')
    ->pluck('count', 'date');
```

---

## 2. Landing Sites

### 2.1 Warehouse query di semua halaman CMS

**Lokasi:** `CmsModuleController.php:50-52`
```php
'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'code', 'name']),
```
**Masalah:** Warehouse data dikirim ke halaman CMS yang mungkin tidak membutuhkannya (dashboard, media).
**Risiko:** Rendah — Over-fetching.
**Rekomendasi:** Kirim hanya di halaman sites.

---

## 3. CMS Media

### 3.1 File upload — no size validation

**Lokasi:** `app/Http/Controllers/CmsMediaController.php`
**Masalah:** Risiko upload file besar >50MB.
**Rekomendasi:** Validasi `max:20480` di store/update.

### 3.2 Disk reference from database

**Lokasi:** `CmsMediaController.php:83`
```php
Storage::disk($cmsMedia->disk)->delete($cmsMedia->path);
```
**Masalah:** Disk name disimpan di database. Jika disk di-rename, file tidak bisa di-delete.
**Rekomendasi:** Simpan disk sebagai konstanta, bukan dari DB.

---

## 4. CMS Access Control

### 4.1 CmsAccessLog — IP address storage

**Masalah:** IP address disimpan tanpa hashing/anonymization. Risk GDPR/Privacy compliance.
**Rekomendasi:** Hash IP atau simpan anonymized (first 3 octets only).

---

## Ringkasan Prioritas CMS

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **High** | All 14-day logs loaded in memory | `CmsModuleController.php:72` |
| **Medium** | 5 uncached stats queries | `CmsModuleController.php:22` |
| **Medium** | IP address stored without hash | `CmsAccessLog.php` |
| **Low** | Warehouse data over-fetching | `CmsModuleController.php:50` |
| **Low** | Disk reference from database | `CmsMediaController.php:83` |
