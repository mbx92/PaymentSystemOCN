@php
    $accentColor = $config['accent_color'] ?? '#1E3A5F';
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
                            <div style="width:52px;height:52px;border:1px solid #dbe4ee;background:#f8fafc;text-align:center;">
                                @if (!empty($brand['logo_data_uri']))
                                    <img src="{{ $brand['logo_data_uri'] }}" alt="Logo" style="width:40px;height:40px;margin-top:5px;">
                                @else
                                    <div style="line-height:52px;font-size:12px;color:{{ $accentColor }};font-weight:700;">LOGO</div>
                                @endif
                            </div>
                        </td>
                    @endif
                    <td>
                        <div style="font-size:24px;font-weight:700;letter-spacing:3px;color:{{ $accentColor }};margin:0 0 2px;">{{ $title }}</div>
                        <div style="font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:6px;">{{ $subtitle }}</div>
                        <div style="font-size:15px;font-weight:700;color:{{ $accentColor }};">{{ $brand['name'] }}</div>
                        @if ($showTagline)
                            <div style="font-size:9px;color:#4b5563;">{{ $brand['tagline'] }}@if(!empty($project)) <br>Project: {{ $project->name }}@endif</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 70mm;">
            <div style="border:1px solid #dbe4ee;background:#f8fafc;padding:9px 11px;">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:{{ $accentColor }};padding-bottom:4px;">{{ $docMetaTitle ?? 'Informasi Dokumen' }}</div>
                <table style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:2px 0;font-size:9px;color:#6b7280;width:45%;">{{ $docNumberLabel ?? 'Nomor' }}</td><td style="text-align:right;font-size:9px;font-weight:700;">{{ $invoice['number'] ?? '-' }}</td></tr>
                    <tr><td style="padding:2px 0;font-size:9px;color:#6b7280;">Tanggal</td><td style="text-align:right;font-size:9px;font-weight:700;">{{ $generatedAt->locale('id')->translatedFormat('d F Y') }}</td></tr>
                    @if(!empty($invoice['status']))
                        <tr><td style="padding:2px 0;font-size:9px;color:#6b7280;">Status</td><td style="text-align:right;font-size:9px;font-weight:700;">{{ strtoupper($invoice['status']) }}</td></tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>
