# Quality Assurance Review — Modul HR (Employee, Legal)

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Employee

### 1.1 Validasi unique employee_no sudah baik

**Lokasi:** `app/Http/Controllers/HREmployeeController.php:36,56`
```php
'employee_no' => 'required|string|max:32|unique:employees,employee_no,'.$employee->id,
```
**Status:** ✅ OK — unique validation dengan ignore pada update.

### 1.2 No company_id di Employee

**Masalah:** Employee tidak memiliki `company_id`. Di multi-bisnis, data karyawan harus per-company.
**Risiko:** Tinggi — Data leakage.
**Rekomendasi:** Tambah `company_id` nullable + FK.

### 1.3 Base_salary exposure ke semua user

**Lokasi:** `HREmployeeController.php:23`
```php
'base_salary' => (float) $e->base_salary,
```
**Masalah:** Gaji pokok dikirim ke frontend untuk semua user yang punya akses HR. Tidak ada filtering berdasarkan role (admin/manager bisa lihat, anggota tidak).
**Risiko:** Sedang — Data sensitif terekspos.
**Rekomendasi:** Filter `base_salary` berdasarkan permission user.

### 1.4 Employee delete — soft delete recommended

**Lokasi:** `HREmployeeController.php:81`
```php
$employee->delete();
```
**Masalah:** Hard delete. Histori payroll dan data transaksi akan broken.
**Risiko:** Tinggi — Data loss.
**Rekomendasi:** Gunakan `SoftDeletes`.

---

## 2. Legal Documents

### 2.1 File upload — validasi tipe file

**Lokasi:** `app/Http/Controllers/HRLegalController.php`
Perlu dicek apakah upload divalidasi tipe file (PDF, DOC, dll) dan ukuran.
**Rekomendasi:** Validasi `mimes:pdf,doc,docx` dan `max:10240` (10MB).

### 2.2 File download — direct path exposure

**Masalah:** Apakah file legal di-download via signed URL atau direct path? Jika direct, risk path traversal.
**Risiko:** Tinggi — Security.
**Rekomendasi:** Gunakan `Storage::download()` dengan signed URL.

### 2.3 Storage cleanup — orphan files

**Masalah:** Jika item legal document dihapus, apakah file di storage ikut terhapus?
**Rekomendasi:** Gunakan event `deleted` model untuk cleanup storage.

---

## Ringkasan Prioritas HR

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **High** | Employee tanpa company_id | `Employee.php` |
| **High** | Hard delete employee → data loss | `HREmployeeController.php:81` |
| **Medium** | Base_salary exposure | `HREmployeeController.php:23` |
| **Medium** | Legal file upload validation | `HRLegalController.php` |
| **High** | Direct file path exposure | `HRLegalController.php` |
