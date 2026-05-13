<?php

namespace App\Services;

use App\Models\LabelProfile;
use RuntimeException;

/**
 * Kirim perintah TSPL (TSC / banyak printer label jaringan) lewat TCP port RAW (biasanya 9100).
 */
class LanTsplPrinter
{
    public static function isValidHost(string $host): bool
    {
        $host = trim($host);
        if ($host === '') {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9.-]{0,253})?$/', $host);
    }

    /**
     * @return array{0: string, 1: int} host dan port
     */
    public function send(string $host, int $port, string $payload): array
    {
        $host = trim($host);
        $port = max(1, min(65535, $port));
        $errno = 0;
        $errstr = '';
        $target = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? sprintf('tcp://[%s]:%d', $host, $port)
            : sprintf('tcp://%s:%d', $host, $port);

        $socket = @stream_socket_client(
            $target,
            $errno,
            $errstr,
            8,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            throw new RuntimeException(
                $errstr !== '' ? "Koneksi TSPL gagal: {$errstr} ({$errno})" : "Tidak bisa terhubung ke printer label {$host}:{$port}."
            );
        }

        stream_set_timeout($socket, 8);
        $payload = preg_replace('/\R+/u', "\n", $payload);
        $payload = str_replace("\n", "\r\n", trim($payload))."\r\n";

        $written = @fwrite($socket, $payload);
        if ($written === false || $written < strlen($payload)) {
            fclose($socket);
            throw new RuntimeException('Gagal mengirim semua data TSPL ke printer.');
        }

        fclose($socket);

        return [$host, $port];
    }

    /**
     * Label contoh untuk uji cetak (nama profil + barcode dummy).
     */
    public function buildSampleJob(LabelProfile $p): string
    {
        return $this->buildLabelJob(
            $p,
            '1234567890',
            'TSPL TEST - '.$p->name,
            'Rp 0',
            $p->labelsAcross()
        );
    }

    /**
     * Satu job TSPL: nama, harga (opsional), Code 128, jumlah salinan (PRINT).
     */
    public function buildLabelJob(LabelProfile $p, string $barcodeData, string $productName, string $priceLine, int $copies): string
    {
        $copies = max(1, min(999, $copies));
        $labelsAcross = $p->labelsAcross();
        $w = $this->fmtMm($p->physicalWidthMm());
        $h = $this->fmtMm($p->height_mm);
        $gap = $this->fmtMm($p->gap_mm);

        $header = [
            "SIZE {$w} mm,{$h} mm",
            "GAP {$gap} mm, 0 mm",
            'SPEED 4',
            'DENSITY 10',
            'DIRECTION 1',
            'REFERENCE 0,0',
            'OFFSET 0 mm',
            'SET PEEL OFF',
            'SET CUTTER OFF',
            'SET PARTIAL_CUTTER OFF',
            'SET TEAR ON',
        ];

        $jobs = [];
        for ($printed = 0; $printed < $copies;) {
            $body = ['CLS'];
            for ($col = 0; $col < $labelsAcross && $printed < $copies; $col++, $printed++) {
                array_push(
                    $body,
                    ...$this->tsplLabelCommandsForColumn($p, $col, $barcodeData, $productName, $priceLine)
                );
            }
            $body[] = 'PRINT 1,1';
            $jobs[] = implode("\n", array_merge($header, $body));
        }

        return implode("\n\n", $jobs);
    }

    /**
     * @return list<string>
     */
    private function tsplLabelCommandsForColumn(LabelProfile $p, int $col, string $barcodeData, string $productName, string $priceLine): array
    {
        $dpi = max(100, (int) $p->dpi);
        $ml = $p->marginLeftDots();
        $mt = $p->marginTopDots();
        $labelWidthDots = $p->widthDots();
        $labelHeightDots = $p->heightDots();
        $x = ($col * $p->columnPitchDots()) + $ml;
        $printableWidth = max(40, $labelWidthDots - ($ml * 2));
        $isCompact = (float) $p->width_mm <= 35 || (float) $p->height_mm <= 18;

        $font = $isCompact ? '"1"' : '"3"';
        $fontDotWidth = $isCompact ? 6 : 12;
        $lineStep = $isCompact
            ? max(12, (int) round(1.8 * $dpi / 25.4))
            : max(22, (int) round(3.5 * $dpi / 25.4));
        $nameMax = max(8, (int) floor($printableWidth / $fontDotWidth));
        $priceMax = max(8, (int) floor($printableWidth / ($isCompact ? 6 : 10)));

        $yName = $mt;
        $yPrice = $yName + $lineStep;
        $hasPrice = trim($priceLine) !== '';
        $yBar = $hasPrice ? $yPrice + $lineStep : $yName + $lineStep + ($isCompact ? 1 : 4);
        $barcodeFormat = $this->normalizeBarcodeForProfile($p, $barcodeData);
        $barNarrow = $this->fittedBarcodeWidth($p, $barcodeFormat['type'], $barcodeFormat['data'], $printableWidth);
        $barWide = $barcodeFormat['type'] === 'code39' ? $barNarrow * 3 : max(2, $barNarrow);
        $humanReadable = $isCompact ? 0 : 1;
        $bottomReserve = $isCompact ? (int) round(2.8 * $dpi / 25.4) : (int) round(4 * $dpi / 25.4);
        $barH = $isCompact
            ? max(26, min(48, $labelHeightDots - $yBar - $bottomReserve))
            : max(48, min(140, $labelHeightDots - $yBar - $bottomReserve));

        $name = $this->tsplText($productName, $nameMax);
        $price = $hasPrice ? $this->tsplText($priceLine, $priceMax) : '';
        $barcode = $barcodeFormat['data'];
        $barcodeCommand = $this->tsplBarcodeCommand($barcodeFormat['type']);
        $commands = [
            'TEXT '.$x.','.$yName.','.$font.',0,1,1,"'.$name.'"',
        ];

        if ($hasPrice) {
            $commands[] = 'TEXT '.$x.','.$yPrice.','.$font.',0,1,1,"'.$price.'"';
        }

        $commands[] = 'BARCODE '.$x.','.$yBar.',"'.$barcodeCommand.'",'.$barH.','.$humanReadable.',0,'.$barNarrow.','.$barWide.',"'.$barcode.'"';

        if ($isCompact) {
            $barcodeTextY = $yBar + $barH + max(1, (int) round(0.4 * $dpi / 25.4));
            if ($barcodeTextY + 10 <= $labelHeightDots - $mt) {
                $commands[] = 'TEXT '.$x.','.$barcodeTextY.',"1",0,1,1,"'.$this->tsplText($barcodeFormat['text'], max(8, (int) floor($printableWidth / 6))).'"';
            }
        }

        return $commands;
    }

    private function fmtMm(float|string $mm): string
    {
        return number_format((float) $mm, 2, '.', '');
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

        $code128 = $this->tsplBarcodeData($data);

        return ['type' => 'code128', 'data' => $code128, 'text' => $code128];
    }

    private function tsplBarcodeCommand(string $type): string
    {
        return match ($type) {
            'ean13' => 'EAN13',
            'code39' => '39',
            default => '128',
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

    private function tsplText(string $utf8, int $maxLen): string
    {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $utf8);
        $clean = $clean !== false ? $clean : $utf8;

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean);
        $ascii = $ascii !== false ? $ascii : preg_replace('/[^\x20-\x7E]/', ' ', $clean);
        $ascii = str_replace(["\r", "\n", '"'], [' ', ' ', "'"], (string) $ascii);
        $ascii = preg_replace('/[^\x20-\x7E]/', ' ', $ascii) ?? '';
        $ascii = preg_replace('/\s+/', ' ', $ascii) ?? '';

        return substr(trim($ascii), 0, $maxLen);
    }

    private function tsplBarcodeData(string $data): string
    {
        $s = preg_replace('/[^\x20-\x7E]/', '', $data) ?? '';
        $s = str_replace('"', '', $s);

        return substr($s, 0, 42);
    }
}
