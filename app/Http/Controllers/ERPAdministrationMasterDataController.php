<?php

namespace App\Http\Controllers;

use App\ERP\Core\Models\DocumentSequence;
use App\ERP\Inventory\Models\Warehouse;
use App\Models\ErpSetting;
use App\Models\ErpChatParserRule;
use App\Models\LandingSite;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

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
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'ERP Setting berhasil diperbarui.']);
    }

    public function landingSites(): Response
    {
        return Inertia::render('ERP/Admin/LandingSites', [
            'landingSites' => LandingSite::query()
                ->with(['warehouse:id,code,name'])
                ->orderBy('is_active', 'desc')
                ->orderBy('name')
                ->get(),
            'warehouses' => Warehouse::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function storeLandingSite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'domain' => 'required|string|max:190|unique:landing_sites,domain',
            'layout_key' => 'required|string|in:toko,cctv',
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
            'layout_key' => 'required|string|in:toko,cctv',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'is_active' => 'required|boolean',
        ]);

        $validated['domain'] = strtolower(trim((string) $validated['domain']));

        $landingSite->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Landing site berhasil diperbarui.']);
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
            'rules' => $query->get(),
            'filters' => [
                'search' => $search,
                'status' => $status,
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

    public function documentSequences(): Response
    {
        return Inertia::render('ERP/Admin/DocumentSequences', [
            'sequences' => DocumentSequence::query()->orderBy('module')->orderBy('document_type')->get(),
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

    public function paymentMethods(): Response
    {
        return Inertia::render('ERP/Admin/PaymentMethods', [
            'paymentMethods' => PaymentMethod::query()->orderBy('name')->get(),
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
}

