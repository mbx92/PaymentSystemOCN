@php
    $label           = $config['label'] ?? 'Hormat kami,';
    $namePlaceholder = $config['name_placeholder'] ?? 'Finance';
@endphp
<table>
    <tr>
        <td style="width:58%;"></td>
        <td style="width:42%;">
            <div class="signature-block">
                {{ $label }}<br><br>
                @if ($namePlaceholder)
                    {{ $namePlaceholder }}<br>
                @endif
                {{ $brand['name'] }}
            </div>
        </td>
    </tr>
</table>
