<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $docTitle ?? 'Dokumen' }} {{ $invoice['number'] ?? '' }}</title>
    @include('pdf.partials.styles')
</head>
<body>
@php
    $rupiah   = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.');
    $brand    = $brand ?? ['name' => config('app.name', 'OCN ERP Suite'), 'tagline' => '', 'logo_data_uri' => null];
    $settings = $templateSettings ?? [];
    $blocks   = $templateBlocks ?? [];
    $accentDefault = $settings['accent_color'] ?? config('pdf.theme.primary', '#1d4ed8');
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
