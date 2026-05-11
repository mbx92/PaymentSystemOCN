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
            1
        );
    }

    /**
     * Satu job TSPL: nama, harga (opsional), Code 128, jumlah salinan (PRINT).
     */
    public function buildLabelJob(LabelProfile $p, string $barcodeData, string $productName, string $priceLine, int $copies): string
    {
        $copies = max(1, min(999, $copies));
        $w = $this->fmtMm($p->width_mm);
        $h = $this->fmtMm($p->height_mm);
        $gap = $this->fmtMm($p->gap_mm);

        $ml = $p->marginLeftDots();
        $mt = $p->marginTopDots();
        $dpi = max(100, (int) $p->dpi);
        $lineStep = max(22, (int) round(3.5 * $dpi / 25.4));

        $yName = $mt;
        $yPrice = $yName + $lineStep;
        $hasPrice = trim($priceLine) !== '';
        $yBar = $hasPrice ? $yPrice + $lineStep : $yName + $lineStep + 4;

        $labelHeightDots = $p->heightDots();
        $barH = max(48, min(140, $labelHeightDots - $yBar - (int) round(4 * $dpi / 25.4)));

        $name = $this->tsplText($productName, 80);
        $price = $hasPrice ? $this->tsplText($priceLine, 32) : '';
        $barcode = $this->tsplBarcodeData($barcodeData);

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

        $body = [
            'CLS',
            'TEXT '.$ml.','.$yName.',"3",0,1,1,"'.$name.'"',
        ];

        if ($hasPrice) {
            $body[] = 'TEXT '.$ml.','.$yPrice.',"3",0,1,1,"'.$price.'"';
        }

        $body[] = 'BARCODE '.$ml.','.$yBar.',"128",'.$barH.',1,0,2,2,"'.$barcode.'"';
        $body[] = 'PRINT 1,1';

        $one = implode("\n", array_merge($header, $body));

        return implode("\n\n", array_fill(0, $copies, $one));
    }

    private function fmtMm(float|string $mm): string
    {
        return number_format((float) $mm, 2, '.', '');
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
