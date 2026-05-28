# LAPORAN TEMUAN — Modul Akunting & Purchasing (beserta Report)

**Tanggal:** 28 Mei 2026  
**Proyek:** Payment System OCN (Laravel 11 + Vue 3 + Inertia.js)

---

## RINGKASAN

Total **21 temuan** dikategorikan sebagai: **Critical (5)**, **High (7)**, **Medium (5)**, **Low (4)**.  
**Perbaikan:** ✅ A1, A3, A4, A5 selesai. A2 sudah benar (false positive).

---

## A. CRITICAL FINDINGS

### A1. Reverse Journal Entry Without New Entry Record (Destructive) ✅ **FIXED**
**Lokasi:** `CashflowController.php:233-268`  
**Perbaikan:** Method `reverseJournalEntryLines()` yang melakukan in-place update telah dihapus. Digantikan oleh `createReversalJournalEntry()` yang membuat **journal entry baru** (prefix `REV-`) dengan debit/credit terbalik, dan mereferensi jurnal asli via `reversed_entry_id`. Jurnal asli tetap utuh untuk audit trail. Semua caller (`updateCashIn`, `updateCashOut`, `destroyCashIn`, `destroyCashOut`) telah diperbarui.

### A2. Payable Document Status Tidak Pernah Update ke `partially_paid` / `paid` ✅ **SUDAH OK (tidak perlu perbaikan)**
**Lokasi:** `ERPAccountingPaymentController.php:193-199`  
**Catatan:** Setelah dicek ulang, status Payable SUDAH diupdate dengan benar:
```php
$lockedPayable->update([
    'paid_amount' => $newPaidAmount,
    'status' => $newPaidAmount >= (float) $lockedPayable->amount
        ? DocumentStatus::Paid
        : DocumentStatus::PartiallyPaid,
]);
```
Status berubah ke `PartiallyPaid` jika `paid_amount < amount`, dan `Paid` jika `paid_amount >= amount`. Temuan ini **false positive**.

### A3. Receivable Model Kurang Relasi & Tidak Terintegrasi ✅ **FIXED**
**Perbaikan:** 
- Model `Receivable` sekarang memiliki relasi `company()`, `customer()`, `journalEntry()`, `payments()` 
- Status di-cast ke `DocumentStatus` enum (konsisten dengan Payable)
- Ditambahkan kolom `company_id`, `journal_entry_id`, `source_module`, `source_reference` ke tabel `receivables` (migration baru)
- Dibuat model `ReceivablePayment` (mirror `PayablePayment`) dengan migration tabel `receivable_payments`


### A1. Reverse Journal Entry Without New Entry Record (Destructive)
**Lokasi:** `CashflowController.php:231-248`  
**Deskripsi:** Method `reverseJournalEntryLines()` melakukan in-place update (membalik debit/credit) langsung di baris jurnal yang *sama*. Ini menghancurkan histori jurnal asli — tidak ada jejak audit bahwa jurnal pernah di-posting dengan nilai aslinya. Setelah di-reverse, jurnal terlihat seperti jurnal baru.  
**Risiko:** Tidak bisa audit trail. Saat ada pemeriksaan, jurnal asli hilang.  
**Rekomendasi:** Buat jurnal balancing baru (credit/debit terbalik) dengan referensi ke jurnal asli (`reversed_entry_id`) — field ini sudah ada di `JournalEntry` model (`$fillable` menyertakan `reversed_entry_id`) tapi tidak pernah dipakai.

### A2. Payable Document Status Tidak Pernah Update ke `partially_paid` / `paid`
**Lokasi:** `ERPPurchasingController.php:834-849`, `ERPAccountingPaymentController` (belum dicek)  
**Deskripsi:** Saat GRN di-post, Payable dibuat dengan `status = DocumentStatus::Posted`. Saat pembayaran supplier dilakukan, Payable `paid_amount` di-update, tapi `status` tidak berubah. Supplier payment controller perlu dicek apakah mengupdate `status` Payable.  
**Risiko:** Payable selalu tampil sebagai "Posted" meski sudah dibayar penuh.  
**Rekomendasi:** Update Payable `status` setelah payment: `partialy_paid` jika `paid_amount < amount`, `paid` jika `paid_amount >= amount`.

### A3. Receivable Model Kurang Relasi & Tidak Terintegrasi
**Lokasi:** `Receivable.php`  
**Deskripsi:** Model Receivable tidak memiliki `BelongsTo` ke `Customer`, `JournalEntry`, atau relasi `payments`. Tidak ada integrasi dengan penagihan project / POS. Sepertinya model ini dibuat tapi belum diimplementasikan (dead code / placeholder).  
**Risiko:** Data piutang tidak bisa dilacak, laporan piutang tidak akurat.  
**Rekomendasi:** Integrasikan Receivable dengan proses invoice project dan POS, atau hapus jika memang belum diperlukan.

