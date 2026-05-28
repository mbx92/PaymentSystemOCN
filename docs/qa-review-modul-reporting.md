# Quality Assurance Review — Modul Reporting & Export

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Company Revenue Report

### 1.1 Repeated query pattern — 3x almost identical queries

**Lokasi:** `app/Http/Controllers/ERPReportingController.php:30-122`
**Masalah:** Query `JournalLine::query()->join('accounts')->join('journal_entries')->leftJoin('companies')` diulang **3 kali** dengan variasi groupBy berbeda:
- Line 30-60: main revenue rows
- Line 62-91: source pivot
- Line 93-122: account breakdown
Setiap query full table scan pada `journal_lines`.
**Risiko:** Tinggi — Performa buruk untuk dataset besar.

**Rekomendasi:**
- Gunakan single query dengan multiple groupBy
- Atau simpan hasil aggregate ke summary table (materialized view)
- Cache hasil report dengan TTL

### 1.2 No date range validation

**Lokasi:** `ERPReportingController.php:26`
```php
[$selectedYear, $dateFrom, $dateTo] = $this->resolveReportingDateRange($request);
```
Perlu dicek: apakah ada validasi bahwa `date_from <= date_to` dan range tidak melebihi 1 tahun?
**Rekomendasi:** Validasi max range 365 hari untuk mencegah query terlalu berat.

### 1.3 Raw SQL with groupBy — potential mode only_full_group_by error

**Lokasi:** `ERPReportingController.php:44-52`
```php
->groupBy('journal_entries.company_id', 'companies.name')
->selectRaw('SUM(journal_lines.credit - journal_lines.debit) as revenue_total')
```
**Masalah:** Field di SELECT harus ada di GROUP BY atau di aggregate function. `companies.name` sudah di GROUP BY — OK. Tapi di MySQL dengan `ONLY_FULL_GROUP_BY`, bisa error jika ada field lain dari `companies` yang tidak sengaja masuk.
**Risiko:** Sedang — SQL error.
**Rekomendasi:** Explicit select hanya field yang di-GROUP BY atau di-aggregate.

---

## 2. Profit & Loss Report

### 2.1 Complex query tanpa index

Report laba-rugi melakukan JOIN antar `journal_lines`, `accounts`, `journal_entries`, `companies`. Perlu dicek apakah ada index di:
- `journal_lines.account_id`
- `journal_lines.journal_entry_id`
- `journal_entries.company_id`
- `journal_entries.entry_date`

**Rekomendasi:** Tambah composite index: `(entry_date, company_id)` di `journal_entries`, `(journal_entry_id, account_id)` di `journal_lines`.

---

## 3. Export (Excel)

### 3.1 Export via GET — no queue

**Lokasi:** `routes/web.php:357-358`
```php
Route::get('export/bulanan', [ReportController::class, 'exportMonthlyExcel'])->name('export.monthly');
Route::get('export/anggota', [ReportController::class, 'exportMemberPaymentsExcel'])->name('export.member-payments');
```
**Masalah:** Export dijalankan synchronously via GET request. Untuk dataset besar, bisa timeout (30s default PHP).
**Risiko:** Tinggi — Timeout, user experience buruk.

**Rekomendasi:**
- Pindahkan export ke queue job
- Kirim notifikasi saat export selesai
- Atau gunakan streaming download

### 3.2 No throttle di export route

Export route tidak memiliki rate limiting. User bisa request export berkali-kali.
**Rekomendasi:** Tambah `throttle:5,1` untuk export routes.

---

## Ringkasan Prioritas Reporting

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Critical** | 3x repeated full table scan query | `ERPReportingController.php:30-122` |
| **High** | Sync export → timeout risk | `routes/web.php:357` |
| **Medium** | Missing composite index | `journal_entries` table |
| **Medium** | No date range validation | `ERPReportingController.php:26` |
| **Low** | Throttle export routes | `routes/web.php:357` |
