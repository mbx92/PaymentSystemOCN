@php
    $accentColor = $config['accent_color'] ?? config('pdf.theme.primary', '#1d4ed8');
    $showLogo    = $config['show_logo'] ?? true;
    $showTagline = $config['show_tagline'] ?? true;
    $title       = $config['title'] ?? ($docTitle ?? 'INVOICE');
    $subtitle    = $config['subtitle'] ?? ($docSubtitle ?? 'Dokumen Tagihan');
@endphp
<table>
    <tr>
        <td style="padding-right: 7mm;">
            <table>
                <tr>
                    @if ($showLogo)
                        <td style="width: 58px;">
                            <div class="logo-box">
                                @if (!empty($brand['logo_data_uri']))
                                    <img src="{{ $brand['logo_data_uri'] }}" alt="Logo">
                                @else
                                    <div class="logo-fallback" style="color: {{ $accentColor }};">LOGO</div>
                                @endif
                            </div>
                        </td>
                    @endif
                    <td>
                        <div class="page-title" style="color: {{ $accentColor }};">{{ $title }}</div>
                        <div class="page-subtitle">{{ $subtitle }}</div>
                        <div class="brand-name" style="color: {{ $accentColor }};">{{ $brand['name'] }}</div>
                        @if ($showTagline)
                            <div class="brand-meta">{{ $brand['tagline'] }}@if(!empty($project)) <br>Project: {{ $project->name }}@endif</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 70mm;">
            <div class="info-box">
                <div class="section-title" style="color: {{ $accentColor }};">{{ $docMetaTitle ?? 'Informasi Dokumen' }}</div>
                <table class="meta-table">
                    <tr><td class="label">{{ $docNumberLabel ?? 'Nomor' }}</td><td class="value">{{ $invoice['number'] ?? '-' }}</td></tr>
                    <tr><td class="label">Tanggal</td><td class="value">{{ $generatedAt->locale('id')->translatedFormat('d F Y') }}</td></tr>
                    @if(!empty($invoice['status']))
                        <tr><td class="label">Status</td><td class="value">{{ strtoupper($invoice['status']) }}</td></tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>
