<?php

namespace App\Http\Controllers;

use App\ERP\Inventory\Models\Warehouse;
use App\Models\ErpSetting;
use App\Models\LabelProfile;
use App\Models\MasterProduct;
use App\Models\MasterProductChannelPrice;
use App\Models\MasterProductUomMapping;
use App\Models\ProductCategory;
use App\Models\Uom;
use App\Services\LanTsplPrinter;
use App\Services\WindowsSmbRawPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ERPMasterProductController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = $this->resolvedPerPage($request);

        $products = MasterProduct::query()
            ->when($request->filled('sales_channel'), fn ($q) => $q->where('sales_channel', $request->string('sales_channel')->toString()))
            ->when($request->filled('product_type'), fn ($q) => $q->where('product_type', $request->string('product_type')->toString()))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->whereHas('warehouseStocks', fn ($sub) => $sub->where('warehouse_id', $request->integer('warehouse_id'))))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'ilike', "%{$term}%")
                        ->orWhere('sku', 'ilike', "%{$term}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('ERP/MasterProducts/Index', [
            'products' => $products,
            'filters' => $this->filtersWithPerPage($request, ['q', 'sales_channel', 'product_type', 'warehouse_id']),
            'categories' => ProductCategory::query()->where('status', 'active')->orderBy('name')->get(['name']),
            'uoms' => Uom::query()->where('status', 'active')->orderBy('code')->get(['code', 'name']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => 'nullable|string|max:64|unique:master_products,sku',
            'barcode' => 'nullable|string|max:100|unique:master_products,barcode',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100|exists:product_categories,name',
            'uom' => 'required|string|max:20|exists:uoms,code',
            'sales_channel' => 'required|in:pos,project,both',
            'product_type' => 'required|in:finished_goods,project_material,service',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
            'selling_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'lead_time_days' => 'nullable|integer|min:1|max:365',
        ]);

        $validated['lead_time_days'] = $validated['lead_time_days'] ?? 7;
        if ($validated['product_type'] === MasterProduct::PRODUCT_TYPE_SERVICE) {
            $validated['stock'] = 0;
            $validated['min_stock'] = 0;
            $validated['total_sold'] = 0;
            $validated['lead_time_days'] = 1;
            $validated['low_stock_alert_enabled'] = false;
        }

        if (empty($validated['sku'])) {
            $validated['sku'] = MasterProduct::generateSku($validated['category']);
        }

        if (empty($validated['barcode'])) {
            $validated['barcode'] = MasterProduct::generateBarcode();
        }

        MasterProduct::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Produk berhasil ditambahkan.']);
    }

    public function previewCodes(Request $request): JsonResponse
    {
        $category = $request->string('category')->toString();

        return response()->json([
            'sku' => $category ? MasterProduct::generateSku($category) : null,
            'barcode' => MasterProduct::generateBarcode(),
        ]);
    }

    public function show(MasterProduct $masterProduct, WindowsSmbRawPrinter $smb): Response
    {
        $masterProduct->load(['uomMappings', 'channelPrices']);

        return Inertia::render('ERP/MasterProducts/Show', [
            'product' => $masterProduct,
            'barcodePrint' => $this->barcodePrintAvailability($smb, $masterProduct),
            'uomMappings' => $masterProduct->uomMappings->map(fn (MasterProductUomMapping $mapping) => [
                'id' => $mapping->id,
                'uom_code' => $mapping->uom_code,
                'multiplier' => (float) $mapping->multiplier,
                'price_operation' => $mapping->price_operation ?: 'multiply',
                'selling_price' => (float) $mapping->selling_price,
                'use_auto_price' => (bool) $mapping->use_auto_price,
                'status' => $mapping->status,
            ]),
            'channelPrices' => $masterProduct->channelPrices->map(fn (MasterProductChannelPrice $price) => [
                'id' => $price->id,
                'sales_channel' => $price->sales_channel,
                'label' => $price->label ?: $this->priceChannelLabel($price->sales_channel),
                'selling_price' => (float) $price->selling_price,
                'status' => $price->status,
            ]),
            'priceChannels' => $this->priceChannels(),
            'uoms' => Uom::query()
                ->where('status', 'active')
                ->orderBy('code')
                ->get(['code', 'name']),
            'categories' => ProductCategory::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['name']),
        ]);
    }

    public function printBarcode(Request $request, MasterProduct $masterProduct, WindowsSmbRawPrinter $smb, LanTsplPrinter $lanTspl): RedirectResponse
    {
        $validated = $request->validate([
            'copies' => 'required|integer|min:1|max:999',
        ]);

        $availability = $this->barcodePrintAvailability($smb, $masterProduct);
        if (! $availability['available']) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => $availability['hint'] ?? 'Cetak barcode tidak tersedia.',
            ]);
        }

        $setting = ErpSetting::query()->with(['labelProfile', 'labelLanProfile'])->first();
        if (! $setting) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Pengaturan ERP belum tersedia.']);
        }

        $barcodeData = trim((string) ($masterProduct->barcode ?: $masterProduct->sku));
        if ($barcodeData === '') {
            return back()->with('flash', ['type' => 'error', 'message' => 'Produk tidak punya barcode atau SKU untuk dicetak.']);
        }

        $priceLine = 'Rp '.number_format((float) $masterProduct->selling_price, 0, ',', '.');

        try {
            if ($this->labelLanChannelReady($setting)) {
                $profile = $setting->resolveLabelProfileForLanPrinting();
                if (! $profile instanceof LabelProfile) {
                    return back()->with('flash', ['type' => 'error', 'message' => 'Profil label untuk TSPL tidak ditemukan.']);
                }
                $host = trim((string) $setting->label_lan_host);
                $port = (int) ($setting->label_lan_port ?: 9100);
                $payload = $lanTspl->buildLabelJob(
                    $profile,
                    $barcodeData,
                    (string) $masterProduct->name,
                    $priceLine,
                    (int) $validated['copies'],
                );
                [$h, $p] = $lanTspl->send($host, $port, $payload);

                return back()->with('flash', [
                    'type' => 'success',
                    'message' => 'Perintah cetak '.(int) $validated['copies'].' label (TSPL) terkirim ke '.$h.':'.$p.'.',
                ]);
            }

            if ($this->labelSmbChannelReady($setting, $smb)) {
                $profile = $setting->labelProfile instanceof LabelProfile ? $setting->labelProfile : null;
                if ($profile === null) {
                    return back()->with('flash', ['type' => 'error', 'message' => 'Profil label belum dipilih.']);
                }
                $unc = $smb->normalizeUnc((string) ($setting->label_smb_unc ?? ''));
                $payload = $smb->productBarcodePayloadForProfile(
                    $profile,
                    $barcodeData,
                    (string) $masterProduct->name,
                    (int) $validated['copies'],
                    $priceLine,
                );
                $smb->sendRaw($unc, $payload);

                return back()->with('flash', [
                    'type' => 'success',
                    'message' => 'Perintah cetak '.(int) $validated['copies'].' label barcode terkirim ke printer (SMB).',
                ]);
            }
        } catch (RuntimeException $e) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        return back()->with('flash', ['type' => 'error', 'message' => 'Tidak ada saluran label yang siap (LAN TSPL atau SMB).']);
    }

    public function update(Request $request, MasterProduct $masterProduct): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:64', Rule::unique('master_products', 'sku')->ignore($masterProduct->id)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('master_products', 'barcode')->ignore($masterProduct->id)],
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100|exists:product_categories,name',
            'uom' => 'required|string|max:20|exists:uoms,code',
            'sales_channel' => 'required|in:pos,project,both',
            'product_type' => 'required|in:finished_goods,project_material,service',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
            'selling_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'lead_time_days' => 'nullable|integer|min:1|max:365',
        ]);

        $validated['lead_time_days'] = $validated['lead_time_days'] ?? $masterProduct->lead_time_days ?? 7;
        if ($validated['product_type'] === MasterProduct::PRODUCT_TYPE_SERVICE) {
            $validated['stock'] = 0;
            $validated['min_stock'] = 0;
            $validated['total_sold'] = 0;
            $validated['lead_time_days'] = 1;
            $validated['low_stock_alert_enabled'] = false;
        }
        $masterProduct->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Produk berhasil diperbarui.']);
    }

    public function storeUomMapping(Request $request, MasterProduct $masterProduct): RedirectResponse
    {
        $validated = $request->validate([
            'uom_code' => 'required|string|exists:uoms,code',
            'multiplier' => 'required|numeric|gt:0',
            'price_operation' => 'nullable|in:multiply,divide',
            'selling_price' => 'nullable|numeric|min:0',
            'use_auto_price' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validated['uom_code'] === $masterProduct->uom) {
            return back()->withErrors([
                'uom_code' => 'UoM mapping tidak boleh sama dengan UoM dasar produk.',
            ]);
        }

        $useAutoPrice = (bool) ($validated['use_auto_price'] ?? false);
        $priceOperation = $validated['price_operation'] ?? 'multiply';
        $price = $useAutoPrice
            ? $this->computeMappedPrice((float) $masterProduct->selling_price, (float) $validated['multiplier'], $priceOperation)
            : ((array_key_exists('selling_price', $validated) && $validated['selling_price'] !== null)
            ? (float) $validated['selling_price']
            : $this->computeMappedPrice((float) $masterProduct->selling_price, (float) $validated['multiplier'], $priceOperation));

        MasterProductUomMapping::query()->updateOrCreate(
            [
                'master_product_id' => $masterProduct->id,
                'uom_code' => $validated['uom_code'],
            ],
            [
                'multiplier' => (float) $validated['multiplier'],
                'price_operation' => $priceOperation,
                'selling_price' => $price,
                'use_auto_price' => $useAutoPrice,
                'status' => $validated['status'],
            ]
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Mapping UoM produk berhasil disimpan.']);
    }

    public function updateUomMapping(Request $request, MasterProduct $masterProduct, MasterProductUomMapping $mapping): RedirectResponse
    {
        abort_unless($mapping->master_product_id === $masterProduct->id, 404);

        $validated = $request->validate([
            'multiplier' => 'required|numeric|gt:0',
            'price_operation' => 'nullable|in:multiply,divide',
            'selling_price' => 'nullable|numeric|min:0',
            'use_auto_price' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        $useAutoPrice = (bool) ($validated['use_auto_price'] ?? false);
        $priceOperation = $validated['price_operation'] ?? ($mapping->price_operation ?: 'multiply');
        $price = $useAutoPrice
            ? $this->computeMappedPrice((float) $masterProduct->selling_price, (float) $validated['multiplier'], $priceOperation)
            : ((array_key_exists('selling_price', $validated) && $validated['selling_price'] !== null)
                ? (float) $validated['selling_price']
                : (float) $mapping->selling_price);

        $mapping->update([
            'multiplier' => (float) $validated['multiplier'],
            'price_operation' => $priceOperation,
            'selling_price' => $price,
            'use_auto_price' => $useAutoPrice,
            'status' => $validated['status'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Mapping UoM produk berhasil diperbarui.']);
    }

    public function destroyUomMapping(MasterProduct $masterProduct, MasterProductUomMapping $mapping): RedirectResponse
    {
        abort_unless($mapping->master_product_id === $masterProduct->id, 404);
        $mapping->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Mapping UoM produk berhasil dihapus.']);
    }

    public function storeChannelPrice(Request $request, MasterProduct $masterProduct): RedirectResponse
    {
        $validated = $request->validate([
            'sales_channel' => ['required', 'string', Rule::in(array_column($this->priceChannels(), 'key'))],
            'selling_price' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        MasterProductChannelPrice::query()->updateOrCreate(
            [
                'master_product_id' => $masterProduct->id,
                'sales_channel' => $validated['sales_channel'],
            ],
            [
                'label' => $this->priceChannelLabel($validated['sales_channel']),
                'selling_price' => (float) $validated['selling_price'],
                'status' => $validated['status'],
            ]
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Harga sales channel produk berhasil disimpan.']);
    }

    public function updateChannelPrice(Request $request, MasterProduct $masterProduct, MasterProductChannelPrice $channelPrice): RedirectResponse
    {
        abort_unless($channelPrice->master_product_id === $masterProduct->id, 404);

        $validated = $request->validate([
            'selling_price' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        $channelPrice->update([
            'selling_price' => (float) $validated['selling_price'],
            'status' => $validated['status'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Harga sales channel produk berhasil diperbarui.']);
    }

    public function destroyChannelPrice(MasterProduct $masterProduct, MasterProductChannelPrice $channelPrice): RedirectResponse
    {
        abort_unless($channelPrice->master_product_id === $masterProduct->id, 404);
        $channelPrice->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Harga sales channel produk berhasil dihapus.']);
    }

    public function destroy(MasterProduct $masterProduct): RedirectResponse
    {
        $masterProduct->delete();

        return redirect()->route('erp.master-products.index')
            ->with('flash', ['type' => 'success', 'message' => 'Produk berhasil dihapus.']);
    }

    private function computeMappedPrice(float $basePrice, float $multiplier, string $operation): float
    {
        if ($operation === 'divide') {
            return round($basePrice / max($multiplier, 0.0001), 2);
        }

        return round($basePrice * $multiplier, 2);
    }

    private function priceChannelLabel(string $key): string
    {
        return collect($this->priceChannels())->firstWhere('key', $key)['label'] ?? strtoupper($key);
    }

    private function priceChannels(): array
    {
        return [
            ['key' => 'retail', 'label' => 'Retail'],
            ['key' => 'grosir', 'label' => 'Grosir'],
            ['key' => 'reseller', 'label' => 'Reseller'],
            ['key' => 'marketplace', 'label' => 'Marketplace'],
            ['key' => 'online', 'label' => 'Online'],
        ];
    }

    /**
     * @return array{available: bool, hint: string|null}
     */
    private function barcodePrintAvailability(WindowsSmbRawPrinter $smb, MasterProduct $product): array
    {
        $data = trim((string) ($product->barcode ?: $product->sku));
        if ($data === '') {
            return [
                'available' => false,
                'hint' => 'Isi barcode atau SKU produk terlebih dahulu.',
            ];
        }

        $setting = ErpSetting::query()->with(['labelProfile', 'labelLanProfile'])->first();
        if ($this->labelLanChannelReady($setting)) {
            return ['available' => true, 'hint' => null];
        }
        if ($this->labelSmbChannelReady($setting, $smb)) {
            return ['available' => true, 'hint' => null];
        }

        return [
            'available' => false,
            'hint' => 'Atur printer label di Administration: Label LAN (TSPL) dengan IP dan profil, atau Label Windows (SMB) dengan UNC (server Windows).',
        ];
    }

    private function labelLanChannelReady(?ErpSetting $setting): bool
    {
        if (! $setting?->label_lan_enabled) {
            return false;
        }
        $host = trim((string) ($setting->label_lan_host ?? ''));
        if ($host === '' || ! LanTsplPrinter::isValidHost($host)) {
            return false;
        }
        $profile = $setting->resolveLabelProfileForLanPrinting();

        return $profile instanceof LabelProfile;
    }

    private function labelSmbChannelReady(?ErpSetting $setting, WindowsSmbRawPrinter $smb): bool
    {
        if (! $smb->supportsUncFromPhp()) {
            return false;
        }
        if (! ($setting?->label_smb_enabled ?? false)) {
            return false;
        }
        $unc = $smb->normalizeUnc((string) ($setting->label_smb_unc ?? ''));
        if (! $smb->isValidUnc($unc)) {
            return false;
        }

        return $setting->labelProfile instanceof LabelProfile;
    }
}
