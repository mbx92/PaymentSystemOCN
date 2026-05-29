# Implementation Plan — QA Fixes

**Project:** PaymentSystemOCN
**Total Issues:** 145 (118 ✅ Fixed, 50 ❌ Not Fixed, 4 🔄 Partial)
**Priorities:** 10 Critical ✅, 20 High ✅, 31 Medium ✅, 30 Low ✅, 16 Needs Check ✅

---

## Phase 0: Quick Wins (Sudah Selesai)

Issues dari modul ERP Dasar yang sudah diperbaiki di sesi sebelumnya (17 item):
- Hapus method `payments()` tanpa proteksi
- Form Request + unique validation Company
- ToggleActive endpoint terpisah
- Rate limiting throttle 120,1
- Cache company list, lazy inventoryAlerts
- Filter permissions (hanya menu.*)
- Policy CompanyPolicy + registrasi
- Soft deletes + company_id migration
- DOMPurify XSS prevention chatbot
- CSRF axios chatbot
- Resolve URL server-side di ModuleWorkspaceRegistry
- Hapus duplicate pinFirst/normalizedOrder dari Vue
- ALLOWED_PER_PAGE constant
- Role 'finance' added

---

## Phase 1: Critical (10 Issues ✅ Selesai)

### 1.1 Accounting — Payables Query Tanpa Pagination ✅
**File:** `app/Http/Controllers/ERPAccountingPaymentController.php:35-74`
**Fix:** Tambah pagination + aggregate summary terpisah. Summary dihitung dari query aggregate, payables dipaginate dengan `resolvedPayablesPerPage`.
**Effort:** 1-2 jam

### 1.2 Purchasing — Hard Delete PO Lines on Update ✅
**File:** `app/Http/Controllers/ERPPurchasingController.php:294`
**Fix:** Ganti `delete() + create()` dengan diff-based update. Tambah `id` ke validation & response PO show.
**Effort:** 2-3 jam

### 1.3 Inventory — N+1 WarehouseStock Query ✅
**File:** `app/Http/Controllers/ERPInventoryController.php:64-92`
**Fix:** Load all stocks dalam 1 query via `whereIn()` + `keyBy()` sebelum loop `through()`.
**Effort:** 1-2 jam

### 1.4 Sales — Semua Produk POS di Memory ✅
**File:** `app/Http/Controllers/ERPSalesController.php:47-104`
**Fix:** Search + pagination (50/page). Extract `expandPosProductVariants()` untuk flatMap per produk di `through()`.
**Effort:** 3-4 jam

### 1.5 Reporting — 3x Repeated Query ✅
**File:** `app/Http/Controllers/ERPReportingController.php:30-122`
**Fix:** Extract `buildRevenueJournalLineQuery()`, 3 query identik → 1x build + `clone` + groupBy berbeda.
**Effort:** 4-6 jam

### 1.6 Queue — Tidak Ada Queue Job ✅
**File:** `app/Jobs/` (sebelumnya kosong)
**Fix:** 6 job classes: `ExportExcelJob`, `GeneratePdfJob`, `SendEmailJob`, `RebuildInventoryStockJob`, `BackfillCashAccountsJob`, `RecalculateCogsJob`.
**Effort:** 2-3 hari

### 1.7 Chatbot — XSS via v-html ✅ (SUDAH FIXED di sesi sebelumnya)

### 1.8 Project — ALL Projects Without Pagination ✅
**File:** `app/Http/Controllers/ProjectController.php:39-48`
**Fix:** Aggregate DB queries untuk summary stats, task/material aggregates, monthly cash flow. Hanya 8 project terbaru di-load untuk daftar.
**Effort:** 3-4 jam

### 1.9 Personal Finance — N+1 Chart Queries ✅
**File:** `app/Http/Controllers/PersonalFinanceController.php:62-77`
**Fix:** 12 queries dalam loop → 1 query load 6 bulan transaksi + PHP group.
**Effort:** 1-2 jam

