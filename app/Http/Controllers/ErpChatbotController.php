<?php

namespace App\Http\Controllers;

use App\ERP\Core\Services\RuleBasedErpChatParser;
use App\Mail\ProjectInvoiceMail;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\InvoiceSendLog;
use App\Models\MasterProduct;
use App\Models\PosSale;
use App\Models\Project;
use App\Models\ProjectPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ErpChatbotController extends Controller
{
    public function ask(Request $request, RuleBasedErpChatParser $parser): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array|max:10',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.text' => 'required_with:history|string|max:2000',
        ]);

        $message = trim($validated['message']);
        $history = $validated['history'] ?? [];
        $parsed = $parser->parse($message);

        if (! $parsed['matched']) {
            $followUp = $this->tryFollowUp($message, $history);
            if ($followUp !== null) {
                return response()->json(['ok' => true, 'intent' => 'follow_up', 'answer' => $followUp]);
            }

            return response()->json([
                'ok' => true,
                'intent' => null,
                'answer' => $this->answerNoMatch($message, $parser),
            ]);
        }

        $intent = $parsed['rule']['intent_key'] ?? null;
        $customResponse = trim((string) ($parsed['rule']['response_text'] ?? ''));

        $this->rememberIntent($intent);

        if ($customResponse !== '') {
            return response()->json([
                'ok' => true,
                'intent' => $intent,
                'answer' => $this->formatParserRuleResponse($intent, $customResponse, $message),
            ]);
        }

        $answer = match ($intent) {
            'greeting'             => $this->answerGreeting(),
            'stock_lookup'         => $this->answerStockLookup($message),
            'product_price_lookup' => $this->answerPriceLookup($message),
            'product_detail'       => $this->answerProductDetail($message),
            'invoice_unpaid_list'  => $this->answerUnpaidInvoiceList(),
            'invoice_due_list'     => $this->answerInvoiceDueList(),
            'pos_sales_today'      => $this->answerPosSalesToday(),
            'pos_sales_yesterday'  => $this->answerPosSalesYesterday(),
            'pos_sales_month'      => $this->answerPosSalesMonth(),
            'pos_sales_last_month' => $this->answerPosSalesLastMonth(),
            'cashflow_today'       => $this->answerCashflowToday(),
            'cashflow_yesterday'   => $this->answerCashflowYesterday(),
            'cashflow_month'       => $this->answerCashflowMonth(),
            'cashflow_last_month'  => $this->answerCashflowLastMonth(),
            'project_active_list'  => $this->answerProjectActiveList(),
            'low_stock_alert'      => $this->answerLowStockAlert(),
            'operational_summary'  => $this->answerOperationalSummary(),
            'top_selling_products' => $this->answerTopSellingProducts(),
            'send_invoice'         => $this->answerSendInvoice($message),
            'invoice_sent_list'    => $this->answerInvoiceSentList(),
            'help'                 => $this->answerHelp(),
            default                => 'Intent dikenali tapi handler belum tersedia untuk: '.$intent,
        };

        return response()->json([
            'ok' => true,
            'intent' => $intent,
            'answer' => $answer,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Follow-up context
    // ──────────────────────────────────────────────────────────────────────────

    private function contextKey(): string
    {
        return 'chatbot_ctx_'.Auth::id();
    }

    private function getContext(): array
    {
        return Cache::get($this->contextKey(), []);
    }

    private function rememberProduct(MasterProduct $product): void
    {
        $ctx = $this->getContext();
        $ctx['product_id'] = $product->id;
        $ctx['product_name'] = $product->name;
        $ctx['entity_type'] = 'product';
        Cache::put($this->contextKey(), $ctx, now()->addMinutes(15));
    }

    private function rememberIntent(string $intent): void
    {
        $ctx = $this->getContext();
        $ctx['last_intent'] = $intent;
        Cache::put($this->contextKey(), $ctx, now()->addMinutes(15));
    }

    private function rememberProject(Project $project): void
    {
        $ctx = $this->getContext();
        $ctx['project_id'] = $project->id;
        $ctx['project_name'] = $project->name;
        $ctx['entity_type'] = 'project';
        Cache::put($this->contextKey(), $ctx, now()->addMinutes(15));
    }

    /**
     * Resolve a follow-up message using cached context and chat history.
     */
    private function tryFollowUp(string $message, array $history = []): ?string
    {
        $lower = Str::of($message)->lower()->squish()->toString();

        $stockTriggers = [
            'stoknya', 'stocknya', 'sisa stok', 'stok nya', 'berapa stok', 'cek stok',
            'stok', 'stock', 'sisa', 'ada berapa', 'tersedia', 'masih ada',
        ];
        $priceTriggers = [
            'harganya', 'brapa harga', 'berapa harga', 'harga nya', 'brp harga', 'pricenya',
            'harga', 'price', 'berapa', 'brp', 'brapa', 'costnya', 'cost',
        ];
        $detailTriggers = [
            'detailnya', 'infonya', 'detail nya', 'info nya', 'lengkapnya',
            'detail', 'info', 'lengkap', 'selengkapnya', 'lebih detail',
        ];

        $ctx = $this->getContext();

        $result = $this->tryFollowUpFromProduct($lower, $ctx, $stockTriggers, $priceTriggers, $detailTriggers);
        if ($result !== null) {
            return $result;
        }

        return $this->tryFollowUpFromHistory($lower, $history, $stockTriggers, $priceTriggers, $detailTriggers);
    }

    private function tryFollowUpFromProduct(string $lower, array $ctx, array $stockTriggers, array $priceTriggers, array $detailTriggers): ?string
    {
        if (! ($ctx['product_id'] ?? null)) {
            return null;
        }

        $product = MasterProduct::find($ctx['product_id']);
        if (! $product) {
            return null;
        }

        $isStock = collect($stockTriggers)->some(fn ($t) => Str::contains($lower, $t));
        $isPrice = collect($priceTriggers)->some(fn ($t) => Str::contains($lower, $t));
        $isDetail = collect($detailTriggers)->some(fn ($t) => Str::contains($lower, $t));

        if ($isStock) {
            $this->rememberProduct($product);
            $status = $product->stock <= ($product->min_stock ?? 0) ? ' ⚠️ *stok rendah*' : '';

            return "**{$product->name}**\nStok: {$product->stock} {$product->uom}{$status}";
        }

        if ($isPrice) {
            $this->rememberProduct($product);
            $price = number_format((float) $product->selling_price, 0, ',', '.');

            return "**{$product->name}**\nHarga: Rp {$price} / {$product->uom}";
        }

        if ($isDetail) {
            $this->rememberProduct($product);

            return $this->formatProductDetail($product);
        }

        return null;
    }

    /**
     * Try to find a product mentioned in chat history when context cache is empty.
     */
    private function tryFollowUpFromHistory(string $lower, array $history, array $stockTriggers, array $priceTriggers, array $detailTriggers): ?string
    {
        if (empty($history)) {
            return null;
        }

        $isStock = collect($stockTriggers)->some(fn ($t) => Str::contains($lower, $t));
        $isPrice = collect($priceTriggers)->some(fn ($t) => Str::contains($lower, $t));
        $isDetail = collect($detailTriggers)->some(fn ($t) => Str::contains($lower, $t));

        if (! $isStock && ! $isPrice && ! $isDetail) {
            return null;
        }

        $product = $this->findProductFromHistory($history);
        if (! $product) {
            return null;
        }

        $this->rememberProduct($product);

        if ($isStock) {
            $status = $product->stock <= ($product->min_stock ?? 0) ? ' ⚠️ *stok rendah*' : '';

            return "**{$product->name}**\nStok: {$product->stock} {$product->uom}{$status}";
        }

        if ($isPrice) {
            $price = number_format((float) $product->selling_price, 0, ',', '.');

            return "**{$product->name}**\nHarga: Rp {$price} / {$product->uom}";
        }

        return $this->formatProductDetail($product);
    }

    /**
     * Scan recent chat history for product names mentioned by the assistant (in bold **Name**).
     */
    private function findProductFromHistory(array $history): ?MasterProduct
    {
        $assistantMessages = collect($history)
            ->where('role', 'assistant')
            ->pluck('text')
            ->reverse()
            ->values();

        foreach ($assistantMessages as $text) {
            if (preg_match('/\*\*(.+?)\*\*/', (string) $text, $m)) {
                $candidateName = trim($m[1]);
                $product = MasterProduct::query()
                    ->where('status', 'active')
                    ->whereRaw('LOWER(name) = ?', [Str::lower($candidateName)])
                    ->first();
                if ($product) {
                    return $product;
                }
            }
        }

        return null;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Intent handlers
    // ──────────────────────────────────────────────────────────────────────────

    private function formatParserRuleResponse(?string $intent, string $template, string $message): string
    {
        if (! $this->parserTemplateHasProductPlaceholders($template)) {
            return $template;
        }

        if (! in_array($intent, ['stock_lookup', 'product_price_lookup'], true)) {
            return $template;
        }

        $term = $this->extractProductTerm($message);
        if ($term === '') {
            return match ($intent) {
                'stock_lookup' => $this->answerStockLookup($message),
                'product_price_lookup' => $this->answerPriceLookup($message),
                default => $template,
            };
        }

        $products = $this->searchProducts($term);
        if ($products->isEmpty() || $products->count() > 1) {
            return match ($intent) {
                'stock_lookup' => $this->answerStockLookup($message),
                'product_price_lookup' => $this->answerPriceLookup($message),
                default => $template,
            };
        }

        return $this->replaceParserProductPlaceholders($template, $products->first());
    }

    private function parserTemplateHasProductPlaceholders(string $template): bool
    {
        return (bool) preg_match('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', $template);
    }

    private function replaceParserProductPlaceholders(string $template, MasterProduct $p): string
    {
        $priceFmt = number_format((float) $p->selling_price, 0, ',', '.');
        $low = (int) $p->stock <= (int) ($p->min_stock ?? 0);
        $stockWarning = $low ? '⚠️ stok rendah' : '';

        $map = [
            'name' => (string) $p->name,
            'nama' => (string) $p->name,
            'sku' => (string) ($p->sku ?? ''),
            'barcode' => (string) ($p->barcode ?? ''),
            'uom' => (string) ($p->uom ?? ''),
            'satuan' => (string) ($p->uom ?? ''),
            'stock' => (string) $p->stock,
            'min_stock' => (string) ($p->min_stock ?? 0),
            'selling_price' => $priceFmt,
            'price' => $priceFmt,
            'harga' => $priceFmt,
            'stock_status' => $stockWarning,
        ];

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            function (array $m) use ($map): string {
                $key = Str::lower((string) $m[1]);

                return array_key_exists($key, $map) ? $map[$key] : (string) $m[0];
            },
            $template
        );
    }

    private function answerNoMatch(string $message, RuleBasedErpChatParser $parser): string
    {
        $suggestion = $parser->suggestClosest($message);

        if ($suggestion) {
            $hint = $suggestion['hint'];

            return "Maaf, saya belum yakin maksudnya. Mungkin yang Anda cari:\n"
                ."- **{$suggestion['name']}** (kata kunci: {$hint})\n\n"
                .'Coba ulangi dengan kata kunci di atas, atau ketik **bantuan**.';
        }

        $hints = $parser->activeRules()
            ->filter(fn ($rule) => ! empty($rule->keywords))
            ->take(6)
            ->map(fn ($rule) => '- '.collect($rule->keywords)->take(3)->implode(', '))
            ->implode("\n");

        $base = 'Maaf, saya belum memahami pertanyaan itu.';
        if ($hints) {
            return $base."\n\nCoba gunakan kata kunci berikut:\n".$hints;
        }

        return $base.' Coba ketik **bantuan** untuk melihat contoh pertanyaan.';
    }

    private function answerGreeting(): string
    {
        $hour = (int) now()->format('H');
        $greeting = match (true) {
            $hour < 11  => 'Selamat pagi',
            $hour < 15  => 'Selamat siang',
            $hour < 18  => 'Selamat sore',
            default     => 'Selamat malam',
        };

        $user = Auth::user()?->name ?? 'Kak';

        return "{$greeting}, **{$user}**! 👋\nAda yang bisa saya bantu hari ini?\n\nKetik **bantuan** untuk melihat semua fitur yang tersedia.";
    }

    private function answerStockLookup(string $message): string
    {
        $term = $this->extractProductTerm($message);
        if ($term === '') {
            return 'Silakan sebutkan produk yang ingin dicek stoknya. Contoh: "stok lid cup".';
        }

        $products = $this->searchProducts($term);
        if ($products->isEmpty()) {
            return "Produk \"**{$term}**\" tidak ditemukan. Pastikan nama/SKU sudah benar.";
        }

        if ($products->count() === 1) {
            $p = $products->first();
            $this->rememberProduct($p);
            $status = $p->stock <= ($p->min_stock ?? 0) ? ' ⚠️ *stok rendah*' : '';

            return "**{$p->name}**\nStok: {$p->stock} {$p->uom}{$status}\n\n💡 Tanya lagi: \"harganya?\" atau \"detailnya?\"";
        }

        $names = $products->take(5)->map(fn ($p) => "- {$p->name} — Stok: {$p->stock} {$p->uom}")->implode("\n");

        return "Ditemukan {$products->count()} produk:\n{$names}\n\nMohon sebutkan lebih spesifik.";
    }

    private function answerPriceLookup(string $message): string
    {
        $term = $this->extractProductTerm($message);
        if ($term === '') {
            return 'Silakan sebutkan produk yang ingin dicek harganya. Contoh: "harga standing pouch".';
        }

        $products = $this->searchProducts($term);
        if ($products->isEmpty()) {
            return "Produk \"**{$term}**\" tidak ditemukan. Pastikan nama/SKU sudah benar.";
        }

        if ($products->count() === 1) {
            $p = $products->first();
            $this->rememberProduct($p);
            $price = number_format((float) $p->selling_price, 0, ',', '.');

            return "**{$p->name}**\nHarga: Rp {$price} / {$p->uom}\n\n💡 Tanya lagi: \"stoknya?\" atau \"detailnya?\"";
        }

        $lines = $products->take(5)->map(fn ($p) => "- {$p->name}: Rp ".number_format((float) $p->selling_price, 0, ',', '.'))->implode("\n");

        return "Ditemukan {$products->count()} produk:\n{$lines}";
    }

    private function answerProductDetail(string $message): string
    {
        $term = $this->extractProductTerm($message);
        if ($term === '') {
            return 'Silakan sebutkan produk yang ingin dilihat detailnya. Contoh: "detail produk lid cup".';
        }

        $products = $this->searchProducts($term);
        if ($products->isEmpty()) {
            return "Produk \"**{$term}**\" tidak ditemukan.";
        }

        if ($products->count() === 1) {
            $p = $products->first();
            $this->rememberProduct($p);

            return $this->formatProductDetail($p);
        }

        $names = $products->take(5)->map(fn ($p) => "- {$p->name}")->implode("\n");

        return "Ditemukan {$products->count()} produk:\n{$names}\n\nMohon sebutkan lebih spesifik.";
    }

    private function formatProductDetail(MasterProduct $p): string
    {
        $price = number_format((float) $p->selling_price, 0, ',', '.');
        $status = $p->stock <= ($p->min_stock ?? 0) ? '⚠️ Stok rendah' : '✅ Stok aman';
        $barcode = $p->barcode ? "\nBarcode: `{$p->barcode}`" : '';
        $sku = $p->sku ? "\nSKU: `{$p->sku}`" : '';

        return "**{$p->name}**{$sku}{$barcode}\n"
            ."Satuan: {$p->uom}\n"
            ."Harga jual: Rp {$price}\n"
            ."Stok: {$p->stock} (min: ".((int) ($p->min_stock ?? 0)).")\n"
            ."Status: {$status}";
    }

    private function answerUnpaidInvoiceList(): string
    {
        $unpaid = ProjectPayment::query()
            ->with('project:id,name,invoice_number')
            ->whereNull('paid_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        if ($unpaid->isEmpty()) {
            return '✅ Tidak ada invoice termin yang belum dibayar saat ini.';
        }

        $total = $unpaid->sum(fn ($p) => (float) $p->amount);
        $totalFmt = number_format($total, 0, ',', '.');

        $lines = $unpaid->map(function (ProjectPayment $p): string {
            $proj = $p->project?->name ?? 'Project';
            $inv = $p->project?->invoice_number ?? '-';
            $amt = number_format((float) $p->amount, 0, ',', '.');

            return "- {$proj} | {$inv} | Termin {$p->term_number} | Rp {$amt}";
        })->implode("\n");

        return "**Invoice belum dibayar** ({$unpaid->count()}):\n{$lines}\n\n**Total: Rp {$totalFmt}**";
    }

    private function answerInvoiceDueList(): string
    {
        $soon = ProjectPayment::query()
            ->with('project:id,name,invoice_number')
            ->whereNull('paid_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays(14)->toDateString())
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        if ($soon->isEmpty()) {
            return '✅ Tidak ada invoice yang jatuh tempo dalam 14 hari ke depan.';
        }

        $overdue = $soon->filter(fn ($p) => Carbon::parse($p->due_date)->isPast());

        $lines = $soon->map(function (ProjectPayment $p) use ($overdue): string {
            $proj = $p->project?->name ?? 'Project';
            $due = $p->due_date ? Carbon::parse($p->due_date)->format('d/m/Y') : '-';
            $amt = number_format((float) $p->amount, 0, ',', '.');
            $flag = $overdue->contains($p) ? ' 🔴' : '';

            return "- {$proj} | Jatuh tempo: {$due} | Rp {$amt}{$flag}";
        })->implode("\n");

        $overdueNote = $overdue->isNotEmpty()
            ? "\n\n🔴 = sudah melewati jatuh tempo ({$overdue->count()} item)"
            : '';

        return "**Invoice jatuh tempo 14 hari ke depan** ({$soon->count()}):\n{$lines}{$overdueNote}";
    }

    private function answerPosSalesToday(): string
    {
        return $this->formatPosSalesSummary(now()->toDateString(), now()->toDateString(), 'POS hari ini');
    }

    private function answerPosSalesYesterday(): string
    {
        $date = now()->subDay()->toDateString();

        return $this->formatPosSalesSummary($date, $date, 'POS kemarin ('.now()->subDay()->format('d/m/Y').')');
    }

    private function answerPosSalesMonth(): string
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->toDateString();
        $month = now()->translatedFormat('F Y');

        return $this->formatPosSalesSummary($start, $end, "POS {$month}");
    }

    private function answerPosSalesLastMonth(): string
    {
        $lastMonth = now()->subMonth();
        $start = $lastMonth->copy()->startOfMonth()->toDateString();
        $end = $lastMonth->copy()->endOfMonth()->toDateString();
        $month = $lastMonth->translatedFormat('F Y');

        return $this->formatPosSalesSummary($start, $end, "POS {$month}");
    }

    private function formatPosSalesSummary(string $startDate, string $endDate, string $label): string
    {
        $q = PosSale::query()->whereBetween('sold_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
        $count = $q->count();
        $total = (float) $q->sum('grand_total');
        $totalFmt = number_format($total, 0, ',', '.');

        if ($count === 0) {
            return "Belum ada transaksi {$label}.";
        }

        $avg = $count > 0 ? number_format($total / $count, 0, ',', '.') : '0';

        return "**{$label}**\nTransaksi: {$count}\nTotal penjualan: Rp {$totalFmt}\nRata-rata/trx: Rp {$avg}";
    }

    private function answerCashflowToday(): string
    {
        return $this->formatCashflowSummary(now()->toDateString(), now()->toDateString(), 'Cashflow hari ini');
    }

    private function answerCashflowYesterday(): string
    {
        $date = now()->subDay()->toDateString();

        return $this->formatCashflowSummary($date, $date, 'Cashflow kemarin ('.now()->subDay()->format('d/m/Y').')');
    }

    private function answerCashflowMonth(): string
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->toDateString();
        $month = now()->translatedFormat('F Y');

        return $this->formatCashflowSummary($start, $end, "Cashflow {$month}");
    }

    private function answerCashflowLastMonth(): string
    {
        $lastMonth = now()->subMonth();
        $start = $lastMonth->copy()->startOfMonth()->toDateString();
        $end = $lastMonth->copy()->endOfMonth()->toDateString();
        $month = $lastMonth->translatedFormat('F Y');

        return $this->formatCashflowSummary($start, $end, "Cashflow {$month}");
    }

    private function formatCashflowSummary(string $startDate, string $endDate, string $label): string
    {
        $cashIn = (float) CashIn::query()->whereBetween('date', [$startDate, $endDate])->sum('amount');
        $cashOut = (float) CashOut::query()->whereBetween('date', [$startDate, $endDate])->sum('amount');
        $net = $cashIn - $cashOut;

        $inFmt = number_format($cashIn, 0, ',', '.');
        $outFmt = number_format($cashOut, 0, ',', '.');
        $netFmt = number_format(abs($net), 0, ',', '.');
        $netLabel = $net >= 0 ? "**+Rp {$netFmt}** ✅" : "**-Rp {$netFmt}** ⚠️";

        return "**{$label}**\nKas masuk : Rp {$inFmt}\nKas keluar : Rp {$outFmt}\nNet        : {$netLabel}";
    }

    private function answerProjectActiveList(): string
    {
        $projects = Project::query()
            ->where('status', 'berjalan')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'name', 'client_name', 'total_value', 'started_at']);

        if ($projects->isEmpty()) {
            return 'Tidak ada project yang sedang berjalan saat ini.';
        }

        $totalValue = $projects->sum(fn ($p) => (float) $p->total_value);
        $totalFmt = number_format($totalValue, 0, ',', '.');

        $lines = $projects->map(function (Project $p): string {
            $value = number_format((float) $p->total_value, 0, ',', '.');
            $client = $p->client_name ? " ({$p->client_name})" : '';

            return "- {$p->name}{$client} | Rp {$value}";
        })->implode("\n");

        return "**Project aktif** ({$projects->count()}):\n{$lines}\n\n**Total nilai: Rp {$totalFmt}**";
    }

    private function answerLowStockAlert(): string
    {
        $low = MasterProduct::query()
            ->where('status', 'active')
            ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
            ->where('low_stock_alert_enabled', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->limit(10)
            ->get(['id', 'name', 'sku', 'stock', 'min_stock', 'uom', 'low_stock_alert_enabled']);

        if ($low->isEmpty()) {
            return '✅ Semua produk aktif memiliki stok di atas minimum.';
        }

        $lines = $low->map(fn ($p) => "- {$p->name} | Stok: {$p->stock} / Min: {$p->min_stock} {$p->uom}")->implode("\n");

        return "**⚠️ Stok rendah** ({$low->count()} produk):\n{$lines}";
    }

    private function answerOperationalSummary(): string
    {
        $thisMonth = CashOut::query()
            ->where('category', 'operasional')
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('amount');

        $lastMonth = CashOut::query()
            ->where('category', 'operasional')
            ->whereYear('date', now()->subMonth()->year)
            ->whereMonth('date', now()->subMonth()->month)
            ->sum('amount');

        $month = now()->translatedFormat('F Y');
        $thisFmt = number_format((float) $thisMonth, 0, ',', '.');
        $lastFmt = number_format((float) $lastMonth, 0, ',', '.');

        $diff = (float) $thisMonth - (float) $lastMonth;
        $trend = $diff > 0 ? '📈 Naik' : ($diff < 0 ? '📉 Turun' : '➡️ Sama');
        $diffFmt = number_format(abs($diff), 0, ',', '.');
        $trendLine = $diff !== 0.0 ? "\nTren: {$trend} Rp {$diffFmt} dari bulan lalu" : '';

        return "**Biaya Operasional**\nBulan ini ({$month}): Rp {$thisFmt}\nBulan lalu            : Rp {$lastFmt}{$trendLine}";
    }

    private function answerTopSellingProducts(): string
    {
        $month = now()->translatedFormat('F Y');
        $start = now()->startOfMonth()->toDateString().' 00:00:00';
        $end = now()->toDateString().' 23:59:59';

        $topProducts = \App\Models\PosSaleItem::query()
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereBetween('pos_sales.sold_at', [$start, $end])
            ->selectRaw('pos_sale_items.product_name, SUM(pos_sale_items.qty) as total_qty, SUM(pos_sale_items.line_total) as total_revenue')
            ->groupBy('pos_sale_items.product_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        if ($topProducts->isEmpty()) {
            return "Belum ada data penjualan bulan {$month}.";
        }

        $lines = $topProducts->map(function ($row, $idx) {
            $rank = $idx + 1;
            $qty = number_format((float) $row->total_qty, 0, ',', '.');
            $rev = number_format((float) $row->total_revenue, 0, ',', '.');

            return "- {$rank}. {$row->product_name} — {$qty} terjual (Rp {$rev})";
        })->implode("\n");

        return "**Produk terlaris {$month}** (Top 10):\n{$lines}";
    }

    private function answerHelp(): string
    {
        return "**📋 Fitur yang tersedia:**\n\n"
            ."**Produk & Stok**\n"
            ."- stok lid cup\n"
            ."- harga standing pouch\n"
            ."- detail produk lid cup\n"
            ."- stok rendah\n"
            ."- produk terlaris\n\n"
            ."**Penjualan POS**\n"
            ."- pos hari ini\n"
            ."- pos kemarin\n"
            ."- penjualan bulan ini\n"
            ."- penjualan bulan lalu\n\n"
            ."**Keuangan**\n"
            ."- cashflow hari ini\n"
            ."- cashflow kemarin\n"
            ."- cashflow bulan ini\n"
            ."- cashflow bulan lalu\n"
            ."- biaya operasional\n\n"
            ."**Invoice & Project**\n"
            ."- invoice belum dibayar\n"
            ."- invoice jatuh tempo\n"
            ."- project aktif\n"
            ."- kirim invoice INV-PRJ-000123 ke email@mail.com\n"
            ."- list invoice yang dikirim\n\n"
            .'💡 Setelah cek produk, Anda bisa tanya "harganya?", "stoknya?", atau "detailnya?" untuk lanjut.';
    }

    private function answerSendInvoice(string $message): string
    {
        $command = $this->parseSendInvoiceCommand($message);
        $invoiceNo = $command['invoice_number'];
        $forcedEmail = $command['email'];
        $confirmed = $command['is_confirmed'];

        if (! $invoiceNo) {
            return "Format belum lengkap.\nGunakan:\n- kirim invoice INV-PRJ-000123 ke client@mail.com\n- lalu: konfirmasi kirim invoice INV-PRJ-000123 ke client@mail.com";
        }

        $project = Project::query()
            ->where('status', 'selesai')
            ->whereRaw('LOWER(invoice_number) = ?', [Str::lower($invoiceNo)])
            ->first();

        if (! $project) {
            $project = Project::query()
                ->where('status', 'selesai')
                ->latest('finished_at')
                ->limit(200)
                ->get()
                ->first(function (Project $candidate) use ($invoiceNo): bool {
                    return Str::lower($this->invoiceNumber($candidate)) === Str::lower($invoiceNo);
                });
        }

        if (! $project) {
            return "Invoice **{$invoiceNo}** tidak ditemukan pada project selesai.";
        }

        $recipientEmail = $forcedEmail;
        if (! $recipientEmail) {
            $recipientEmail = filter_var((string) $project->client_contact, FILTER_VALIDATE_EMAIL) ?: null;
        }

        if (! $recipientEmail) {
            return "Email penerima belum ditemukan.\nSertakan email di perintah, contoh:\n`kirim invoice {$project->invoice_number} ke client@mail.com`";
        }

        if (! $confirmed) {
            $amount = number_format((float) $project->total_value, 0, ',', '.');

            return "Siap mengirim invoice:\n"
                ."- Invoice: **{$project->invoice_number}**\n"
                ."- Project: {$project->name}\n"
                ."- Nominal: Rp {$amount}\n"
                ."- Tujuan: {$recipientEmail}\n\n"
                ."Jika sudah benar, kirim perintah:\n"
                ."`konfirmasi kirim invoice {$project->invoice_number} ke {$recipientEmail}`";
        }

        $project->load(['payments', 'cashIns']);
        $project->loadSum('cashIns as paid_amount', 'amount');
        $invoice = $this->mapProjectInvoice($project);

        try {
            $pdf = Pdf::loadView('pdf.project-invoice', [
                'project' => $project,
                'invoice' => $invoice,
                'generatedAt' => now(),
            ])->setPaper('a4');

            $pdfBinary = $pdf->output();
            $fileName = ($invoice['number'] ?? 'invoice').'.pdf';

            Mail::to($recipientEmail)->send(new ProjectInvoiceMail(
                invoice: $invoice,
                project: [
                    'name' => $project->name,
                    'client_name' => $project->client_name,
                ],
                recipientName: $project->client_name ?: 'Pelanggan',
                pdfBinary: $pdfBinary,
                pdfFileName: $fileName,
            ));

            InvoiceSendLog::query()->create([
                'project_id' => $project->id,
                'invoice_number' => $invoice['number'] ?? (string) $project->invoice_number,
                'recipient_email' => $recipientEmail,
                'status' => 'sent',
                'message' => 'Invoice terkirim via chatbot.',
                'sent_by' => Auth::id(),
                'sent_at' => now(),
            ]);

            return "✅ Invoice **{$invoice['number']}** berhasil dikirim ke **{$recipientEmail}**.";
        } catch (\Throwable $e) {
            InvoiceSendLog::query()->create([
                'project_id' => $project->id,
                'invoice_number' => $project->invoice_number ?? $invoiceNo,
                'recipient_email' => $recipientEmail,
                'status' => 'failed',
                'message' => $e->getMessage(),
                'sent_by' => Auth::id(),
                'sent_at' => now(),
            ]);

            return '❌ Gagal mengirim invoice. Cek konfigurasi mail (MAIL_MAILER/SMTP) dan alamat email tujuan.';
        }
    }

    private function answerInvoiceSentList(): string
    {
        $logs = InvoiceSendLog::query()
            ->latest('sent_at')
            ->latest('id')
            ->limit(10)
            ->get(['invoice_number', 'recipient_email', 'status', 'sent_at']);

        if ($logs->isEmpty()) {
            return 'Belum ada riwayat pengiriman invoice dari chatbot.';
        }

        $lines = $logs->map(function (InvoiceSendLog $log): string {
            $when = $log->sent_at?->format('d/m/Y H:i') ?? '-';
            $icon = $log->status === 'sent' ? '✅' : '❌';

            return "- {$icon} {$log->invoice_number} | {$log->recipient_email} | {$when}";
        })->implode("\n");

        return "**Riwayat invoice terkirim** (10 terakhir):\n{$lines}";
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function extractProductTerm(string $message): string
    {
        $normalized = Str::of($message)
            ->lower()
            ->replaceMatches('/[^\pL\pN\s]/u', ' ')
            ->squish()
            ->toString();

        if (preg_match('/(?:produk|barang)\s+(.+)/u', $normalized, $matches) === 1) {
            $normalized = trim((string) ($matches[1] ?? ''));
        }

        $noiseWords = [
            'saya', 'sy', 'aku', 'gue', 'gw', 'mau', 'ingin', 'pengen', 'minta',
            'tanya', 'nanya', 'cek', 'tolong', 'please', 'dong', 'deh', 'lah', 'kah',
            'berapa', 'ada', 'sisa', 'total', 'untuk', 'dari', 'yang', 'di', 'ke',
            'produk', 'barang', 'item', 'nya', 'ya', 'nih', 'ini', 'itu', 'the',
            'stok', 'stock', 'harga', 'price', 'of', 'sekarang', 'saat', 'ini',
            'lihat', 'tampilkan', 'show', 'kasih', 'tau', 'tahu', 'bisa', 'boleh',
            'gimana', 'bagaimana', 'detail', 'info', 'informasi', 'data',
            'cari', 'carikan', 'cekkan',
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
            ->get(['id', 'name', 'sku', 'barcode', 'uom', 'stock', 'min_stock', 'selling_price']);
    }

    private function parseSendInvoiceCommand(string $message): array
    {
        $normalized = Str::of($message)->lower()->squish()->toString();
        $isConfirmed = Str::contains($normalized, 'konfirmasi');

        $invoiceNo = null;
        if (preg_match('/(inv[\w\-\/]+)/i', $message, $m) === 1) {
            $invoiceNo = strtoupper(trim((string) $m[1]));
        }

        $email = null;
        if (preg_match('/([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})/i', $message, $m) === 1) {
            $email = strtolower(trim((string) $m[1]));
        }

        return [
            'invoice_number' => $invoiceNo,
            'email' => $email,
            'is_confirmed' => $isConfirmed,
        ];
    }

    private function mapProjectInvoice(Project $project): array
    {
        $paidAmount = (float) ($project->paid_amount ?? $project->cashIns()->sum('amount'));
        $amount = (float) $project->total_value;
        $remaining = max($amount - $paidAmount, 0);

        return [
            'id' => $project->id,
            'number' => $project->invoice_number ?: $this->invoiceNumber($project),
            'project' => $project->name,
            'client' => $project->client_name,
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remaining,
            'status' => $remaining <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid'),
            'finished_at' => $project->finished_at?->format('Y-m-d'),
            'created_at' => $project->created_at?->format('Y-m-d'),
        ];
    }

    private function invoiceNumber(Project $project): string
    {
        return $project->invoice_number
            ?: ('INV-PRJ-'.($project->finished_at?->format('Ymd') ?? $project->created_at?->format('Ymd') ?? now()->format('Ymd')).'-'.strtoupper(substr(str_replace('-', '', (string) $project->getKey()), -6)));
    }
}
