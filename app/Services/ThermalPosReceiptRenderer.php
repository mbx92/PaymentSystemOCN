<?php

namespace App\Services;

final class ThermalPosReceiptRenderer
{
    public function defaultHeaderTemplate(): string
    {
        return "{{app_name}}\n"
            ."STRUK PENJUALAN\n"
            ."\n"
            ."Nomor Transaksi: {{transaction_number}}\n"
            ."Tanggal: {{date}}\n"
            ."Waktu: {{time}}\n"
            ."Metode Pembayaran: {{payment_method}}\n"
            .'Kasir: {{cashier}}';
    }

    public function defaultItemLineTemplate(): string
    {
        return "{{item_padded_line}}\n"
            .'{{unit_price}} / {{uom}}';
    }

    public function defaultFooterTemplate(): string
    {
        return "{{footer_row_subtotal}}\n"
            ."{{footer_row_discount}}\n"
            ."{{footer_rows_additional_charges}}\n"
            ."{{footer_row_grand_total}}\n"
            ."{{footer_row_cash_paid}}\n"
            .'{{footer_row_change}}';
    }

    /**
     * Margin kiri dalam jumlah spasi (kolom Font A), dari mm dan lebar kertas nominal.
     */
    public static function marginCharsFromMm(float $marginMm, string $paperMm, int $cols): int
    {
        if ($marginMm <= 0) {
            return 0;
        }
        $paper = $paperMm === '58' ? 58.0 : 80.0;
        $v = (int) round($marginMm * $cols / $paper);

        return max(0, min($cols - 4, $v));
    }

    /**
     * @param  array{header?: string|null, item_line?: string|null, footer?: string|null}  $template
     * @param  array{
     *     margin_left_mm?: float|int|string|null,
     *     header_align?: string|null,
     *     item_align?: string|null,
     *     footer_align?: string|null,
     *     section_gap?: int|string|null,
     *     header_emphasis?: bool|string|null,
     *     content_cols?: int|null,
     * }  $layout
     * @return list<array{type: 'lines', align: 'left'|'center'|'right', lines: list<string>, double_height_first?: bool}|array{type: 'separator'}|array{type: 'spacer', count: positive-int}>
     */
    public function buildReceiptSegments(array $template, ThermalPosReceiptData $data, string $paperMm, int $cols, array $layout): array
    {
        $header = trim((string) ($template['header'] ?? '')) ?: $this->defaultHeaderTemplate();
        $itemLine = trim((string) ($template['item_line'] ?? '')) ?: $this->defaultItemLineTemplate();
        $footer = trim((string) ($template['footer'] ?? '')) ?: $this->defaultFooterTemplate();

        $headerAlign = $this->normalizeAlign($layout['header_align'] ?? 'center');
        $itemAlign = $this->normalizeAlign($layout['item_align'] ?? 'left');
        $footerAlign = $this->normalizeAlign($layout['footer_align'] ?? 'right');
        $gap = max(0, min(3, (int) ($layout['section_gap'] ?? 0)));
        $emphasis = filter_var($layout['header_emphasis'] ?? true, FILTER_VALIDATE_BOOL);
        $lineCols = (int) ($layout['content_cols'] ?? $cols);
        $lineCols = max(8, min($lineCols, $cols));

        $segments = [];

        $headerGroups = $this->splitHeaderLineGroups($this->expandMultiline($this->replaceScalars($header, $data, $lineCols)));
        foreach ($headerGroups as $idx => $group) {
            if ($idx > 0) {
                $segments[] = ['type' => 'spacer', 'count' => 1];
            }
            $segments[] = [
                'type' => 'lines',
                'align' => $idx === 0 ? $headerAlign : 'left',
                'lines' => $this->mapLines($group, $lineCols),
                'double_height_first' => $emphasis && $idx === 0,
            ];
        }

        $segments = array_merge($segments, $this->spacerSegments($gap));
        $segments[] = ['type' => 'separator'];
        $segments = array_merge($segments, $this->spacerSegments($gap));

        foreach ($data->lines as $row) {
            $rawLines = $this->expandMultiline($this->replaceItemLine($itemLine, $row, $lineCols));
            $segments[] = [
                'type' => 'lines',
                'align' => $itemAlign,
                'lines' => $this->mapLines($rawLines, $lineCols),
                'double_height_first' => false,
            ];
        }

        $segments = array_merge($segments, $this->spacerSegments($gap));
        $segments[] = ['type' => 'separator'];
        $segments = array_merge($segments, $this->spacerSegments($gap));

        $footerLines = $this->mapLines($this->expandMultiline($this->replaceScalars($footer, $data, $lineCols)), $lineCols);
        $segments[] = [
            'type' => 'lines',
            'align' => $footerAlign,
            'lines' => $footerLines,
            'double_height_first' => false,
        ];

        return $segments;
    }

    /**
     * @return list<array{type: 'spacer', count: positive-int}>
     */
    private function spacerSegments(int $gap): array
    {
        if ($gap <= 0) {
            return [];
        }

        return [['type' => 'spacer', 'count' => $gap]];
    }

