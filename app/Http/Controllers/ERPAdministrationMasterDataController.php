<?php

namespace App\Http\Controllers;

use App\ERP\Core\Models\DocumentSequence;
use App\ERP\Inventory\Models\Warehouse;
use App\Exports\MasterProductImportTemplateExport;
use App\Exports\ProjectImportTemplateExport;
use App\Imports\MasterProductsImport;
use App\Imports\ProjectsImport;
use App\Models\ErpChatParserRule;
use App\Models\ErpSetting;
use App\Models\LabelProfile;
use App\Models\LandingSite;
use App\Models\LandingSitePage;
use App\Models\PaymentMethod;
use App\Services\LanEscPosPrinter;
use App\Services\LanTsplPrinter;
use App\Services\ServerMetricsService;
use App\Services\ThermalPosReceiptData;
use App\Services\ThermalPosReceiptRenderer;
use App\Services\WindowsSmbRawPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class ERPAdministrationMasterDataController extends Controller
{
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
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'domain' => 'required|string|max:190|unique:landing_sites,domain',
            'layout_key' => 'required|string|in:toko,cctv,coming_soon',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'is_active' => 'required|boolean',
        ]);

        $validated['domain'] = strtolower(trim((string) $validated['domain']));

        LandingSite::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Landing site berhasil ditambahkan.']);
    }

    public function updateLandingSite(Request $request, LandingSite $landingSite): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'domain' => 'required|string|max:190|unique:landing_sites,domain,'.$landingSite->id,
            'layout_key' => 'required|string|in:toko,cctv,coming_soon',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'is_active' => 'required|boolean',
        ]);

        $validated['domain'] = strtolower(trim((string) $validated['domain']));

        $landingSite->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Landing site berhasil diperbarui.']);
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
                ->orderBy('name')
                ->paginate($this->resolvedPerPage($request))
                ->withQueryString(),
            'filters' => $this->filtersWithPerPage($request, []),
        ]);
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|alpha_dash|unique:payment_methods,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['code'] = strtolower($validated['code']);
        PaymentMethod::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Metode pembayaran berhasil ditambahkan.']);
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|alpha_dash|unique:payment_methods,code,'.$paymentMethod->id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['code'] = strtolower($validated['code']);
        $paymentMethod->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Metode pembayaran berhasil diperbarui.']);
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

    public function dataImport(Request $request): Response
    {
        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['products', 'projects', 'seeders'], true)) {
            $tab = 'products';
        }

        return Inertia::render('ERP/Admin/DataImport', [
            'activeTab' => $tab,
            'seeders' => $this->dataImportSeederRows(),
        ]);
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

    public function masterProductImport(): RedirectResponse
    {
        return redirect()->route('erp.admin.data-import', ['tab' => 'products']);
    }

    public function downloadMasterProductImportTemplate()
    {
        return Excel::download(new MasterProductImportTemplateExport, 'template-import-produk-master.xlsx');
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
            ['key' => 'product_categories', 'label' => 'Kategori Produk', 'description' => '17 kategori produk bisnis OCN Tech.', 'class' => 'ProductCategorySeeder'],
            ['key' => 'uom', 'label' => 'Satuan (UoM)', 'description' => '18 unit pengukuran dan 7 konversi.', 'class' => 'UomSeeder'],
            ['key' => 'label_profiles', 'label' => 'Profil Label', 'description' => '9 profil label thermal (ZPL & TSPL) untuk ukuran retail.', 'class' => 'LabelProfileSeeder'],
            ['key' => 'parser_rules', 'label' => 'Parser Rules Chatbot', 'description' => '35 rule parser keyword untuk chatbot ERP.', 'class' => 'ErpChatParserRuleSeeder'],
            ['key' => 'pos_receipt', 'label' => 'Template Struk POS', 'description' => 'Template struk thermal POS default.', 'class' => 'FillThermalPosReceiptTemplatesSeeder'],
        ];
    }

    public function serverMonitoring(ServerMetricsService $metrics): Response
    {
        return Inertia::render('ERP/Admin/ServerMonitoring', [
            'metrics' => $metrics->collect(),
        ]);
    }
}
