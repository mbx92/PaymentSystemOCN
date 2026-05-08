<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\Models\CategoryCoaMapping;
use App\Models\CashCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(): Response
    {
        $mappings = CategoryCoaMapping::query()
            ->where('domain', 'cash_out')
            ->with('account:id,code,name,type')
            ->get()
            ->keyBy('category');

        $categories = CashCategory::query()
            ->where('domain', 'cash_out')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['key', 'label', 'is_active']);

        return Inertia::render('ERP/Accounting/ExpenseCategories', [
            'categories' => $categories->map(fn ($c) => [
                'category' => $c->key,
                'label' => $c->label,
                'is_active' => (bool) $c->is_active,
                'account_id' => $mappings->get($c->key)?->account_id,
                'account' => $mappings->get($c->key)?->account
                    ? [
                        'id' => $mappings->get($c->key)->account->id,
                        'code' => $mappings->get($c->key)->account->code,
                        'name' => $mappings->get($c->key)->account->name,
                        'type' => $mappings->get($c->key)->account->type,
                    ]
                    : null,
            ])->values(),
            'accounts' => Account::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
        ]);
    }

    public function upsert(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|max:50',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        $exists = CashCategory::query()
            ->where('domain', 'cash_out')
            ->where('key', $validated['category'])
            ->exists();
        if (! $exists) {
            abort(422, 'Kategori tidak ditemukan.');
        }

        if (! $validated['account_id']) {
            CategoryCoaMapping::query()
                ->where('domain', 'cash_out')
                ->where('category', $validated['category'])
                ->delete();

            return back()->with('flash', ['type' => 'success', 'message' => 'Mapping kategori berhasil dihapus.']);
        }

        CategoryCoaMapping::query()->updateOrCreate(
            ['domain' => 'cash_out', 'category' => $validated['category']],
            ['account_id' => (int) $validated['account_id']]
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Mapping kategori berhasil disimpan.']);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:50|regex:/^[a-z0-9_]+$/',
            'label' => 'required|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        CashCategory::query()->updateOrCreate(
            ['domain' => 'cash_out', 'key' => $validated['key']],
            [
                'label' => $validated['label'],
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Kategori pengeluaran berhasil ditambahkan.']);
    }
}

