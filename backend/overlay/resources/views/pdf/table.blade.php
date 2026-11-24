<!doctype html><html><head><meta charset="utf-8">@include('pdf._style')</head><body>
@include('pdf._header')
<div class="doc-title">{{ strtoupper($title) }}</div>
@if(!empty($filters))<p class="muted">{{ $filters }}</p>@endif
<table class="grid">
    <thead><tr>@foreach($headings as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach($rows as $row)
        <tr @if(is_string($row[0] ?? null) && str_starts_with($row[0], 'TOTAL')) class="total" @endif>
        @foreach($row as $cell)<td @if(is_float($cell) || is_int($cell)) class="r" @endif>{{ is_float($cell) ? number_format($cell, 2) : $cell }}</td>@endforeach
        </tr>
    @endforeach
    </tbody>
</table>
<p class="muted" style="margin-top:10px">Generated {{ now()->format('j M Y, g:i a') }}</p>
</body></html>
