# Quality Assurance Review — Modul Personal Finance

**Project:** PaymentSystemOCN
**Tanggal:** 2026-05-28

---

## 1. Personal Finance Overview

### 1.1 N+1 query pattern — wallet balance

**Lokasi:** `app/Http/Controllers/PersonalFinanceController.php:31-41`
```php
$walletRows = $wallets->map(function (PersonalWallet $w) {
    $in = (float) PersonalTransaction::query()->where('wallet_id', $w->id)->where('type', 'income')->sum('amount');
    $out = (float) PersonalTransaction::query()->where('wallet_id', $w->id)->where('type', 'expense')->sum('amount');
    // ...
});
```
**Masalah:** Untuk setiap wallet, ada 2 query SUM terpisah (income + expense). Jika user punya 10 wallets → 20 queries.
**Risiko:** Tinggi — Performa.

**Rekomendasi:**
```php
$balances = PersonalTransaction::query()
    ->whereIn('wallet_id', $wallets->pluck('id'))
    ->selectRaw('wallet_id, type, SUM(amount) as total')
    ->groupBy('wallet_id', 'type')
    ->get()
    ->groupBy('wallet_id');
```

### 1.2 Chart query — 12 queries untuk 6 bulan

**Lokasi:** `PersonalFinanceController.php:62-77`
```php
for ($i = 5; $i >= 0; $i--) {
    $chartIncome[] = round((float) PersonalTransaction::query()
        ->where('user_id', $userId)
        ->where('type', 'income')
        ->whereYear('occurred_on', $d->year)
        ->whereMonth('occurred_on', $d->month)
        ->sum('amount'), 2);
    $chartExpense[] = round((float) PersonalTransaction::query()
        ->where('user_id', $userId)
        ->where('type', 'expense')
        ->whereYear('occurred_on', $d->year)
        ->whereMonth('occurred_on', $d->month)
        ->sum('amount'), 2);
}
```
**Masalah:** 12 query terpisah untuk chart 6 bulan. Harusnya single query dengan GROUP BY.
**Risiko:** Tinggi — Performa.

**Rekomendasi:**
```php
$monthlyData = PersonalTransaction::query()
    ->where('user_id', $userId)
    ->where('occurred_on', '>=', now()->subMonths(6)->startOfMonth())
    ->selectRaw("TO_CHAR(occurred_on, 'YYYY-MM') as month, type, SUM(amount) as total")
    ->groupBy('month', 'type')
    ->orderBy('month')
    ->get();
```

### 1.3 Investment net worth — N+1 via groupBy in PHP

**Lokasi:** `PersonalFinanceController.php:94-101`
```php
$investmentNetById = PersonalInvestmentMovement::query()
    ->whereIn('investment_id', $investments->pluck('id'))
    ->get()
    ->groupBy('investment_id')
    ->map(function ($group) {
        return round($group->sum(function (PersonalInvestmentMovement $movement) {
            $amount = (float) $movement->amount;
```
**Masalah:** Movement records di-load semua ke memory lalu di-group. Risiko query besar.
**Rekomendasi:**
```php
$investmentNetById = PersonalInvestmentMovement::query()
    ->whereIn('investment_id', $investments->pluck('id'))
    ->selectRaw('investment_id, SUM(CASE WHEN type = 'buy' THEN amount ELSE -amount END) as net')
    ->groupBy('investment_id')
    ->pluck('net', 'investment_id');
```

---

## 2. Personal Transactions

### 2.1 No soft delete

**Masalah:** Transaksi personal dihapus permanen. Tidak ada audit trail.
**Rekomendasi:** Tambah `SoftDeletes` atau `deleted_at` untuk restore capability.

### 2.2 Currency field — no validation

**Lokasi:** Wallet model
**Masalah:** Field `currency` tanpa validasi. Risk data tidak konsisten.
**Rekomendasi:** Validasi `in:IDR,USD,SGD` atau gunakan enum.

---

## Ringkasan Prioritas Personal Finance

| Sev | Issue | Lokasi |
|-----|-------|--------|
| **Critical** | N+1 queries in chart (12 queries) | `PersonalFinanceController.php:62` |
| **High** | N+1 queries wallet balance (2N queries) | `PersonalFinanceController.php:32` |
| **Medium** | Investment net worth in-memory group | `PersonalFinanceController.php:95` |
| **Low** | No soft delete transactions | Model |
| **Low** | Currency validation | `PersonalWallet.php` |
