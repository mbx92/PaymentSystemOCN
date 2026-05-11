<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\CoaSetting;
use App\Models\CashCategory;
use App\Models\CategoryCoaMapping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ERPAccountingCoaSettingsController extends Controller
{
    /**
     * @return array<int, array{key: string, label: string, description: string, amount_source: string, default_account_code: string, source_module: string}>
     */
    private function definitions(): array
    {
        return [
            [
                'key' => 'pos_sale_cash_account',
                'label' => 'POS - Akun Kas/Bank',
                'description' => 'Dipakai saat transaksi POS sukses diposting. (GL: pos_sale)',
                'amount_source' => 'PosSale.grand_total (gross_total - discount_total + additional_fee)',
                'default_account_code' => '1001',
                'source_module' => 'pos_sale',
            ],
            [
                'key' => 'pos_sale_revenue_account',
                'label' => 'POS - Akun Penjualan',
                'description' => 'Credit untuk nilai penjualan bersih tanpa biaya tambahan. (GL: pos_sale)',
                'amount_source' => 'PosSale.gross_total - PosSale.discount_total',
                'default_account_code' => '4001',
                'source_module' => 'pos_sale',
            ],
            [
                'key' => 'pos_sale_additional_income_account',
                'label' => 'POS - Akun Biaya Tambahan',
                'description' => 'Credit untuk biaya tambahan. Jika kosong, sistem fallback ke akun penjualan POS.',
                'amount_source' => 'PosSale.additional_fee',
                'default_account_code' => '4001',
                'source_module' => 'pos_sale',
            ],
            [
                'key' => 'project_invoice_cash_account',
                'label' => 'Invoice Project - Akun Kas/Bank',
                'description' => 'Debit saat pembayaran invoice project atau termin project dicatat sebagai kas masuk.',
                'amount_source' => 'CashIn.amount dari pembayaran project',
                'default_account_code' => '1001',
                'source_module' => 'project_invoice_payment',
            ],
            [
                'key' => 'project_invoice_revenue_account',
                'label' => 'Invoice Project - Akun Pendapatan',
                'description' => 'Credit untuk pendapatan invoice project.',
                'amount_source' => 'CashIn.amount dari pembayaran project',
                'default_account_code' => '4001',
                'source_module' => 'project_invoice_payment',
            ],
        ];
    }

    public function index(): Response
    {
        $defs = $this->definitions();
        $keys = array_map(fn ($d) => $d['key'], $defs);

        $saved = CoaSetting::query()
            ->whereIn('key', $keys)
            ->get(['key', 'account_id'])
            ->keyBy('key');

        $mappings = CategoryCoaMapping::query()
            ->with('account:id,code,name,type')
            ->get()
            ->groupBy(fn (CategoryCoaMapping $mapping) => $mapping->domain.'|'.$mapping->category);

        return Inertia::render('ERP/Accounting/CoaSettings', [
            'settings' => array_map(function (array $definition) use ($saved): array {
                $row = $saved->get($definition['key']);

                return [
                    ...$definition,
                    'account_id' => $row?->account_id,
                ];
            }, $defs),
            'accounts' => Account::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
            'categoryMappings' => [
                'cash_in' => $this->categoryRows('cash_in', $mappings),
                'cash_out' => $this->categoryRows('cash_out', $mappings),
            ],
        ]);
    }

    public function upsert(Request $request): RedirectResponse
    {
        $defs = $this->definitions();
        $allowed = array_map(fn ($d) => $d['key'], $defs);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:80', 'in:'.implode(',', $allowed)],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        CoaSetting::query()->updateOrCreate(
            ['key' => $validated['key']],
            ['account_id' => $validated['account_id'] ?? null],
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Pengaturan CoA berhasil disimpan.']);
    }

    public function upsertCategoryMapping(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'in:cash_in,cash_out'],
            'category' => ['required', 'string', 'max:50'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $exists = CashCategory::query()
            ->where('domain', $validated['domain'])
            ->where('key', $validated['category'])
            ->exists();

        if (! $exists) {
            abort(422, 'Kategori tidak ditemukan.');
        }

        if (! $validated['account_id']) {
            CategoryCoaMapping::query()
                ->where('domain', $validated['domain'])
                ->where('category', $validated['category'])
                ->delete();

            return back()->with('flash', ['type' => 'success', 'message' => 'Mapping kategori berhasil dihapus.']);
        }

        CategoryCoaMapping::query()->updateOrCreate(
            ['domain' => $validated['domain'], 'category' => $validated['category']],
            ['account_id' => (int) $validated['account_id']],
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Mapping kategori berhasil disimpan.']);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'in:cash_in,cash_out'],
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        CashCategory::query()->updateOrCreate(
            ['domain' => $validated['domain'], 'key' => $validated['key']],
            [
                'label' => $validated['label'],
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ],
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Kategori cashflow berhasil disimpan.']);
    }

    private function categoryRows(string $domain, Collection $mappings): Collection
    {
        $usageMap = [
            'cash_in' => [
                'pendapatan_project' => 'Dipakai untuk pembayaran invoice dan termin project.',
                'pendapatan_jasa' => 'Dipakai untuk kas masuk manual kategori jasa.',
                'penjualan_pos' => 'Dipakai untuk klasifikasi arus kas penjualan POS.',
                'lainnya' => 'Dipakai untuk kas masuk manual lain-lain.',
            ],
            'cash_out' => [
                'refund_penjualan_pos' => 'Dipakai untuk klasifikasi refund transaksi POS.',
                'biaya_tim' => 'Dipakai untuk pembayaran biaya tim.',
                'komisi_referral' => 'Dipakai untuk komisi referral.',
                'operasional' => 'Dipakai untuk biaya operasional umum.',
                'lainnya' => 'Dipakai untuk kas keluar manual lain-lain.',
            ],
        ];

        return CashCategory::query()
            ->where('domain', $domain)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['domain', 'key', 'label', 'is_active'])
            ->map(function (CashCategory $category) use ($domain, $mappings, $usageMap): array {
                /** @var CategoryCoaMapping|null $mapping */
                $mapping = $mappings->get($domain.'|'.$category->key)?->first();

                return [
                    'domain' => $category->domain,
                    'category' => $category->key,
                    'label' => $category->label,
                    'is_active' => (bool) $category->is_active,
                    'used_by' => $usageMap[$domain][$category->key] ?? 'Dipakai saat kategori ini dipilih pada transaksi cashflow.',
                    'amount_source' => $domain === 'cash_in' ? 'CashIn.amount' : 'CashOut.amount',
                    'account_id' => $mapping?->account_id,
                    'account' => $mapping?->account
                        ? [
                            'id' => $mapping->account->id,
                            'code' => $mapping->account->code,
                            'name' => $mapping->account->name,
                            'type' => $mapping->account->type,
                        ]
                        : null,
                ];
            })
            ->values();
    }
}

