@php
    $rows = $rows ?? [];
@endphp
<table class="mt-md">
    <tr>
        <td></td>
        <td style="width: 84mm;">
            <table class="summary-box">
                @foreach ($rows as $row)
                    <tr>
                        <td class="label">{{ $row[0] }}</td>
                        <td class="value">{{ $row[1] }}</td>
                    </tr>
                @endforeach
                <tr class="summary-total">
                    <td>{{ $totalLabel ?? 'Total' }}</td>
                    <td class="text-right">{{ $totalValue }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
