# Implementation Plan — QA Fixes

**Project:** PaymentSystemOCN
**Total Issues:** 145 (38 ✅ Fixed, 15 🔄 Phase 2, 50 ❌ Not Fixed, 4 🔄 Partial)
**Priorities:** 10 Critical, 20 High, 31 Medium, 30 Low, 16 Needs Check

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

## Phase 3: Medium Priority (31 Issues) — Estimasi: 10-15 hari

### 3.1 Accounting (6 items)
| Issue | Effort |
|-------|--------|
| COA Setting hardcoded definitions → config file | 3h |
| Journal Entry no soft delete/void → implement void pattern | 4h |
| Opening Balance duplicate → unique validation | 1h |
| Cash In/Out company scope → global scope | 2h |
| COA upsert unique validation | 1h |
| Duplicated perPage array | 30m |

### 3.2 Purchasing (3 items)
| Issue | Effort |
|-------|--------|
| Supplier code unique constraint | 1h |
| LIKE search tanpa index → fulltext index | 2h |
| GRN number duplicate → unique constraint | 1h |

### 3.3 Inventory (3 items)
| Issue | Effort |
|-------|--------|
| Denormalized stock field → DB event sync | 3h |
| Warehouse no soft delete | 1h |
| MismatchSummary overhead → cache | 2h |

### 3.4 CRM (3 items)
| Issue | Effort |
|-------|--------|
| Cross-company customer → company_id | 2h |
| Duplicate detection email/phone | 2h |
| LIKE search → fulltext index | 2h |

### 3.5 HR (2 items)
| Issue | Effort |
|-------|--------|
| Base_salary exposure → filter by role | 1h |
| Legal file upload validation | 1h |

### 3.6 Reporting (3 items)
| Issue | Effort |
|-------|--------|
| Missing composite index → migration | 1h |
| Date range validation | 1h |
| ONLY_FULL_GROUP_BY error | 1h |

### 3.7 Notification (2 items)
| Issue | Effort |
|-------|--------|
| N+1 markAllRead → bulk upsert | 1h |
| buildFor() overhead → cache | 2h |

### 3.8 Payment Integration (2 items)
| Issue | Effort |
|-------|--------|
| Payment method active validation | 1h |
| Float comparison → bccomp | 1h |

### 3.9 Queue (2 items)
| Issue | Effort |
|-------|--------|
| Rebuild stock sync → queue | 3h |
| Backfill operations sync → batch job | 4h |

### 3.10 Chatbot (1 item)
| Issue | Effort |
|-------|--------|
| Chat history localStorage → clear on logout | 1h |

### 3.11 Project (1 item)
| Issue | Effort |
|-------|--------|
| LIKE search tanpa index → fulltext | 2h |

### 3.12 R&D (1 item)
| Issue | Effort |
|-------|--------|
| Summary queries cache | 1h |

### 3.13 CMS (2 items)
| Issue | Effort |
|-------|--------|
| Uncache stats queries → cache | 1h |
| IP anonymization | 1h |

### 3.14 Auth (3 items)
| Issue | Effort |
|-------|--------|
| Session timeout config | 30m |
| Disable self-registration | 30m |
| Logout from all devices | 2h |

---

## Phase 4: Low Priority (30 Issues) — Estimasi: 5-8 hari

### 4.1 UI/UX Improvements (ERP Dasar)
| Issue | Effort |
|-------|--------|
| Icon map dynamic registration | 3h |
| Loading state reorder | 1h |
| ARIA labels | 3h |
| Hardcoded icon map warning | 1h |

### 4.2 Accounting (4 items)
| Issue | Effort |
|-------|--------|
| Deadlock retry logic | 2h |
| CashAccount validation consistency | 1h |
| Supplier payment float comparison | 1h |

### 4.3 Purchasing (3 items)
| Issue | Effort |
|-------|--------|
| Mass assignment spread → explicit | 1h |
| PO status guard → isEditable() method | 1h |
| Over-receiving validation | 2h |

### 4.4 Sales (1 item)
| Issue | Effort |
|-------|--------|
| Stock increment atomic → DB::raw | 1h |

### 4.5 CRM (3 items)
| Issue | Effort |
|-------|--------|
| Mass assignment audit | 2h |
| Users query cache | 1h |
| Activity notification integration | 4h |

### 4.6 HR (1 item)
| Issue | Effort |
|-------|--------|
| Orphan file cleanup → model event | 2h |

### 4.7 Reporting (1 item)
| Issue | Effort |
|-------|--------|
| Export throttle | 30m |

### 4.8 File Upload (5 items)
| Issue | Effort |
|-------|--------|
| Logo URL relative → absolute | 30m |
| Delete old logo update | 1h |
| CMS disk reference | 1h |
| File download query param | 2h |
| Storage symbolic link production | 30m |

### 4.9 Notification (2 items)
| Issue | Effort |
|-------|--------|
| Poll exponential backoff | 1h |
| Route PATCH/DELETE confusion | 30m |

### 4.10 Payment (2 items)
| Issue | Effort |
|-------|--------|
| TeamDistribution lock | 2h |
| Receipt download URL | 1h |

### 4.11 Personal Finance (3 items)
| Issue | Effort |
|-------|--------|
| Soft delete transactions | 1h |
| Currency validation | 1h |
| Investment net worth optimization | 2h |

### 4.12 Invoice (2 items)
| Issue | Effort |
|-------|--------|
| PDF cache | 3h |
| Route model binding | 1h |

### 4.13 Auth (2 items)
| Issue | Effort |
|-------|--------|
| Profile company_id | 1h |
| Email verification | 1h |
| Login rate limit ketat | 30m |

---

## Phase 5: Needs Check (16 Items) — Estimasi: 3-5 hari

| # | Issue | Action | Effort |
|---|-------|--------|--------|
| 1 | Stock transfer validation | Review code, tambah validasi | 1h |
| 2 | Pipeline stages hardcoded? | Review model, buat CRUD jika perlu | 3h |
| 3 | Mail template fallback | Buat text fallback | 1h |
| 4 | Queue config unclear | Setup .env + config | 2h |
| 5 | Receipt generate on-the-fly? | Review, implement cache | 2h |
| 6 | Budget convert validation | Tambah rules | 1h |
| 7 | Summary query optimization | Review, implement aggregate | 3h |
| 8 | Model fillable audit (28 models) | Systematic review | 8h |
| 9 | getInvoiceDocument N+1 | Review service class | 2h |
| 10 | Storage cleanup event | Implement model event | 2h |
| 11 | File upload validation R&D | Review, tambah rules | 1h |
| 12 | File upload CMS validation | Review, tambah rules | 1h |
| 13 | Legal download path traversal | Implement signed URL | 3h |
| 14 | Notification center cache | Implement cache layer | 2h |
| 15 | Profile company_id exposure | Review + fix | 1h |
| 16 | Login rate limit | Adjust RateLimiter | 30m |

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

Phase 3 (Medium) ── Bergantung Phase 1.6 (Queue) untuk beberapa items

Phase 4 (Low) ── Independen

Phase 5 (Needs Check) ── Independen, perlu review kode
```

## Total Estimasi

| Phase | Issues | Estimasi |
|-------|--------|----------|
| Phase 0 (Selesai) | 17 | ✅ |
| Phase 1 (Critical) | 10 | ✅ |
| Phase 2 (High) | 20 | ✅ |
| Phase 3 (Medium) | 31 | 10-15 hari |
| Phase 4 (Low) | 30 | 5-8 hari |
| Phase 5 (Needs Check) | 16 | 3-5 hari |
| **Total** | **95 tersisa** | **18-28 hari** |
