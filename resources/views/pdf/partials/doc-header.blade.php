@php
    $brand = $brand ?? ['name' => config('app.name', 'OCN ERP Suite'), 'tagline' => '', 'logo_data_uri' => null];
    $metaRows = $metaRows ?? [];
    $infoWidth = $infoWidth ?? '70mm';
@endphp
<table>
    <tr>
        <td style="padding-right: 7mm;">
            <table>
                <tr>
                    <td style="width: 60px;">
                        <div class="logo-box">
                            @if (!empty($brand['logo_data_uri']))
                                <img src="{{ $brand['logo_data_uri'] }}" alt="Logo">
                            @else
                                <div class="logo-fallback">LOGO</div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="page-title">{{ $docTitle }}</div>
                        <div class="page-subtitle">{{ $docSubtitle }}</div>
                        <div class="brand-name">{{ $brand['name'] }}</div>
                        @if (!empty($brandLines))
                            <div class="brand-meta">
                                @foreach ($brandLines as $line)
                                    {{ $line }}@if (!$loop->last)<br>@endif
                                @endforeach
                            </div>
                        @elseif (!empty($brand['tagline']))
                            <div class="brand-meta">{{ $brand['tagline'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: {{ $infoWidth }};">
            <div class="info-box">
                <div class="section-title">{{ $metaTitle ?? 'Informasi Dokumen' }}</div>
                <table class="meta-table">
                    @foreach ($metaRows as $label => $value)
                        <tr>
                            <td class="label">{{ $label }}</td>
                            <td class="value">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </td>
    </tr>
</table>
