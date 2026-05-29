<?php

namespace App\Http\Controllers;

use App\Models\PersonalBudget;
use App\Models\PersonalCategory;
use App\Models\PersonalInvestment;
use App\Models\PersonalInvestmentMovement;
use App\Models\PersonalTransaction;
use App\Models\PersonalWallet;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PersonalFinanceController extends Controller
{
    public function overview(Request $request): Response
    {
        $userId = (int) $request->user()->id;
        $this->ensureDefaults($userId);

        $wallets = PersonalWallet::query()
            ->where('user_id', $userId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $walletIds = $wallets->pluck('id');
        $walletBalances = PersonalTransaction::query()
            ->whereIn('wallet_id', $walletIds)
            ->selectRaw('wallet_id, type, SUM(amount) as total')
            ->groupBy('wallet_id', 'type')
            ->get()
            ->groupBy('wallet_id')
            ->map(fn ($rows) => [
                'income' => (float) ($rows->firstWhere('type', 'income')?->total ?? 0),
                'expense' => (float) ($rows->firstWhere('type', 'expense')?->total ?? 0),
            ]);

        $walletRows = $wallets->map(function (PersonalWallet $w) use ($walletBalances) {
            $balance = $walletBalances[$w->id] ?? ['income' => 0, 'expense' => 0];

            return [
                'id' => $w->id,
                'name' => $w->name,
                'currency' => $w->currency,
                'balance' => round($balance['income'] - $balance['expense'], 2),
            ];
        });

        $now = now();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        $monthIncome = (float) PersonalTransaction::query()
            ->where('user_id', $userId)
            ->where('type', 'income')
            ->whereBetween('occurred_on', [$monthStart, $monthEnd])
            ->sum('amount');

        $monthExpense = (float) PersonalTransaction::query()
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('occurred_on', [$monthStart, $monthEnd])
            ->sum('amount');

        $chartLabels = [];
        $chartIncome = [];
        $chartExpense = [];
        $sixMonthsAgo = now()->subMonths(5)->startOfMonth();
        $chartTransactions = PersonalTransaction::query()
            ->where('user_id', $userId)
            ->where('occurred_on', '>=', $sixMonthsAgo)
            ->get(['occurred_on', 'type', 'amount']);
        $chartLookup = [];
        foreach ($chartTransactions as $t) {
            $monthKey = $t->occurred_on->format('Y-m');
            $chartLookup[$monthKey][$t->type] = ($chartLookup[$monthKey][$t->type] ?? 0) + (float) $t->amount;
        }
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $monthKey = $d->format('Y-m');
            $chartLabels[] = $d->translatedFormat('M Y');
            $chartIncome[] = round($chartLookup[$monthKey]['income'] ?? 0, 2);
            $chartExpense[] = round($chartLookup[$monthKey]['expense'] ?? 0, 2);
        }

        $assetTypeLabels = [
            'tabungan' => 'Tabungan / deposito',
            'saham' => 'Saham',
            'reksadana' => 'Reksadana',
            'emas' => 'Emas / logam mulia',
            'crypto' => 'Crypto',
            'lainnya' => 'Lainnya',
        ];

        $investments = PersonalInvestment::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $investmentNetById = Cache::remember('investment_net_'.$userId, now()->addMinutes(5), function () use ($investments) {
            return PersonalInvestmentMovement::query()
                ->whereIn('investment_id', $investments->pluck('id'))
                ->get()
                ->groupBy('investment_id')
                ->map(function ($group) {
                    return round($group->sum(function (PersonalInvestmentMovement $movement) {
                        $amount = (float) $movement->amount;

                        return $movement->flow === 'withdrawal' ? -$amount : $amount;
                    }), 2);
                });
        });

        $investmentRows = $investments->map(function (PersonalInvestment $investment) use ($assetTypeLabels, $investmentNetById) {
            return [
                'id' => $investment->id,
                'name' => $investment->name,
                'asset_type' => $investment->asset_type,
                'asset_label' => $assetTypeLabels[$investment->asset_type] ?? $investment->asset_type,
                'institution' => $investment->institution,
                'is_active' => (bool) $investment->is_active,
                'net_flow' => round((float) ($investmentNetById[$investment->id] ?? 0), 2),
            ];
        });

        return Inertia::render('Personal/Overview', [
            'wallets' => $walletRows,
            'month' => [
                'label' => $now->translatedFormat('F Y'),
                'income' => round($monthIncome, 2),
                'expense' => round($monthExpense, 2),
                'net' => round($monthIncome - $monthExpense, 2),
            ],
            'chart' => [
                'labels' => $chartLabels,
                'income' => $chartIncome,
                'expense' => $chartExpense,
            ],
            'investments' => [
                'summary' => [
                    'count' => $investmentRows->count(),
                    'active_count' => $investmentRows->where('is_active', true)->count(),
                    'net_flow' => round((float) $investmentRows->sum('net_flow'), 2),
                ],
                'items' => $investmentRows,
            ],
        ]);
    }

    public function transactions(Request $request): Response
    {
        $userId = (int) $request->user()->id;
        $this->ensureDefaults($userId);

        $wallets = PersonalWallet::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'currency', 'is_default']);
        $categories = PersonalCategory::query()->where('user_id', $userId)->orderBy('type')->orderBy('name')->get(['id', 'name', 'type', 'color']);

        $transactions = PersonalTransaction::query()
            ->where('user_id', $userId)
            ->with(['wallet:id,name', 'category:id,name,type'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (PersonalTransaction $t) => [
                'id' => $t->id,
                'wallet_id' => $t->wallet_id,
                'category_id' => $t->category_id,
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'occurred_on' => $t->occurred_on->format('Y-m-d'),
                'note' => $t->note,
                'wallet' => $t->wallet?->name,
                'category' => $t->category?->name,
                'category_type' => $t->category?->type,
            ]);

        return Inertia::render('Personal/Transactions', [
            'wallets' => $wallets,
            'categories' => $categories,
            'transactions' => $transactions,
        ]);
    }

    public function storeTransaction(Request $request): RedirectResponse
    {
        $userId = (int) $request->user()->id;
        $this->ensureDefaults($userId);

        $validated = $request->validate([
            'wallet_id' => ['required', Rule::exists('personal_wallets', 'id')->where('user_id', $userId)],
            'category_id' => ['nullable', Rule::exists('personal_categories', 'id')->where('user_id', $userId)],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => 'required|numeric|min:0.01',
            'occurred_on' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        if (! empty($validated['category_id'])) {
            $cat = PersonalCategory::query()->where('user_id', $userId)->findOrFail((int) $validated['category_id']);
            if ($cat->type !== $validated['type']) {
                return back()->with('flash', ['type' => 'error', 'message' => 'Kategori tidak cocok dengan tipe transaksi.']);
            }
        }

        PersonalTransaction::query()->create([
            'user_id' => $userId,
            'wallet_id' => (int) $validated['wallet_id'],
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'type' => $validated['type'],
            'amount' => number_format((float) $validated['amount'], 2, '.', ''),
            'occurred_on' => Carbon::parse($validated['occurred_on'])->format('Y-m-d'),
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Transaksi tersimpan.']);
    }

    public function updateTransaction(Request $request, PersonalTransaction $transaction): RedirectResponse
    {
        $this->assertTransactionOwner($request, $transaction);

        $validated = $request->validate([
            'wallet_id' => ['required', Rule::exists('personal_wallets', 'id')->where('user_id', $transaction->user_id)],
            'category_id' => ['nullable', Rule::exists('personal_categories', 'id')->where('user_id', $transaction->user_id)],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => 'required|numeric|min:0.01',
            'occurred_on' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        if (! empty($validated['category_id'])) {
            $cat = PersonalCategory::query()->where('user_id', $transaction->user_id)->findOrFail((int) $validated['category_id']);
            if ($cat->type !== $validated['type']) {
                return back()->with('flash', ['type' => 'error', 'message' => 'Kategori tidak cocok dengan tipe transaksi.']);
            }
        }

        $transaction->update([
            'wallet_id' => (int) $validated['wallet_id'],
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'type' => $validated['type'],
            'amount' => number_format((float) $validated['amount'], 2, '.', ''),
            'occurred_on' => Carbon::parse($validated['occurred_on'])->format('Y-m-d'),
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Transaksi diperbarui.']);
    }

    public function destroyTransaction(Request $request, PersonalTransaction $transaction): RedirectResponse
    {
        $this->assertTransactionOwner($request, $transaction);
        $transaction->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Transaksi dihapus.']);
    }

    public function categories(Request $request): Response
    {
        $userId = (int) $request->user()->id;
        $this->ensureDefaults($userId);

        $categories = PersonalCategory::query()
            ->where('user_id', $userId)
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (PersonalCategory $c) => $this->mapCategoryRow($c));

        return Inertia::render('Personal/Categories', [
            'categories' => $categories,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $userId = (int) $request->user()->id;
        $this->ensureDefaults($userId);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'type' => ['required', Rule::in(['income', 'expense'])],
            'color' => 'nullable|string|max:16',
        ]);

        $name = trim($validated['name']);

        $exists = PersonalCategory::query()
            ->where('user_id', $userId)
            ->where('type', $validated['type'])
            ->where('name', $name)
            ->exists();

        if ($exists) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Kategori dengan nama dan tipe yang sama sudah ada.']);
        }

        PersonalCategory::query()->create([
            'user_id' => $userId,
            'name' => $name,
            'type' => $validated['type'],
            'color' => $validated['color'] ?? null,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Kategori ditambahkan.']);
    }

    public function updateCategory(Request $request, PersonalCategory $category): RedirectResponse
    {
        $this->assertCategoryOwner($request, $category);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'type' => ['required', Rule::in(['income', 'expense'])],
            'color' => 'nullable|string|max:16',
        ]);

        $name = trim($validated['name']);
        $userId = (int) $category->user_id;

        $duplicate = PersonalCategory::query()
            ->where('user_id', $userId)
            ->where('type', $validated['type'])
            ->where('name', $name)
            ->where('id', '!=', $category->id)
            ->exists();

        if ($duplicate) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Kategori dengan nama dan tipe yang sama sudah ada.']);
        }

        if ($category->type !== $validated['type']) {
            $hasTransactions = PersonalTransaction::query()
                ->where('category_id', $category->id)
                ->exists();
            if ($hasTransactions) {
                return back()->with('flash', ['type' => 'error', 'message' => 'Tipe kategori tidak bisa diubah karena sudah dipakai transaksi.']);
            }
            $hasBudgets = PersonalBudget::query()
                ->where('category_id', $category->id)
                ->exists();
            if ($hasBudgets) {
                return back()->with('flash', ['type' => 'error', 'message' => 'Tipe kategori tidak bisa diubah karena sudah dipakai anggaran.']);
            }
        }

        $category->update([
            'name' => $name,
            'type' => $validated['type'],
            'color' => $validated['color'] ?? null,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Kategori diperbarui.']);
    }

    public function destroyCategory(Request $request, PersonalCategory $category): RedirectResponse
    {
        $this->assertCategoryOwner($request, $category);
        $category->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Kategori dihapus.']);
    }

    public function wallets(Request $request): Response
    {
        $userId = (int) $request->user()->id;
        $this->ensureDefaults($userId);

        $wallets = PersonalWallet::query()
            ->where('user_id', $userId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PersonalWallet $w) => $this->mapWalletRow($w));

        return Inertia::render('Personal/Wallets', [
            'wallets' => $wallets,
            'currencies' => [
                ['value' => 'IDR', 'label' => 'IDR — Rupiah'],
            ],
        ]);
    }

    public function storeWallet(Request $request): RedirectResponse
    {
        $userId = (int) $request->user()->id;
        $this->ensureDefaults($userId);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'currency' => 'required|string|size:3|in:IDR,USD,SGD,MYR,JPY,CNY,EUR,GBP,AUD,KRW,THB,VND,PHP,INR,BRL,CAD,CHF,NZD,SEK,NOK,DKK,TRY,ZAR,MXN,RUB,HKD,TWD,BDT,PKR,NPR,LKR,KHR,LAK,MMK',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_default' => 'required|boolean',
        ]);

        $isDefault = (bool) $validated['is_default'];
        if (! PersonalWallet::query()->where('user_id', $userId)->exists()) {
            $isDefault = true;
        }

        if ($isDefault) {
            PersonalWallet::query()->where('user_id', $userId)->update(['is_default' => false]);
        }

        PersonalWallet::query()->create([
            'user_id' => $userId,
            'name' => trim($validated['name']),
            'currency' => strtoupper(trim($validated['currency'])),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_default' => $isDefault,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Dompet ditambahkan.']);
    }

    public function updateWallet(Request $request, PersonalWallet $wallet): RedirectResponse
    {
        $this->assertWalletOwner($request, $wallet);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'currency' => 'required|string|size:3|in:IDR,USD,SGD,MYR,JPY,CNY,EUR,GBP,AUD,KRW,THB,VND,PHP,INR,BRL,CAD,CHF,NZD,SEK,NOK,DKK,TRY,ZAR,MXN,RUB,HKD,TWD,BDT,PKR,NPR,LKR,KHR,LAK,MMK',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_default' => 'required|boolean',
        ]);

        $isDefault = (bool) $validated['is_default'];
        if ($isDefault) {
            PersonalWallet::query()
                ->where('user_id', $wallet->user_id)
                ->where('id', '!=', $wallet->id)
                ->update(['is_default' => false]);
        } elseif ($wallet->is_default) {
            $other = PersonalWallet::query()
                ->where('user_id', $wallet->user_id)
                ->where('id', '!=', $wallet->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->first();
            if ($other) {
                $other->update(['is_default' => true]);
            } else {
                $isDefault = true;
            }
        }

        $wallet->update([
            'name' => trim($validated['name']),
            'currency' => strtoupper(trim($validated['currency'])),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_default' => $isDefault,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Dompet diperbarui.']);
    }

    public function destroyWallet(Request $request, PersonalWallet $wallet): RedirectResponse
    {
        $this->assertWalletOwner($request, $wallet);

        $walletCount = PersonalWallet::query()->where('user_id', $wallet->user_id)->count();
        if ($walletCount <= 1) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Minimal harus ada satu dompet.']);
        }

        if (PersonalTransaction::query()->where('wallet_id', $wallet->id)->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Dompet tidak bisa dihapus karena masih memiliki transaksi.']);
        }

        $wasDefault = $wallet->is_default;
        $userId = (int) $wallet->user_id;
        $wallet->delete();

        if ($wasDefault) {
            $next = PersonalWallet::query()
                ->where('user_id', $userId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->first();
            $next?->update(['is_default' => true]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Dompet dihapus.']);
    }

    public function budgets(Request $request): Response
    {
        $userId = (int) $request->user()->id;
        $this->ensureDefaults($userId);

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $year = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));

        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $end = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $expenseCategories = PersonalCategory::query()
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->orderBy('name')
            ->get(['id', 'name']);

        $budgets = PersonalBudget::query()
            ->where('user_id', $userId)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('category_id');

        $spentByCategory = PersonalTransaction::query()
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('occurred_on', [$start, $end])
            ->whereIn('category_id', $expenseCategories->pluck('id'))
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $rows = $expenseCategories->map(function (PersonalCategory $c) use ($budgets, $spentByCategory) {
            $b = $budgets->get($c->id);
            $spent = (float) ($spentByCategory[$c->id] ?? 0);
            $limit = $b ? (float) $b->amount_limit : null;

            return [
                'category_id' => $c->id,
                'category_name' => $c->name,
                'amount_limit' => $limit,
                'spent' => round($spent, 2),
                'remaining' => $limit !== null ? round($limit - $spent, 2) : null,
                'pct' => $limit !== null && $limit > 0 ? round(min(100, ($spent / $limit) * 100), 1) : null,
            ];
        });

        return Inertia::render('Personal/Budgets', [
            'period' => ['year' => $year, 'month' => $month, 'label' => Carbon::createFromDate($year, $month, 1)->translatedFormat('F Y')],
            'rows' => $rows,
        ]);
    }

    public function storeBudget(Request $request): RedirectResponse
    {
        $userId = (int) $request->user()->id;
        $this->ensureDefaults($userId);

        $validated = $request->validate([
            'category_id' => ['required', Rule::exists('personal_categories', 'id')->where('user_id', $userId)],
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'amount_limit' => 'required|numeric|min:0.01',
        ]);

        $cat = PersonalCategory::query()->where('user_id', $userId)->findOrFail((int) $validated['category_id']);
        if ($cat->type !== 'expense') {
            return back()->with('flash', ['type' => 'error', 'message' => 'Anggaran hanya untuk kategori pengeluaran.']);
        }

        PersonalBudget::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'category_id' => (int) $validated['category_id'],
                'year' => (int) $validated['year'],
                'month' => (int) $validated['month'],
            ],
            ['amount_limit' => number_format((float) $validated['amount_limit'], 2, '.', '')]
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Anggaran disimpan.']);
    }

    public function investments(Request $request): Response
    {
        $userId = (int) $request->user()->id;

        $investments = PersonalInvestment::query()
            ->where('user_id', $userId)
            ->with(['movements' => fn ($q) => $q->orderByDesc('occurred_on')->orderByDesc('id')->limit(80)])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $netByInvestment = PersonalInvestmentMovement::query()
            ->whereIn('investment_id', $investments->pluck('id'))
            ->get()
            ->groupBy('investment_id')
            ->map(function ($group) {
                return round($group->sum(function (PersonalInvestmentMovement $m) {
                    $a = (float) $m->amount;

                    return match ($m->flow) {
                        'withdrawal' => -$a,
                        default => $a,
                    };
                }), 2);
            });

        $items = $investments->map(function (PersonalInvestment $inv) use ($netByInvestment) {
            $net = (float) ($netByInvestment[$inv->id] ?? 0);

            return [
                'id' => $inv->id,
                'name' => $inv->name,
                'asset_type' => $inv->asset_type,
                'institution' => $inv->institution,
                'notes' => $inv->notes,
                'opened_at' => $inv->opened_at?->format('Y-m-d'),
                'is_active' => (bool) $inv->is_active,
                'net_flow' => round($net, 2),
                'movements' => $inv->movements->map(fn (PersonalInvestmentMovement $m) => [
                    'id' => $m->id,
                    'occurred_on' => $m->occurred_on->format('Y-m-d'),
                    'flow' => $m->flow,
                    'amount' => (float) $m->amount,
                    'note' => $m->note,
                ]),
            ];
        });

        return Inertia::render('Personal/Investments', [
            'investments' => $items,
            'assetTypes' => [
                ['value' => 'tabungan', 'label' => 'Tabungan / deposito'],
                ['value' => 'saham', 'label' => 'Saham'],
                ['value' => 'reksadana', 'label' => 'Reksadana'],
                ['value' => 'emas', 'label' => 'Emas / logam mulia'],
                ['value' => 'crypto', 'label' => 'Crypto'],
                ['value' => 'lainnya', 'label' => 'Lainnya'],
            ],
        ]);
    }

    public function storeInvestment(Request $request): RedirectResponse
    {
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'asset_type' => ['required', 'string', 'max:32'],
            'institution' => 'nullable|string|max:160',
            'notes' => 'nullable|string|max:2000',
            'opened_at' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);

        PersonalInvestment::query()->create([
            'user_id' => $userId,
            'name' => trim($validated['name']),
            'asset_type' => $validated['asset_type'],
            'institution' => isset($validated['institution']) ? trim((string) $validated['institution']) ?: null : null,
            'notes' => $validated['notes'] ?? null,
            'opened_at' => isset($validated['opened_at']) ? Carbon::parse($validated['opened_at'])->format('Y-m-d') : null,
            'is_active' => (bool) $validated['is_active'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Investasi ditambahkan.']);
    }

    public function updateInvestment(Request $request, PersonalInvestment $investment): RedirectResponse
    {
        $this->assertInvestmentOwner($request, $investment);

        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'asset_type' => ['required', 'string', 'max:32'],
            'institution' => 'nullable|string|max:160',
            'notes' => 'nullable|string|max:2000',
            'opened_at' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);

        $investment->update([
            'name' => trim($validated['name']),
            'asset_type' => $validated['asset_type'],
            'institution' => isset($validated['institution']) ? trim((string) $validated['institution']) ?: null : null,
            'notes' => $validated['notes'] ?? null,
            'opened_at' => isset($validated['opened_at']) ? Carbon::parse($validated['opened_at'])->format('Y-m-d') : null,
            'is_active' => (bool) $validated['is_active'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Investasi diperbarui.']);
    }

    public function destroyInvestment(Request $request, PersonalInvestment $investment): RedirectResponse
    {
        $this->assertInvestmentOwner($request, $investment);
        $investment->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Investasi dihapus.']);
    }

    public function storeInvestmentMovement(Request $request, PersonalInvestment $investment): RedirectResponse
    {
        $this->assertInvestmentOwner($request, $investment);

        $validated = $request->validate([
            'flow' => ['required', Rule::in(['deposit', 'withdrawal', 'dividend'])],
            'amount' => 'required|numeric|min:0.01',
            'occurred_on' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        PersonalInvestmentMovement::query()->create([
            'investment_id' => $investment->id,
            'flow' => $validated['flow'],
            'amount' => number_format((float) $validated['amount'], 2, '.', ''),
            'occurred_on' => Carbon::parse($validated['occurred_on'])->format('Y-m-d'),
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Mutasi investasi tersimpan.']);
    }

    private function ensureDefaults(int $userId): void
    {
        if (! PersonalWallet::query()->where('user_id', $userId)->exists()) {
            PersonalWallet::query()->create([
                'user_id' => $userId,
                'name' => 'Dompet utama',
                'currency' => 'IDR',
                'sort_order' => 0,
                'is_default' => true,
            ]);
        }

        if (! PersonalCategory::query()->where('user_id', $userId)->exists()) {
            $defs = [
                ['name' => 'Gaji & penghasilan', 'type' => 'income'],
                ['name' => 'Bisnis / freelance', 'type' => 'income'],
                ['name' => 'Makan & belanja', 'type' => 'expense'],
                ['name' => 'Transport', 'type' => 'expense'],
                ['name' => 'Tagihan & utilitas', 'type' => 'expense'],
                ['name' => 'Hiburan', 'type' => 'expense'],
                ['name' => 'Lain-lain (keluar)', 'type' => 'expense'],
            ];
            foreach ($defs as $d) {
                PersonalCategory::query()->firstOrCreate(
                    [
                        'user_id' => $userId,
                        'name' => $d['name'],
                        'type' => $d['type'],
                    ],
                    []
                );
            }
        }
    }

    private function assertTransactionOwner(Request $request, PersonalTransaction $transaction): void
    {
        if ((int) $transaction->user_id !== (int) $request->user()->id) {
            abort(404);
        }
    }

    private function assertInvestmentOwner(Request $request, PersonalInvestment $investment): void
    {
        if ((int) $investment->user_id !== (int) $request->user()->id) {
            abort(404);
        }
    }

    private function assertCategoryOwner(Request $request, PersonalCategory $category): void
    {
        if ((int) $category->user_id !== (int) $request->user()->id) {
            abort(404);
        }
    }

    private function assertWalletOwner(Request $request, PersonalWallet $wallet): void
    {
        if ((int) $wallet->user_id !== (int) $request->user()->id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCategoryRow(PersonalCategory $c): array
    {
        $txCount = PersonalTransaction::query()->where('category_id', $c->id)->count();
        $budgetCount = PersonalBudget::query()->where('category_id', $c->id)->count();

        return [
            'id' => $c->id,
            'name' => $c->name,
            'type' => $c->type,
            'color' => $c->color,
            'transaction_count' => $txCount,
            'budget_count' => $budgetCount,
            'can_delete' => $txCount === 0 && $budgetCount === 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapWalletRow(PersonalWallet $w): array
    {
        $summary = PersonalTransaction::query()
            ->where('wallet_id', $w->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END), 0) as income_total, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) as expense_total, COUNT(*) as tx_count")
            ->first();

        return [
            'id' => $w->id,
            'name' => $w->name,
            'currency' => $w->currency,
            'sort_order' => $w->sort_order,
            'is_default' => (bool) $w->is_default,
            'balance' => round((float) ($summary?->income_total ?? 0) - (float) ($summary?->expense_total ?? 0), 2),
            'transaction_count' => (int) ($summary?->tx_count ?? 0),
            'can_delete' => ((int) ($summary?->tx_count ?? 0)) === 0,
        ];
    }
}
