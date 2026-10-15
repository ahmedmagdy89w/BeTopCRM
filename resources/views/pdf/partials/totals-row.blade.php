{{-- Totals row with mirrored cell order for RTL: dompdf does not mirror table
     columns for direction:rtl, so Arabic totals render value-first in DOM
     (value left, label right) and label-first otherwise. --}}
<tr @if (! empty($total)) class="total" @endif>
    @if (! empty($rtl))
        <td>{{ $value }}</td>
        <td class="right">{{ $label }}</td>
    @else
        <td>{{ $label }}</td>
        <td class="right">{{ $value }}</td>
    @endif
</tr>
