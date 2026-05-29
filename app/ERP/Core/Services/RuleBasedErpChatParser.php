<?php

namespace App\ERP\Core\Services;

use App\Models\ErpChatParserRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RuleBasedErpChatParser
{
    private const BUILT_IN_RULES = [
        ['name' => 'Greeting / sapaan', 'intent_key' => 'greeting', 'keywords' => ['halo', 'hai', 'hi', 'hello', 'hey', 'selamat'], 'match_mode' => 'or', 'priority' => 1, 'response_text' => null],
        ['name' => 'Greeting / terima kasih', 'intent_key' => 'greeting', 'keywords' => ['terima kasih', 'makasih', 'thanks', 'thx', 'tq'], 'match_mode' => 'or', 'priority' => 2, 'response_text' => null],
        ['name' => 'Lookup stok produk', 'intent_key' => 'stock_lookup', 'keywords' => ['stok'], 'match_mode' => 'and', 'priority' => 10, 'response_text' => null],
        ['name' => 'Lookup stok barang (EN)', 'intent_key' => 'stock_lookup', 'keywords' => ['stock'], 'match_mode' => 'and', 'priority' => 11, 'response_text' => null],
        ['name' => 'Cek sisa barang', 'intent_key' => 'stock_lookup', 'keywords' => ['sisa barang', 'barang tersedia', 'ada barang', 'persediaan'], 'match_mode' => 'or', 'priority' => 12, 'response_text' => null],
        ['name' => 'Harga produk', 'intent_key' => 'product_price_lookup', 'keywords' => ['harga'], 'match_mode' => 'and', 'priority' => 20, 'response_text' => null],
        ['name' => 'Harga produk (EN)', 'intent_key' => 'product_price_lookup', 'keywords' => ['price'], 'match_mode' => 'or', 'priority' => 21, 'response_text' => null],
        ['name' => 'Berapa harga produk', 'intent_key' => 'product_price_lookup', 'keywords' => ['berapa harga', 'brp harga', 'harga berapa', 'biaya produk'], 'match_mode' => 'or', 'priority' => 22, 'response_text' => null],
        ['name' => 'Detail produk', 'intent_key' => 'product_detail', 'keywords' => ['detail produk', 'info produk', 'data produk', 'detail barang'], 'match_mode' => 'or', 'priority' => 23, 'response_text' => null],
        ['name' => 'Detail produk (informal)', 'intent_key' => 'product_detail', 'keywords' => ['lihat produk', 'cek produk', 'cari produk', 'info barang'], 'match_mode' => 'or', 'priority' => 24, 'response_text' => null],
        ['name' => 'Stok rendah / low stock', 'intent_key' => 'low_stock_alert', 'keywords' => ['stok rendah', 'stock rendah', 'low stock', 'stok menipis', 'stok habis', 'hampir habis'], 'match_mode' => 'or', 'priority' => 25, 'response_text' => null],
        ['name' => 'Produk terlaris', 'intent_key' => 'top_selling_products', 'keywords' => ['terlaris', 'produk terlaris', 'best seller', 'paling laku', 'top produk', 'paling banyak terjual'], 'match_mode' => 'or', 'priority' => 26, 'response_text' => null],
        ['name' => 'Invoice belum dibayar', 'intent_key' => 'invoice_unpaid_list', 'keywords' => ['invoice', 'belum dibayar'], 'match_mode' => 'and', 'priority' => 30, 'response_text' => null],
        ['name' => 'Invoice belum lunas', 'intent_key' => 'invoice_unpaid_list', 'keywords' => ['invoice belum lunas', 'tagihan belum dibayar', 'piutang belum masuk'], 'match_mode' => 'or', 'priority' => 31, 'response_text' => null],
        ['name' => 'Invoice jatuh tempo', 'intent_key' => 'invoice_due_list', 'keywords' => ['invoice', 'jatuh tempo'], 'match_mode' => 'and', 'priority' => 32, 'response_text' => null],
        ['name' => 'Invoice overdue', 'intent_key' => 'invoice_due_list', 'keywords' => ['overdue', 'terlambat bayar', 'tagihan terlambat', 'lewat tempo'], 'match_mode' => 'or', 'priority' => 33, 'response_text' => null],
        ['name' => 'Penjualan POS hari ini', 'intent_key' => 'pos_sales_today', 'keywords' => ['pos', 'hari ini'], 'match_mode' => 'and', 'priority' => 40, 'response_text' => null],
        ['name' => 'Penjualan hari ini (umum)', 'intent_key' => 'pos_sales_today', 'keywords' => ['penjualan hari ini', 'sales hari ini', 'omset hari ini', 'omzet hari ini'], 'match_mode' => 'or', 'priority' => 40, 'response_text' => null],
        ['name' => 'Penjualan POS kemarin', 'intent_key' => 'pos_sales_yesterday', 'keywords' => ['pos', 'kemarin'], 'match_mode' => 'and', 'priority' => 41, 'response_text' => null],
        ['name' => 'Penjualan kemarin (umum)', 'intent_key' => 'pos_sales_yesterday', 'keywords' => ['penjualan kemarin', 'sales kemarin', 'omset kemarin'], 'match_mode' => 'or', 'priority' => 41, 'response_text' => null],
        ['name' => 'Penjualan POS bulan ini', 'intent_key' => 'pos_sales_month', 'keywords' => ['pos bulan ini', 'penjualan bulan ini', 'sales bulan ini', 'omset bulan ini'], 'match_mode' => 'or', 'priority' => 42, 'response_text' => null],
        ['name' => 'Penjualan POS bulan lalu', 'intent_key' => 'pos_sales_last_month', 'keywords' => ['pos bulan lalu', 'penjualan bulan lalu', 'sales bulan lalu', 'omset bulan lalu'], 'match_mode' => 'or', 'priority' => 43, 'response_text' => null],
        ['name' => 'Cashflow hari ini', 'intent_key' => 'cashflow_today', 'keywords' => ['cashflow', 'hari ini'], 'match_mode' => 'and', 'priority' => 50, 'response_text' => null],
        ['name' => 'Kas hari ini', 'intent_key' => 'cashflow_today', 'keywords' => ['kas hari ini', 'arus kas hari ini', 'cash hari ini'], 'match_mode' => 'or', 'priority' => 50, 'response_text' => null],
        ['name' => 'Cashflow kemarin', 'intent_key' => 'cashflow_yesterday', 'keywords' => ['cashflow', 'kemarin'], 'match_mode' => 'and', 'priority' => 51, 'response_text' => null],
        ['name' => 'Kas kemarin', 'intent_key' => 'cashflow_yesterday', 'keywords' => ['kas kemarin', 'arus kas kemarin'], 'match_mode' => 'or', 'priority' => 51, 'response_text' => null],
        ['name' => 'Cashflow bulan ini', 'intent_key' => 'cashflow_month', 'keywords' => ['cashflow bulan ini', 'kas bulan ini', 'arus kas bulan ini'], 'match_mode' => 'or', 'priority' => 52, 'response_text' => null],
        ['name' => 'Cashflow bulan lalu', 'intent_key' => 'cashflow_last_month', 'keywords' => ['cashflow bulan lalu', 'kas bulan lalu', 'arus kas bulan lalu'], 'match_mode' => 'or', 'priority' => 53, 'response_text' => null],
        ['name' => 'Project aktif', 'intent_key' => 'project_active_list', 'keywords' => ['project aktif', 'proyek aktif', 'project berjalan', 'daftar project'], 'match_mode' => 'or', 'priority' => 60, 'response_text' => null],
        ['name' => 'Biaya operasional', 'intent_key' => 'operational_summary', 'keywords' => ['biaya operasional', 'pengeluaran', 'cost operational', 'operating cost'], 'match_mode' => 'or', 'priority' => 70, 'response_text' => null],
        ['name' => 'Kirim invoice', 'intent_key' => 'send_invoice', 'keywords' => ['kirim invoice', 'send invoice', 'email invoice'], 'match_mode' => 'or', 'priority' => 80, 'response_text' => null],
        ['name' => 'List invoice terkirim', 'intent_key' => 'invoice_sent_list', 'keywords' => ['list invoice yang dikirim', 'invoice terkirim', 'riwayat kirim invoice'], 'match_mode' => 'or', 'priority' => 81, 'response_text' => null],
        ['name' => 'Bantuan', 'intent_key' => 'help', 'keywords' => ['bantuan', 'help', 'cara pakai', 'tutorial'], 'match_mode' => 'or', 'priority' => 90, 'response_text' => null],
    ];

    private array $synonyms = [
        // Invoice
        'invoice jatuh tempo' => 'invoice jatuh tempo',
        'tagihan jatuh tempo' => 'invoice jatuh tempo',
        'faktur jatuh tempo' => 'invoice jatuh tempo',
        'tagihan belum bayar' => 'invoice belum dibayar',
        'tagihan belum lunas' => 'invoice belum dibayar',
        'belum lunas' => 'invoice belum dibayar',
        'belum terbayar' => 'invoice belum dibayar',
        'unpaid invoice' => 'invoice belum dibayar',
        'unpaid' => 'belum dibayar',
        // Project
        'project berjalan' => 'project aktif',
        'project on going' => 'project aktif',
        'project ongoing' => 'project aktif',
        'proyek aktif' => 'project aktif',
        'proyek berjalan' => 'project aktif',
        'daftar project' => 'project aktif',
        'daftar proyek' => 'project aktif',
        // POS
        'penjualan hari ini' => 'pos hari ini',
        'sales hari ini' => 'pos hari ini',
        'omzet hari ini' => 'pos hari ini',
        'omset hari ini' => 'pos hari ini',
        'revenue hari ini' => 'pos hari ini',
        'penjualan kemarin' => 'pos kemarin',
        'sales kemarin' => 'pos kemarin',
        'omzet kemarin' => 'pos kemarin',
        'omset kemarin' => 'pos kemarin',
        'penjualan bulan ini' => 'pos bulan ini',
        'sales bulan ini' => 'pos bulan ini',
        'omzet bulan ini' => 'pos bulan ini',
        'omset bulan ini' => 'pos bulan ini',
        'penjualan bulan lalu' => 'pos bulan lalu',
        'sales bulan lalu' => 'pos bulan lalu',
        // Cashflow
        'kas hari ini' => 'cashflow hari ini',
        'keuangan hari ini' => 'cashflow hari ini',
        'arus kas hari ini' => 'cashflow hari ini',
        'kas kemarin' => 'cashflow kemarin',
        'keuangan kemarin' => 'cashflow kemarin',
        'arus kas kemarin' => 'cashflow kemarin',
        'kas bulan ini' => 'cashflow bulan ini',
        'keuangan bulan ini' => 'cashflow bulan ini',
        'arus kas bulan ini' => 'cashflow bulan ini',
        'kas bulan lalu' => 'cashflow bulan lalu',
        'keuangan bulan lalu' => 'cashflow bulan lalu',
        // Stock
        'stok habis' => 'stok rendah',
        'barang habis' => 'stok rendah',
        'stock rendah' => 'stok rendah',
        'stock habis' => 'stok rendah',
        'low stock' => 'stok rendah',
        'stok menipis' => 'stok rendah',
        'stock menipis' => 'stok rendah',
        // Operational
        'pengeluaran' => 'biaya operasional',
        'biaya bulan ini' => 'biaya operasional',
        'biaya oprasional' => 'biaya operasional',
        'cost operational' => 'biaya operasional',
        'operating cost' => 'biaya operasional',
        // Invoice send
        'kirim tagihan' => 'kirim invoice',
        'send invoice' => 'kirim invoice',
        'kirim faktur' => 'kirim invoice',
        'email invoice' => 'kirim invoice',
        // Help
        'tolong' => 'bantuan',
        'help' => 'bantuan',
        'cara pakai' => 'bantuan',
        'tutorial' => 'bantuan',
        // Greeting
        'selamat pagi' => 'halo',
        'selamat siang' => 'halo',
        'selamat sore' => 'halo',
        'selamat malam' => 'halo',
        'good morning' => 'halo',
        'good afternoon' => 'halo',
        'good evening' => 'halo',
        'hello' => 'halo',
        'hey' => 'halo',
        'hi' => 'halo',
        'hai' => 'halo',
        // Product detail
        'info produk' => 'detail produk',
        'info barang' => 'detail produk',
        'detail barang' => 'detail produk',
        'data produk' => 'detail produk',
        // Top selling
        'produk laris' => 'produk terlaris',
        'barang terlaris' => 'produk terlaris',
        'barang laris' => 'produk terlaris',
        'best seller' => 'produk terlaris',
        'top seller' => 'produk terlaris',
        'top selling' => 'produk terlaris',
        'paling laku' => 'produk terlaris',
    ];

    private array $typoMap = [
        'stok' => ['stk', 'sok', 'stck', 'sotk'],
        'harga' => ['hrga', 'hrganya', 'hargnya'],
        'invoice' => ['inovice', 'invoce', 'invois', 'infoice'],
        'cashflow' => ['casflow', 'cash flow', 'casflo', 'cashflo'],
        'project' => ['proyek', 'projek', 'proect'],
        'produk' => ['produck', 'proudk', 'prodik'],
        'operasional' => ['oprasional', 'operasioal', 'operasionl'],
        'bantuan' => ['bantu', 'bantuannya'],
        'penjualan' => ['penjualn', 'pnjualan', 'jualan'],
        'kemarin' => ['kemren', 'kmarin', 'kemarn'],
        'terlaris' => ['terlaris', 'trlaris'],
    ];

    public function parse(string $message, ?Collection $rules = null): array
    {
        $normalized = $this->normalize($message);
        $activeRules = ($rules ?? $this->activeRules())->sortBy('priority')->values();
        $matches = collect();

        foreach ($activeRules as $rule) {
            $keywords = collect($rule->keywords)
                ->filter(fn ($kw) => is_string($kw) && trim($kw) !== '')
                ->map(fn ($kw) => Str::of($kw)->lower()->trim()->toString())
                ->values();

            if ($keywords->isEmpty()) {
                continue;
            }

            $matchMode = $rule->match_mode ?? 'and';
            $matched = $matchMode === 'or'
                ? $keywords->some(fn ($kw) => $this->fuzzyContains($normalized, $kw))
                : $keywords->every(fn ($kw) => $this->fuzzyContains($normalized, $kw));

            if (! $matched) {
                continue;
            }

            $matchedKeywords = $keywords
                ->filter(fn ($kw) => $this->fuzzyContains($normalized, $kw))
                ->values();

            $specificityScore = $matchedKeywords->reduce(function (int $carry, string $kw): int {
                $wordCount = count(preg_split('/\s+/', $kw) ?: []);

                return max($carry, ($wordCount * 100) + strlen($kw));
            }, 0);

            $matches->push([
                'specificity' => $specificityScore,
                'priority' => (int) ($rule->priority ?? 9999),
                'rule' => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'intent_key' => $rule->intent_key,
                    'priority' => $rule->priority,
                    'keywords' => $keywords->values()->all(),
                    'match_mode' => $matchMode,
                    'response_text' => $rule->response_text,
                ],
            ]);
        }

        if ($matches->isNotEmpty()) {
            $selected = $matches
                ->sortBy([
                    ['specificity', 'desc'],
                    ['priority', 'asc'],
                ])
                ->first();

            return [
                'matched' => true,
                'rule' => $selected['rule'],
            ];
        }

        return [
            'matched' => false,
            'rule' => null,
        ];
    }

    public function activeRules(): Collection
    {
        return Cache::remember('erp_chat_parser_rules', 300, function () {
            $databaseRules = ErpChatParserRule::query()
                ->where('is_active', true)
                ->orderBy('priority')
                ->orderBy('id')
                ->get();

            if ($databaseRules->isNotEmpty()) {
                return $databaseRules;
            }

            return collect(self::BUILT_IN_RULES)->map(function (array $rule, int $index): object {
                return (object) [
                    'id' => 'builtin-'.($index + 1),
                    'name' => $rule['name'],
                    'intent_key' => $rule['intent_key'],
                    'keywords' => $rule['keywords'],
                    'match_mode' => $rule['match_mode'],
                    'priority' => $rule['priority'],
                    'response_text' => $rule['response_text'],
                    'is_active' => true,
                ];
            })->sortBy('priority')->values();
        });
    }

    /**
     * Suggest the closest intent when no exact match is found.
     */
    public function suggestClosest(string $message): ?array
    {
        $normalized = $this->normalize($message);
        $words = preg_split('/\s+/', $normalized) ?: [];

        $best = null;
        $bestScore = 0;

        foreach ($this->activeRules() as $rule) {
            $keywords = collect($rule->keywords)
                ->filter(fn ($kw) => is_string($kw) && trim($kw) !== '')
                ->map(fn ($kw) => Str::lower(trim($kw)))
                ->values();

            if ($keywords->isEmpty()) {
                continue;
            }

            $score = 0;
            foreach ($keywords as $kw) {
                $kwParts = preg_split('/\s+/', $kw) ?: [];
                foreach ($kwParts as $kwPart) {
                    foreach ($words as $word) {
                        if (strlen($word) < 3) {
                            continue;
                        }
                        $distance = levenshtein($word, $kwPart);
                        $maxLen = max(strlen($word), strlen($kwPart));
                        if ($maxLen > 0 && ($distance / $maxLen) <= 0.35) {
                            $score += (1 - $distance / $maxLen);
                        }
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $rule;
            }
        }

        if ($best && $bestScore >= 0.5) {
            $hint = collect($best->keywords)->take(3)->implode(', ');

            return [
                'intent_key' => $best->intent_key,
                'name' => $best->name,
                'hint' => $hint,
                'score' => round($bestScore, 2),
            ];
        }

        return null;
    }

    private function normalize(string $message): string
    {
        $lower = Str::of($message)->lower()->squish()->toString();

        // Multi-word synonyms first (longest first for greedy replace)
        $sorted = $this->synonyms;
        uksort($sorted, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($sorted as $alias => $canonical) {
            $lower = str_replace($alias, $canonical, $lower);
        }

        return $lower;
    }

    /**
     * Check if $haystack contains $needle, with typo tolerance for single words.
     */
    private function fuzzyContains(string $haystack, string $needle): bool
    {
        if (str_contains($needle, ' ')) {
            return $this->fuzzyContainsMultiWord($haystack, $needle);
        }

        return $this->fuzzyContainsSingleWord($haystack, $needle);
    }

    private function fuzzyContainsSingleWord(string $haystack, string $needle): bool
    {
        $haystackWords = preg_split('/\s+/', $haystack) ?: [];

        if (in_array($needle, $haystackWords, true)) {
            return true;
        }

        foreach ($this->typoMap as $canonical => $typos) {
            if ($needle === $canonical) {
                foreach ($typos as $typo) {
                    if (in_array($typo, $haystackWords, true)) {
                        return true;
                    }
                }
            }
        }

        foreach ($haystackWords as $word) {
            if (strlen($word) < 4 || strlen($needle) < 4) {
                continue;
            }
            $dist = levenshtein($word, $needle);
            $maxLen = max(strlen($word), strlen($needle));
            if ($maxLen > 0 && ($dist / $maxLen) <= 0.25) {
                return true;
            }
        }

        return false;
    }

    private function fuzzyContainsMultiWord(string $haystack, string $needle): bool
    {
        if (Str::contains($haystack, $needle)) {
            return true;
        }

        $needleWords = preg_split('/\s+/', $needle) ?: [];
        $haystackWords = preg_split('/\s+/', $haystack) ?: [];

        foreach ($needleWords as $nw) {
            if (strlen($nw) < 2) {
                continue;
            }

            $matched = $this->fuzzyContainsSingleWord(implode(' ', $haystackWords), $nw);

            if (! $matched) {
                return false;
            }
        }

        return true;
    }
}