    /**
     * Baris header kosong pertama memisahkan blok judul (rata sesuai pengaturan) dan blok meta (selalu kiri).
     *
     * @param  list<string>  $lines
     * @return list<list<string>>
     */
    private function splitHeaderLineGroups(array $lines): array
    {
        $groups = [];
        $buf = [];
        foreach ($lines as $ln) {
            if ($ln === '' && $buf !== []) {
                $groups[] = $buf;
                $buf = [];

                continue;
            }
            if ($ln === '' && $buf === []) {
                continue;
            }
            $buf[] = $ln;
        }
        if ($buf !== []) {
            $groups[] = $buf;
        }

        return $groups !== [] ? $groups : [[]];
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function mapLines(array $lines, int $cols): array
    {
        return array_values(array_map(fn (string $ln) => $this->truncateLine($ln, $cols), $lines));
    }

    /**
     * @return list<string>
     */
    public function renderPlainLines(array $template, ThermalPosReceiptData $data, int $maxCols): array
    {
        $layout = [
            'header_align' => 'left',
            'item_align' => 'left',
            'footer_align' => 'left',
            'section_gap' => 0,
            'header_emphasis' => false,
        ];
        $segments = $this->buildReceiptSegments($template, $data, '80', $maxCols, $layout);
        $out = [];
        foreach ($segments as $seg) {
            if (($seg['type'] ?? '') === 'lines') {
                foreach ($seg['lines'] as $ln) {
                    $out[] = $ln;
                }
            } elseif (($seg['type'] ?? '') === 'separator') {
                $out[] = str_repeat('-', max(8, min($maxCols, 48)));
            } elseif (($seg['type'] ?? '') === 'spacer') {
                $n = max(0, min(10, (int) ($seg['count'] ?? 0)));
                for ($i = 0; $i < $n; $i++) {
                    $out[] = '';
                }
            }
        }

        return $out;
    }

    private function normalizeAlign(?string $align): string
    {
        $a = strtolower(trim((string) $align));

        return in_array($a, ['left', 'center', 'right'], true) ? $a : 'left';
    }

    private function replaceScalars(string $text, ThermalPosReceiptData $data, int $cols): string
    {
        $cols = max(8, $cols);
        $rightW = min($this->footerAmountColumnByteWidth($data), max(6, $cols - 4));

        $map = [
            '{{app_name}}' => $data->appName,
            '{{transaction_number}}' => $data->transactionNumber,
            '{{date}}' => $data->date,
            '{{time}}' => $data->time,
            '{{payment_method}}' => $data->paymentMethod,
            '{{cashier}}' => $data->cashierName,
            '{{gross_total}}' => $data->grossTotal,
            '{{discount_total}}' => $data->discountTotal,
            '{{additional_fee}}' => $data->additionalFee,
            '{{grand_total}}' => $data->grandTotal,
            '{{cash_paid}}' => $data->cashPaid,
            '{{change}}' => $data->change,
            '{{footer_row_subtotal}}' => $this->formatFooterMoneyLine('Subtotal', $data->grossTotal, $cols, $rightW),
            '{{footer_row_discount}}' => $this->formatFooterMoneyLine('Diskon', $data->discountTotal, $cols, $rightW),
            '{{footer_row_additional_fee}}' => $this->formatFooterMoneyLine('Biaya lain', $data->additionalFee, $cols, $rightW),
            '{{footer_rows_additional_charges}}' => $this->formatAdditionalChargeRows($data, $cols, $rightW),
            '{{footer_row_grand_total}}' => $this->formatFooterMoneyLine('TOTAL', $data->grandTotal, $cols, $rightW),
            '{{footer_row_cash_paid}}' => $this->formatFooterMoneyLine('Dibayar', $data->cashPaid, $cols, $rightW),
            '{{footer_row_change}}' => $this->formatFooterMoneyLine('Kembali', $data->change, $cols, $rightW),
        ];

        return strtr($text, $map);
    }

    private function formatAdditionalChargeRows(ThermalPosReceiptData $data, int $cols, int $rightW): string
    {
        $rows = [];

        foreach ($data->additionalCharges as $charge) {
            $name = trim((string) ($charge['name'] ?? ''));
            $amount = trim((string) ($charge['amount'] ?? '0'));
            if ($name === '') {
                $name = 'Biaya lain';
            }
            $rows[] = $this->formatFooterMoneyLine($name, $amount, $cols, $rightW);
        }

        if ($rows === [] && (float) str_replace(['.', ','], ['', '.'], $data->additionalFee) > 0) {
            $rows[] = $this->formatFooterMoneyLine('Biaya lain', $data->additionalFee, $cols, $rightW);
        }

        return implode("\n", $rows);
    }

    /**
     * Lebar kolom kanan (byte Latin-1) untuk blok "Rp …" agar semua baris footer sejajar.
     */
    private function footerAmountColumnByteWidth(ThermalPosReceiptData $data): int
    {
        $max = 0;
        foreach ([$data->grossTotal, $data->discountTotal, $data->additionalFee, $data->grandTotal, $data->cashPaid, $data->change] as $amt) {
            $part = 'Rp '.$amt;
            $latin = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $part) ?: $part;
            $max = max($max, strlen($latin));
        }

        return max(6, $max);
    }

