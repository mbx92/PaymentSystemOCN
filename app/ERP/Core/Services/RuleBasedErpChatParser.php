<?php

namespace App\ERP\Core\Services;

use App\Models\ErpChatParserRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RuleBasedErpChatParser
{
    private array $synonyms = [
        // Invoice
        'invoice jatuh tempo'  => 'invoice jatuh tempo',
        'tagihan jatuh tempo'  => 'invoice jatuh tempo',
        'faktur jatuh tempo'   => 'invoice jatuh tempo',
        'tagihan belum bayar'  => 'invoice belum dibayar',
        'tagihan belum lunas'  => 'invoice belum dibayar',
        'belum lunas'          => 'invoice belum dibayar',
        'belum terbayar'       => 'invoice belum dibayar',
        'unpaid invoice'       => 'invoice belum dibayar',
        'unpaid'               => 'belum dibayar',
        // Project
        'project berjalan'  => 'project aktif',
        'project on going'  => 'project aktif',
        'project ongoing'   => 'project aktif',
        'proyek aktif'      => 'project aktif',
        'proyek berjalan'   => 'project aktif',
        'daftar project'    => 'project aktif',
        'daftar proyek'     => 'project aktif',
        // POS
        'penjualan hari ini' => 'pos hari ini',
        'sales hari ini'     => 'pos hari ini',
        'omzet hari ini'     => 'pos hari ini',
        'omset hari ini'     => 'pos hari ini',
        'revenue hari ini'   => 'pos hari ini',
        'penjualan kemarin'  => 'pos kemarin',
        'sales kemarin'      => 'pos kemarin',
        'omzet kemarin'      => 'pos kemarin',
        'omset kemarin'      => 'pos kemarin',
        'penjualan bulan ini'  => 'pos bulan ini',
        'sales bulan ini'      => 'pos bulan ini',
        'omzet bulan ini'      => 'pos bulan ini',
        'omset bulan ini'      => 'pos bulan ini',
        'penjualan bulan lalu' => 'pos bulan lalu',
        'sales bulan lalu'     => 'pos bulan lalu',
        // Cashflow
        'kas hari ini'        => 'cashflow hari ini',
        'keuangan hari ini'   => 'cashflow hari ini',
        'arus kas hari ini'   => 'cashflow hari ini',
        'kas kemarin'         => 'cashflow kemarin',
        'keuangan kemarin'    => 'cashflow kemarin',
        'arus kas kemarin'    => 'cashflow kemarin',
        'kas bulan ini'       => 'cashflow bulan ini',
        'keuangan bulan ini'  => 'cashflow bulan ini',
        'arus kas bulan ini'  => 'cashflow bulan ini',
        'kas bulan lalu'      => 'cashflow bulan lalu',
        'keuangan bulan lalu' => 'cashflow bulan lalu',
        // Stock
        'stok habis'   => 'stok rendah',
        'barang habis'  => 'stok rendah',
        'stock rendah'  => 'stok rendah',
        'stock habis'   => 'stok rendah',
        'low stock'     => 'stok rendah',
        'stok menipis'  => 'stok rendah',
        'stock menipis' => 'stok rendah',
        // Operational
        'pengeluaran'      => 'biaya operasional',
        'biaya bulan ini'  => 'biaya operasional',
        'biaya oprasional' => 'biaya operasional',
        'cost operational' => 'biaya operasional',
        'operating cost'   => 'biaya operasional',
        // Invoice send
        'kirim tagihan'  => 'kirim invoice',
        'send invoice'   => 'kirim invoice',
        'kirim faktur'   => 'kirim invoice',
        'email invoice'  => 'kirim invoice',
        // Help
        'tolong'     => 'bantuan',
        'help'       => 'bantuan',
        'cara pakai' => 'bantuan',
        'tutorial'   => 'bantuan',
        // Greeting
        'selamat pagi'   => 'halo',
        'selamat siang'  => 'halo',
        'selamat sore'   => 'halo',
        'selamat malam'  => 'halo',
        'good morning'   => 'halo',
        'good afternoon' => 'halo',
        'good evening'   => 'halo',
        'hello'          => 'halo',
        'hey'            => 'halo',
        'hi'             => 'halo',
        'hai'            => 'halo',
        // Product detail
        'info produk'   => 'detail produk',
        'info barang'   => 'detail produk',
        'detail barang' => 'detail produk',
        'data produk'   => 'detail produk',
        // Top selling
        'produk laris'    => 'produk terlaris',
        'barang terlaris' => 'produk terlaris',
        'barang laris'    => 'produk terlaris',
        'best seller'     => 'produk terlaris',
        'top seller'      => 'produk terlaris',
        'top selling'     => 'produk terlaris',
        'paling laku'     => 'produk terlaris',
    ];

    private array $typoMap = [
        'stok'   => ['stk', 'sok', 'stck', 'sotk'],
        'harga'  => ['hrga', 'hrganya', 'hargnya'],
        'invoice' => ['inovice', 'invoce', 'invois', 'infoice'],
        'cashflow' => ['casflow', 'cash flow', 'casflo', 'cashflo'],
        'project' => ['proyek', 'projek', 'proect'],
        'produk'  => ['produck', 'proudk', 'prodik'],
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

            return [
                'matched' => true,
                'rule' => [
                    'id'            => $rule->id,
                    'name'          => $rule->name,
                    'intent_key'    => $rule->intent_key,
                    'priority'      => $rule->priority,
                    'keywords'      => $keywords->values()->all(),
                    'match_mode'    => $matchMode,
                    'response_text' => $rule->response_text,
                ],
            ];
        }

        return [
            'matched' => false,
            'rule'    => null,
        ];
    }

    public function activeRules(): Collection
    {
        return ErpChatParserRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
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
        if (Str::contains($haystack, $needle)) {
            return true;
        }

        // For multi-word keywords, try exact containment only (already handled by synonyms)
        if (str_contains($needle, ' ')) {
            return false;
        }

        // Single-word: check known typos
        foreach ($this->typoMap as $canonical => $typos) {
            if ($needle === $canonical) {
                foreach ($typos as $typo) {
                    if (Str::contains($haystack, $typo)) {
                        return true;
                    }
                }
            }
        }

        // Levenshtein on each word in haystack
        $haystackWords = preg_split('/\s+/', $haystack) ?: [];
        foreach ($haystackWords as $word) {
            if (strlen($word) < 3 || strlen($needle) < 3) {
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
}
