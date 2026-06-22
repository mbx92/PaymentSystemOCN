<?php

namespace App\Http\Controllers;

use App\Services\SupplierCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierCatalogController extends Controller
{
    public function __construct(
        private readonly SupplierCatalogService $catalog,
    ) {}

    public function page(): Response
    {
        return Inertia::render('ERP/Projects/SupplierCatalog', [
            'supplier_name' => $this->catalog->supplierName(),
            'sheets' => $this->catalog->sheets(),
        ]);
    }

    public function sheets(): JsonResponse
    {
        return response()->json([
            'supplier_name' => $this->catalog->supplierName(),
            'sheets' => $this->catalog->sheets(),
        ]);
    }

    public function items(Request $request, string $sheetKey): JsonResponse
    {
        $search = $request->string('q')->toString();

        try {
            $items = $this->catalog->itemsForSheet($sheetKey, $search !== '' ? $search : null);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'items' => [],
                'total' => 0,
            ], 502);
        }

        return response()->json([
            'sheet_key' => $sheetKey,
            'items' => $items,
            'total' => count($items),
        ]);
    }
}
