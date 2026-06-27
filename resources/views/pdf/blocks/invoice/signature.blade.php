@php
    $label           = $config['label'] ?? 'Hormat kami,';
    $namePlaceholder = $config['name_placeholder'] ?? 'Finance';
@endphp
<table style="width:100%;margin-top:10px;border-collapse:collapse;">
    <tr>
        <td colspan="2" style="border-top:1px solid #cbd5e1;padding:0;line-height:0;font-size:0;">&nbsp;</td>
    </tr>
    <tr>
        <td style="width:58%;"></td>
        <td style="width:42%;padding-top:22px;text-align:center;font-size:9px;color:#4b5563;">
            {{ $label }}<br><br>
            @if ($namePlaceholder)
                {{ $namePlaceholder }}<br>
            @endif
            {{ $brand['name'] }}
        </td>
    </tr>
</table>