### A4. `onHandByProductIdForReorder()` Query N+1 / Inefisien ✅ **FIXED**
**Lokasi:** `ERPPurchasingController.php:933-951`  
**Perbaikan:** Dua query terpisah (`SUM available` + `DISTINCT product_id`) digabung jadi **single query** dengan `COUNT(*)` sebagai `has_rows`. Hasil query di-key-by `master_product_id` dan digunakan langsung di loop PHP. Satu query SQL dieliminasi per pemanggilan.

### A5. `applyJournalSourceFilter()` Query Besar Tanpa Index ✅ **FIXED**
**Lokasi:** `ERPReportingController.php:578-639`  
**Perbaikan:** Method `applyJournalSourceFilter()` sekarang menerima parameter `?string $dateFrom` dan `?string $dateTo`. Filter tanggal diterapkan pada query `CashIn`/`CashOut` saat `pluck('id')`, membatasi data yang dimuat ke memory sesuai rentang report. Semua pemanggil (companyRevenue, profitLossByCompany, generalLedger, trialBalance) telah diperbarui untuk mengirimkan tanggal.

---

## B. HIGH FINDINGS

### B1. PO Update Hapus Semua Lines (Destructive)
**Lokasi:** `ERPPurchasingController.php:293-297`  
**Deskripsi:** Saat update PO, semua line lama di-*delete* (`$purchaseOrder->lines()->delete()`) lalu di-*recreate*. Jika ada referensi ke PO line dari modul lain (misal GRN line), FK akan error. Juga risk data loss jika crash di tengah proses.  
**Risiko:** Data PO line bisa hilang jika ada error di tengah proses atau jika line dirujuk oleh tabel lain.  
**Rekomendasi:** Gunakan pendekatan upsert: update qty untuk line existing, hapus hanya line yang dihapus user.

### B2. Company Scope untuk Cash In/Out di Overview Bermasalah
**Lokasi:** `ERPAccountingOverviewController.php:95-107`  
**Deskripsi:** `applyCompanyScope()` menggunakan OR logic complex yang bisa return data dari company lain — query ini bisa return transaksi yang seharusnya tidak termasuk scope company.  
**Risiko:** Overview bisa menampilkan data company lain, melanggar data isolation.  
**Rekomendasi:** Sederhanakan logic company scope — gunakan `whereHas('journalEntry', fn => where('company_id', $id))` untuk yang sudah di-journal, dan `whereHas('creator', fn => ...)` untuk yang belum.

### B3. Average Cost Calc Potensi Division by Zero
**Lokasi:** `ERPPurchasingController.php:782-786`  
**Deskripsi:** Rumus weighted average: `$oldStock > 0 ? (($oldStock * $oldCost) + ($newQty * $unitPrice)) / ($oldStock + $newQty) : $unitPrice`. Jika `$newQty` = 0, hasilnya `$unitPrice` — tapi `$newQty` seharusnya `qty_received` yang sudah > 0. Validasi terbaru sudah cek `qty_received > 0` di `storeGoodsReceipt()` tapi belum tentu valid jika product dihapus dari PO line.  
**Risiko:** Potensi division by zero atau NaN cost.  
**Rekomendasi:** Tambahkan guard: `if ($newQty <= 0) continue;` sebelum kalkulasi.

### B4. Journal Entry `entry_no` Unique Constraint Race Condition
**Lokasi:** `GlPostingService.php:70-83`  
**Deskripsi:** `nextUniqueEntryNo()` menggunakan loop `do-while` untuk cek uniqueness. Max 20 attempts. Jika melebihi 20, akan return duplicate entry_no dan menyebabkan DB constraint error.  
**Risiko:** Error 500 "Duplicate entry" jika ada high concurrency.  
**Rekomendasi:** Buat unique constraint di DB dan tangani exception, atau gunakan DB sequence/lock yang lebih robust.

### B5. Datang Tertutup / Fiscal Period Reopen Tidak Bisa di Period Sebelumnya
**Lokasi:** `FiscalPeriodService.php:88-114`  
**Deskripsi:** `ensureDateIsOpen()` hanya cek jika ada closed period yang mencakup tanggal tersebut. Jika user tutup bulan Mei, maka transaksi tanggal 15 Mei akan ditolak. Tapi jika user buka kembali (reopen) period, validasi akan lolos — namun journal entry yang sudah diposting di periode tersebut tidak di-reverse.  
**Risiko:** Akuntansi tidak konsisten setelah reopen.  
**Rekomendasi:** Jangan hanya set `is_closed = false` saat reopen — posting otomatis jurnal koreksi atau minta konfirmasi user.

