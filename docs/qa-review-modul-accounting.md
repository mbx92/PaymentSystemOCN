# Quality Assurance Review — Modul Accounting (COA, Journal Entry, Cashflow, Payment)

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. COA & Chart of Accounts

### 1.1 Account model — missing company_id

**Lokasi:** `app/ERP/Accounting/Models/Account.php`
**Masalah:** COA tidak memiliki `company_id`. Di multi-bisnis, chart of accounts harus per-company.
**Risiko:** Tinggi — Data inconsistency antar perusahaan.
**Rekomendasi:** Tambah `company_id` + FK + scope query.

### 1.2 COA Setting hardcoded definitions

**Lokasi:** `app/Http/Controllers/ERPAccountingCoaSettingsController.php:21-89`
**Masalah:** Definitions akun COA (`pos_sale_cash_account`, `pos_sale_revenue_account`, dll) di-hardcode di controller method. Tidak extensible tanpa edit controller.
**Risiko:** Sedang — Maintainability.
**Rekomendasi:** Extract ke config file (`config/erp-coa.php`) atau service class.

### 1.3 COA Setting upsert tanpa unique validation

**Lokasi:** `ERPAccountingCoaSettingsController`
**Masalah:** Tidak ada validasi duplikasi key+company_id saat upsert COA setting.
**Risiko:** Rendah — Duplikasi data.
**Rekomendasi:** Tambah `unique` constraint di migration dan validasi.

---

## 2. Journal Entry & GL Posting

### 2.1 GlPostingService — potensi unbalanced journal

**Lokasi:** `app/ERP/Accounting/Services/GlPostingService.php`
**Masalah:** Dari pattern pemanggilan, total debit dan credit dihitung di controller, bukan diservice. Risk unbalanced entry jika ada bug perhitungan.
**Risiko:** Tinggi — Kerusakan data akuntansi.
**Rekomendasi:** Validasi balance (`sum(debit) === sum(credit)`) di GlPostingService sebelum save.

### 2.2 Journal Entry — no soft delete / void

**Lokasi:** `app/ERP/Accounting/Models/JournalEntry.php`
**Masalah:** Jurnal yang salah tidak bisa di-void, hanya bisa di-edit langsung. Risk inkonsistensi audit trail.
**Risiko:** Sedang — Audit trail integrity.
**Rekomendasi:** Implementasi pattern void (status = 'void' + reversing entry).

### 2.3 OpeningBalanceController — tidak ada validasi company scope

**Lokasi:** `app/Http/Controllers/ERPAccountingOpeningBalanceController.php`
**Masalah:** Opening balance disimpan tanpa company_id atau validasi apakah periode sudah ada opening balance.
**Risiko:** Sedang — Duplikasi opening balance.
**Rekomendasi:** Validasi unique `[company_id, fiscal_period_id]`.

---

## 3. Cashflow

### 3.1 CashflowController — duplicated perPage logic

**Lokasi:** `CashflowController`
**Masalah:** Mendifinisikan ulang `$allowed` array untuk perPage, duplikasi dari base `Controller::resolvedPerPage()`.
**Risiko:** Rendah — Duplikasi kode.
**Rekomendasi:** Gunakan `$this->resolvedPerPage($request)` dari base controller.

### 3.2 Cash In / Cash Out — company scope

**Lokasi:** `app/Http/Controllers/CashInController.php`, `CashOutController.php`
**Masalah:** Query tidak discope by company (fallback via `journalEntry.company_id` di overview, bukan langsung di model).
**Risiko:** Sedang — Data leakage antar perusahaan.
**Rekomendasi:** Tambah global scope atau explicit `whereHas('journalEntry', fn => ...)` di setiap query.

---

## 4. Payment (Payable / Member)

### 4.1 ERPAccountingPaymentController::index — N+1 query

**Lokasi:** `app/Http/Controllers/ERPAccountingPaymentController.php:35-74`
**Masalah:** `Payable::query()->with([...])->get()` — load SEMUA payables tanpa pagination ke memory. Untuk bisnis dengan ribuan hutang, ini memory overflow.
```php
$payables = Payable::query()
    ->with(['vendor', 'purchaseOrder', 'goodsReceipt', 'payments.cashAccount'])
    ->orderByRaw('(amount - paid_amount) desc')
    ->get() // <-- NO PAGINATION!
    ->map(...)
```
**Risiko:** **Critical** — Memory overflow, request timeout.

**Rekomendasi:**
```php
$payables = Payable::query()
    ->with(...)
    ->orderByRaw(...)
    ->paginate($this->resolvedPerPage($request)) // <-- ADD PAGINATION
    ->through(...)
```

### 4.2 Store supplier payment — potential race condition

**Lokasi:** `ERPAccountingPaymentController.php:149-166`
**Masalah:** `lockForUpdate()` sudah digunakan, baik. Tapi tidak ada retry logic untuk deadlock.
**Risiko:** Rendah — Deadlock.
**Rekomendasi:** Tambah `DB::transaction()` dengan retry.

### 4.3 CashAccount validation rule tidak konsisten

**Lokasi:** `ERPAccountingPaymentController.php:145`
```php
'cash_account_id' => Account::cashBankIdValidationRules(),
```
**Masalah:** Validation rule diambil dari method model. Jika method berubah, tidak ada type safety.
**Risiko:** Rendah.
**Rekomendasi:** Extract ke Form Request.

---

## Ringkasan Prioritas Accounting

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Critical** | Payables query tanpa pagination → memory overflow | `ERPAccountingPaymentController.php:39` |
| **High** | COA tanpa company_id | `Account.php` |
| **High** | Unbalanced journal risk | `GlPostingService.php` |
| **Medium** | Hardcoded COA definitions | `ERPAccountingCoaSettingsController.php:21` |
| **Medium** | No soft delete / void journal | `JournalEntry.php` |
| **Medium** | Opening balance duplicate risk | `ERPAccountingOpeningBalanceController.php` |
