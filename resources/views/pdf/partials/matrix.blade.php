<table>
    <tr><th>Wilayah</th>@foreach ($columns as $c)<th class="num">{{ $c }}</th>@endforeach<th class="num">Total</th></tr>
    @foreach ($matrix as $r)
        <tr><td>{{ $r['wilayah'] }}</td>@foreach ($columns as $c)<td class="num">{{ $r[$c] ?: '' }}</td>@endforeach<td class="num"><b>{{ $r['Total'] }}</b></td></tr>
    @endforeach
    <tr><th>Total</th>@foreach ($columns as $c)<th class="num">{{ $matrix->sum($c) }}</th>@endforeach<th class="num">{{ $matrix->sum('Total') }}</th></tr>
</table>
