# Quality Assurance Review — File Upload & Storage

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Storage Disk — Public

**Masalah:** Semua file upload menggunakan `Storage::disk('public')` yang disimpan di `storage/app/public/`. Di production, perlu `php artisan storage:link` atau symbolic link.
- Logo ERP
- File legal HR
- Receipt R&D
- CMS media

## 2. Logo Upload

### 2.1 Delete old logo tidak update URL

**Lokasi:** `app/Http/Controllers/ERPAdministrationMasterDataController.php:98-108`
```php
$logoPath = $setting->app_logo_path;
if (($validated['remove_logo'] ?? false) && $logoPath) {
    Storage::disk('public')->delete($logoPath);
}
```
**Masalah:** Jika logo di-remove, `$setting->app_logo_path` tidak di-set ke null di bagian ini. Perlu dicek apakah ada update setelahnya.
**Risiko:** Rendah — Stale data.

### 2.2 Logo URL menggunakan Storage::url()

**Lokasi:** `HandleInertiaRequests.php:74`
```php
'app_logo_url' => $erpSetting?->app_logo_path ? Storage::url($erpSetting->app_logo_path) : null,
```
**Masalah:** `Storage::url()` return relative URL untuk local disk. Jika app di-subfolder, URL bisa salah.
**Risiko:** Rendah — URL broken.
**Rekomendasi:** Gunakan `asset(Storage::url(...))` untuk absolute URL.

---

## 3. CMS Media

### 3.1 Disk selection

**Lokasi:** `app/Http/Controllers/CmsMediaController.php:83`
```php
Storage::disk($cmsMedia->disk)->delete($cmsMedia->path);
```
**Masalah:** Disk disimpan di database. Jika disk name berubah, file tidak bisa di-delete.
**Risiko:** Rendah — Orphan files.
**Rekomendasi:** Gunakan konfigurasi tetap, bukan dari database.

### 3.2 File size validation

Perlu dicek ukuran maksimal file upload di CMS. Risiko upload file besar (>50MB).
**Rekomendasi:** Validasi `max:20480` (20MB) untuk media.

---

## 4. Legal HR Files

### 4.1 Path traversal risk

**Lokasi:** `app/Http/Controllers/HRLegalController.php`
**Masalah:** Jika filename tidak di-sanitize, risk path traversal via `../` di filename.
**Rekomendasi:** Gunakan `$request->file('file')->store('legal', 'public')` yang handle sanitasi otomatis.

### 4.2 File download via query parameter

**Route:** `erp/hr/legal/files/download` dan `erp/hr/legal/files/view`
**Masalah:** Jika menggunakan query parameter untuk path file, risk path traversal.
**Rekomendasi:** Gunakan signed URL atau hash-based file reference.

---

## 5. Custom Storage Route

**Lokasi:** `routes/web.php:65-72`
```php
Route::get('/storage/{path}', function (string $path) {
    $disk = Storage::disk('public');
    if (! $disk->exists($path)) {
        abort(404);
    }
    return response()->file($disk->path($path));
})->where('path', '.*')->name('storage.serve');
```
**Masalah:** Custom route untuk serve storage file. Ini bypass Nginx/Apache direct serve. Untuk setiap request file, Laravel bootstrap penuh di-load. Performa buruk untuk banyak file request.
**Risiko:** Tinggi — Performa, CPU overhead.

**Rekomendasi:**
- Di production, gunakan Nginx direct serve: `try_files $uri $uri/ /index.php?$query_string;`
- Atau simpan storage path di public disk langsung via symbolic link

---

## Ringkasan Prioritas File Upload

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **High** | Custom storage route → full Laravel bootstrap | `routes/web.php:65` |
| **Medium** | Path traversal risk file download | `HRLegalController.php` |
| **Medium** | No file size validation CMS | `CmsMediaController.php` |
| **Low** | Logo URL relative | `HandleInertiaRequests.php:74` |
| **Low** | Orphan files on delete | `CmsMediaController.php:83` |
