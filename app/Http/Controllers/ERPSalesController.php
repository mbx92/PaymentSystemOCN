<?php

namespace App\Http\Controllers;

use App\Models\MasterProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ERPSalesController extends Controller
{
    public function pos(Request $request): Response
    {
        $products = MasterProduct::query()
            ->where('status', 'active')
            ->whereIn('sales_channel', ['pos', 'both'])
            ->orderBy('name')
            ->get(['sku', 'name', 'selling_price', 'stock'])
            ->map(fn (MasterProduct $product) => [
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'price' => (float) $product->selling_price,
                'stock' => $product->stock,
            ]);

        return Inertia::render('ERP/Sales/POS', [
            'products' => $products,
            'fullscreen' => $request->boolean('fullscreen'),
        ]);
    }

    public function projectInvoices(): Response
    {
        return Inertia::render('ERP/Sales/ProjectInvoices', [
            'invoices' => [
                ['number' => 'INV-PRJ-0001', 'project' => 'Website Company Profile', 'client' => 'PT Maju Jaya', 'amount' => 12500000, 'status' => 'posted'],
                ['number' => 'INV-PRJ-0002', 'project' => 'Instalasi CCTV Gudang', 'client' => 'CV Sinar Plastik', 'amount' => 9800000, 'status' => 'approved'],
            ],
        ]);
    }
}
