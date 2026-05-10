<?php

namespace App\Services;

use RuntimeException;

class LanEscPosPrinter
{
    /** Lebar teks Font A umum (kolom) — 58mm vs 80mm */
    private const PAPER_COLUMNS = [
        '58' => 32,
        '80' => 48,
    ];

    /** Baris kosong sebelum GS V (potong) agar teks paling bawah tidak masuk area pisau */
    private const TRAILING_BLANK_LINES_BEFORE_CUT = 10;

    /**
     * @param  string  $paperWidth  `58` atau `80` (mm)
     * @return array{0: string, 1: int} host and port used
     */
    public function sendTestReceipt(string $host, int $port, string $titleLine = 'TEST PRINT LAN', string $paperWidth = '80'): array
    {
        $paper = $this->normalizePaperWidth($paperWidth);
        $payload = $this->buildTestPayload($titleLine, $paper);

        return $this->sendToLanPrinter(trim($host), (int) $port, $payload);
    }

    /**
     * Struk dari segmen (perataan + margin + penekanan baris pertama header).
     *
     * @param  list<array{type: 'lines', align: string, lines: list<string>, double_height_first?: bool}|array{type: 'separator'}|array{type: 'spacer', count: int}>  $segments
     * @return array{0: string, 1: int}
     */
    public function sendStructuredReceipt(string $host, int $port, array $segments, string $paperWidth, int $marginChars = 0): array
    {
        $paper = $this->normalizePaperWidth($paperWidth);
        $payload = $this->buildReceiptFromSegments($segments, $paper, max(0, $marginChars));

        return $this->sendToLanPrinter(trim($host), (int) $port, $payload);
    }

    /**
     * @param  list<string>  $plainLines
     * @return array{0: string, 1: int}
     */
    public function sendPlainTextReceipt(string $host, int $port, array $plainLines, string $paperWidth = '80'): array
    {
        $segments = [[
            'type' => 'lines',
            'align' => 'left',
            'lines' => array_values($plainLines),
            'double_height_first' => false,
        ]];

        return $this->sendStructuredReceipt($host, $port, $segments, $paperWidth, 0);
    }