    /**
     * Satu baris footer: label kiri + spasi + "Rp …" yang lebarnya tetap dan angka rata kanan dalam kolom itu.
     */
    private function formatFooterMoneyLine(string $labelUtf8, string $amountDigitsUtf8, int $budgetCols, int $rightColByteWidth): string
    {
        $budgetCols = max(8, $budgetCols);
        $rightColByteWidth = max(4, min($rightColByteWidth, $budgetCols - 5));

        $amountPartUtf8 = 'Rp '.$amountDigitsUtf8;
        $amountLatin = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $amountPartUtf8) ?: $amountPartUtf8;
        if (strlen($amountLatin) > $rightColByteWidth) {
            $amountLatin = substr($amountLatin, -$rightColByteWidth);
        }
        $paddedAmount = str_pad($amountLatin, $rightColByteWidth, ' ', STR_PAD_LEFT);

        $labelLatin = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', trim($labelUtf8)) ?: trim($labelUtf8);
        $maxLabel = $budgetCols - $rightColByteWidth - 1;
        if ($maxLabel < 1) {
            return $this->latin1LineToUtf8($paddedAmount);
        }
        if (strlen($labelLatin) > $maxLabel) {
            $labelLatin = substr($labelLatin, 0, max(1, $maxLabel - 3)).'...';
        }
        $pad = $budgetCols - strlen($labelLatin) - $rightColByteWidth;
        if ($pad < 1) {
            $labelLatin = substr($labelLatin, 0, max(1, strlen($labelLatin) + $pad - 1));
            $pad = $budgetCols - strlen($labelLatin) - $rightColByteWidth;
        }
        if ($pad < 1) {
            $pad = 1;
        }

        $lineLatin = $labelLatin.str_repeat(' ', $pad).$paddedAmount;
        if (strlen($lineLatin) > $budgetCols) {
            $lineLatin = substr($lineLatin, 0, $budgetCols);
        }

        return $this->latin1LineToUtf8($lineLatin);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function replaceItemLine(string $text, array $row, int $contentCols): string
    {
        $sku = (string) ($row['sku'] ?? '');
        $name = (string) ($row['name'] ?? '');
        $qty = $row['qty'] ?? '';
        $unit = (string) ($row['unit_price'] ?? '');
        $total = (string) ($row['line_total'] ?? '');
        $uom = (string) ($row['uom'] ?? $row['satuan'] ?? '');
        $disc = $row['discount_percent'] ?? '0';
        if (is_float($disc) || is_int($disc)) {
            $disc = (string) $disc;
        }

        $qtyStr = (string) $qty;
        $leftRaw = ' '.$qtyStr.' x '.$name;

        $map = [
            '{{sku}}' => $sku,
            '{{name}}' => $name,
            '{{qty}}' => $qtyStr,
            '{{unit_price}}' => $unit,
            '{{line_total}}' => $total,
            '{{uom}}' => $uom,
            '{{satuan}}' => $uom,
            '{{discount_percent}}' => (string) $disc,
            '{{item_padded_line}}' => $this->padLeftRightThermal($leftRaw, $total, $contentCols),
        ];

        return strtr($text, $map);
    }

    /**
     * Satu baris: teks kiri + spasi + total kanan, lebar mengikuti kolom kertas (byte Latin-1 seperti printer).
     */
    private function padLeftRightThermal(string $leftUtf8, string $rightUtf8, int $budget): string
    {
        $budget = max(4, $budget);
        $left = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $leftUtf8) ?: $leftUtf8;
        $right = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $rightUtf8) ?: $rightUtf8;
        $rLen = strlen($right);
        if ($rLen > $budget) {
            $right = substr($right, -$budget);
            $rLen = strlen($right);
        }
        $maxLeft = $budget - $rLen - 1;
        if ($maxLeft < 1) {
            $combined = substr($left, 0, max(1, $budget - $rLen)).$right;

            return $this->latin1LineToUtf8($combined);
        }
        if (strlen($left) > $maxLeft) {
            $left = substr($left, 0, max(1, $maxLeft - 3)).'...';
        }
        $pad = $budget - strlen($left) - strlen($right);

        return $this->latin1LineToUtf8($left.str_repeat(' ', max(1, $pad)).$right);
    }

    private function latin1LineToUtf8(string $latin): string
    {
        $u = @iconv('ISO-8859-1', 'UTF-8//TRANSLIT', $latin);

        return $u !== false ? $u : $latin;
    }

    /**
     * @return list<string>
     */
    private function expandMultiline(string $text): array
    {
        $parts = preg_split("/\r\n|\n|\r/", $text) ?: [];

        return array_values(array_map('trim', $parts));
    }

    private function truncateLine(string $line, int $maxCols): string
    {
        if (strlen($line) <= $maxCols) {
            return $line;
        }

        return substr($line, 0, max(1, $maxCols - 1)).'…';
    }
}
