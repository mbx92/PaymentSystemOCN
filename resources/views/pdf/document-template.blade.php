<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $docTitle ?? 'Dokumen' }} {{ $invoice['number'] ?? '' }}</title>
    <style>
        @page { size: A4; margin: 15mm 15mm 15mm 15mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0; width: 100%;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px; color: #243447; background: #ffffff; line-height: 1.3;
        }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .doc-section {
            margin-top: 10px;
            padding: 10px 11px;
            background: #f8fafc;
            border: 1px solid #dbe4ee;
        }
        .doc-section-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-bottom: 6px;
            margin-bottom: 2px;
        }
        .doc-section .inner-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #dbe4ee;
            background: #ffffff;
        }
    </style>
</head>
<body>
@php
    $rupiah   = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
    $brand    = $brand ?? ['name' => config('app.name', 'OCN ERP Suite'), 'tagline' => '', 'logo_data_uri' => null];
    $settings = $templateSettings ?? [];
    $blocks   = $templateBlocks ?? [];
    $accentDefault = $settings['accent_color'] ?? '#1E3A5F';
    $unwrapTypes = ['header', 'footer', 'signature', 'notes', 'payment_terms'];
@endphp

@foreach ($blocks as $block)
    @if (!($block['enabled'] ?? true)) @continue @endif
    @php
        $config = $block['config'] ?? [];
        $blockAccent = $config['accent_color'] ?? $accentDefault;
    @endphp
    @if (in_array($block['type'], $unwrapTypes, true))
        @include('pdf.blocks.' . $docType . '.' . $block['type'])
    @else
        <div class="doc-section">
            @include('pdf.blocks.' . $docType . '.' . $block['type'])
        </div>
    @endif
@endforeach
</body>
</html>
