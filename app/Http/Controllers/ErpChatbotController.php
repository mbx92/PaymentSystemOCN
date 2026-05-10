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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ErpChatbotController extends Controller
{
    public function ask(Request $request, RuleBasedErpChatParser $parser): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $message = trim($validated['message']);
        $parsed = $parser->parse($message);

        if (! $parsed['matched']) {
            return response()->json([
                'ok' => true,
                'intent' => null,
                'answer' => $this->answerNoMatch($parser),
            ]);
        }

        $intent = $parsed['rule']['intent_key'] ?? null;
        $customResponse = trim((string) ($parsed['rule']['response_text'] ?? ''));

        if ($customResponse !== '') {
            return response()->json([
                'ok' => true,
                'intent' => $intent,
                'answer' => $this->formatParserRuleResponse($intent, $customResponse, $message),
            ]);
        }

        $answer = match ($intent) {
            'stock_lookup' => $this->answerStockLookup($message),
            'product_price_lookup' => $this->answerPriceLookup($message),
            'invoice_unpaid_list' => $this->answerUnpaidInvoiceList(),
            'invoice_due_list' => $this->answerInvoiceDueList(),
            'pos_sales_today' => $this->answerPosSalesToday(),
            'pos_sales_month' => $this->answerPosSalesMonth(),
            'cashflow_today' => $this->answerCashflowToday(),
            'cashflow_month' => $this->answerCashflowMonth(),
            'project_active_list' => $this->answerProjectActiveList(),
            'low_stock_alert' => $this->answerLowStockAlert(),
            'operational_summary' => $this->answerOperationalSummary(),
            'send_invoice' => $this->answerSendInvoice($message),
            'invoice_sent_list' => $this->answerInvoiceSentList(),
            'help' => $this->answerHelp(),
            default => 'Intent dikenali tapi handler belum tersedia untuk: '.$intent,
        };

        return response()->json([
            'ok' => true,
            'intent' => $intent,
            'answer' => $answer,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Intent handlers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Custom reply dari parser rule: teks statis, atau template dengan placeholder produk
     * (mis. {{name}}, {{uom}}, {{stock}}, {{price}}) untuk intent stok/harga.
     */
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
        if ($products->isEmpty()) {
            return match ($intent) {
                'stock_lookup' => $this->answerStockLookup($message),
                'product_price_lookup' => $this->answerPriceLookup($message),
                default => $template,
            };
        }

        if ($products->count() > 1) {
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

    private function answerNoMatch(RuleBasedErpChatParser $parser): string
    {
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

    private function answerStockLookup(string $message): string
    {
        $term = $this->extractProductTerm($message);
        if ($term === '') {
            return 'Silakan sebutkan produk yang ingin dicek stoknya. Contoh: "stok lid cup".';
        }

        $products = $this->searchProducts($term);
        if ($products->isEmpty()) {
            return "Produk \"**{$term}**\" tidak ditemukan.";
        }

        if ($products->count() > 1) {
            $names = $products->take(5)->map(fn ($p) => "- {$p->name}")->implode("\n");

            return "Ditemukan beberapa produk:\n{$names}\n\nMohon sebutkan lebih spesifik.";
        }

        $p = $products->first();
        $status = $p->stock <= ($p->min_stock ?? 0) ? ' ⚠️ *stok rendah*' : '';

        return "**{$p->name}**\nStok: {$p->stock} {$p->uom}{$status}";
    }

    private function answerPriceLookup(string $message): string
    {
        $term = $this->extractProductTerm($message);
        if ($term === '') {
            return 'Silakan sebutkan produk yang ingin dicek harganya. Contoh: "harga standing pouch".';
        }

        $products = $this->searchProducts($term);
        if ($products->isEmpty()) {
            return "Produk \"**{$term}**\" tidak ditemukan.";
        }

        if ($products->count() > 1) {
            $lines = $products->take(5)->map(fn ($p) => "- {$p->name}: Rp ".number_format((float) $p->selling_price, 0, ',', '.'))->implode("\n");

            return "Ditemukan beberapa produk:\n{$lines}";
        }

        $p = $products->first();
        $price = number_format((float) $p->selling_price, 0, ',', '.');

        return "**{$p->name}**\nHarga: Rp {$price} / {$p->uom}";
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

        $lines = $unpaid->map(function (ProjectPayment $p): string {
            $proj = $p->project?->name ?? 'Project';
            $inv = $p->project?->invoice_number ?? '-';
            $amt = number_format((float) $p->amount, 0, ',', '.');

            return "- {$proj} | {$inv} | Termin {$p->term_number} | Rp {$amt}";
        })->implode("\n");

        return "**Invoice belum dibayar** ({$unpaid->count()}):\n{$lines}";
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

        $lines = $soon->map(function (ProjectPayment $p): string {
            $proj = $p->project?->name ?? 'Project';
            $due = $p->due_date ? Carbon::parse($p->due_date)->format('d/m/Y') : '-';
            $amt = number_format((float) $p->amount, 0, ',', '.');

            return "- {$proj} | Jatuh tempo: {$due} | Rp {$amt}";
        })->implode("\n");

        return "**Invoice jatuh tempo 14 hari ke depan** ({$soon->count()}):\n{$lines}";
    }

    private function answerPosSalesToday(): string
    {
        $q = PosSale::query()->whereDate('sold_at', now()->toDateString());
        $count = $q->count();
        $total = number_format((float) $q->sum('grand_total'), 0, ',', '.');

        if ($count === 0) {
            return 'Belum ada transaksi POS hari ini.';
        }

        return "**POS hari ini**\nTransaksi: {$count}\nTotal penjualan: Rp {$total}";
    }

    private function answerPosSalesMonth(): string
    {
        $q = PosSale::query()
            ->whereYear('sold_at', now()->year)
            ->whereMonth('sold_at', now()->month);

        $count = $q->count();
        $total = number_format((float) $q->sum('grand_total'), 0, ',', '.');
        $month = now()->translatedFormat('F Y');

        if ($count === 0) {
            return "Belum ada transaksi POS bulan {$month}.";
        }

        return "**POS {$month}**\nTransaksi: {$count}\nTotal penjualan: Rp {$total}";
    }

    private function answerCashflowToday(): string
    {
        $today = now()->toDateString();

        $cashIn = (float) CashIn::query()->whereDate('date', $today)->sum('amount');
        $cashOut = (float) CashOut::query()->whereDate('date', $today)->sum('amount');
        $net = $cashIn - $cashOut;

        $inFmt = number_format($cashIn, 0, ',', '.');
        $outFmt = number_format($cashOut, 0, ',', '.');
        $netFmt = number_format(abs($net), 0, ',', '.');
        $netLabel = $net >= 0 ? "**+Rp {$netFmt}**" : "**-Rp {$netFmt}**";

        return "**Cashflow hari ini**\nKas masuk : Rp {$inFmt}\nKas keluar : Rp {$outFmt}\nNet        : {$netLabel}";
    }

    private function answerCashflowMonth(): string
    {
        $cashIn = (float) CashIn::query()
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('amount');

        $cashOut = (float) CashOut::query()
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('amount');

        $net = $cashIn - $cashOut;
        $month = now()->translatedFormat('F Y');

        $inFmt = number_format($cashIn, 0, ',', '.');
        $outFmt = number_format($cashOut, 0, ',', '.');
        $netFmt = number_format(abs($net), 0, ',', '.');
        $netLabel = $net >= 0 ? "**+Rp {$netFmt}**" : "**-Rp {$netFmt}**";

        return "**Cashflow {$month}**\nKas masuk : Rp {$inFmt}\nKas keluar : Rp {$outFmt}\nNet        : {$netLabel}";
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

        $lines = $projects->map(function (Project $p): string {
            $value = number_format((float) $p->total_value, 0, ',', '.');
            $client = $p->client_name ? " ({$p->client_name})" : '';

            return "- {$p->name}{$client} | Rp {$value}";
        })->implode("\n");

        return "**Project aktif** ({$projects->count()}):\n{$lines}";
    }

    private function answerLowStockAlert(): string
    {
        $low = MasterProduct::query()
            ->where('status', 'active')
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->limit(10)
            ->get(['id', 'name', 'sku', 'stock', 'min_stock', 'uom']);

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

        return "**Biaya Operasional**\nBulan ini ({$month}): Rp {$thisFmt}\nBulan lalu            : Rp {$lastFmt}";
    }

    private function answerHelp(): string
    {
        return "**Contoh pertanyaan:**\n"
            ."- stok lid cup\n"
            ."- harga standing pouch\n"
            ."- invoice belum dibayar\n"
            ."- invoice jatuh tempo\n"
            ."- pos hari ini\n"
            ."- penjualan bulan ini\n"
            ."- cashflow hari ini\n"
            ."- cashflow bulan ini\n"
            ."- project aktif\n"
            ."- stok rendah\n"
            ."- biaya operasional\n"
            ."- kirim invoice INV-PRJ-000123 ke client@mail.com\n"
            ."- konfirmasi kirim invoice INV-PRJ-000123 ke client@mail.com\n"
            .'- list invoice yang dikirim';
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

            return "- {$log->invoice_number} | {$log->recipient_email} | {$log->status} | {$when}";
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
            'saya', 'sy', 'aku', 'mau', 'ingin', 'tanya', 'nanya', 'cek', 'tolong', 'please', 'dong',
            'berapa', 'ada', 'sisa', 'total', 'untuk', 'dari', 'yang', 'di', 'ke',
            'produk', 'barang', 'item', 'nya', 'ya', 'nih', 'ini', 'itu',
            'stok', 'stock', 'harga', 'price', 'of', 'sekarang', 'saat', 'ini',
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
