<?php

namespace App\Http\Controllers;

use App\ERP\Core\Services\RuleBasedErpChatParser;
use App\Models\MasterProduct;
use App\Models\PosSale;
use App\Models\ProjectPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ErpChatbotController extends Controller
{
    public function ask(Request $request, RuleBasedErpChatParser $parser): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $message = trim($validated['message']);
        $parsed = $parser->parse($message);

        if (! $parsed['matched']) {
            return response()->json([
                'ok' => true,
                'intent' => null,
                'answer' => 'Maaf, saya belum memahami pertanyaan itu. Coba gunakan kata kunci seperti: stok, harga, invoice belum dibayar, atau POS hari ini.',
            ]);
        }

        $intent = $parsed['rule']['intent_key'] ?? null;
        $customResponse = trim((string) ($parsed['rule']['response_text'] ?? ''));
        if ($customResponse !== '') {
            return response()->json([
                'ok' => true,
                'intent' => $intent,
                'answer' => $customResponse,
            ]);
        }

        $answer = match ($intent) {
            'stock_lookup' => $this->answerStockLookup($message),
            'product_price_lookup' => $this->answerPriceLookup($message),
            'invoice_unpaid_list' => $this->answerUnpaidInvoiceList(),
            'invoice_due_list' => 'Fitur invoice jatuh tempo belum diaktifkan. Saat ini kamu bisa gunakan "invoice belum dibayar".',
            'pos_sales_today' => $this->answerPosSalesToday(),
            'help' => $this->answerHelp(),
            default => 'Intent dikenali, tapi handler belum tersedia.',
        };

        return response()->json([
            'ok' => true,
            'intent' => $intent,
            'answer' => $answer,
        ]);
    }

    private function answerStockLookup(string $message): string
    {
        $term = $this->extractProductTerm($message);
        if ($term === '') {
            return 'Silakan sebutkan produk yang ingin dicek stoknya. Contoh: "stok lid cup".';
        }

        $products = $this->searchProducts($term);
        if ($products->isEmpty()) {
            return "Produk dengan kata kunci \"{$term}\" tidak ditemukan.";
        }

        if ($products->count() > 1) {
            $names = $products->take(5)->map(fn ($product) => $product->name)->implode(', ');
            return "Ditemukan beberapa produk: {$names}. Mohon sebutkan lebih spesifik.";
        }

        $product = $products->first();
        return "Stok {$product->name} saat ini {$product->stock} {$product->uom}.";
    }

    private function answerPriceLookup(string $message): string
    {
        $term = $this->extractProductTerm($message);
        if ($term === '') {
            return 'Silakan sebutkan produk yang ingin dicek harganya. Contoh: "harga standing pouch".';
        }

        $products = $this->searchProducts($term);
        if ($products->isEmpty()) {
            return "Produk dengan kata kunci \"{$term}\" tidak ditemukan.";
        }

        if ($products->count() > 1) {
            $names = $products->take(5)->map(fn ($product) => $product->name)->implode(', ');
            return "Ditemukan beberapa produk: {$names}. Mohon sebutkan lebih spesifik.";
        }

        $product = $products->first();
        $price = number_format((float) $product->selling_price, 0, ',', '.');
        return "Harga {$product->name} adalah Rp {$price} per {$product->uom}.";
    }

    private function answerUnpaidInvoiceList(): string
    {
        $unpaid = ProjectPayment::query()
            ->with('project:id,name,invoice_number')
            ->whereNull('paid_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        if ($unpaid->isEmpty()) {
            return 'Saat ini tidak ada invoice termin project yang belum dibayar.';
        }

        $lines = $unpaid->map(function (ProjectPayment $payment): string {
            $projectName = $payment->project?->name ?? 'Project';
            $invoiceNo = $payment->project?->invoice_number ?? '-';
            $amount = number_format((float) $payment->amount, 0, ',', '.');
            return "- {$projectName} | Invoice: {$invoiceNo} | Termin {$payment->term_number} | Rp {$amount}";
        })->implode("\n");

        return "Top unpaid invoice:\n{$lines}";
    }

    private function answerPosSalesToday(): string
    {
        $todaySales = PosSale::query()->whereDate('sold_at', now()->toDateString());
        $count = $todaySales->count();
        $total = (float) $todaySales->sum('grand_total');
        $formatted = number_format($total, 0, ',', '.');

        return "POS hari ini: {$count} transaksi, total penjualan Rp {$formatted}.";
    }

    private function answerHelp(): string
    {
        return "Contoh pertanyaan:\n- stok lid cup\n- harga standing pouch\n- invoice belum dibayar\n- pos hari ini";
    }

    private function extractProductTerm(string $message): string
    {
        $normalized = Str::of($message)
            ->lower()
            ->replaceMatches('/[^\pL\pN\s]/u', ' ')
            ->squish()
            ->toString();

        // Prefer text after explicit "produk" / "barang" marker when available.
        if (preg_match('/(?:produk|barang)\s+(.+)/u', $normalized, $matches) === 1) {
            $normalized = trim((string) ($matches[1] ?? ''));
        }

        $noiseWords = [
            'saya', 'sy', 'aku', 'mau', 'ingin', 'tanya', 'nanya', 'cek', 'tolong', 'please', 'dong',
            'berapa', 'ada', 'sisa', 'total', 'untuk', 'dari', 'yang', 'di', 'ke',
            'produk', 'barang', 'item', 'nya', 'ya', 'nih', 'ini', 'itu',
            'stok', 'stock', 'harga', 'price', 'of',
        ];

        $parts = preg_split('/\s+/', $normalized) ?: [];
        $filtered = collect($parts)
            ->reject(fn ($part) => in_array($part, $noiseWords, true))
            ->implode(' ');

        return trim($filtered);
    }

    private function searchProducts(string $term)
    {
        $termLower = Str::lower($term);

        return MasterProduct::query()
            ->where('status', 'active')
            ->where(function ($query) use ($termLower): void {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.$termLower.'%'])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ['%'.$termLower.'%'])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', ['%'.$termLower.'%']);
            })
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name', 'sku', 'uom', 'stock', 'selling_price']);
    }
}