### B6. Source Filter `pos` Tidak Konsisten
**Lokasi:** `ERPReportingController.php:584-590`, `CashflowController.php:498-503`  
**Deskripsi:** Di `ERPReportingController`, source 'pos' menggunakan pendekatan `whereIn('source_module', posModules)`. Tapi di `CashflowController`, POS entries dibangun dari model `PosSale` langsung, bukan dari journal entries. Ini menyebabkan inkonsistensi data antara report dan cashflow.  
**Risiko:** Report GL dengan filter POS bisa berbeda angkanya dengan Cashflow POS.  
**Rekomendasi:** Standardisasi pendekatan — semua ambil dari journal entries, atau semua dari model sumber langsung.

### B7. Tidak Ada Unit Test untuk GLPostingService
**Lokasi:** `tests/`  
**Deskripsi:** `GlPostingService` adalah core engine akuntansi yang menangani double-entry posting. Tidak ada unit test khusus untuk service ini. Hanya ada integration test di `tests/Feature/`.  
**Risiko:** Perubahan pada GL posting logic tidak terdeteksi.  
**Rekomendasi:** Tambahkan unit test untuk validasi balancing, error handling, dan concurrent posting.

---

## C. MEDIUM FINDINGS

### C1. `Receivable.php` — Hanya Placeholder
**Lokasi:** `Receivable.php`  
**Deskripsi:** Model hanya berisi `$fillable` dan `$casts`. Tidak ada relasi `BelongsTo`, `HasMany`, atau method lainnya. Tidak ada controller yang menggunakan model ini.  
**Rekomendasi:** Implementasi atau hapus.

### C2. `buildPosEntries()` Duplicate Logic
**Lokasi:** `CashflowController.php:498-567`, `ERPAccountingOverviewController.php:568-635`  
**Deskripsi:** Kedua controller memiliki method `posOverviewRows()` dan `buildPosEntries()` yang sangat mirip — keduanya query PosSale, map journal entries, dan format output.  
**Rekomendasi:** Extract ke service class terpisah.

### C3. `companySummaries()` Duplicate Loop Pattern
**Lokasi:** `ERPAccountingOverviewController.php:366-415`  
**Deskripsi:** Loop untuk `$posRows`, `$supplierPaymentRows`, `$inventoryRows` hampir identik — masing-masing cek key, merge ke summaryMap. Bisa dipersingkat jadi helper method.  
**Rekomendasi:** Extract loop pattern ke method `mergeIntoSummary()`.

### C4. `report/cashflow` Duplicate Source Options
**Lokasi:** `ERPReportingController.php:640-649`, `CashflowController.php:631-642`  
**Deskripsi:** Kedua controller punya `sourceOptions()` method dengan nilai serupa tapi tidak identik. ERPReportingController tidak punya 'supplier_payment', 'member_payment', 'inventaris'. CashflowController tidak punya 'opening_balance'.  
**Rekomendasi:** Definisikan source options di satu tempat (class constant atau config).

### C5. Parameter `?` vs Named Binding di Raw Queries
**Lokasi:** `ERPAccountingOverviewController.php:437, 455`  
**Deskripsi:** Transaction breakdown menggunakan `whereRaw('COALESCE(...) = ?', [$companyId])`. Pola ini rawan error jika ada perubahan jumlah kolom di COALESCE.  
**Rekomendasi:** Gunakan `->when($companyId, fn => ...)` join-based filter seperti method lainnya.

---

## D. LOW FINDINGS

