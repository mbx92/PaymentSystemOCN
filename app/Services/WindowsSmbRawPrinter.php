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
        $pw = max(100, $p->widthDots());
        $ll = max(80, $p->heightDots());
        $ml = $p->marginLeftDots();
        $mt = $p->marginTopDots();
        $innerH = $ll - $mt - 8;
        $barH = max(36, min(120, (int) ($innerH * 0.35)));
        $line2 = $mt + 26;
        $title = $this->sanitizeForZplText((string) $p->name);

        return "^XA\n"
            ."^PW{$pw}\n"
            ."^LL{$ll}\n"
            ."^LH0,0\n"
            ."^FO{$ml},{$mt}^A0N,22,22^FDPaymentSystemOCN^FS\n"
            ."^FO{$ml},{$line2}^A0N,16,16^FD{$title}^FS\n"
            ."^FO{$ml},".($line2 + 28)."^BY2^BCN,{$barH},Y,N,N^FD1234567890^FS\n"
            ."^XZ\n";
    }

    private function sampleEplForProfile(LabelProfile $p): string
    {
        $q = max(200, $p->widthDots());
        $Q = max(200, $p->heightDots());
        $gap = $p->gapDots();
        $ml = $p->marginLeftDots();
        $mt = $p->marginTopDots();
        $barH = max(30, min(100, (int) (($Q - $mt - 20) * 0.35)));
        $title = $this->sanitizeForEplQuoted((string) $p->name);

        return "N\n"
            ."q{$q}\n"
            ."Q{$Q},{$gap}\n"
            ."A{$ml},{$mt},0,3,1,1,N,\"PaymentSystemOCN\"\n"
            ."A{$ml},".($mt + 28).",0,2,1,1,N,\"{$title}\"\n"
            ."B{$ml},".($mt + 56).",0,1,2,4,{$barH},B,\"1234567890\"\n"
            ."P1\n";
    }

    private function sanitizeForZplText(string $text): string
    {
        $text = str_replace(['^', '~'], ' ', $text);

        return substr($text, 0, 80);
    }

    private function sanitizeForEplQuoted(string $text): string
    {
        $text = str_replace(["\n", "\r", '"'], [' ', ' ', "'"], $text);

        return substr($text, 0, 60);
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
        $pw = max(100, $p->widthDots());
        $ll = max(80, $p->heightDots());
        $ml = $p->marginLeftDots();
        $mt = $p->marginTopDots();
        $safeName = $this->sanitizeForZplText($name);
        $safePrice = trim($priceLine) !== '' ? $this->sanitizeForZplText($priceLine) : '';
        $safeBarcode = $this->sanitizeBarcodeForZpl($barcode);

        $nameY = $mt + 2;
        $priceY = $nameY + 18;
        $barY = $safePrice !== '' ? $priceY + 20 : $nameY + 22;
        $roomBelowBar = $ll - $barY - 8;
        $barH = max(28, min(100, (int) ($roomBelowBar * 0.55)));

        $priceField = $safePrice !== ''
            ? "^FO{$ml},{$priceY}^A0N,20,20^FD{$safePrice}^FS\n"
            : '';

        $one = "^XA\n"
            ."^PW{$pw}\n"
            ."^LL{$ll}\n"
            ."^LH0,0\n"
            ."^FO{$ml},{$nameY}^A0N,16,16^FD{$safeName}^FS\n"
            .$priceField
            ."^FO{$ml},{$barY}^BY2^BCN,{$barH},Y,N,N^FD{$safeBarcode}^FS\n"
            ."^XZ\n";

        return str_repeat($one, $copies);
    }

    private function productBarcodeEpl(LabelProfile $p, string $barcode, string $name, int $copies, string $priceLine): string
    {
        $q = max(200, $p->widthDots());
        $Q = max(200, $p->heightDots());
        $gap = $p->gapDots();
        $ml = $p->marginLeftDots();
        $mt = $p->marginTopDots();
        $safeName = $this->sanitizeForEplQuoted($name);
        $safePrice = trim($priceLine) !== '' ? $this->sanitizeForEplQuoted($priceLine) : '';
        $safeBarcode = $this->sanitizeBarcodeForEpl($barcode);

        $nameY = $mt;
        $priceY = $nameY + 22;
        $barY = $safePrice !== '' ? $priceY + 22 : $nameY + 28;
        $roomBelowBar = $Q - $barY - 12;
        $barH = max(28, min(90, (int) ($roomBelowBar * 0.55)));

        $priceLineEpl = $safePrice !== ''
            ? "A{$ml},{$priceY},0,2,1,1,N,\"{$safePrice}\"\n"
            : '';

        $one = "N\n"
            ."q{$q}\n"
            ."Q{$Q},{$gap}\n"
            ."A{$ml},{$nameY},0,2,1,1,N,\"{$safeName}\"\n"
            .$priceLineEpl
            .'B'.$ml.','.$barY.",0,1,2,4,{$barH},B,\"{$safeBarcode}\"\n"
            ."P1\n";

        return str_repeat($one, $copies);
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
