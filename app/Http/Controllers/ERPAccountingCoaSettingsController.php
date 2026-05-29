<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\CoaSetting;
use App\Models\CashCategory;
use App\Models\CategoryCoaMapping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ERPAccountingCoaSettingsController extends Controller
{
    private function definitions(): array
    {
        return config('accounting.coa_definitions', [
            [
                'key' => 'pos_sale_cash_account',
                'label' => 'POS - Akun Kas/Bank',
                'description' => 'Dipakai saat transaksi POS sukses diposting. (GL: pos_sale)',
                'amount_source' => 'PosSale.grand_total (penjualan bersih + biaya lain yang ditagih ke pelanggan, tidak termasuk biaya admin channel)',
                'default_account_code' => '1001',
                'source_module' => 'pos_sale',
            ],
            [
                'key' => 'pos_sale_revenue_account',
                'label' => 'POS - Akun Penjualan',
                'description' => 'Credit penjualan barang (nilai transaksi). Biaya admin channel (jurnal saja) mengurangi kredit ini di jurnal POS.',
                'amount_source' => 'PosSale.gross_total - PosSale.discount_total',
                'default_account_code' => '4002',
                'source_module' => 'pos_sale',
            ],
            [
                'key' => 'pos_sale_additional_income_account',
                'label' => 'POS - Biaya lainnya (ditagih)',
                'description' => 'Credit untuk biaya lain yang menambah total bayar (contoh ongkir). Jika kosong, fallback ke akun penjualan POS.',
                'amount_source' => 'PosSale.additional_fee (hanya baris biaya dengan jenis "tambah ke total")',
                'default_account_code' => '4004',
                'source_module' => 'pos_sale',
            ],
            [
                'key' => 'pos_sale_sales_channel_admin_expense',
                'label' => 'POS - Beban admin channel',
                'description' => 'Debit beban untuk biaya potongan sales channel (pasangan kredit: hutang estimasi channel).',
                'amount_source' => 'PosSale.sales_channel_admin_fee',
                'default_account_code' => '5016',
                'source_module' => 'pos_sale',
            ],
            [
                'key' => 'pos_sale_sales_channel_admin_payable',
                'label' => 'POS - Hutang estimasi biaya channel',
                'description' => 'Kredit hutang estimasi atas biaya admin channel (disettle ke pembayaran aktual menyusul).',
                'amount_source' => 'PosSale.sales_channel_admin_fee',
                'default_account_code' => '2090',
                'source_module' => 'pos_sale',
            ],
            [
                'key' => 'pos_sale_cogs_account',
                'label' => 'POS - Akun HPP / COGS',
                'description' => 'Debit Harga Pokok Penjualan (credit ke akun persediaan) saat transaksi POS diposting.',
                'amount_source' => 'MasterProduct.unit_cost * qty (average cost)',
                'default_account_code' => '5009',
                'source_module' => 'pos_sale_cogs',
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
                'default_account_code' => '4003',
                'source_module' => 'project_invoice_payment',
            ],
        ]);
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('q'));
        $perPage = $this->resolvedPerPage($request);
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

        $settings = collect(array_map(function (array $definition) use ($saved): array {
            $row = $saved->get($definition['key']);

            return [
                ...$definition,
                'account_id' => $row?->account_id,
            ];
        }, $defs))
            ->filter(function (array $row) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $haystack = strtolower(implode(' ', [
                    $row['key'],
                    $row['label'],
                    $row['description'],
                    $row['source_module'],
                    $row['amount_source'],
                ]));

                return str_contains($haystack, strtolower($search));
            })
            ->values();

        $settingsPaginator = $this->paginateCollection($settings, $perPage, $request);

        return Inertia::render('ERP/Accounting/CoaSettings', [
            'settings' => $settingsPaginator,
            'accounts' => Account::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
            'categoryMappings' => [
                'cash_in' => $this->paginateCollection($this->categoryRows('cash_in', $mappings, $search), $perPage, $request, 'cash_in_page'),
                'cash_out' => $this->paginateCollection($this->categoryRows('cash_out', $mappings, $search), $perPage, $request, 'cash_out_page'),
            ],
            'filters' => [
                'q' => $search,
                'per_page' => $perPage,
                'cash_in_page' => (int) $request->query('cash_in_page', 1),
                'cash_out_page' => (int) $request->query('cash_out_page', 1),
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

    public function applyDefaults(): RedirectResponse
    {
        $defaults = config('accounting.apply_defaults');

        foreach ($defaults['admin_channel_accounts'] as $account) {
            Account::query()->updateOrCreate(
                ['code' => $account['code']],
                $account + ['is_active' => true],
            );
        }

        foreach ($defaults['system_defaults'] as $key => $code) {
            $account = Account::query()->where('code', $code)->first();
            if ($account) {
                CoaSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['account_id' => $account->id],
                );
            }
        }

        foreach ($defaults['category_defaults'] as $mapping) {
            $account = Account::query()->where('code', $mapping['account_code'])->first();
            if ($account) {
                CategoryCoaMapping::query()->updateOrCreate(
                    ['domain' => $mapping['domain'], 'category' => $mapping['category']],
                    ['account_id' => $account->id],
                );
            }
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Semua pengaturan COA berhasil disesuaikan ke default standar akuntansi.']);
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

    private function categoryRows(string $domain, Collection $mappings, string $search = ''): Collection
    {
        $usageMap = [
            'cash_in' => [
                'pendapatan_project' => 'Dipakai untuk pembayaran invoice dan termin project.',
                'uang_muka_project' => 'Dipakai untuk kas masuk uang muka project sebagai pendapatan diterima dimuka.',
                'pendapatan_jasa' => 'Dipakai untuk kas masuk manual kategori jasa.',
                'penjualan_pos' => 'Dipakai untuk klasifikasi arus kas penjualan POS.',
                'lainnya' => 'Dipakai untuk kas masuk manual lain-lain.',
            ],
            'cash_out' => [
                'refund_penjualan_pos' => 'Dipakai untuk klasifikasi refund transaksi POS.',
                'biaya_tim' => 'Dipakai untuk pembayaran biaya tim.',
                'komisi_referral' => 'Dipakai untuk komisi referral.',
                'operasional' => 'Dipakai untuk biaya operasional umum.',
                'pembelian_material_project' => 'Dipakai untuk pembelian material project dari dana internal perusahaan.',
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
            ->filter(function (array $row) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $haystack = strtolower(implode(' ', [
                    $row['category'],
                    $row['label'],
                    $row['used_by'],
                    $row['amount_source'],
                    $row['account']['code'] ?? '',
                    $row['account']['name'] ?? '',
                ]));

                return str_contains($haystack, strtolower($search));
            })
            ->values();
    }

    private function paginateCollection(Collection $items, int $perPage, Request $request, string $pageName = 'page'): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage($pageName);

        return new LengthAwarePaginator(
            $items->forPage($currentPage, $perPage)->values(),
            $items->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => $pageName]
        );
    }
}