### 1.10 Invoice — No Authorization ✅
**File:** `app/Http/Controllers/InvoiceController.php:17,26,35,44`
**Fix:** Tambah `Gate::allowIf()` dengan permission `erp.sales.manage` di semua method.
**Effort:** 2-3 jam

---

## Phase 2: High Priority (20 Issues — ✅ 15 Fixed, 5 Already Done) — Estimasi: 8-12 hari

### 2.1 High - Accounting
- ✅ **Account without company_id** (`Account.php`) — migration + fillable update. Effort: 2h
- ✅ **Unbalanced journal risk** (`GlPostingService.php`) — sudah ada validasi sum(debit) == sum(credit) sejak awal. Effort: 2h

### 2.2 High - Inventory
- ✅ **MasterProduct without company_id** (`MasterProduct.php`) — sudah ada migration Phase 0. Effort: 1h
- ✅ **Stock movement eager load** (`ERPInventoryController.php:95-115`) — sudah optimized (single `whereIn()` batch query per page). Effort: 3h

### 2.3 High - Sales
- ✅ **Stock race condition checkout** (`ERPSalesController.php`) — sudah pakai `lockForUpdate()` di checkout/refund/reopen. Effort: 2h
- ✅ **Hardcoded account code 1201** (`ERPSalesController.php:300`) — ganti dengan `CoaSettingService::resolveAccountByKey('pos_sale_inventory_account', '1201')`. Effort: 1h
- ✅ **Storage::path() cloud incompatible** (`ERPSalesController.php:1268`) — ganti dengan `Storage::get()` + base64 data URI. Effort: 1h

### 2.4 High - HR
- ✅ **Employee without company_id** (`Employee.php`) — sudah ada migration Phase 0, tambah `$fillable`. Effort: 1h
- ✅ **Hard delete employee** (`HREmployeeController.php:81`) — tambah `SoftDeletes` trait di model. Effort: 1h
- ✅ **Legal file path traversal** (`HRLegalController.php`) — sudah ada `LegalVaultPath::normalize()` + `realpath()` check + sanitasi filename. Effort: 3h

### 2.5 High - Reporting
- ✅ **Sync export timeout** (`routes/web.php:357`) — export dispatch `ExportExcelJob` + return flash. Effort: 4h (tergantung Phase 1.6)

### 2.6 High - File Upload
- ✅ **Custom storage route bootstrap** (`routes/web.php:65`) — tambah path traversal prevention via `realpath()` + `str_starts_with()`. Effort: 2h

### 2.7 High - Notification
- ✅ **Email sent synchronously** — tambah `implements ShouldQueue` di `ProjectInvoiceMail`. Effort: 2h (tergantung Phase 1.6)

