# Quality Assurance Review — Modul CRM (Lead, Customer, Pipeline, Activity)

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Lead Management

### 1.1 Mass assignment — spread $validated langsung

**Lokasi:** `app/Http/Controllers/CrmLeadController.php:79`
```php
CrmLead::query()->create($validated);
```
**Masalah:** `$validated` di-spread langsung ke `create()`. Jika ada field baru ditambahkan ke request tapi tidak di `$fillable`, akan silent fail. Tapi jika `$fillable` terlalu longgar, risk mass assignment.
**Risiko:** Rendah — Mass assignment (bergantung pada `$fillable` model).
**Rekomendasi:** Periksa `$fillable` di `CrmLead` model. Explicit pick fields.

### 1.2 Lead search — LIKE dengan wildcard prefix

**Lokasi:** `CrmLeadController.php:19-24`
```php
$sub->where('name', 'like', "%{$q}%")
    ->orWhere('company', 'like', "%{$q}%")
```
**Masalah:** LIKE `%...%` tidak bisa menggunakan index. Full table scan.
**Risiko:** Sedang — Performa.
**Rekomendasi:** Tambah fulltext index atau gunakan pagination dengan search.

### 1.3 Users query untuk PIC dropdown

**Lokasi:** `CrmLeadController.php:51-54`
```php
$users = User::query()
    ->whereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'manajer']))
    ->orderBy('name')
    ->get(['id', 'name']);
```
**Masalah:** Query users dengan role filter di setiap halaman leads. Untuk sistem dengan banyak users, ini overhead.
**Risiko:** Rendah — Performa.
**Rekomendasi:** Cache user list dengan TTL.

---

## 2. Customer Database

### 2.1 Customer duplicate detection

**Masalah:** Tidak ada validasi duplicate customer berdasarkan email/phone. Bisa create customer yang sama berulang kali.
**Risiko:** Sedang — Data duplicate.
**Rekomendasi:** Tambah unique constraint nullable di `email` atau implementasi duplicate detection.

### 2.2 Cross-company customer sharing

**Masalah:** Customer tidak punya `company_id`. Di multi-bisnis, customer bisa di-share atau per-company? Perlu diklarifikasi.
**Risiko:** Sedang — Data boundary.
**Rekomendasi:** Tambah `company_id` nullable + scope, atau tabel pivot `company_customer`.

---

## 3. Pipeline & Activity

### 3.1 Pipeline stages — hardcoded atau di DB?

Perlu dicek apakah pipeline stages di-hardcode atau bisa dikonfigurasi dari database.
**Rekomendasi:** Pipeline stages harus configurable (tambah/edit dari UI).

### 3.2 Activity — no notification integration

**Masalah:** Activity follow-up tidak mengirim notifikasi ke PIC. Risk missed follow-up.
**Rekomendasi:** Integrasi dengan notification system saat activity di-create dengan due date.

---

## Ringkasan Prioritas CRM

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Medium** | Cross-company customer boundary | `CrmCustomer` model |
| **Medium** | LIKE search tanpa index | `CrmLeadController.php:19` |
| **Medium** | Duplicate customer risk | `CrmCustomerController` |
| **Low** | User query tanpa cache | `CrmLeadController.php:51` |
| **Low** | No notification for activity follow-up | `CrmActivityController` |