### D1. Typo / Coding Style
- `ERPReportingController.php:153` — clone query `$typePivotQuery = clone $query;` — tapi $query sudah dimodifikasi oleh `groupBy` dan `selectRaw`, jadi clone mengambil state yang sudah diubah.
- `ERPPurchasingController.php:782` — `$newQty` bukan "new qty" yang sebenarnya, ini adalah `qty_received`, `$oldStock` adalah stock *sebelum* increment (tapi hitungannya pakai `stock - newQty` yang benar.

### D2. `strict_types` Tidak Digunakan
Sebagian besar file tidak mendeklarasikan `declare(strict_types=1)`. Ini bisa menyebabkan type coercion yang tidak diinginkan pada operasi finansial.

### D3. Hardcoded Account Codes
`Account::query()->where('code', '1201')->firstOrFail()` dan `->where('code', '2001')->firstOrFail()` di `ERPPurchasingController.php` — hardcoded kode akun. Jika struktur COA berubah, kode akan error.

### D4. Missing Database Indexes
Beberapa query JOIN menggunakan kolom yang mungkin tidak ter-index:
- `journal_entries.source_module` (digunakan di filter)
- `journal_entries.source_reference` (digunakan di filter)
- `cash_in.category`, `cash_out.category`
- `accounts.type` (digunakan di filter revenue/expense)

---

## STRUKTUR FILE REVIEW

### Accounting Module (Akunting)
| File | Baris | Kualitas | Notes |
|------|-------|----------|-------|
| `app/ERP/Accounting/Models/Account.php` | 134 | ✅ Baik | Clean model, static helpers baik |
| `app/ERP/Accounting/Models/JournalEntry.php` | 47 | ✅ Baik | Auditable trait, cast DocumentStatus |
| `app/ERP/Accounting/Models/JournalLine.php` | 37 | ✅ Baik | Simple, no timestamps |
| `app/ERP/Accounting/Models/Payable.php` | 63 | ✅ Baik | Relasi lengkap |
| `app/ERP/Accounting/Models/PayablePayment.php` | 48 | ✅ Baik | |
| `app/ERP/Accounting/Models/Receivable.php` | 33 | ❌ Placeholder | Tidak punya relasi |
| `app/ERP/Accounting/Models/CoaSetting.php` | 29 | ✅ Baik | |
| `app/ERP/Accounting/Services/GlPostingService.php` | 84 | ⚠️ Medium | Race condition di entry_no |
| `app/ERP/Accounting/Services/CoaSettingService.php` | 39 | ✅ Baik | |
| `app/ERP/Accounting/Support/CashAccountLabelResolver.php` | - | ✅ | |

### Purchasing Module
| File | Baris | Kualitas | Notes |
|------|-------|----------|-------|
| `app/ERP/Purchasing/Models/PurchaseOrder.php` | 73 | ✅ Baik | |
| `app/ERP/Purchasing/Models/PurchaseOrderLine.php` | 40 | ✅ Baik | |
| `app/ERP/Purchasing/Models/GoodsReceipt.php` | 56 | ✅ Baik | |
| `app/ERP/Purchasing/Models/GoodsReceiptLine.php` | 34 | ✅ Baik | |
| `app/ERP/Purchasing/Models/Vendor.php` | 34 | ✅ Baik | |

### Controllers
| File | Baris | Kualitas | Notes |
|------|-------|----------|-------|
| `Controllers/ERPReportingController.php` | 854 | ⚠️ Medium | Banyak duplikasi query, source filter inefficient |
| `Controllers/ERPPurchasingController.php` | 990 | ⚠️ Medium | PO update delete lines, GRN posting complex |
| `Controllers/CashflowController.php` | 801 | ⚠️ Medium | Reverse destructive, duplikasi buildEntries |
| `Controllers/ERPAccountingOverviewController.php` | 739 | ⚠️ Medium | CompanyScope OR complex, duplikasi loop |
| `Controllers/ERPAccountingUtilityController.php` | 680 | ✅ Baik | Utility methods clean |
| `Controllers/ReportController.php` | 322 | ✅ Baik | Delegates to service classes |

### Report Pages (Vue)
| File | Kualitas | Notes |
|------|----------|-------|
| `ERP/Reports/CompanyRevenue.vue` | ✅ | |
| `ERP/Reports/CompanyProfitLoss.vue` | ✅ | |
| `ERP/Reports/GeneralLedger.vue` | ✅ | |
| `ERP/Reports/TrialBalance.vue` | ✅ | |
| `Reports/Cashflow.vue` | ✅ | Legacy |
| `Reports/Monthly.vue` | ✅ | Legacy |
| `Reports/Pos.vue` | ✅ | Legacy |
| `Reports/Projects.vue` | ✅ | Legacy |

---

## KESIMPULAN

1. **Architecture**: Domain-driven design dengan pemisahan `app/ERP/{Module}` sudah baik. Namun ada duplikasi logic antar controller (CashflowController vs ERPAccountingOverviewController vs ERPReportingController).

2. **Data Integrity**: Masalah terbesar ada di **A1** (reverse journal in-place) dan **A5** (inefficient source filter) yang bisa merusak integritas data akuntansi.

3. **Missing Features**: Status payable tidak pernah berubah, receivable belum diintegrasikan, dan fiscal period reopen tidak safety.

4. **Performance**: Query reorder planning dan source filter report perlu optimasi index dan query structure.

5. **Testing**: Tidak ada unit test untuk core `GlPostingService`.

**Prioritas utama**: A1 (reverse journal), A2 (payable status), A5 (report source filter), B1 (PO update destructive).
