<?php

namespace App\Http\Controllers;

use App\ERP\Core\Models\DocumentSequence;
use App\ERP\Inventory\Models\Warehouse;
use App\ERP\Purchasing\Models\GoodsReceiptLine;
use App\ERP\Purchasing\Models\PurchaseOrderLine;
use App\ERP\Purchasing\Models\Vendor;
use App\Exports\CrmCustomerImportTemplateExport;
use App\Exports\MasterProductImportTemplateExport;
use App\Exports\ProjectImportTemplateExport;
use App\Imports\CrmCustomersImport;
use App\Imports\MasterProductsImport;
use App\Imports\ProjectsImport;
use App\Models\ErpChatParserRule;
use App\Models\ErpSetting;
use App\Models\LabelProfile;
use App\Models\LandingSite;
use App\Models\LandingSitePage;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\PaymentMethod;
use App\Models\PosSaleItem;
use App\Models\ProductStockMovement;
use App\Models\ProcurementImportStaging;
use App\Models\ProjectMaterial;
use App\Services\ProjectMaterialReservationService;
use App\Services\LanEscPosPrinter;
use App\Services\LanTsplPrinter;
use App\Services\DatabaseBackupService;
use App\Services\LegacyProjectImportService;
use App\Services\LegacyProjectSalesQcService;
use App\Services\LegacySupplierImportService;
use App\Services\ProcurementImportStagingService;
use App\Services\ProductionErpSyncService;
use App\Services\ServerMetricsService;
use App\Services\ThermalPosReceiptData;
use App\Services\ThermalPosReceiptRenderer;
use App\Services\WindowsSmbRawPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ERPAdministrationMasterDataController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $databaseBackupService,
    ) {}

    public function erpSettings(): Response
    {
        $setting = ErpSetting::query()->first();

        return Inertia::render('ERP/Admin/ErpSettings', [
            'setting' => [
                'app_name' => $setting?->app_name ?? 'OCN ERP Suite',
                'app_tagline' => $setting?->app_tagline ?? 'Integrated Business Platform',
                'app_logo_path' => $setting?->app_logo_path,
                'app_logo_url' => $setting?->app_logo_path ? Storage::url($setting->app_logo_path) : null,
                'module_menu_layout' => $setting?->resolvedModuleMenuLayout() ?? ErpSetting::MODULE_MENU_LAYOUT_GRID,
            ],
        ]);
    }

    public function updateErpSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => 'required|string|max:120',
            'app_tagline' => 'nullable|string|max:190',
            'app_logo' => 'nullable|image|max:2048',
            'remove_logo' => 'nullable|boolean',
            'module_menu_layout' => ['required', Rule::in(ErpSetting::moduleMenuLayoutOptions())],
        ]);

        $setting = ErpSetting::query()->firstOrCreate([], [
            'app_name' => 'OCN ERP Suite',
            'app_tagline' => 'Integrated Business Platform',
        ]);

        $logoPath = $setting->app_logo_path;
        if (($validated['remove_logo'] ?? false) && $logoPath) {
            Storage::disk('public')->delete($logoPath);
            $logoPath = null;
        }

        if ($request->hasFile('app_logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath = $request->file('app_logo')->store('erp-settings', 'public');
        }

        $setting->update([
            'app_name' => $validated['app_name'],
            'app_tagline' => $validated['app_tagline'] ?? null,
            'app_logo_path' => $logoPath,
            'module_menu_layout' => $validated['module_menu_layout'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'ERP Setting berhasil diperbarui.']);
    }

    public function landingSites(Request $request): Response
    {
        return Inertia::render('ERP/Admin/LandingSites', [
            'landingSites' => LandingSite::query()
                ->with(['warehouse:id,code,name', 'page:id,landing_site_id,is_published'])
                ->orderBy('is_active', 'desc')
                ->orderBy('name')
                ->paginate($this->resolvedPerPage($request))
                ->withQueryString(),
            'warehouses' => Warehouse::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'filters' => $this->filtersWithPerPage($request, []),
        ]);
    }

    public function storeLandingSite(Request $request): RedirectResponse
    {
        $request->merge([
            'domain' => $this->normalizeLandingDomain((string) $request->input('domain')),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'domain' => ['required', 'string', 'max:190', Rule::unique('landing_sites', 'domain')],
            'layout_key' => 'required|string|in:toko,cctv,coming_soon,countdown',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'is_active' => 'required|boolean',
        ]);

        LandingSite::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Landing site berhasil ditambahkan.']);
    }

    public function updateLandingSite(Request $request, LandingSite $landingSite): RedirectResponse
    {
        $request->merge([
            'domain' => $this->normalizeLandingDomain((string) $request->input('domain')),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'domain' => ['required', 'string', 'max:190', Rule::unique('landing_sites', 'domain')->ignore($landingSite->id)],
            'layout_key' => 'required|string|in:toko,cctv,coming_soon,countdown',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'is_active' => 'required|boolean',
        ]);

        $landingSite->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Landing site berhasil diperbarui.']);
    }

    public function checkLandingSiteDomain(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:190',
        ]);

        $normalizedDomain = $this->normalizeLandingDomain((string) $validated['domain']);

        $landingSite = LandingSite::query()
            ->with(['warehouse:id,code,name', 'page:id,landing_site_id,is_published,countdown_at'])
            ->where('domain', $normalizedDomain)
            ->first();

        return response()->json([
            'input_domain' => (string) $validated['domain'],
            'normalized_domain' => $normalizedDomain,
            'exists' => $landingSite !== null,
            'landing_site' => $landingSite ? [
                'id' => $landingSite->id,
                'name' => $landingSite->name,
                'domain' => $landingSite->domain,
                'layout_key' => $landingSite->layout_key,
                'warehouse_id' => $landingSite->warehouse_id,
                'is_active' => (bool) $landingSite->is_active,
                'warehouse' => $landingSite->warehouse ? [
                    'id' => $landingSite->warehouse->id,
                    'code' => $landingSite->warehouse->code,
                    'name' => $landingSite->warehouse->name,
                ] : null,
                'page' => $landingSite->page ? [
                    'is_published' => (bool) $landingSite->page->is_published,
                    'countdown_at' => $landingSite->page->countdown_at?->toIso8601String(),
                ] : null,
            ] : null,
        ]);
    }

    public function landingSiteCms(Request $request, LandingSite $landingSite): Response
    {
        $landingSite->load('page');

        return Inertia::render('ERP/Admin/LandingSiteCms', [
            'landingSite' => $landingSite,
            'cmsModule' => $request->boolean('cms'),
            'pageContent' => [
                'headline' => $landingSite->page?->headline ?? '',
                'subheadline' => $landingSite->page?->subheadline ?? '',
                'body' => $landingSite->page?->body ?? '',
                'countdown_at' => $landingSite->page?->countdown_at?->format('Y-m-d\TH:i'),
                'primary_cta_text' => $landingSite->page?->primary_cta_text ?? '',
                'primary_cta_url' => $landingSite->page?->primary_cta_url ?? '',
                'secondary_cta_text' => $landingSite->page?->secondary_cta_text ?? '',
                'secondary_cta_url' => $landingSite->page?->secondary_cta_url ?? '',
                'contact_text' => $landingSite->page?->contact_text ?? '',
                'seo_title' => $landingSite->page?->seo_title ?? '',
                'seo_description' => $landingSite->page?->seo_description ?? '',
                'is_published' => (bool) ($landingSite->page?->is_published ?? true),
            ],
        ]);
    }

    public function updateLandingSiteCms(Request $request, LandingSite $landingSite): RedirectResponse
    {
        $validated = $request->validate([
            'headline' => 'nullable|string|max:190',
            'subheadline' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:4000',
            'countdown_at' => 'nullable|date',
            'primary_cta_text' => 'nullable|string|max:80',
            'primary_cta_url' => 'nullable|string|max:500',
            'secondary_cta_text' => 'nullable|string|max:80',
            'secondary_cta_url' => 'nullable|string|max:500',
            'contact_text' => 'nullable|string|max:255',
            'seo_title' => 'nullable|string|max:190',
            'seo_description' => 'nullable|string|max:255',
            'is_published' => 'required|boolean',
        ]);

        LandingSitePage::query()->updateOrCreate(
            ['landing_site_id' => $landingSite->id],
            [
                'headline' => trim((string) ($validated['headline'] ?? '')) ?: null,
                'subheadline' => trim((string) ($validated['subheadline'] ?? '')) ?: null,
                'body' => trim((string) ($validated['body'] ?? '')) ?: null,
                'countdown_at' => $validated['countdown_at'] ?? null,
                'primary_cta_text' => trim((string) ($validated['primary_cta_text'] ?? '')) ?: null,
                'primary_cta_url' => trim((string) ($validated['primary_cta_url'] ?? '')) ?: null,
                'secondary_cta_text' => trim((string) ($validated['secondary_cta_text'] ?? '')) ?: null,
                'secondary_cta_url' => trim((string) ($validated['secondary_cta_url'] ?? '')) ?: null,
                'contact_text' => trim((string) ($validated['contact_text'] ?? '')) ?: null,
                'seo_title' => trim((string) ($validated['seo_title'] ?? '')) ?: null,
                'seo_description' => trim((string) ($validated['seo_description'] ?? '')) ?: null,
                'is_published' => (bool) $validated['is_published'],
            ]
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Konten landing page berhasil disimpan.']);
    }

    private function normalizeLandingDomain(string $domain): string
    {
        $normalized = strtolower(trim($domain));
        $normalized = preg_replace('#^https?://#', '', $normalized) ?? $normalized;
        $normalized = preg_replace('#/.*$#', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/:\d+$/', '', $normalized) ?? $normalized;

        if ($normalized !== '' && ! str_contains($normalized, '://')) {
            $parsedHost = parse_url('http://'.$normalized, PHP_URL_HOST);
            if (is_string($parsedHost) && $parsedHost !== '') {
                $normalized = $parsedHost;
            }
        }

        return trim($normalized, ". \t\n\r\0\x0B");
    }

    public function parserRules(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $query = ErpChatParserRule::query()->orderBy('priority')->orderBy('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('intent_key', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%');
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('is_active', $status === 'active');
        }

        return Inertia::render('ERP/Admin/ParserRules', [
            'rules' => $query->paginate($this->resolvedPerPage($request))->withQueryString(),
            'filters' => $this->filtersWithPerPage($request, ['search', 'status']),
            'capabilities' => [
                'built_in_intents' => [
                    [
                        'key' => 'stock_lookup',
                        'label' => 'Cek stok produk',
                        'source' => 'data',
                        'description' => 'Mencari produk aktif lalu menampilkan stok live dari database.',
                        'examples' => ['stok lid cup', 'cek stok kabel lan'],
                        'custom_reply_supported' => true,
                    ],
                    [
                        'key' => 'product_price_lookup',
                        'label' => 'Cek harga produk',
                        'source' => 'data',
                        'description' => 'Mengambil harga jual live produk berdasarkan nama, SKU, atau barcode.',
                        'examples' => ['harga standing pouch', 'berapa harga kabel lan'],
                        'custom_reply_supported' => true,
                    ],
                    [
                        'key' => 'product_detail',
                        'label' => 'Detail produk',
                        'source' => 'data',
                        'description' => 'Menampilkan detail stok, SKU, barcode, satuan, dan harga jual.',
                        'examples' => ['detail produk lid cup', 'info barang switch 8 port'],
                        'custom_reply_supported' => false,
                    ],
                    [
                        'key' => 'low_stock_alert',
                        'label' => 'Alert stok rendah',
                        'source' => 'data',
                        'description' => 'Mengambil produk dengan stok di bawah atau sama dengan minimum.',
                        'examples' => ['stok rendah', 'barang habis'],
                        'custom_reply_supported' => false,
                    ],
                    [
                        'key' => 'top_selling_products',
                        'label' => 'Produk terlaris',
                        'source' => 'data',
                        'description' => 'Meringkas produk POS terlaris berdasarkan penjualan bulan berjalan.',
                        'examples' => ['produk terlaris', 'top selling'],
                        'custom_reply_supported' => false,
                    ],
                    [
                        'key' => 'pos_sales_today',
                        'label' => 'Ringkasan POS',
                        'source' => 'data',
                        'description' => 'Menampilkan transaksi, total penjualan, dan rata-rata transaksi untuk periode tertentu.',
                        'examples' => ['pos hari ini', 'penjualan bulan ini'],
                        'custom_reply_supported' => false,
                    ],
                    [
                        'key' => 'cashflow_today',
                        'label' => 'Ringkasan cashflow',
                        'source' => 'data',
                        'description' => 'Menghitung kas masuk, kas keluar, dan net untuk periode tertentu.',
                        'examples' => ['cashflow hari ini', 'kas bulan ini'],
                        'custom_reply_supported' => false,
                    ],
                    [
                        'key' => 'invoice_unpaid_list',
                        'label' => 'Invoice belum dibayar',
                        'source' => 'data',
                        'description' => 'Menampilkan daftar termin project yang belum dibayar.',
                        'examples' => ['invoice belum dibayar', 'tagihan belum lunas'],
                        'custom_reply_supported' => false,
                    ],
                    [
                        'key' => 'invoice_due_list',
                        'label' => 'Invoice jatuh tempo',
                        'source' => 'data',
                        'description' => 'Menampilkan daftar invoice yang akan atau sudah jatuh tempo.',
                        'examples' => ['invoice jatuh tempo', 'tagihan jatuh tempo'],
                        'custom_reply_supported' => false,
                    ],
                    [
                        'key' => 'project_active_list',
                        'label' => 'Project aktif',
                        'source' => 'data',
                        'description' => 'Menampilkan daftar project berstatus berjalan dan total nilainya.',
                        'examples' => ['project aktif', 'daftar project'],
                        'custom_reply_supported' => false,
                    ],
                    [
                        'key' => 'send_invoice',
                        'label' => 'Kirim invoice',
                        'source' => 'action',
                        'description' => 'Menyiapkan dan mengirim invoice project selesai setelah konfirmasi user.',
                        'examples' => ['kirim invoice INV-PRJ-000123 ke client@mail.com'],
                        'custom_reply_supported' => false,
                    ],
                    [
                        'key' => 'invoice_sent_list',
                        'label' => 'Riwayat kirim invoice',
                        'source' => 'data',
                        'description' => 'Menampilkan histori pengiriman invoice dari chatbot.',
                        'examples' => ['list invoice yang dikirim'],
                        'custom_reply_supported' => false,
                    ],
                    [
                        'key' => 'greeting',
                        'label' => 'Sapaan & bantuan',
                        'source' => 'built_in',
                        'description' => 'Balasan percakapan dasar seperti halo dan bantuan.',
                        'examples' => ['halo', 'bantuan'],
                        'custom_reply_supported' => false,
                    ],
                ],
                'notes' => [
                    'Tahap 1 memindahkan query chatbot ke service domain terpisah agar lebih mudah dikembangkan.',
                    'Rule parser tetap dipakai untuk mengenali intent, tetapi banyak jawaban sekarang mengambil data live dari database.',
                    'Custom reply bertemplate placeholder saat ini aman dipakai khusus untuk intent stok dan harga produk.',
                ],
            ],
        ]);
    }

    public function storeParserRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'intent_key' => 'required|string|max:60',
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'required|string|max:60',
            'match_mode' => 'required|in:and,or',
            'priority' => 'required|integer|min:1|max:9999',
            'is_active' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
            'response_text' => 'nullable|string|max:2000',
        ]);

        $validated['intent_key'] = strtolower(trim((string) $validated['intent_key']));
        $validated['keywords'] = collect($validated['keywords'])
            ->map(fn ($keyword) => strtolower(trim((string) $keyword)))
            ->filter()
            ->values()
            ->all();

        ErpChatParserRule::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Parser rule berhasil ditambahkan.']);
    }

    public function updateParserRule(Request $request, ErpChatParserRule $parserRule): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'intent_key' => 'required|string|max:60',
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'required|string|max:60',
            'match_mode' => 'required|in:and,or',
            'priority' => 'required|integer|min:1|max:9999',
            'is_active' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
            'response_text' => 'nullable|string|max:2000',
        ]);

        $validated['intent_key'] = strtolower(trim((string) $validated['intent_key']));
        $validated['keywords'] = collect($validated['keywords'])
            ->map(fn ($keyword) => strtolower(trim((string) $keyword)))
            ->filter()
            ->values()
            ->all();

        $parserRule->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Parser rule berhasil diperbarui.']);
    }

    public function destroyParserRule(ErpChatParserRule $parserRule): RedirectResponse
    {
        $parserRule->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Parser rule berhasil dihapus.']);
    }

    public function documentSequences(Request $request): Response
    {
        $query = DocumentSequence::query()->orderBy('module')->orderBy('document_type');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('module', 'like', $term)
                    ->orWhere('document_type', 'like', $term)
                    ->orWhere('prefix', 'like', $term);
            });
        }

        if ($request->filled('module')) {
            $query->where('module', $request->string('module')->toString());
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->string('document_type')->toString());
        }

        $moduleOptions = DocumentSequence::query()
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->filter()
            ->values()
            ->all();

        $typeOptions = DocumentSequence::query()
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type')
            ->filter()
            ->values()
            ->all();

        return Inertia::render('ERP/Admin/DocumentSequences', [
            'sequences' => $query->paginate($this->resolvedPerPage($request))->withQueryString(),
            'moduleOptions' => $moduleOptions,
            'typeOptions' => $typeOptions,
            'filters' => $this->filtersWithPerPage($request, ['q', 'module', 'document_type']),
        ]);
    }

    public function storeDocumentSequence(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module' => 'required|string|max:50',
            'document_type' => 'required|string|max:100',
            'prefix' => 'required|string|max:20',
            'padding_length' => 'required|integer|min:3|max:10',
            'running_number' => 'required|integer|min:0',
        ]);

        DocumentSequence::query()->updateOrCreate(
            ['module' => $validated['module'], 'document_type' => $validated['document_type']],
            [
                'prefix' => strtoupper($validated['prefix']),
                'padding_length' => (int) $validated['padding_length'],
                'running_number' => (int) $validated['running_number'],
            ],
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Setting nomor dokumen berhasil disimpan.']);
    }

    public function updateDocumentSequence(Request $request, DocumentSequence $documentSequence): RedirectResponse
    {
        $validated = $request->validate([
            'prefix' => 'required|string|max:20',
            'padding_length' => 'required|integer|min:3|max:10',
            'running_number' => 'required|integer|min:0',
        ]);

        $documentSequence->update([
            'prefix' => strtoupper($validated['prefix']),
            'padding_length' => (int) $validated['padding_length'],
            'running_number' => (int) $validated['running_number'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Sequence nomor dokumen berhasil diperbarui.']);
    }

    public function paymentMethods(Request $request): Response
    {
        return Inertia::render('ERP/Admin/PaymentMethods', [
            'paymentMethods' => PaymentMethod::query()
                ->with('salesChannelAssignments')
                ->orderBy('name')
                ->paginate($this->resolvedPerPage($request))
                ->through(fn (PaymentMethod $method) => $this->formatPaymentMethodForAdmin($method))
                ->withQueryString(),
            'priceChannels' => PaymentMethod::salesChannelOptions(),
            'filters' => $this->filtersWithPerPage($request, []),
        ]);
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|alpha_dash|unique:payment_methods,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'sales_channels' => ['required', 'array', 'min:1'],
            'sales_channels.*' => ['required', 'string', Rule::in(PaymentMethod::SALES_CHANNEL_KEYS)],
            'status' => 'required|in:active,inactive',
        ]);

        $channels = $validated['sales_channels'];
        unset($validated['sales_channels']);
        $validated['code'] = strtolower($validated['code']);

        $method = PaymentMethod::query()->create($validated);
        $method->syncSalesChannels($channels);

        return back()->with('flash', ['type' => 'success', 'message' => 'Metode pembayaran berhasil ditambahkan.']);
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|alpha_dash|unique:payment_methods,code,'.$paymentMethod->id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'sales_channels' => ['required', 'array', 'min:1'],
            'sales_channels.*' => ['required', 'string', Rule::in(PaymentMethod::SALES_CHANNEL_KEYS)],
            'status' => 'required|in:active,inactive',
        ]);

        $channels = $validated['sales_channels'];
        unset($validated['sales_channels']);
        $validated['code'] = strtolower($validated['code']);
        $paymentMethod->update($validated);
        $paymentMethod->syncSalesChannels($channels);

        return back()->with('flash', ['type' => 'success', 'message' => 'Metode pembayaran berhasil diperbarui.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPaymentMethodForAdmin(PaymentMethod $method): array
    {
        $channels = $method->salesChannelsList();

        return [
            'id' => $method->id,
            'code' => $method->code,
            'name' => $method->name,
            'description' => $method->description,
            'sales_channels' => $channels,
            'sales_channel_labels' => array_map(
                fn (string $key) => PaymentMethod::salesChannelLabel($key),
                $channels
            ),
            'status' => $method->status,
        ];
    }

    public function thermalPrinter(): RedirectResponse
    {
        return redirect()->route('erp.admin.printer-and-label', ['tab' => 'thermal']);
    }

    public function printerAndLabelSettings(Request $request): Response
    {
        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['thermal', 'label-smb', 'label-lan', 'label-profiles'], true)) {
            $tab = 'thermal';
        }

        $setting = ErpSetting::query()->with(['labelProfile', 'labelLanProfile'])->first();
        $renderer = new ThermalPosReceiptRenderer;

        $proto = $setting?->labelProfile?->protocol ?? $setting?->label_smb_protocol ?? 'zpl';

        return Inertia::render('ERP/Admin/PrinterAndLabelSettings', [
            'activeTab' => $tab,
            'printer' => [
                'thermal_printer_enabled' => (bool) ($setting?->thermal_printer_enabled ?? false),
                'thermal_printer_host' => $setting?->thermal_printer_host ?? '',
                'thermal_printer_port' => (int) ($setting?->thermal_printer_port ?? 9100),
                'thermal_paper_width' => $setting?->thermal_paper_width ?? '80',
                'thermal_pos_header_template' => $setting?->thermal_pos_header_template ?? '',
                'thermal_pos_item_line_template' => $setting?->thermal_pos_item_line_template ?? '',
                'thermal_pos_footer_template' => $setting?->thermal_pos_footer_template ?? '',
                'thermal_pos_margin_left_mm' => (string) ($setting?->thermal_pos_margin_left_mm ?? '2.00'),
                'thermal_pos_header_align' => $setting?->thermal_pos_header_align ?? 'center',
                'thermal_pos_item_align' => $setting?->thermal_pos_item_align ?? 'left',
                'thermal_pos_footer_align' => $setting?->thermal_pos_footer_align ?? 'right',
                'thermal_pos_section_gap' => (int) ($setting?->thermal_pos_section_gap ?? 0),
                'thermal_pos_header_emphasis' => (bool) ($setting?->thermal_pos_header_emphasis ?? true),
            ],
            'thermalTemplateDefaults' => [
                'header' => $renderer->defaultHeaderTemplate(),
                'item_line' => $renderer->defaultItemLineTemplate(),
                'footer' => $renderer->defaultFooterTemplate(),
            ],
            'labelSmb' => [
                'label_smb_enabled' => (bool) ($setting?->label_smb_enabled ?? false),
                'label_smb_unc' => $setting?->label_smb_unc ?? '',
                'label_smb_protocol' => in_array($proto, ['zpl', 'epl', 'tspl'], true) ? $proto : 'zpl',
                'label_smb_profile_id' => $setting?->label_smb_profile_id,
            ],
            'labelLan' => [
                'label_lan_enabled' => (bool) ($setting?->label_lan_enabled ?? false),
                'label_lan_host' => $setting?->label_lan_host ?? '',
                'label_lan_port' => (int) ($setting?->label_lan_port ?? 9100),
                'label_lan_profile_id' => $setting?->label_lan_profile_id,
                'label_smb_profile_id' => $setting?->label_smb_profile_id,
            ],
            'labelProfiles' => LabelProfile::query()->orderBy('name')->get([
                'id', 'name', 'width_mm', 'height_mm', 'dpi', 'margin_left_mm', 'margin_top_mm', 'gap_mm', 'rows', 'protocol', 'barcode_type', 'barcode_width',
            ]),
            'serverIsWindows' => PHP_OS_FAMILY === 'Windows',
        ]);
    }

    public function updateThermalPrinter(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'thermal_printer_enabled' => 'required|boolean',
            'thermal_printer_host' => [
                'nullable',
                'string',
                'max:64',
                Rule::when($request->boolean('thermal_printer_enabled'), ['required', 'ip']),
            ],
            'thermal_printer_port' => 'required|integer|min:1|max:65535',
            'thermal_paper_width' => 'required|string|in:58,80',
            'thermal_pos_header_template' => 'nullable|string|max:16000',
            'thermal_pos_item_line_template' => 'nullable|string|max:16000',
            'thermal_pos_footer_template' => 'nullable|string|max:16000',
            'thermal_pos_margin_left_mm' => 'nullable|numeric|min:0|max:25',
            'thermal_pos_header_align' => 'required|string|in:left,center,right',
            'thermal_pos_item_align' => 'required|string|in:left,center,right',
            'thermal_pos_footer_align' => 'required|string|in:left,center,right',
            'thermal_pos_section_gap' => 'required|integer|min:0|max:3',
            'thermal_pos_header_emphasis' => 'required|boolean',
        ]);

        $setting = ErpSetting::query()->firstOrCreate([], [
            'app_name' => 'OCN ERP Suite',
            'app_tagline' => 'Integrated Business Platform',
        ]);

        $host = isset($validated['thermal_printer_host']) ? trim((string) $validated['thermal_printer_host']) : null;
        if (! ($validated['thermal_printer_enabled'] ?? false)) {
            $host = $host ?: null;
        }

        $setting->update([
            'thermal_printer_enabled' => $validated['thermal_printer_enabled'],
            'thermal_printer_host' => $host,
            'thermal_printer_port' => (int) $validated['thermal_printer_port'],
            'thermal_paper_width' => $validated['thermal_paper_width'],
            'thermal_pos_header_template' => $validated['thermal_pos_header_template'] ?? null,
            'thermal_pos_item_line_template' => $validated['thermal_pos_item_line_template'] ?? null,
            'thermal_pos_footer_template' => $validated['thermal_pos_footer_template'] ?? null,
            'thermal_pos_margin_left_mm' => $validated['thermal_pos_margin_left_mm'] ?? 0,
            'thermal_pos_header_align' => $validated['thermal_pos_header_align'],
            'thermal_pos_item_align' => $validated['thermal_pos_item_align'],
            'thermal_pos_footer_align' => $validated['thermal_pos_footer_align'],
            'thermal_pos_section_gap' => (int) $validated['thermal_pos_section_gap'],
            'thermal_pos_header_emphasis' => (bool) $validated['thermal_pos_header_emphasis'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Pengaturan printer thermal LAN berhasil disimpan.']);
    }

    public function testThermalPrinter(Request $request, LanEscPosPrinter $printer): RedirectResponse
    {
        $validated = $request->validate([
            'thermal_printer_host' => 'required|string|max:64|ip',
            'thermal_printer_port' => 'required|integer|min:1|max:65535',
            'thermal_paper_width' => 'required|string|in:58,80',
        ]);

        try {
            [$host, $port] = $printer->sendTestReceipt(
                $validated['thermal_printer_host'],
                (int) $validated['thermal_printer_port'],
                'TEST PRINT LAN',
                $validated['thermal_paper_width'],
            );

            return back()->with('flash', [
                'type' => 'success',
                'message' => "Test print terkirim ke {$host}:{$port}.",
            ]);
        } catch (RuntimeException $e) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function previewThermalReceipt(Request $request, ThermalPosReceiptRenderer $renderer, LanEscPosPrinter $printer): JsonResponse
    {
        $validated = $request->validate([
            'thermal_paper_width' => 'required|string|in:58,80',
            'thermal_pos_header_template' => 'nullable|string|max:16000',
            'thermal_pos_item_line_template' => 'nullable|string|max:16000',
            'thermal_pos_footer_template' => 'nullable|string|max:16000',
            'thermal_pos_margin_left_mm' => 'nullable|numeric|min:0|max:25',
            'thermal_pos_header_align' => 'required|string|in:left,center,right',
            'thermal_pos_item_align' => 'required|string|in:left,center,right',
            'thermal_pos_footer_align' => 'required|string|in:left,center,right',
            'thermal_pos_section_gap' => 'required|integer|min:0|max:3',
            'thermal_pos_header_emphasis' => 'required|boolean',
        ]);

        $setting = ErpSetting::query()->first();
        $sample = ThermalPosReceiptData::sample()->withAppName((string) ($setting?->app_name ?: 'OCN ERP Suite'));

        $template = [
            'header' => $validated['thermal_pos_header_template'] ?? null,
            'item_line' => $validated['thermal_pos_item_line_template'] ?? null,
            'footer' => $validated['thermal_pos_footer_template'] ?? null,
        ];

        $layout = [
            'header_align' => $validated['thermal_pos_header_align'],
            'item_align' => $validated['thermal_pos_item_align'],
            'footer_align' => $validated['thermal_pos_footer_align'],
            'section_gap' => (int) $validated['thermal_pos_section_gap'],
            'header_emphasis' => (bool) $validated['thermal_pos_header_emphasis'],
        ];

        $paper = $printer->normalizePaperWidth($validated['thermal_paper_width']);
        $cols = $printer->paperColumnWidth($paper);
        $marginMm = (float) ($validated['thermal_pos_margin_left_mm'] ?? 0);
        $marginChars = ThermalPosReceiptRenderer::marginCharsFromMm($marginMm, $paper, $cols);
        $layout['content_cols'] = max(8, $cols - $marginChars);

        $segments = $renderer->buildReceiptSegments($template, $sample, $paper, $cols, $layout);
        $preview = $printer->previewReceiptVisual($segments, $paper, $marginChars);

        return response()->json($preview);
    }

    public function testThermalPosReceipt(Request $request, LanEscPosPrinter $printer, ThermalPosReceiptRenderer $renderer): RedirectResponse
    {
        $validated = $request->validate([
            'thermal_printer_host' => 'required|string|max:64|ip',
            'thermal_printer_port' => 'required|integer|min:1|max:65535',
            'thermal_paper_width' => 'required|string|in:58,80',
        ]);

        $setting = ErpSetting::query()->first();

        $template = [
            'header' => $setting?->thermal_pos_header_template,
            'item_line' => $setting?->thermal_pos_item_line_template,
            'footer' => $setting?->thermal_pos_footer_template,
        ];

        $paper = $printer->normalizePaperWidth($validated['thermal_paper_width']);
        $cols = $printer->paperColumnWidth($paper);

        $sample = ThermalPosReceiptData::sample()->withAppName((string) ($setting?->app_name ?: 'OCN ERP Suite'));
        $layout = [
            'header_align' => $setting?->thermal_pos_header_align ?? 'center',
            'item_align' => $setting?->thermal_pos_item_align ?? 'left',
            'footer_align' => $setting?->thermal_pos_footer_align ?? 'right',
            'section_gap' => (int) ($setting?->thermal_pos_section_gap ?? 0),
            'header_emphasis' => (bool) ($setting?->thermal_pos_header_emphasis ?? true),
        ];
        $marginMm = (float) ($setting?->thermal_pos_margin_left_mm ?? 0);
        $marginChars = ThermalPosReceiptRenderer::marginCharsFromMm($marginMm, $paper, $cols);
        $layout['content_cols'] = max(8, $cols - $marginChars);
        $segments = $renderer->buildReceiptSegments($template, $sample, $paper, $cols, $layout);

        try {
            [$host, $port] = $printer->sendStructuredReceipt(
                $validated['thermal_printer_host'],
                (int) $validated['thermal_printer_port'],
                $segments,
                $validated['thermal_paper_width'],
                $marginChars,
            );

            return back()->with('flash', [
                'type' => 'success',
                'message' => "Struk POS (template) terkirim ke {$host}:{$port}.",
            ]);
        } catch (RuntimeException $e) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function labelPrinterSmb(): RedirectResponse
    {
        return redirect()->route('erp.admin.printer-and-label', ['tab' => 'label-smb']);
    }

    public function updateLabelPrinterSmb(Request $request, WindowsSmbRawPrinter $smb): RedirectResponse
    {
        $validated = $request->validate([
            'label_smb_enabled' => 'required|boolean',
            'label_smb_unc' => ['nullable', 'string', 'max:260'],
            'label_smb_profile_id' => ['nullable', 'exists:label_profiles,id'],
        ]);

        $normalized = $smb->normalizeUnc((string) ($validated['label_smb_unc'] ?? ''));

        if ($validated['label_smb_enabled'] && $normalized === '') {
            return back()
                ->withErrors(['label_smb_unc' => 'Path UNC wajib diisi saat fitur diaktifkan.'])
                ->withInput();
        }

        if ($validated['label_smb_enabled'] && empty($validated['label_smb_profile_id'])) {
            return back()
                ->withErrors(['label_smb_profile_id' => 'Pilih profil label (ukuran kertas) saat fitur diaktifkan.'])
                ->withInput();
        }

        if ($normalized !== '' && ! $smb->isValidUnc($normalized)) {
            return back()
                ->withErrors(['label_smb_unc' => 'Format UNC tidak valid. Gunakan \\\\NAMA-SERVER\\NamaPrinter atau smb://NAMA-SERVER/NamaPrinter'])
                ->withInput();
        }

        $setting = ErpSetting::query()->firstOrCreate([], [
            'app_name' => 'OCN ERP Suite',
            'app_tagline' => 'Integrated Business Platform',
        ]);

        $profileId = $validated['label_smb_profile_id'] ?? null;
        $profile = $profileId ? LabelProfile::query()->find($profileId) : null;

        $setting->update([
            'label_smb_enabled' => $validated['label_smb_enabled'],
            'label_smb_unc' => $normalized !== '' ? $normalized : null,
            'label_smb_profile_id' => $profileId,
            'label_smb_protocol' => $profile?->protocol ?? $setting->label_smb_protocol ?? 'zpl',
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Pengaturan label Windows (SMB) berhasil disimpan.']);
    }

    public function testLabelPrinterSmb(Request $request, WindowsSmbRawPrinter $smb): RedirectResponse
    {
        $validated = $request->validate([
            'label_smb_unc' => 'required|string|max:260',
            'label_smb_profile_id' => 'required|integer|exists:label_profiles,id',
        ]);

        $unc = $smb->normalizeUnc($validated['label_smb_unc']);
        if (! $smb->isValidUnc($unc)) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Path UNC tidak valid untuk uji cetak.',
            ]);
        }

        $profile = LabelProfile::query()->findOrFail((int) $validated['label_smb_profile_id']);

        try {
            $payload = $smb->samplePayloadForProfile($profile);
            $smb->sendRaw($unc, $payload);

            return back()->with('flash', [
                'type' => 'success',
                'message' => 'Data uji ('.strtoupper($profile->protocol).', '.$profile->name.') terkirim ke '.$unc.'.',
            ]);
        } catch (RuntimeException $e) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function labelPrinterLan(): RedirectResponse
    {
        return redirect()->route('erp.admin.printer-and-label', ['tab' => 'label-lan']);
    }

    public function dataImport(Request $request): Response|RedirectResponse
    {
        $tab = $request->string('tab')->toString();
        if ($tab === 'legacy-qc') {
            return redirect()->route('erp.admin.legacy-import');
        }

        if (! in_array($tab, ['products', 'projects', 'customers', 'seeders', 'backup'], true)) {
            $tab = 'products';
        }

        return Inertia::render('ERP/Admin/DataImport', [
            'activeTab' => $tab,
            'seeders' => $this->dataImportSeederRows(),
            'warehouses' => Warehouse::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'projectMaterialProducts' => MasterProduct::query()
                ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'warehouse_id'])
                ->map(fn (MasterProduct $product) => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'warehouse_id' => $product->warehouse_id,
                ]),
            'svcSkuNormalizationSummary' => $this->svcSkuNormalizationSummary(),
            'backupMeta' => $this->databaseBackupService->backupMeta(),
            'legacyQcReport' => session('legacy_project_sales_qc'),
            'procurementVendors' => Vendor::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (Vendor $vendor) => [
                    'id' => $vendor->id,
                    'code' => $vendor->code,
                    'name' => $vendor->name,
                ]),
            'procurementImportStagings' => ProcurementImportStaging::query()
                ->with(['project:id,name,import_key', 'company:id,name', 'warehouse:id,name,code', 'lines.product:id,name,sku', 'lines.vendor:id,name,code', 'converter:id,name'])
                ->latest('procurement_date')
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn (ProcurementImportStaging $staging) => [
                    'id' => $staging->id,
                    'source_import_key' => $staging->source_import_key,
                    'legacy_project_number' => $staging->legacy_project_number,
                    'legacy_project_name' => $staging->legacy_project_name,
                    'procurement_date' => optional($staging->procurement_date)->toDateString(),
                    'status' => $staging->status,
                    'notes' => $staging->notes,
                    'converted_at' => optional($staging->converted_at)->toDateTimeString(),
                    'converted_by_name' => $staging->converter?->name,
                    'conversion_summary' => $staging->conversion_summary,
                    'company_name' => $staging->company?->name,
                    'warehouse_name' => $staging->warehouse?->name,
                    'warehouse_code' => $staging->warehouse?->code,
                    'project' => $staging->project ? [
                        'id' => $staging->project->id,
                        'name' => $staging->project->name,
                        'import_key' => $staging->project->import_key,
                    ] : null,
                    'lines' => $staging->lines->map(fn ($line) => [
                        'id' => $line->id,
                        'master_product_id' => $line->master_product_id,
                        'product_name' => $line->product_name,
                        'sku' => $line->product?->sku,
                        'qty' => (float) $line->qty,
                        'unit' => $line->unit,
                        'unit_cost' => (float) $line->unit_cost,
                        'line_total' => (float) $line->line_total,
                        'status' => $line->status,
                        'vendor_id' => $line->vendor_id,
                        'vendor_code' => $line->vendor?->code,
                        'vendor_name' => $line->vendor?->name,
                    ])->values(),
                ]),
        ]);
    }

    public function normalizeSvcSkuProducts(): RedirectResponse
    {
        $updatedCount = 0;

        DB::transaction(function () use (&$updatedCount): void {
            MasterProduct::query()
                ->where('sku', 'like', 'SVC-%')
                ->orderBy('id')
                ->chunkById(100, function ($products) use (&$updatedCount): void {
                    foreach ($products as $product) {
                        $needsUpdate = $product->product_type !== MasterProduct::PRODUCT_TYPE_SERVICE
                            || $product->warehouse_id !== null
                            || (float) $product->stock !== 0.0
                            || (float) $product->min_stock !== 0.0
                            || (bool) $product->low_stock_alert_enabled !== false;

                        if (! $needsUpdate) {
                            continue;
                        }

                        $product->forceFill([
                            'product_type' => MasterProduct::PRODUCT_TYPE_SERVICE,
                            'warehouse_id' => null,
                            'stock' => 0,
                            'min_stock' => 0,
                            'low_stock_alert_enabled' => false,
                        ])->save();

                        $updatedCount++;
                    }
                });
        });

        return redirect()
            ->route('erp.admin.data-import', ['tab' => 'products'])
            ->with('flash', [
                'type' => 'success',
                'message' => "Normalisasi SKU SVC ke Jasa / Non Stok selesai. {$updatedCount} produk diperbarui.",
            ]);
    }

    public function legacyImport(): Response
    {
        return Inertia::render('ERP/Admin/LegacyImport', $this->legacyImportPageProps());
    }

    public function productionDbSync(ProductionErpSyncService $syncService): Response
    {
        return Inertia::render('ERP/Admin/ProductionDbSync', $this->productionDbSyncPageProps($syncService));
    }

    public function runProductionDbSync(Request $request, ProductionErpSyncService $syncService): RedirectResponse
    {
        $modules = array_keys($syncService->modules());
        $tables = $syncService->allTables();

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['dry_run', 'execute'])],
            'module_keys' => ['nullable', 'array'],
            'module_keys.*' => ['string', Rule::in($modules)],
            'table_names' => ['nullable', 'array'],
            'table_names.*' => ['string', Rule::in($tables)],
            'chunk_size' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);

        try {
            $result = $syncService->sync(
                array_values($validated['module_keys'] ?? []),
                array_values($validated['table_names'] ?? []),
                ($validated['mode'] ?? 'dry_run') !== 'execute',
                (int) $validated['chunk_size'],
            );

            $modeLabel = ($validated['mode'] ?? 'dry_run') === 'execute' ? 'execute import' : 'dry run';

            return redirect()
                ->route('erp.admin.production-db-sync')
                ->with('production_sync_result', $result)
                ->with('production_sync_filters', [
                    'mode' => $validated['mode'],
                    'module_keys' => array_values($validated['module_keys'] ?? []),
                    'table_names' => array_values($validated['table_names'] ?? []),
                    'chunk_size' => (int) $validated['chunk_size'],
                ])
                ->with('flash', [
                    'type' => 'success',
                    'message' => 'Production DB sync selesai dijalankan dalam mode '.$modeLabel.'.',
                ]);
        } catch (\Throwable $e) {
            Log::error('Production DB sync failed.', [
                'mode' => $validated['mode'] ?? null,
                'module_keys' => $validated['module_keys'] ?? [],
                'table_names' => $validated['table_names'] ?? [],
                'chunk_size' => $validated['chunk_size'] ?? null,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return redirect()
                ->route('erp.admin.production-db-sync')
                ->with('production_sync_filters', [
                    'mode' => $validated['mode'] ?? 'dry_run',
                    'module_keys' => array_values($validated['module_keys'] ?? []),
                    'table_names' => array_values($validated['table_names'] ?? []),
                    'chunk_size' => (int) ($validated['chunk_size'] ?? 500),
                ])
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'Production DB sync gagal: '.$e->getMessage(),
                ]);
        }
    }

    public function legacyImportProject(
        string $legacyProjectId,
        LegacyProjectSalesQcService $legacyProjectSalesQcService,
        LegacyProjectImportService $legacyProjectImportService,
    ): Response|RedirectResponse
    {
        try {
            $report = $legacyProjectSalesQcService->buildReport();
            $project = collect($report['projects'] ?? [])
                ->first(fn (array $row) => (string) ($row['legacy_id'] ?? '') === $legacyProjectId);

            if (! $project) {
                return redirect()
                    ->route('erp.admin.legacy-import')
                    ->with('legacy_project_sales_qc', $report)
                    ->with('flash', [
                        'type' => 'warning',
                        'message' => 'Project legacy yang diminta tidak ditemukan di hasil QC terbaru.',
                    ]);
            }

            $procurementGate = $legacyProjectImportService->procurementGateForProject($project);
            $project['requires_procurement_first'] = (bool) ($procurementGate['requires_procurement'] ?? false);
            $project['procurement_ready_for_import'] = (bool) ($procurementGate['ready'] ?? false);
            $project['procurement_gate_message'] = $procurementGate['message'] ?? null;
            $project['procurement_staging_status'] = $procurementGate['staging_status'] ?? null;
            $relatedProcurementStagings = $this->procurementImportStagingPayload(
                ProcurementImportStaging::query()
                    ->with(['project:id,name,import_key', 'company:id,name', 'warehouse:id,name,code', 'lines.product:id,name,sku', 'lines.vendor:id,name,code', 'converter:id,name'])
                    ->where('source_import_key', (string) ($project['import_key'] ?? ''))
                    ->latest('updated_at')
                    ->latest('created_at')
                    ->get()
            );

            return Inertia::render('ERP/Admin/LegacyImportProjectShow', [
                'project' => $project,
                'generatedAt' => $report['generated_at'] ?? null,
                'source' => $report['source'] ?? null,
                'procurementVendors' => collect($this->legacyImportPageProps()['procurementVendors'] ?? [])->values()->all(),
                'relatedProcurementStagings' => $relatedProcurementStagings,
            ]);
        } catch (\Throwable $e) {
            Log::error('Legacy import project detail failed.', [
                'legacy_project_id' => $legacyProjectId,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return redirect()
                ->route('erp.admin.legacy-import')
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'Detail project legacy gagal dibuka: '.$e->getMessage(),
                ]);
        }
    }

    public function runLegacyProjectSalesQc(LegacyProjectSalesQcService $legacyProjectSalesQcService): RedirectResponse
    {
        try {
            $report = $legacyProjectSalesQcService->buildReport();

            return redirect()
                ->route('erp.admin.legacy-import')
                ->with('legacy_project_sales_qc', $report)
                ->with('flash', [
                    'type' => 'success',
                    'message' => sprintf(
                        'QC legacy selesai. %d ready, %d warning, %d blocked.',
                        (int) ($report['summary']['ready_projects'] ?? 0),
                        (int) ($report['summary']['warning_projects'] ?? 0),
                        (int) ($report['summary']['blocked_projects'] ?? 0)
                    ),
                ]);
        } catch (\Throwable $e) {
            Log::error('Legacy project sales QC failed.', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return redirect()
                ->route('erp.admin.legacy-import')
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'QC legacy gagal dijalankan: '.$e->getMessage(),
                ]);
        }
    }

    public function importSelectedLegacyProjects(Request $request, LegacyProjectImportService $legacyProjectImportService, LegacyProjectSalesQcService $legacyProjectSalesQcService): RedirectResponse
    {
        $validated = $request->validate([
            'import_keys' => ['required', 'array', 'min:1'],
            'import_keys.*' => ['required', 'string', 'max:191'],
            'legacy_project_id' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $result = $legacyProjectImportService->importSelected(
                array_values($validated['import_keys']),
                (int) $request->user()->id,
            );

            $report = $legacyProjectSalesQcService->buildReport();

            $message = sprintf(
                'Import legacy selesai. %d project dibuat.',
                (int) ($result['created_project_count'] ?? 0),
            );

            if (! empty($result['skipped'])) {
                $message .= ' '.count($result['skipped']).' project dilewati.';
            }

            $redirect = $request->filled('legacy_project_id')
                ? redirect()->route('erp.admin.legacy-import.projects.show', $request->input('legacy_project_id'))
                : redirect()->route('erp.admin.legacy-import');

            return $redirect
                ->with('legacy_project_sales_qc', $report)
                ->with('flash', [
                    'type' => 'success',
                    'message' => $message,
                    'import_kind' => 'legacy-selected-projects',
                    'imported_count' => (int) ($result['created_project_count'] ?? 0),
                    'import_errors' => collect($result['skipped'] ?? [])->values()->map(fn (string $message, int $index) => [
                        'row' => $index + 1,
                        'message' => $message,
                    ])->all(),
                ]);
        } catch (\Throwable $e) {
            Log::error('Legacy selected project import failed.', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            $redirect = $request->filled('legacy_project_id')
                ? redirect()->route('erp.admin.legacy-import.projects.show', $request->input('legacy_project_id'))
                : redirect()->route('erp.admin.legacy-import');

            return $redirect
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'Import selected projects gagal: '.$e->getMessage(),
                ]);
        }
    }

    public function prepareLegacyProjectProcurement(Request $request, LegacyProjectImportService $legacyProjectImportService): RedirectResponse
    {
        $validated = $request->validate([
            'import_keys' => ['required', 'array', 'min:1'],
            'import_keys.*' => ['required', 'string', 'max:191'],
            'legacy_project_id' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $result = $legacyProjectImportService->prepareProcurementStagings(
                array_values($validated['import_keys']),
                (int) $request->user()->id,
            );

            $redirect = $request->filled('legacy_project_id')
                ? redirect()->route('erp.admin.legacy-import.projects.show', $request->input('legacy_project_id'))
                : redirect()->route('erp.admin.legacy-import');

            return $redirect->with('flash', [
                'type' => 'success',
                'message' => sprintf(
                    'Procurement staging berhasil disiapkan untuk %d project. %d project dilewati.',
                    (int) ($result['prepared_count'] ?? 0),
                    count($result['skipped'] ?? []),
                ),
                'import_kind' => 'legacy-procurement-preparation',
                'import_errors' => collect($result['skipped'] ?? [])->values()->map(fn (string $message, int $index) => [
                    'row' => $index + 1,
                    'message' => $message,
                ])->all(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Legacy procurement staging preparation failed.', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            $redirect = $request->filled('legacy_project_id')
                ? redirect()->route('erp.admin.legacy-import.projects.show', $request->input('legacy_project_id'))
                : redirect()->route('erp.admin.legacy-import');

            return $redirect->with('flash', [
                'type' => 'error',
                'message' => 'Persiapan procurement staging gagal: '.$e->getMessage(),
            ]);
        }
    }

    public function importLegacySuppliers(Request $request, LegacySupplierImportService $legacySupplierImportService): RedirectResponse
    {
        $validated = $request->validate([
            'legacy_ids' => ['required', 'array', 'min:1'],
            'legacy_ids.*' => ['required', 'string', 'max:191'],
        ]);

        try {
            $result = $legacySupplierImportService->importSelected(
                array_values($validated['legacy_ids']),
                (int) $request->user()->id,
            );

            $message = sprintf(
                'Import supplier legacy selesai. %d vendor dibuat, %d vendor diupdate.',
                (int) ($result['created_count'] ?? 0),
                (int) ($result['updated_count'] ?? 0),
            );

            if ((int) ($result['skipped_count'] ?? 0) > 0) {
                $message .= ' '.(int) $result['skipped_count'].' supplier dilewati.';
            }

            return redirect()
                ->route('erp.admin.legacy-import')
                ->with('flash', [
                    'type' => 'success',
                    'message' => $message,
                    'import_kind' => 'legacy-suppliers',
                    'imported_count' => (int) (($result['created_count'] ?? 0) + ($result['updated_count'] ?? 0)),
                    'import_errors' => collect($result['skipped'] ?? [])->values()->map(fn (string $message, int $index) => [
                        'row' => $index + 1,
                        'message' => $message,
                    ])->all(),
                ]);
        } catch (\Throwable $e) {
            Log::error('Legacy supplier import failed.', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return redirect()
                ->route('erp.admin.legacy-import')
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'Import supplier legacy gagal: '.$e->getMessage(),
                ]);
        }
    }

    public function updateProcurementImportStaging(Request $request, ProcurementImportStaging $procurementImportStaging, ProcurementImportStagingService $procurementImportStagingService): RedirectResponse
    {
        $validated = $request->validate([
            'procurement_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'legacy_project_id' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $procurementImportStagingService->updateDraft($procurementImportStaging, $validated);
            $report = app(LegacyProjectSalesQcService::class)->buildReport();

            $redirect = $request->filled('legacy_project_id')
                ? redirect()->route('erp.admin.legacy-import.projects.show', $request->input('legacy_project_id'))
                : redirect()->route('erp.admin.legacy-import');

            return $redirect
                ->with('legacy_project_sales_qc', $report)
                ->with('flash', [
                    'type' => 'success',
                    'message' => 'Draft procurement staging berhasil diperbarui.',
                ]);
        } catch (\Throwable $e) {
            Log::error('Procurement import staging update failed.', [
                'staging_id' => $procurementImportStaging->id,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            $redirect = $request->filled('legacy_project_id')
                ? redirect()->route('erp.admin.legacy-import.projects.show', $request->input('legacy_project_id'))
                : redirect()->route('erp.admin.legacy-import');

            return $redirect
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'Update procurement staging gagal: '.$e->getMessage(),
                ]);
        }
    }

    public function convertProcurementImportStaging(Request $request, ProcurementImportStaging $procurementImportStaging, ProcurementImportStagingService $procurementImportStagingService): RedirectResponse
    {
        try {
            $result = $procurementImportStagingService->convertToPurchasingDocuments(
                $procurementImportStaging,
                (int) $request->user()->id,
            );
            $report = app(LegacyProjectSalesQcService::class)->buildReport();

            $redirect = $request->filled('legacy_project_id')
                ? redirect()->route('erp.admin.legacy-import.projects.show', $request->input('legacy_project_id'))
                : redirect()->route('erp.admin.legacy-import');

            return $redirect
                ->with('legacy_project_sales_qc', $report)
                ->with('flash', [
                    'type' => 'success',
                    'message' => sprintf(
                        'Procurement staging berhasil dikonversi menjadi %d PO dan %d GR. Seluruh GR legacy langsung diposting ke stok gudang dan hutang usaha.',
                        count($result['purchase_orders'] ?? []),
                        count($result['goods_receipts'] ?? []),
                    ),
                ]);
        } catch (\Throwable $e) {
            Log::error('Procurement import staging convert failed.', [
                'staging_id' => $procurementImportStaging->id,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            $redirect = $request->filled('legacy_project_id')
                ? redirect()->route('erp.admin.legacy-import.projects.show', $request->input('legacy_project_id'))
                : redirect()->route('erp.admin.legacy-import');

            return $redirect
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'Konversi procurement staging gagal: '.$e->getMessage(),
                ]);
        }
    }

    public function reconcileProcurementImportStagings(Request $request, ProcurementImportStagingService $procurementImportStagingService, LegacyProjectSalesQcService $legacyProjectSalesQcService): RedirectResponse
    {
        try {
            $legacyProjectId = trim((string) $request->input('legacy_project_id', ''));
            $sourceImportKey = trim((string) $request->input('source_import_key', ''));
            $isScopedToProject = $legacyProjectId !== '' && $sourceImportKey !== '';

            $summary = $isScopedToProject
                ? $procurementImportStagingService->reconcileStagingsForImportKey($sourceImportKey)
                : $procurementImportStagingService->reconcileOpenStagings();

            $redirect = redirect()->route(
                $isScopedToProject ? 'erp.admin.legacy-import.projects.show' : 'erp.admin.legacy-import',
                $isScopedToProject ? $legacyProjectId : []
            );

            if (! $isScopedToProject) {
                $report = $legacyProjectSalesQcService->buildReport();
                $redirect->with('legacy_project_sales_qc', $report);
            }

            return $redirect
                ->with('flash', [
                    'type' => 'success',
                    'message' => sprintf(
                        $isScopedToProject
                            ? 'Cek staging project selesai. %d staging dicek, %d line jasa dihapus, %d line master direfresh, %d staging dihapus.'
                            : 'Cek tabel staging selesai. %d staging dicek, %d line jasa dihapus, %d line master direfresh, %d staging dihapus.',
                        (int) ($summary['checked_stagings'] ?? 0),
                        (int) ($summary['removed_service_lines'] ?? 0),
                        (int) ($summary['refreshed_product_lines'] ?? 0),
                        (int) ($summary['deleted_stagings'] ?? 0),
                    ),
                ]);
        } catch (\Throwable $e) {
            Log::error('Procurement import staging reconcile failed.', [
                'legacy_project_id' => $request->input('legacy_project_id'),
                'source_import_key' => $request->input('source_import_key'),
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return redirect()
                ->route(
                    $request->filled('legacy_project_id') ? 'erp.admin.legacy-import.projects.show' : 'erp.admin.legacy-import',
                    $request->filled('legacy_project_id') ? (string) $request->input('legacy_project_id') : []
                )
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'Cek tabel staging gagal: '.$e->getMessage(),
                ]);
        }
    }

    public function downloadDatabaseBackup(): BinaryFileResponse|RedirectResponse
    {
        try {
            return $this->databaseBackupService->downloadPostgresDump();
        } catch (\Throwable $e) {
            Log::error('Database backup download failed.', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return redirect()
                ->route('erp.admin.data-import', ['tab' => 'backup'])
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'Backup database gagal dijalankan: '.$e->getMessage(),
                ]);
        }
    }

    public function runSeeder(Request $request): JsonResponse
    {
        $allowedClasses = array_column($this->dataImportSeederRows(), 'class');

        $request->validate([
            'seeder' => ['required', 'string', Rule::in($allowedClasses)],
        ]);

        $class = 'Database\\Seeders\\'.$request->input('seeder');

        try {
            Artisan::call('db:seed', ['--class' => $class, '--force' => true]);
            $output = trim(Artisan::output());

            return response()->json([
                'success' => true,
                'message' => "Seeder {$request->input('seeder')} berhasil dijalankan.",
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Gagal menjalankan seeder: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Hapus penempatan produk per gudang. Produk yang hanya terdaftar di gudang ini juga dihapus dari master
     * (jika tidak ada pembelian, GR, material project, POS, atau riwayat stok lain yang menaut).
     * Stok di gudang boleh tidak nol. Operasi tetap ditolak bila ada riwayat pergerakan stok untuk gudang ini, material project
     * ke gudang ini, atau baris penerimaan barang ke gudang ini (serta relasi global untuk produk yang akan dihapus dari master).
     * Produk yang masih punya penempatan di gudang lain: hanya baris gudang ini yang dihapus.
     */
    public function clearWarehouseProductAssignments(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];

        $stockForWarehouse = MasterProductWarehouseStock::query()->where('warehouse_id', $warehouseId);

        if (! $stockForWarehouse->exists()) {
            return back()->with('flash', [
                'type' => 'warning',
                'message' => 'Tidak ada penempatan produk di gudang yang dipilih.',
            ]);
        }

        $reasons = [];

        if (ProductStockMovement::query()->where('warehouse_id', $warehouseId)->exists()) {
            $reasons[] = 'Masih ada riwayat pergerakan stok untuk gudang ini.';
        }

        if (ProjectMaterial::query()->where('warehouse_id', $warehouseId)->exists()) {
            $reasons[] = 'Masih ada material project yang menaut ke gudang ini.';
        }

        if (GoodsReceiptLine::query()->whereHas('goodsReceipt', fn ($q) => $q->where('warehouse_id', $warehouseId))->exists()) {
            $reasons[] = 'Masih ada baris penerimaan barang ke gudang ini.';
        }

        $productIds = $stockForWarehouse->clone()->pluck('master_product_id')->unique()->values()->all();

        $masterProductIdsOnlyInThisWarehouse = [];
        foreach ($productIds as $productId) {
            $hasOtherWarehouse = MasterProductWarehouseStock::query()
                ->where('master_product_id', $productId)
                ->where('warehouse_id', '!=', $warehouseId)
                ->exists();
            if (! $hasOtherWarehouse) {
                $masterProductIdsOnlyInThisWarehouse[] = $productId;
            }
        }

        if ($masterProductIdsOnlyInThisWarehouse !== []) {
            $onlyIds = $masterProductIdsOnlyInThisWarehouse;
            if (ProductStockMovement::query()->whereIn('master_product_id', $onlyIds)->exists()) {
                $reasons[] = 'Ada produk yang hanya terdaftar di gudang ini tetapi masih memiliki riwayat pergerakan stok.';
            }
            if (ProjectMaterial::query()->whereIn('master_product_id', $onlyIds)->exists()) {
                $reasons[] = 'Ada produk yang hanya terdaftar di gudang ini tetapi masih dipakai sebagai material project.';
            }
            if (PurchaseOrderLine::query()->whereIn('master_product_id', $onlyIds)->exists()) {
                $reasons[] = 'Ada produk yang hanya terdaftar di gudang ini tetapi masih ada di pembelian (PO).';
            }
            if (GoodsReceiptLine::query()->whereIn('master_product_id', $onlyIds)->exists()) {
                $reasons[] = 'Ada produk yang hanya terdaftar di gudang ini tetapi masih ada di penerimaan barang.';
            }
            if (PosSaleItem::query()->whereIn('master_product_id', $onlyIds)->exists()) {
                $reasons[] = 'Ada produk yang hanya terdaftar di gudang ini tetapi masih ada di riwayat penjualan POS.';
            }
        }

        if ($reasons !== []) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Tidak dapat mengosongkan penempatan produk: '.implode(' ', $reasons),
            ]);
        }

        $deletedRows = $stockForWarehouse->clone()->count();
        $deletedMasterCount = count($masterProductIdsOnlyInThisWarehouse);

        DB::transaction(function () use ($warehouseId, $productIds, $masterProductIdsOnlyInThisWarehouse): void {
            if ($masterProductIdsOnlyInThisWarehouse !== []) {
                MasterProduct::query()->whereIn('id', $masterProductIdsOnlyInThisWarehouse)->delete();
            }

            MasterProductWarehouseStock::query()->where('warehouse_id', $warehouseId)->delete();

            $survivorIds = array_values(array_diff($productIds, $masterProductIdsOnlyInThisWarehouse));

            foreach ($survivorIds as $productId) {
                if (! MasterProduct::query()->whereKey($productId)->exists()) {
                    continue;
                }

                $sumQty = (float) MasterProductWarehouseStock::query()
                    ->where('master_product_id', $productId)
                    ->sum('qty');

                MasterProduct::query()->whereKey($productId)->update([
                    'stock' => max(0, (int) floor($sumQty)),
                ]);
            }
        });

        $msg = "Penempatan produk di gudang berhasil dikosongkan ({$deletedRows} baris dihapus).";
        if ($deletedMasterCount > 0) {
            $msg .= " {$deletedMasterCount} produk master ikut dihapus (hanya terdaftar di gudang ini).";
        }

        return back()->with('flash', [
            'type' => 'success',
            'message' => $msg,
        ]);
    }

    public function syncMasterProductOriginWarehouses(): RedirectResponse
    {
        $updatedCount = 0;
        $clearedCount = 0;

        DB::transaction(function () use (&$updatedCount, &$clearedCount): void {
            MasterProduct::query()
                ->orderBy('id')
                ->chunkById(100, function ($products) use (&$updatedCount, &$clearedCount): void {
                    foreach ($products as $product) {
                        if ($product->product_type === MasterProduct::PRODUCT_TYPE_SERVICE) {
                            if ($product->warehouse_id !== null) {
                                $product->forceFill(['warehouse_id' => null])->save();
                                $clearedCount++;
                            }
                            continue;
                        }

                        $resolvedWarehouseId = MasterProductWarehouseStock::query()
                            ->where('master_product_id', $product->id)
                            ->orderByDesc('qty')
                            ->orderBy('warehouse_id')
                            ->value('warehouse_id');

                        if ((int) ($product->warehouse_id ?? 0) === (int) ($resolvedWarehouseId ?? 0)) {
                            continue;
                        }

                        $product->forceFill(['warehouse_id' => $resolvedWarehouseId])->save();

                        if ($resolvedWarehouseId === null) {
                            $clearedCount++;
                        } else {
                            $updatedCount++;
                        }
                    }
                });
        });

        return redirect()
            ->route('erp.admin.data-import', ['tab' => 'products'])
            ->with('flash', [
                'type' => 'success',
                'message' => "Sinkron warehouse asal item selesai. {$updatedCount} item diperbarui, {$clearedCount} item dikosongkan.",
            ]);
    }

    public function syncProjectMaterialOriginWarehouses(): RedirectResponse
    {
        $updatedCount = 0;

        DB::transaction(function () use (&$updatedCount): void {
            ProjectMaterial::query()
                ->with('product:id,product_type,warehouse_id')
                ->orderBy('id')
                ->chunkById(100, function ($materials) use (&$updatedCount): void {
                    foreach ($materials as $material) {
                        $product = $material->product;
                        if (! $product || $product->product_type === MasterProduct::PRODUCT_TYPE_SERVICE) {
                            continue;
                        }

                        $resolvedWarehouseId = $product->warehouse_id;
                        if ($resolvedWarehouseId === null || (int) $material->warehouse_id === (int) $resolvedWarehouseId) {
                            continue;
                        }

                        $material->forceFill([
                            'warehouse_id' => $resolvedWarehouseId,
                        ])->save();
                        $updatedCount++;
                    }
                });
        });

        $reservationSummary = app(ProjectMaterialReservationService::class)->syncAllWarehouseReservations();

        return redirect()
            ->route('erp.admin.data-import', ['tab' => 'products'])
            ->with('flash', [
                'type' => 'success',
                'message' => "Sinkron material project selesai. {$updatedCount} baris dipindahkan ke warehouse asal item. Reserved gudang disesuaikan pada {$reservationSummary['warehouse_rows_updated']} baris stok.",
            ]);
    }

    public function relocateProjectMaterialWarehouse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'master_product_id' => ['required', 'integer', 'exists:master_products,id'],
            'source_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:source_warehouse_id'],
        ]);

        $product = MasterProduct::query()->findOrFail((int) $validated['master_product_id']);
        if (! $product->isStockTracked()) {
            throw ValidationException::withMessages([
                'master_product_id' => 'Produk jasa tidak memiliki material project per gudang.',
            ]);
        }

        $productId = (int) $product->id;
        $sourceWarehouseId = (int) $validated['source_warehouse_id'];
        $destinationWarehouseId = (int) $validated['destination_warehouse_id'];
        $movedCount = 0;
        $mergedCount = 0;

        DB::transaction(function () use ($productId, $sourceWarehouseId, $destinationWarehouseId, &$movedCount, &$mergedCount): void {
            $sourceRows = ProjectMaterial::query()
                ->where('master_product_id', $productId)
                ->where('warehouse_id', $sourceWarehouseId)
                ->lockForUpdate()
                ->get();

            if ($sourceRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'source_warehouse_id' => 'Tidak ada material project untuk item ini pada gudang sumber.',
                ]);
            }

            foreach ($sourceRows as $sourceRow) {
                $destinationRow = ProjectMaterial::query()
                    ->where('project_id', $sourceRow->project_id)
                    ->where('master_product_id', $productId)
                    ->where('warehouse_id', $destinationWarehouseId)
                    ->lockForUpdate()
                    ->first();

                if ($destinationRow) {
                    $destinationPlannedQty = (float) $destinationRow->planned_qty;
                    $sourcePlannedQty = (float) $sourceRow->planned_qty;
                    $combinedPlannedQty = $destinationPlannedQty + $sourcePlannedQty;

                    $destinationRow->planned_qty = $combinedPlannedQty;
                    $destinationRow->reserved_qty = (float) $destinationRow->reserved_qty + (float) $sourceRow->reserved_qty;
                    $destinationRow->issued_qty = (float) $destinationRow->issued_qty + (float) $sourceRow->issued_qty;
                    $destinationRow->unit_cost = $combinedPlannedQty > 0
                        ? (($destinationPlannedQty * (float) $destinationRow->unit_cost) + ($sourcePlannedQty * (float) $sourceRow->unit_cost)) / $combinedPlannedQty
                        : 0;
                    $destinationRow->unit_price = $combinedPlannedQty > 0
                        ? (($destinationPlannedQty * (float) $destinationRow->unit_price) + ($sourcePlannedQty * (float) $sourceRow->unit_price)) / $combinedPlannedQty
                        : 0;
                    $destinationRow->notes = $this->mergeProjectMaterialNotes($destinationRow->notes, $sourceRow->notes);
                    $destinationRow->status = $this->projectMaterialStatus($destinationRow);
                    $destinationRow->save();

                    $sourceRow->delete();
                    $mergedCount++;
                    continue;
                }

                $sourceRow->warehouse_id = $destinationWarehouseId;
                $sourceRow->status = $this->projectMaterialStatus($sourceRow);
                $sourceRow->save();
                $movedCount++;
            }
        });

        $sourceStock = app(ProjectMaterialReservationService::class)->syncWarehouseReservation($productId, $sourceWarehouseId);
        $destinationStock = app(ProjectMaterialReservationService::class)->syncWarehouseReservation($productId, $destinationWarehouseId);

        return redirect()
            ->route('erp.admin.data-import', ['tab' => 'products'])
            ->with('flash', [
                'type' => 'success',
                'message' => "Material project item berhasil dipindahkan. {$movedCount} baris dipindah, {$mergedCount} baris digabung. Reserve sumber kini {$sourceStock->reserved_qty}, reserve tujuan {$destinationStock->reserved_qty}.",
            ]);
    }

    public function masterProductImport(): RedirectResponse
    {
        return redirect()->route('erp.admin.data-import', ['tab' => 'products']);
    }

    public function downloadMasterProductImportTemplate()
    {
        return Excel::download(new MasterProductImportTemplateExport, 'template-import-produk-master.xlsx');
    }

    public function downloadCrmCustomerImportTemplate()
    {
        return Excel::download(new CrmCustomerImportTemplateExport, 'template-import-customer.xlsx');
    }

    public function downloadProjectImportTemplate()
    {
        return Excel::download(new ProjectImportTemplateExport, 'template-import-project.xlsx');
    }

    public function importMasterProducts(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $import = new MasterProductsImport;
        Excel::import($import, $request->file('file'));

        $errCount = count($import->errors);
        $msg = "Impor produk selesai: {$import->imported} baris disimpan.";
        if ($errCount > 0) {
            $msg .= " {$errCount} baris dilewati (lihat detail di bawah).";
        }

        $created = $import->autoCreated;
        $autoMsgs = [];
        if (count($created['categories']) > 0) {
            $autoMsgs[] = count($created['categories']).' kategori baru';
        }
        if (count($created['uoms']) > 0) {
            $autoMsgs[] = count($created['uoms']).' UOM baru';
        }
        if (count($created['warehouses']) > 0) {
            $autoMsgs[] = count($created['warehouses']).' gudang baru';
        }
        if (count($autoMsgs) > 0) {
            $msg .= ' Master data dibuat otomatis: '.implode(', ', $autoMsgs).'.';
        }

        return redirect()
            ->route('erp.admin.data-import', ['tab' => 'products'])
            ->with('flash', [
                'type' => $errCount && $import->imported === 0 ? 'error' : ($errCount ? 'warning' : 'success'),
                'message' => $msg,
                'import_errors' => $import->errors,
                'imported_count' => $import->imported,
                'import_kind' => 'products',
                'auto_created' => $created,
            ]);
    }

    public function importProjects(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $import = new ProjectsImport;
        Excel::import($import, $request->file('file'));

        $errCount = count($import->errors);
        $msg = "Impor project selesai: {$import->imported} baris disimpan.";
        if ($errCount > 0) {
            $msg .= " {$errCount} baris dilewati (lihat detail di bawah).";
        }
        if (count($import->autoCreatedUsers) > 0) {
            $msg .= ' User baru dibuat otomatis: '.implode(', ', $import->autoCreatedUsers).'.';
        }

        return redirect()
            ->route('erp.admin.data-import', ['tab' => 'projects'])
            ->with('flash', [
                'type' => $errCount && $import->imported === 0 ? 'error' : ($errCount ? 'warning' : 'success'),
                'message' => $msg,
                'import_errors' => $import->errors,
                'imported_count' => $import->imported,
                'import_kind' => 'projects',
            ]);
    }

    public function importCrmCustomers(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $import = new CrmCustomersImport;
        Excel::import($import, $request->file('file'));

        $errCount = count($import->errors);
        $msg = "Impor customer selesai: {$import->imported} baris disimpan.";
        if ($errCount > 0) {
            $msg .= " {$errCount} baris dilewati (lihat detail di bawah).";
        }

        return redirect()
            ->route('erp.admin.data-import', ['tab' => 'customers'])
            ->with('flash', [
                'type' => $errCount && $import->imported === 0 ? 'error' : ($errCount ? 'warning' : 'success'),
                'message' => $msg,
                'import_errors' => $import->errors,
                'imported_count' => $import->imported,
                'import_kind' => 'customers',
            ]);
    }

    public function updateLabelPrinterLan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label_lan_enabled' => 'required|boolean',
            'label_lan_host' => ['nullable', 'string', 'max:64'],
            'label_lan_port' => 'required|integer|min:1|max:65535',
            'label_lan_profile_id' => ['nullable', 'exists:label_profiles,id'],
        ]);

        $setting = ErpSetting::query()->firstOrCreate([], [
            'app_name' => 'OCN ERP Suite',
            'app_tagline' => 'Integrated Business Platform',
        ]);

        $host = trim((string) ($validated['label_lan_host'] ?? ''));

        if ($validated['label_lan_enabled']) {
            if ($host === '') {
                return back()
                    ->withErrors(['label_lan_host' => 'Alamat IP atau hostname wajib diisi saat Label LAN diaktifkan.'])
                    ->withInput();
            }
            if (! LanTsplPrinter::isValidHost($host)) {
                return back()
                    ->withErrors(['label_lan_host' => 'Format alamat tidak valid (gunakan IP atau hostname).'])
                    ->withInput();
            }
            $lanPid = $validated['label_lan_profile_id'] ?? null;
            if (empty($lanPid) && empty($setting->label_smb_profile_id)) {
                return back()
                    ->withErrors(['label_lan_profile_id' => 'Pilih profil label, atau atur profil di halaman Label Windows (SMB) untuk dipakai sebagai ukuran default.'])
                    ->withInput();
            }
        }

        $setting->update([
            'label_lan_enabled' => $validated['label_lan_enabled'],
            'label_lan_host' => $validated['label_lan_enabled'] ? $host : null,
            'label_lan_port' => (int) $validated['label_lan_port'],
            'label_lan_profile_id' => $validated['label_lan_profile_id'] ?: null,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Pengaturan label LAN (TSPL) berhasil disimpan.']);
    }

    public function testLabelPrinterLan(LanTsplPrinter $lanTspl): RedirectResponse
    {
        $setting = ErpSetting::query()->with(['labelProfile', 'labelLanProfile'])->first();

        if (! ($setting?->label_lan_enabled ?? false)) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Aktifkan Label LAN (TSPL) dan simpan pengaturan (IP, port, profil) terlebih dahulu.',
            ]);
        }

        $host = trim((string) ($setting->label_lan_host ?? ''));
        if ($host === '' || ! LanTsplPrinter::isValidHost($host)) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Alamat printer label LAN belum valid.',
            ]);
        }

        $profile = $setting->resolveLabelProfileForLanPrinting();
        if (! $profile instanceof LabelProfile) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Profil label tidak ditemukan. Pilih profil di Label LAN atau Label SMB.',
            ]);
        }

        try {
            $payload = $lanTspl->buildSampleJob($profile);
            [$h, $p] = $lanTspl->send($host, (int) ($setting->label_lan_port ?: 9100), $payload);

            return back()->with('flash', [
                'type' => 'success',
                'message' => 'Data uji TSPL ('.$profile->name.') terkirim ke '.$h.':'.$p.'.',
            ]);
        } catch (RuntimeException $e) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function maintenanceMode(): Response
    {
        $setting = ErpSetting::query()->firstOrCreate([], [
            'app_name' => 'OCN ERP Suite',
            'app_tagline' => 'Integrated Business Platform',
        ]);

        return Inertia::render('ERP/Admin/MaintenanceMode', [
            'state' => [
                'maintenance_global_enabled' => (bool) $setting->maintenance_global_enabled,
                'maintenance_global_message' => (string) ($setting->maintenance_global_message ?? ''),
                'modules' => $setting->mergedMaintenanceModules(),
            ],
            'moduleLabels' => [
                'accounting' => 'Accounting, kas masuk/keluar, rekonsiliasi',
                'sales' => 'Sales & POS',
                'purchasing' => 'Purchasing',
                'inventory' => 'Inventory & master produk',
                'projects' => 'Projects, termin, anggaran',
                'hr' => 'HR & legal',
                'reporting' => 'Reporting & laporan/export',
                'administration' => 'Pengaturan ERP (admin)',
            ],
        ]);
    }

    public function updateMaintenanceMode(Request $request): RedirectResponse
    {
        $keys = array_keys(ErpSetting::defaultMaintenanceModules());
        $rules = [
            'maintenance_global_enabled' => 'required|boolean',
            'maintenance_global_message' => 'nullable|string|max:2000',
            'modules' => 'required|array',
        ];
        foreach ($keys as $key) {
            $rules["modules.{$key}.enabled"] = 'required|boolean';
            $rules["modules.{$key}.message"] = 'nullable|string|max:1000';
        }

        $validated = $request->validate($rules);

        $clean = ErpSetting::defaultMaintenanceModules();
        foreach ($keys as $key) {
            $row = $validated['modules'][$key] ?? [];
            $msg = isset($row['message']) && is_string($row['message']) ? trim($row['message']) : null;
            $clean[$key] = [
                'enabled' => ErpSetting::coerceMaintenanceEnabled($row['enabled'] ?? false),
                'message' => $msg !== null && $msg !== '' ? Str::limit($msg, 1000) : null,
            ];
        }

        $setting = ErpSetting::query()->firstOrCreate([], [
            'app_name' => 'OCN ERP Suite',
            'app_tagline' => 'Integrated Business Platform',
        ]);

        $setting->update([
            'maintenance_global_enabled' => $validated['maintenance_global_enabled'],
            'maintenance_global_message' => $validated['maintenance_global_message'] ?? null,
            'maintenance_modules' => $clean,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Pengaturan maintenance mode berhasil disimpan.']);
    }

    /**
     * Satu sumber kebenaran untuk tab Data Import → Seeders (UI + validasi POST).
     *
     * @return list<array{key: string, label: string, description: string, class: string}>
     */
    private function dataImportSeederRows(): array
    {
        return [
            ['key' => 'coa', 'label' => 'Chart of Accounts (COA)', 'description' => 'Akun-akun COA standar PSAK, kategori kas, dan mapping COA.', 'class' => 'CoaSeeder'],
            ['key' => 'product_categories', 'label' => 'Kategori Produk', 'description' => 'Kategori produk bisnis OCN Tech.', 'class' => 'ProductCategorySeeder'],
            ['key' => 'uom', 'label' => 'Satuan (UoM)', 'description' => '18 unit pengukuran dan 7 konversi.', 'class' => 'UomSeeder'],
            ['key' => 'master_products', 'label' => 'Master Produk', 'description' => '20 produk demo CCTV/IT beserta stok awal gudang.', 'class' => 'MasterProductSeeder'],
            ['key' => 'label_profiles', 'label' => 'Profil Label', 'description' => '9 profil label thermal (ZPL & TSPL) untuk ukuran retail.', 'class' => 'LabelProfileSeeder'],
            ['key' => 'parser_rules', 'label' => 'Parser Rules Chatbot', 'description' => '35 rule parser keyword untuk chatbot ERP.', 'class' => 'ErpChatParserRuleSeeder'],
            ['key' => 'pos_receipt', 'label' => 'Template Struk POS', 'description' => 'Template struk thermal POS default.', 'class' => 'FillThermalPosReceiptTemplatesSeeder'],
            ['key' => 'project_flow', 'label' => 'Alur Project Demo', 'description' => '5 data project lengkap: budget, termin, cashflow, tim, task, referral, dan material.', 'class' => 'ProjectFlowSeeder'],
        ];
    }

    private function legacyImportPageProps(): array
    {
        $request = request();
        $legacyQcReport = session('legacy_project_sales_qc');
        $legacySupplierReport = null;
        $legacySupplierReportError = null;

        if ($legacyQcReport === null) {
            try {
                $legacyQcReport = app(LegacyProjectSalesQcService::class)->buildReport();
            } catch (\Throwable $e) {
                Log::warning('Legacy project QC report auto-refresh failed.', [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
        }

        try {
            $legacySupplierReport = app(LegacySupplierImportService::class)->buildReport();
        } catch (\Throwable $e) {
            $legacySupplierReportError = $e->getMessage();
        }

        $legacyProjectSearch = trim((string) $request->string('legacy_project_q'));
        $legacyProjectReadiness = trim((string) $request->string('legacy_project_readiness'));
        $legacyProjectVisibility = trim((string) $request->string('legacy_project_visibility', 'hide_imported'));
        $legacyProjectPerPage = $this->resolvedPerPage($request);
        $legacySupplierSearch = trim((string) $request->string('legacy_supplier_q'));
        $legacySupplierPerPage = $this->resolvedLegacyImportAuxPerPage($request, 'legacy_supplier_per_page');

        $legacyProjectPaginator = null;
        if (is_array($legacyQcReport)) {
            $projectRows = collect($legacyQcReport['projects'] ?? [])
                ->filter(function (array $project) use ($legacyProjectSearch, $legacyProjectReadiness, $legacyProjectVisibility): bool {
                    if ($legacyProjectSearch !== '') {
                        $haystack = strtolower(implode(' ', [
                            (string) ($project['project_number'] ?? ''),
                            (string) ($project['title'] ?? ''),
                            (string) ($project['customer_name'] ?? ''),
                            (string) ($project['import_key'] ?? ''),
                        ]));

                        if (! str_contains($haystack, strtolower($legacyProjectSearch))) {
                            return false;
                        }
                    }

                    if ($legacyProjectReadiness !== '' && (string) ($project['readiness'] ?? '') !== $legacyProjectReadiness) {
                        return false;
                    }

                    if ($legacyProjectVisibility === 'show_imported_only' && (bool) ($project['is_importable'] ?? false)) {
                        return false;
                    }

                    if ($legacyProjectVisibility === 'hide_imported' && ! (bool) ($project['is_importable'] ?? false)) {
                        return false;
                    }

                    return true;
                })
                ->values();

            $legacyProjectPaginator = $this->paginateCollectionForRequest($projectRows, $legacyProjectPerPage, $request, 'legacy_project_page');
            $legacyQcReport['projects'] = $legacyProjectPaginator->items();
        }

        $legacySupplierPaginator = null;
        if (is_array($legacySupplierReport)) {
            $supplierRows = collect($legacySupplierReport['suppliers'] ?? [])
                ->filter(function (array $supplier) use ($legacySupplierSearch): bool {
                    if ($legacySupplierSearch === '') {
                        return true;
                    }

                    $haystack = strtolower(implode(' ', [
                        (string) ($supplier['name'] ?? ''),
                        (string) ($supplier['contact_person'] ?? ''),
                        (string) ($supplier['phone'] ?? ''),
                        (string) ($supplier['email'] ?? ''),
                        (string) ($supplier['legacy_id'] ?? ''),
                    ]));

                    return str_contains($haystack, strtolower($legacySupplierSearch));
                })
                ->values();

            $legacySupplierPaginator = $this->paginateCollectionForRequest($supplierRows, $legacySupplierPerPage, $request, 'legacy_supplier_page');
            $legacySupplierReport['suppliers'] = $legacySupplierPaginator->items();
        }

        return [
            'legacyQcReport' => $legacyQcReport,
            'legacyProjectPaginator' => $legacyProjectPaginator,
            'legacySupplierReport' => $legacySupplierReport,
            'legacySupplierPaginator' => $legacySupplierPaginator,
            'legacySupplierReportError' => $legacySupplierReportError,
            'filters' => [
                'legacy_project_q' => $legacyProjectSearch,
                'legacy_project_readiness' => $legacyProjectReadiness,
                'legacy_project_visibility' => $legacyProjectVisibility,
                'per_page' => $legacyProjectPerPage,
                'legacy_supplier_q' => $legacySupplierSearch,
                'legacy_supplier_per_page' => $legacySupplierPerPage,
            ],
            'procurementVendors' => Vendor::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (Vendor $vendor) => [
                    'id' => $vendor->id,
                    'code' => $vendor->code,
                    'name' => $vendor->name,
                ]),
            'procurementImportStagings' => $this->procurementImportStagingPayload(
                ProcurementImportStaging::query()
                    ->with(['project:id,name,import_key', 'company:id,name', 'warehouse:id,name,code', 'lines.product:id,name,sku', 'lines.vendor:id,name,code', 'converter:id,name'])
                    ->latest('updated_at')
                    ->latest('procurement_date')
                    ->latest('created_at')
                    ->limit(20)
                    ->get()
            ),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProcurementImportStaging>  $stagings
     * @return array<int, array<string, mixed>>
     */
    private function procurementImportStagingPayload(\Illuminate\Support\Collection $stagings): array
    {
        return $stagings
            ->map(fn (ProcurementImportStaging $staging) => [
                'id' => $staging->id,
                'source_import_key' => $staging->source_import_key,
                'legacy_project_number' => $staging->legacy_project_number,
                'legacy_project_name' => $staging->legacy_project_name,
                'procurement_date' => optional($staging->procurement_date)->toDateString(),
                'status' => $staging->status,
                'notes' => $staging->notes,
                'converted_at' => optional($staging->converted_at)->toDateTimeString(),
                'converted_by_name' => $staging->converter?->name,
                'conversion_summary' => $staging->conversion_summary,
                'company_name' => $staging->company?->name,
                'warehouse_name' => $staging->warehouse?->name,
                'warehouse_code' => $staging->warehouse?->code,
                'project' => $staging->project ? [
                    'id' => $staging->project->id,
                    'name' => $staging->project->name,
                    'import_key' => $staging->project->import_key,
                ] : null,
                'lines' => $staging->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'master_product_id' => $line->master_product_id,
                    'product_name' => $line->product_name,
                    'sku' => $line->product?->sku,
                    'qty' => (float) $line->qty,
                    'unit' => $line->unit,
                    'unit_cost' => (float) $line->unit_cost,
                    'line_total' => (float) $line->line_total,
                    'status' => $line->status,
                    'vendor_id' => $line->vendor_id,
                    'vendor_code' => $line->vendor?->code,
                    'vendor_name' => $line->vendor?->name,
                ])->values(),
            ])
            ->values()
            ->all();
    }

    private function resolvedLegacyImportAuxPerPage(Request $request, string $key): int
    {
        $perPage = (int) $request->query($key, 25);
        $allowed = [25, 50, 75, 100, 125, 150, 175, 200, 225, 250];

        return in_array($perPage, $allowed, true) ? $perPage : 25;
    }

    private function paginateCollectionForRequest(\Illuminate\Support\Collection $rows, int $perPage, Request $request, string $pageName = 'page'): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage($pageName);

        return new LengthAwarePaginator(
            $rows->forPage($currentPage, $perPage)->values()->all(),
            $rows->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => $pageName]
        );
    }

    private function productionDbSyncPageProps(ProductionErpSyncService $syncService): array
    {
        $modules = $syncService->modules();
        $sourceConnection = $syncService->sourceConnection();
        $targetConnection = $syncService->targetConnection();

        return [
            'syncConfig' => [
                'source_connection' => $sourceConnection,
                'target_connection' => $targetConnection,
                'source_configured' => $this->databaseConnectionConfigured($sourceConnection),
                'target_configured' => $this->databaseConnectionConfigured($targetConnection),
                'modules' => collect($modules)
                    ->map(fn (array $tables, string $key) => [
                        'key' => $key,
                        'label' => Str::of($key)->replace('_', ' ')->title()->toString(),
                        'tables' => array_values($tables),
                        'table_count' => count($tables),
                    ])
                    ->values()
                    ->all(),
                'all_tables' => $syncService->allTables(),
            ],
            'lastResult' => session('production_sync_result'),
            'lastFilters' => session('production_sync_filters', [
                'mode' => 'dry_run',
                'module_keys' => [],
                'table_names' => [],
                'chunk_size' => 500,
            ]),
        ];
    }

    private function databaseConnectionConfigured(string $connection): bool
    {
        $config = (array) config('database.connections.'.$connection, []);

        return trim((string) ($config['url'] ?? '')) !== ''
            || trim((string) ($config['database'] ?? '')) !== '';
    }

    private function projectMaterialStatus(ProjectMaterial $material): string
    {
        $plannedQty = (float) $material->planned_qty;
        $reservedQty = (float) $material->reserved_qty;
        $issuedQty = (float) $material->issued_qty;

        if ($plannedQty > 0 && $issuedQty >= $plannedQty) {
            return 'issued';
        }

        if ($plannedQty > 0 && $reservedQty >= $plannedQty) {
            return 'ready';
        }

        if ($reservedQty > 0) {
            return 'partial';
        }

        return 'planned';
    }

    private function mergeProjectMaterialNotes(?string $existingNotes, ?string $incomingNotes): ?string
    {
        $notes = collect([$existingNotes, $incomingNotes])
            ->map(fn ($note) => trim((string) $note))
            ->filter()
            ->unique()
            ->values();

        return $notes->isEmpty() ? null : $notes->implode("\n");
    }

    private function svcSkuNormalizationSummary(): array
    {
        $products = MasterProduct::query()
            ->with('warehouse:id,code,name')
            ->where('sku', 'like', 'SVC-%')
            ->orderBy('sku')
            ->get([
                'id',
                'sku',
                'name',
                'product_type',
                'warehouse_id',
                'stock',
                'min_stock',
                'low_stock_alert_enabled',
            ]);

        $pending = $products->filter(function (MasterProduct $product): bool {
            return $product->product_type !== MasterProduct::PRODUCT_TYPE_SERVICE
                || $product->warehouse_id !== null
                || (float) $product->stock !== 0.0
                || (float) $product->min_stock !== 0.0
                || (bool) $product->low_stock_alert_enabled !== false;
        })->values();

        return [
            'total_count' => $products->count(),
            'pending_count' => $pending->count(),
            'normalized_count' => $products->count() - $pending->count(),
            'samples' => $pending
                ->take(8)
                ->map(fn (MasterProduct $product) => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'product_type' => $product->product_type,
                    'warehouse_name' => $product->warehouse?->name,
                    'warehouse_code' => $product->warehouse?->code,
                    'stock' => (float) $product->stock,
                    'min_stock' => (float) $product->min_stock,
                    'low_stock_alert_enabled' => (bool) $product->low_stock_alert_enabled,
                ])
                ->values(),
        ];
    }

    public function serverMonitoring(ServerMetricsService $metrics): Response
    {
        return Inertia::render('ERP/Admin/ServerMonitoring', [
            'metrics' => $metrics->collect(),
        ]);
    }
}
