# Quality Assurance Review — Modul Project

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Project Overview

### 1.1 N+1 query — load ALL projects tanpa pagination

**Lokasi:** `app/Http/Controllers/ProjectController.php:39-48`
```php
$projects = Project::query()
    ->with(['cashIns', 'cashOuts', 'tasks', 'materials', 'projectTypeDefinition'])
    ->latest()
    ->get(); // <-- NO PAGINATION!
```
**Masalah:** Semua project di-load ke memory dengan 5+ relasi. Untuk bisnis dengan >100 project, ini memory intensive. Setiap relasi (`cashIns`, `cashOuts`, `tasks`, `materials`) bisa memiliki ratusan record per project.
**Risiko:** **Critical** — Memory overflow, slow page load.

**Rekomendasi:**
- Tambah pagination (25 per page)
- Atau gunakan summary/aggregate query langsung dari DB:
```php
$summary = DB::table('projects')
    ->selectRaw('COUNT(*) as total')
    ->selectRaw('SUM(contract_value) as total_value')
    ->selectRaw("SUM(CASE WHEN status = 'berjalan' THEN 1 ELSE 0 END) as active_count")
    ->first();
```

### 1.2 FlatMap tasks dan materials — collection in memory

**Lokasi:** `ProjectController.php:61,69`
```php
$tasks = $projects->flatMap->tasks->values();
$materials = $projects->flatMap->materials->values();
```
**Masalah:** Semua tasks dan materials dari semua project di-flatMap ke single collection di memory. Untuk 100 project dengan masing-masing 10 tasks dan 20 materials = 1000 + 2000 items.
**Risiko:** Sedang — Memory.

### 1.3 Summary query bisa di-optimasi dengan single SQL

**Lokasi:** `ProjectController.php:50-91`
**Masalah:** Semua summary (total contract, collected, spent, status counts, task summary, material summary) dihitung dari collection di PHP, bukan SQL aggregate. Bisa lebih efisien dengan query langsung.
**Rekomendasi:**
```php
$summary = Project::query()
    ->selectRaw('COUNT(*) as count')
    ->selectRaw('SUM(resolveListTotalValue()) as total_value') // atau stored
    ->first();
```

---

## 2. Project CRUD

### 2.1 Eager loading — `with()` terlalu banyak

**Lokasi:** `TeamDistributionController.php:31`
```php
$projectQuery = Project::query()
    ->with(['cashIns', 'referrals', 'cashOuts', 'materials.product', 'convertedBudget.items', 'teamDistributions'])
```
**Masalah:** 6 relasi di-load untuk setiap project di list. Ini bisa menyebabkan join atau multiple queries yang berat.
**Risiko:** Tinggi — Performa.

**Rekomendasi:** Load relasi hanya saat detail page (show), bukan di list/index.

### 2.2 Project filter — LIKE tanpa index

**Lokasi:** Across project controllers
**Masalah:** Pencarian menggunakan `LIKE '%...%'` di field `name`, `client_name`. Tidak bisa menggunakan index.
**Rekomendasi:** Tambah fulltext index di `projects(name, client_name)`.

### 2.3 TeamDistribution — UUID validation

**Lokasi:** `TeamDistributionController.php:26`
```php
if (filled($projectId) && ! Str::isUuid($projectId)) {
    return redirect()->route('team-distribution.calculator');
}
```
**Masalah:** Silent redirect jika project_id bukan UUID. User tidak dapat feedback error.
**Risiko:** Rendah — UX.
**Rekomendasi:** Tampilkan flash error message.

---

## 3. Project Budget

### 3.1 Budget convert — validasi minimal items

**Lokasi:** `ProjectBudgetController.php`
**Masalah:** Budget bisa di-convert ke project tanpa memiliki budget items. Risk project tanpa scope.
**Rekomendasi:** Validasi `budget_items` minimal 1 item sebelum convert.

### 3.2 Budget PDF — sync generation

**Lokasi:** `ProjectBudgetController::pdf()`
**Masalah:** PDF budget di-generate synchronously. Untuk budget dengan banyak item, bisa slow.
**Rekomendasi:** Queue job untuk generate PDF, atau streaming response.

---

## Ringkasan Prioritas Project

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Critical** | ALL projects loaded without pagination + 5 relations | `ProjectController.php:39` |
| **High** | Excessive eager loading in list | `TeamDistributionController.php:31` |
| **Medium** | FlatMap → memory collection | `ProjectController.php:61,69` |
| **Medium** | LIKE search tanpa index | `ProjectController.php:34` |
| **Low** | Silent redirect UUID validation | `TeamDistributionController.php:26` |
| **Low** | Budget PDF sync | `ProjectBudgetController.php` |