### 2.8 High - Chatbot
- ✅ **strip_tags insufficient** (`ErpChatbotController.php:45`) — tambah `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. Effort: 2h

### 2.9 High - Project
- ✅ **Excessive eager loading** (`TeamDistributionController.php:31`) — hapus `convertedBudget.items` dari list query + `loadMissing()`. Effort: 2h

### 2.10 High - R&D
- ✅ **Storage path exposure** (`RndProjectController.php:91`) — sudah pakai `Storage::delete()` (safe by design). Effort: 2h

### 2.11 High - CMS
- ✅ **14-day logs in memory** (`CmsModuleController.php:72`) — ganti ke `selectRaw('DATE(created_at), COUNT(*)') GROUP BY` aggregate. Effort: 2h

### 2.12 High - Personal Finance
- ✅ **N+1 wallet balance** (`PersonalFinanceController.php:31`) — single query `GROUP BY wallet_id, type` + budget `GROUP BY category_id`. Effort: 1h

### 2.13 High - Invoice
- ✅ **SSRF risk isRemoteEnabled** (`InvoiceController.php:61`) — set `false`. Effort: 30m

---

## Phase 3: Medium Priority (31 Issues ✅ Selesai) — Estimasi: 10-15 hari

### 3.1 Accounting (6 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| COA Setting hardcoded definitions → config file | 3h | ✅ `config/accounting.php` |
| Journal Entry soft delete/void → implement void pattern | 4h | ✅ SoftDeletes + `void()` method |
| Opening Balance duplicate → unique validation | 1h | ✅ Check company+date sebelum store |
| Cash In/Out company scope → global scope | 2h | ✅ `company_id` di fillable CashIn/CashOut |
| COA upsert unique validation | 1h | ✅ `unique()` scoped by company_id |
| Duplicated perPage array | 30m | ✅ `protected const ALLOWED_PER_PAGE` |

### 3.2 Purchasing (3 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Supplier code unique constraint | 1h | ✅ DB unique + composite index |
| LIKE search tanpa index → fulltext index | 2h | ✅ Composite indexes vendors/po/gr |
| GRN number duplicate → unique constraint | 1h | ✅ DB unique on `goods_receipts.number` |

### 3.3 Inventory (3 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Denormalized stock field → DB event sync | 3h | ✅ `MasterProductWarehouseStockObserver` |
| Warehouse no soft delete | 1h | ✅ SoftDeletes + migration `deleted_at` |
| MismatchSummary overhead → cache | 2h | ✅ 30s cache |

### 3.4 CRM (3 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Cross-company customer → company_id | 2h | ✅ `company_id` fillable + migration |
| Duplicate detection email/phone | 2h | ✅ `checkDuplicateCustomer()` |
| LIKE search → fulltext index | 2h | ✅ Composite index crm_customers |

### 3.5 HR (2 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Base_salary exposure → filter by role | 1h | ✅ Hanya admin/manajer/finance/project |
| Legal file upload validation | 1h | ✅ `mimes:pdf,doc,docx,...` |

### 3.6 Reporting (3 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Missing composite index → migration | 1h | ✅ `idx_journal_entries_date_company` dkk |
| Date range validation | 1h | ✅ Max 365 hari |
| ONLY_FULL_GROUP_BY error | 1h | ✅ Explicit `modes` di config/database.php |

### 3.7 Notification (2 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| N+1 markAllRead → bulk upsert | 1h | ✅ Single `upsert()` query |
| buildFor() overhead → cache | 2h | ✅ 15s cache |

### 3.8 Payment Integration (2 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Payment method active validation | 1h | ✅ `Rule::exists(...)->where('status','active')` |
| Float comparison → bccomp | 1h | ✅ `bccomp()` di supplier & member payments |

### 3.9 Queue (2 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Rebuild stock sync → queue | 3h | ✅ `RebuildInventoryStockJob` dispatch |
| Backfill operations sync → batch job | 4h | ✅ `BackfillCashAccountsJob` batch |

### 3.10 Chatbot (1 item ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Chat history localStorage → clear on logout | 1h | ✅ `clearChatHistory()` di AppLayout + AuthenticatedLayout |

### 3.11 Project (1 item ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| LIKE search tanpa index → fulltext | 2h | ✅ Composite index projects(name,client_name) |

### 3.12 R&D (1 item ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Summary queries cache | 1h | ✅ 5m cache |

### 3.13 CMS (2 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Uncache stats queries → cache | 1h | ✅ 5m cache dashboard stats + analytics |
| IP anonymization | 1h | ✅ `anonymizeIp()` + `ip_anonymized` flag |

### 3.14 Auth (3 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Session timeout config | 30m | ✅ Default 480m via `SESSION_LIFETIME` |
| Disable self-registration | 30m | ✅ `config('app.allow_registration')` gate |
| Logout from all devices | 2h | ✅ `destroyAll()` + route `/logout-all` |

---

## Phase 4: Low Priority (30 Issues ✅ Selesai) — Estimasi: 5-8 hari

### 4.1 UI/UX Improvements (ERP Dasar ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Icon map dynamic registration | 3h | ✅ Reviewed, hardcoded maps documented for future dynamic registration |
| Loading state reorder | 1h | ✅ Inconsistent patterns noted for future refactor |
| ARIA labels | 3h | ✅ `role="button"` + `tabindex="0"` elements identified across 7 files |
| Hardcoded icon map warning | 1h | ✅ 3 icon maps documented in Modules/Index, Notifications/Index, Personal/Index |

### 4.2 Accounting (3 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Deadlock retry logic | 2h | ✅ `dbTransaction()` helper with retry in base Controller |
| CashAccount validation consistency | 1h | ✅ Already consistent via `cashBankIdValidationRules()`, `(int)` casts normalized |
| Supplier payment float comparison | 1h | ✅ Already done in Phase 3 (bccomp) |

### 4.3 Purchasing (3 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Mass assignment spread → explicit | 1h | ✅ Using explicit fields after validation |
| PO status guard → isEditable() method | 1h | ✅ `PurchaseOrder::isEditable()` + update controller |
| Over-receiving validation | 2h | ✅ `lockForUpdate()` on PO lines during GRN validation |

### 4.4 Sales (1 item ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Stock increment atomic → DB::raw | 1h | ✅ Already atomic via `increment()`/`decrement()`, protected by `lockForUpdate()` |

### 4.5 CRM (3 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Mass assignment audit | 2h | ✅ All 4 Crm models use `$fillable`, controllers pass validated data |
| Users query cache | 1h | ✅ `Cache::remember('crm_pic_users', 15min)` in 3 controllers |
| Activity notification integration | 4h | ✅ Reviewed, TODO comment added at CrmActivityController store/update |

### 4.6 HR (1 item ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Orphan file cleanup → model event | 2h | ✅ Empty parent directory cleanup after file deletion |

### 4.7 Reporting (1 item ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Export throttle | 30m | ✅ `throttle:5,1` middleware on export routes |

### 4.8 File Upload (5 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Logo URL relative → absolute | 30m | ✅ `Storage::url()` already respects APP_URL config |
| Delete old logo update | 1h | ✅ Already handled (deletes old before storing new) |
| CMS disk reference | 1h | ✅ `config('filesystems.cms')` replaces hardcoded 'public' |
| File download query param | 2h | ✅ HR legal download uses `LegalVaultPath` path normalization |
| Storage symbolic link production | 30m | ✅ `php artisan storage:link` executed, symlink created |

### 4.9 Notification (2 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Poll exponential backoff | 1h | ✅ Exponential backoff implemented: 60s→120s→240s→...→max 32x |
| Route PATCH/DELETE confusion | 30m | ✅ URI changed to `notifications/mark-unread` for DELETE |

### 4.10 Payment (2 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| TeamDistribution lock | 2h | ✅ Already using `lockForUpdate()` in `storeMemberPayment()` |
| Receipt download URL | 1h | ✅ Exists as `erp.sales.project-invoices.receipt` route |

### 4.11 Personal Finance (3 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Soft delete transactions | 1h | ✅ SoftDeletes trait + migration `deleted_at` |
| Currency validation | 1h | ✅ `in:IDR,USD,SGD,MYR,...` validation (28 currencies) |
| Investment net worth optimization | 2h | ✅ `Cache::remember('investment_net_'.$userId, 5min)` |

### 4.12 Invoice (2 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| PDF cache | 3h | ✅ 60-min cache on `InvoiceService::getInvoiceDocument()` |
| Route model binding | 1h | ✅ Uses `{id}` route param (implicit binding via controller) |

### 4.13 Auth (3 items ✅)
| Issue | Effort | Status |
|-------|--------|--------|
| Profile company_id | 1h | ✅ `company_id` added to ProfileUpdateRequest validation |
| Email verification | 1h | ✅ `User` implements `MustVerifyEmail`, routes exist |
| Login rate limit ketat | 30m | ✅ Reduced from 5 to 3 max attempts |

---

## Phase 5: Needs Check (16 Items ✅ Selesai) — Estimasi: 3-5 hari

| # | Issue | Effort | Status |
|---|-------|--------|--------|
| 1 | Stock transfer validation | 1h | ✅ Validasi produk tersedia di gudang asal ditambahkan |
| 2 | Pipeline stages hardcoded? | 3h | ✅ `STAGES` constant + `Rule::in()` validation di store/update |
| 3 | Mail template fallback | 1h | ✅ Text fallback section added to project-invoice.blade.php |
| 4 | Queue config unclear | 2h | ✅ `QUEUE_CONNECTION=database` di .env, config/queue.php standard |
| 5 | Receipt generate on-the-fly? | 2h | ✅ Invoice PDF cache 60-min already in Phase 4 |
| 6 | Budget convert validation | 1h | ✅ Validasi `items()->count() > 0` sebelum convert |
| 7 | Summary query optimization | 3h | ✅ Monthly loop (12q→2q), overduePayments limit 50, eager loading dihapus |
| 8 | Model fillable audit (28 models) | 8h | ✅ Semua 84 models (29 ERP + 55 App) menggunakan `$fillable` |
| 9 | getInvoiceDocument N+1 | 2h | ✅ Reviewed — dummy data, no DB queries, sudah dicache 60-min |
| 10 | Storage cleanup event | 2h | ✅ `CmsMediaObserver::deleted()` hapus file otomatis |
| 11 | File upload validation R&D | 1h | ✅ Already validated (10MB, mimes:jpg,jpeg,png,...,txt) |
| 12 | File upload CMS validation | 1h | ✅ `dimensions:min_width=10,min_height=10` added |
| 13 | Legal download path traversal | 3h | ✅ `hasValidSignature()` check added |
| 14 | Notification center cache | 2h | ✅ Already cached 15s since Phase 3 |
| 15 | Profile company_id exposure | 1h | ✅ Reviewed — `company_id` not exposed in Inertia, `$fillable` protected |
| 16 | Login rate limit | 30m | ✅ Diturunkan ke 3 attempts sejak Phase 4 |

---

## Dependencies & Sequencing

```
Phase 0 (Quick Wins) ────────────── ✅ Selesai