    /**
     * @return array{0: string, 1: int} host and port used
     */
    public function sendToLanPrinter(string $host, int $port, string $payload): array
    {
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
                $errstr !== '' ? "Koneksi gagal: {$errstr} ({$errno})" : "Tidak bisa terhubung ke {$host}:{$port}."
            );
        }

        stream_set_timeout($socket, 8);

        $written = @fwrite($socket, $payload);
        if ($written === false || $written < strlen($payload)) {
            fclose($socket);
            throw new RuntimeException('Gagal mengirim data ke printer (koneksi terputus atau buffer penuh).');
        }

        fclose($socket);

        return [$host, $port];
    }

    public function normalizePaperWidth(string $paperWidth): string
    {
        $w = trim($paperWidth);

        return in_array($w, ['58', '80'], true) ? $w : '80';
    }

    public function paperColumnWidth(string $paperWidth): int
    {
        $w = $this->normalizePaperWidth($paperWidth);

        return self::PAPER_COLUMNS[$w];
    }

    /**
     * @param  list<array{type: 'lines', align: string, lines: list<string>, double_height_first?: bool}|array{type: 'separator'}|array{type: 'spacer', count: int}>  $segments
     */
    public function buildReceiptFromSegments(array $segments, string $paper, int $marginChars): string
    {
        $cols = $this->paperColumnWidth($paper);
        $marginChars = max(0, min($cols - 4, $marginChars));
        $buf = "\x1B\x40";
        $globalFirstLine = true;

        foreach ($segments as $segment) {
            $type = $segment['type'] ?? '';

            if ($type === 'separator') {
                $buf .= "\x1Ba\x00";
                $dashLen = max(8, $cols - $marginChars);
                $buf .= $this->encodeLatin1Line(str_repeat(' ', $marginChars).str_repeat('-', $dashLen));
                $globalFirstLine = false;

                continue;
            }

            if ($type === 'spacer') {
                $n = max(0, min(10, (int) ($segment['count'] ?? 0)));
                for ($i = 0; $i < $n; $i++) {
                    $buf .= "\n";
                }

                continue;
            }

            if ($type !== 'lines') {
                continue;
            }

            $align = $this->normalizeAlign((string) ($segment['align'] ?? 'left'));
            $lines = $segment['lines'] ?? [];
            $doubleFirst = (bool) ($segment['double_height_first'] ?? false);

            $buf .= $this->escAlign($align);

            foreach ($lines as $i => $line) {
                $useDouble = $doubleFirst && $globalFirstLine && $i === 0;
                if ($useDouble) {
                    $buf .= "\x1B!\x10";
                }
                $formatted = $this->formatLineForColumn($line, $align, $cols, $marginChars);
                $buf .= $this->encodeLatin1Line($formatted);
                if ($useDouble) {
                    $buf .= "\x1B!\x00";
                }
                $globalFirstLine = false;
            }
        }

        $buf .= str_repeat("\n", self::TRAILING_BLANK_LINES_BEFORE_CUT);
        $buf .= "\x1D\x56\x01";

        return $buf;
    }

    /**
     * Baris pratinjau struk di browser (UTF-8), mengikuti lebar kolom & padding yang sama dengan {@see buildReceiptFromSegments}.
     *
     * @param  list<array{type: 'lines', align: string, lines: list<string>, double_height_first?: bool}|array{type: 'separator'}|array{type: 'spacer', count: int}>  $segments
     * @return array{cols: int, paper_mm: string, rows: list<array<string, mixed>>}
     */
    public function previewReceiptVisual(array $segments, string $paperWidth, int $marginChars): array
    {
        $paper = $this->normalizePaperWidth($paperWidth);
        $cols = $this->paperColumnWidth($paper);
        $marginChars = max(0, min($cols - 4, $marginChars));
        $rows = [];
        $globalFirstLine = true;

        foreach ($segments as $segment) {
            $type = $segment['type'] ?? '';

            if ($type === 'separator') {
                $dashLen = max(8, $cols - $marginChars);
                $latinRule = str_repeat(' ', $marginChars).str_repeat('-', $dashLen);
                $rows[] = [
                    'kind' => 'rule',
                    'text' => $this->latin1VisualToUtf8($latinRule),
                ];
                $globalFirstLine = false;

                continue;
            }

            if ($type === 'spacer') {
                $n = max(0, min(10, (int) ($segment['count'] ?? 0)));
                for ($i = 0; $i < $n; $i++) {
                    $rows[] = ['kind' => 'blank'];
                }

                continue;
            }

            if ($type !== 'lines') {
                continue;
            }

            $align = $this->normalizeAlign((string) ($segment['align'] ?? 'left'));
            $lines = $segment['lines'] ?? [];
            $doubleFirst = (bool) ($segment['double_height_first'] ?? false);

            foreach ($lines as $i => $line) {
                $useDouble = $doubleFirst && $globalFirstLine && $i === 0;
                $latinLine = $this->formatLineForColumn($line, $align, $cols, $marginChars);
                $rows[] = [
                    'kind' => 'text',
                    'text' => $this->latin1VisualToUtf8($latinLine),
                    'double' => $useDouble,
                ];
                $globalFirstLine = false;
            }
        }

        return [
            'cols' => $cols,
            'paper_mm' => $paper,
            'rows' => $rows,
        ];
    }

    private function latin1VisualToUtf8(string $latin): string
    {
        $utf = @iconv('ISO-8859-1', 'UTF-8//TRANSLIT', $latin);

        return $utf !== false ? $utf : $latin;
    }

    private function normalizeAlign(string $align): string
    {
        $a = strtolower(trim($align));

        return in_array($a, ['left', 'center', 'right'], true) ? $a : 'left';
    }

    private function escAlign(string $align): string
    {
        return match ($align) {
            'center' => "\x1Ba\x01",
            'right' => "\x1Ba\x02",
            default => "\x1Ba\x00",
        };
    }

    /**
     * Pad teks UTF-8 ke lebar kolom (byte Latin-1 setelah translit) dengan perataan di area cetak (setelah margin).
     */
    private function formatLineForColumn(string $utf8Line, string $align, int $cols, int $marginChars): string
    {
        $margin = str_repeat(' ', $marginChars);
        $budget = max(1, $cols - $marginChars);
        $latin = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $utf8Line) ?: $utf8Line;
        if (strlen($latin) > $budget) {
            $latin = substr($latin, 0, max(1, $budget - 1))."\x85";
        }
        $rest = $budget - strlen($latin);
        $chunk = match ($align) {
            'center' => str_repeat(' ', intdiv($rest, 2)).$latin.str_repeat(' ', $rest - intdiv($rest, 2)),
            'right' => str_repeat(' ', $rest).$latin,
            default => $latin.str_repeat(' ', $rest),
        };

        return $margin.$chunk;
    }

    /**
     * @param  list<string>  $lines
     */
    public function buildReceiptFromPlainLines(array $lines, string $paper): string
    {
        $segments = [[
            'type' => 'lines',
            'align' => 'left',
            'lines' => array_values($lines),
            'double_height_first' => false,
        ]];

        return $this->buildReceiptFromSegments($segments, $paper, 0);
    }

    private function buildTestPayload(string $titleLine, string $paper): string
    {
        $cols = $this->paperColumnWidth($paper);
        $now = now()->format('Y-m-d H:i:s');
        $labelPaper = $paper === '58' ? '58mm' : '80mm';
        $lines = [
            str_repeat('=', $cols),
            $titleLine,
            'PaymentSystemOCN',
            "Kertas: {$labelPaper}",
            $now,
            str_repeat('-', $cols),
            'OK — ESC/POS via TCP',
            '',
            '',
        ];

        $buf = "\x1B\x40";
        $buf .= "\x1Ba\x01";
        foreach ($lines as $line) {
            $buf .= $this->encodeLatin1Line($line);
        }
        $buf .= "\x1Ba\x00";
        $buf .= str_repeat("\n", self::TRAILING_BLANK_LINES_BEFORE_CUT);
        $buf .= "\x1D\x56\x01";

        return $buf;
    }

    private function encodeLatin1Line(string $line): string
    {
        $normalized = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $line) ?: $line;

        return $normalized."\n";
    }
}
