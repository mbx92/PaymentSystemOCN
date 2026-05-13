<?php

namespace App\Services;

use App\Models\LabelProfile;
use RuntimeException;

class WindowsSmbRawPrinter
{
    /**
     * Ubah input pengguna menjadi path UNC Windows (\\SERVER\Share).
     */
    public function normalizeUnc(string $input): string
    {
        $s = trim($input);
        if ($s === '') {
            return '';
        }

        if (preg_match('#^smb://#i', $s)) {
            $s = (string) preg_replace('#^smb://#i', '', $s);
            $s = str_replace('/', '\\', $s);
            $s = '\\\\'.ltrim($s, '\\');
        } else {
            $s = str_replace('/', '\\', $s);
            if (! str_starts_with($s, '\\\\')) {
                $s = '\\\\'.ltrim($s, '\\');
            }
        }

        return $s;
    }

    public function isValidUnc(string $unc): bool
    {
        if ($unc === '' || ! str_starts_with($unc, '\\\\')) {
            return false;
        }

        return (bool) preg_match('/^\\\\\\\\[^\\\\]+\\\\.+$/', $unc);
    }

    public function supportsUncFromPhp(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Kirim byte mentah ke queue printer Windows (path UNC).
     * Hanya di Windows; proses PHP harus punya akses ke share (user/domain).
     */
    public function sendRaw(string $unc, string $payload): void
    {
        if (! $this->supportsUncFromPhp()) {
            throw new RuntimeException(
                'Cetak ke path UNC Windows hanya didukung jika PHP berjalan di Windows. Untuk server Linux gunakan LAN raw (port 9100) atau agen SMB terpisah.'
            );
        }

        if (! $this->isValidUnc($unc)) {
            throw new RuntimeException('Path UNC tidak valid. Contoh: \\\\NAMA-PC\\NamaPrinter atau smb://NAMA-PC/NamaPrinter');
        }

        $fp = @fopen($unc, 'wb');
        if ($fp === false) {
            $err = error_get_last();
            $msg = $err['message'] ?? 'fopen gagal';

            throw new RuntimeException("Tidak bisa membuka printer UNC: {$msg}");
        }

        try {
            $written = @fwrite($fp, $payload);
            if ($written === false || $written < strlen($payload)) {
                throw new RuntimeException('Gagal menulis semua data ke printer (share atau antrian menolak).');
            }
        } finally {
            fclose($fp);
        }
    }

    /**
     * Contoh struk/label mengikuti ukuran & margin profil (konversi mm → titik lewat DPI).
     */
    public function samplePayloadForProfile(LabelProfile $profile): string
    {
        return match ($profile->protocol) {
            'tspl' => app(LanTsplPrinter::class)->buildSampleJob($profile),
            'epl' => $this->sampleEplForProfile($profile),
            default => $this->sampleZplForProfile($profile),
        };
    }

    private function sampleZplForProfile(LabelProfile $p): string
    {
        return $this->productBarcodeZpl($p, '1234567890', 'PaymentSystemOCN', $p->labelsAcross(), 'TEST');
    }

    private function sampleEplForProfile(LabelProfile $p): string
    {
        return $this->productBarcodeEpl($p, '1234567890', 'PaymentSystemOCN', $p->labelsAcross(), 'TEST');
    }

    private function sanitizeForZplText(string $text, int $maxLen = 80): string
    {
        $text = str_replace(['^', '~'], ' ', $text);

        return substr($text, 0, $maxLen);
    }

    private function sanitizeForEplQuoted(string $text, int $maxLen = 60): string
    {
        $text = str_replace(["\n", "\r", '"'], [' ', ' ', "'"], $text);

        return substr($text, 0, $maxLen);
    }

    /**
     * Label produk (barcode Code 128 / EPL B) diulang sebanyak $copies.
     *
     * @param  string  $barcodeData  Isi barcode (biasanya barcode atau SKU).
     * @param  string  $priceLine  Teks harga (mis. Rp 125.000), kosong jika tidak ditampilkan.
     */
    public function productBarcodePayloadForProfile(LabelProfile $profile, string $barcodeData, string $productName, int $copies, string $priceLine = ''): string
    {
        $copies = max(1, min(999, $copies));

        return match ($profile->protocol) {
            'tspl' => app(LanTsplPrinter::class)->buildLabelJob($profile, $barcodeData, $productName, $priceLine, $copies),
            'epl' => $this->productBarcodeEpl($profile, $barcodeData, $productName, $copies, $priceLine),
            default => $this->productBarcodeZpl($profile, $barcodeData, $productName, $copies, $priceLine),
        };
    }

    private function productBarcodeZpl(LabelProfile $p, string $barcode, string $name, int $copies, string $priceLine): string
    {
        $labelsAcross = $p->labelsAcross();
        $pw = max(100, $p->physicalWidthDots());
        $ll = max(80, $p->heightDots());
        $copies = max(1, min(999, $copies));
        $jobs = [];

        for ($printed = 0; $printed < $copies;) {
            $body = "^XA\n"
                ."^PW{$pw}\n"
                ."^LL{$ll}\n"
                ."^LH0,0\n";

            for ($col = 0; $col < $labelsAcross && $printed < $copies; $col++, $printed++) {
                $body .= $this->zplLabelFieldsForColumn($p, $col, $barcode, $name, $priceLine);
            }

            $jobs[] = $body."^XZ\n";
        }

        return implode('', $jobs);
    }

    private function productBarcodeEpl(LabelProfile $p, string $barcode, string $name, int $copies, string $priceLine): string
    {
        $labelsAcross = $p->labelsAcross();
        $q = max(100, $p->physicalWidthDots());
        $Q = max(80, $p->heightDots());
        $gap = $p->gapDots();
        $copies = max(1, min(999, $copies));
        $jobs = [];

        for ($printed = 0; $printed < $copies;) {
            $body = "N\n"
                ."q{$q}\n"
                ."Q{$Q},{$gap}\n";

            for ($col = 0; $col < $labelsAcross && $printed < $copies; $col++, $printed++) {
                $body .= $this->eplLabelFieldsForColumn($p, $col, $barcode, $name, $priceLine);
            }

            $jobs[] = $body."P1\n";
        }

        return implode('', $jobs);
    }

    private function zplLabelFieldsForColumn(LabelProfile $p, int $col, string $barcode, string $name, string $priceLine): string
    {
        $ml = $p->marginLeftDots();
        $mt = $p->marginTopDots();
        $labelWidthDots = $p->widthDots();
        $labelHeightDots = max(80, $p->heightDots());
        $x = ($col * $p->columnPitchDots()) + $ml;
        $fieldWidth = max(40, $labelWidthDots - ($ml * 2));
        $isCompact = (float) $p->width_mm <= 35 || (float) $p->height_mm <= 18;

        $nameFont = $isCompact ? 12 : 16;
        $priceFont = $isCompact ? 14 : 20;
        $barcodeFont = $isCompact ? 10 : 14;
        $nameY = $mt + ($isCompact ? 0 : 2);
        $priceY = $nameY + ($isCompact ? 14 : 18);
        $hasPrice = trim($priceLine) !== '';
        $barY = $hasPrice ? $priceY + ($isCompact ? 15 : 20) : $nameY + ($isCompact ? 16 : 22);
        $bottomReserve = $isCompact ? 16 : 8;
        $barH = $isCompact
            ? max(26, min(46, $labelHeightDots - $barY - $bottomReserve))
            : max(28, min(100, (int) (($labelHeightDots - $barY - 8) * 0.55)));
        $barcodeFormat = $this->normalizeBarcodeForProfile($p, $barcode);
        $moduleWidth = $this->fittedBarcodeWidth($p, $barcodeFormat['type'], $barcodeFormat['data'], $fieldWidth);
        $humanReadable = $isCompact ? 'N' : 'Y';

        $safeName = $this->sanitizeForZplText($name, max(8, (int) floor($fieldWidth / max(1, $nameFont * 0.58))));
        $safePrice = $hasPrice
            ? $this->sanitizeForZplText($priceLine, max(8, (int) floor($fieldWidth / max(1, $priceFont * 0.58))))
            : '';
        $safeBarcode = $barcodeFormat['data'];
        $safeBarcodeText = $barcodeFormat['text'];

        $fields = "^FO{$x},{$nameY}^A0N,{$nameFont},{$nameFont}^FB{$fieldWidth},1,0,L,0^FD{$safeName}^FS\n";
        if ($safePrice !== '') {
            $fields .= "^FO{$x},{$priceY}^A0N,{$priceFont},{$priceFont}^FB{$fieldWidth},1,0,L,0^FD{$safePrice}^FS\n";
        }

        $fields .= "^FO{$x},{$barY}".$this->zplBarcodeField($barcodeFormat['type'], $moduleWidth, $barH, $humanReadable, $safeBarcode);

        if ($isCompact) {
            $barcodeTextY = $barY + $barH + 2;
            if ($barcodeTextY + $barcodeFont <= $labelHeightDots - $mt) {
                $barcodeText = $this->sanitizeForZplText($safeBarcodeText, max(8, (int) floor($fieldWidth / 6)));
                $fields .= "^FO{$x},{$barcodeTextY}^A0N,{$barcodeFont},{$barcodeFont}^FB{$fieldWidth},1,0,L,0^FD{$barcodeText}^FS\n";
            }
        }

        return $fields;
    }

    private function eplLabelFieldsForColumn(LabelProfile $p, int $col, string $barcode, string $name, string $priceLine): string
    {
        $ml = $p->marginLeftDots();
        $mt = $p->marginTopDots();
        $labelWidthDots = $p->widthDots();
        $labelHeightDots = max(80, $p->heightDots());
        $x = ($col * $p->columnPitchDots()) + $ml;
        $fieldWidth = max(40, $labelWidthDots - ($ml * 2));
        $isCompact = (float) $p->width_mm <= 35 || (float) $p->height_mm <= 18;

        $font = $isCompact ? 1 : 2;
        $fontDotWidth = $isCompact ? 8 : 12;
        $nameY = $mt;
        $priceY = $nameY + ($isCompact ? 14 : 22);
        $hasPrice = trim($priceLine) !== '';
        $barY = $hasPrice ? $priceY + ($isCompact ? 15 : 22) : $nameY + ($isCompact ? 18 : 28);
        $bottomReserve = $isCompact ? 15 : 12;
        $barH = $isCompact
            ? max(26, min(46, $labelHeightDots - $barY - $bottomReserve))
            : max(28, min(90, (int) (($labelHeightDots - $barY - 12) * 0.55)));
        $barcodeFormat = $this->normalizeBarcodeForProfile($p, $barcode);
        $narrow = $this->fittedBarcodeWidth($p, $barcodeFormat['type'], $barcodeFormat['data'], $fieldWidth);
        $wide = $barcodeFormat['type'] === 'code39' ? $narrow * 3 : max(2, $narrow);
        $humanReadable = $isCompact ? 'N' : 'B';

        $safeName = $this->sanitizeForEplQuoted($name, max(8, (int) floor($fieldWidth / $fontDotWidth)));
        $safePrice = $hasPrice
            ? $this->sanitizeForEplQuoted($priceLine, max(8, (int) floor($fieldWidth / ($isCompact ? 8 : 10))))
            : '';
        $safeBarcode = $barcodeFormat['data'];
        $safeBarcodeText = $barcodeFormat['text'];

        $fields = "A{$x},{$nameY},0,{$font},1,1,N,\"{$safeName}\"\n";
        if ($safePrice !== '') {
            $fields .= "A{$x},{$priceY},0,{$font},1,1,N,\"{$safePrice}\"\n";
        }

        $fields .= 'B'.$x.','.$barY.',0,'.$this->eplBarcodeSelection($barcodeFormat['type']).",{$narrow},{$wide},{$barH},{$humanReadable},\"{$safeBarcode}\"\n";

        if ($isCompact) {
            $barcodeTextY = $barY + $barH + 2;
            if ($barcodeTextY + 10 <= $labelHeightDots - $mt) {
                $barcodeText = $this->sanitizeForEplQuoted($safeBarcodeText, max(8, (int) floor($fieldWidth / 8)));
                $fields .= "A{$x},{$barcodeTextY},0,1,1,1,N,\"{$barcodeText}\"\n";
            }
        }

        return $fields;
    }

    /**
     * @return array{type: string, data: string, text: string}
     */
    private function normalizeBarcodeForProfile(LabelProfile $p, string $data): array
    {
        $type = $p->barcodeType();

        if ($type === 'ean13') {
            $digits = preg_replace('/\D+/', '', $data) ?? '';
            if (strlen($digits) === 12) {
                return ['type' => 'ean13', 'data' => $digits, 'text' => $digits.$this->ean13CheckDigit($digits)];
            }
            if (strlen($digits) === 13) {
                return ['type' => 'ean13', 'data' => substr($digits, 0, 12), 'text' => $digits];
            }
        }

        if ($type === 'code39') {
            $code39 = strtoupper(preg_replace('/[^0-9A-Z .\-\/+$%]/', '', $data) ?? '');
            if ($code39 !== '') {
                $code39 = substr($code39, 0, 32);

                return ['type' => 'code39', 'data' => $code39, 'text' => $code39];
            }
        }

        $code128 = $this->sanitizeBarcodeForZpl($data);

        return ['type' => 'code128', 'data' => $code128, 'text' => $code128];
    }

    private function zplBarcodeField(string $type, int $moduleWidth, int $barH, string $humanReadable, string $safeBarcode): string
    {
        return match ($type) {
            'ean13' => "^BY{$moduleWidth}^BEN,{$barH},{$humanReadable},N^FD{$safeBarcode}^FS\n",
            'code39' => "^BY{$moduleWidth},3^B3N,N,{$barH},{$humanReadable},N^FD{$safeBarcode}^FS\n",
            default => "^BY{$moduleWidth}^BCN,{$barH},{$humanReadable},N,N^FD{$safeBarcode}^FS\n",
        };
    }

    private function eplBarcodeSelection(string $type): string
    {
        return match ($type) {
            'ean13' => 'E30',
            'code39' => '3',
            default => '1',
        };
    }

    private function fittedBarcodeWidth(LabelProfile $p, string $type, string $data, int $printableWidth): int
    {
        $modules = match ($type) {
            'ean13' => 95,
            'code39' => max(1, strlen($data)) * 13 + 10,
            default => (max(1, strlen($data)) + 3) * 11 + 2,
        };
        $maxWidth = max(1, (int) floor($printableWidth / max(1, $modules)));

        return max(1, min($p->barcodeWidth(), $maxWidth, 3));
    }

    private function ean13CheckDigit(string $digits12): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits12[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }

    private function sanitizeBarcodeForZpl(string $s): string
    {
        $s = str_replace(['^', '~'], '-', trim($s));

        return substr($s, 0, 48);
    }

    private function sanitizeBarcodeForEpl(string $s): string
    {
        $s = str_replace(["\n", "\r", '"'], [' ', ' ', "'"], trim($s));

        return substr($s, 0, 40);
    }
}