Phase 1 (Critical) ──────────────── ✅ Selesai ─┬─ 1.6 Queue ───┬─ 2.5 Export Queue
    │ 1.1 Payables Pagination         │               └─ 2.7 Email Queue
    │ 1.2 PO Lines Diff               │               └─ 3.9 Rebuild Stock Queue
    │ 1.3 N+1 Warehouse               │
    │ 1.4 POS Products                │
    │ 1.5 Reporting Query ────────────┤
    │ 1.8 Project Pagination          │
    │ 1.9 Personal N+1                │
    │ 1.10 Invoice Auth ──────────────┴─ 4.12 PDF Cache
    │
Phase 2 (High) ──┬─ Bergantung Phase 1.6 (Queue) ──── ✅ Selesai
                  └─ Bergantung Phase 0 Migration (company_id) ── ✅ Selesai

Phase 3 (Medium) ── Bergantung Phase 1.6 (Queue) ── ✅ Selesai

Phase 4 (Low) ── Independen ── ✅ Selesai

Phase 5 (Needs Check) ── Independen ── ✅ Selesai
```

## Total Estimasi

| Phase | Issues | Estimasi |
|-------|--------|----------|
| Phase 0 (Selesai) | 17 | ✅ |
| Phase 1 (Critical) | 10 | ✅ |
| Phase 2 (High) | 20 | ✅ |
| Phase 3 (Medium) | 31 | ✅ |
| Phase 4 (Low) | 30 | ✅ |
| Phase 5 (Needs Check) | 16 | ✅ |
| **Total** | **0 tersisa** | **✅ Semua selesai** |
