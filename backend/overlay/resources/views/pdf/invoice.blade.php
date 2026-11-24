<!doctype html><html><head><meta charset="utf-8">@include('pdf._style')</head><body>
@include('pdf._header')
<div class="doc-title">SCHOOL FEES INVOICE</div>
<table style="margin-bottom:8px"><tr>
    <td class="box" style="width:55%"><div class="label">Bill to</div><strong>{{ $invoice->student->first_name }} {{ $invoice->student->last_name }}</strong><br>{{ $invoice->student->admission_no }} &middot; {{ $invoice->student->schoolClass->name ?? '' }}</td>
    <td style="width:2%"></td>
    <td class="box"><span class="muted">Invoice:</span> <strong>{{ $invoice->number }}</strong><br><span class="muted">Term:</span> {{ $invoice->term->name }}, {{ $invoice->term->academicSession->name }}<br>
        <span class="muted">Due:</span> {{ $invoice->due_date?->format('j M Y') }}<br><span class="badge {{ $invoice->status }}">{{ strtoupper($invoice->status) }}</span></td>
</tr></table>
<table class="grid">
    <thead><tr><th>Description</th><th>Category</th><th class="r">Amount</th></tr></thead>
    <tbody>@foreach($invoice->items as $i)<tr><td>{{ $i->description }}</td><td>{{ ucfirst($i->category ?? '') }}</td><td class="r">{{ number_format((float)$i->amount, 2) }}</td></tr>@endforeach</tbody>
    <tfoot>
        <tr><td colspan="2" class="r">Sub-total</td><td class="r">{{ number_format((float)$invoice->total, 2) }}</td></tr>
        <tr><td colspan="2" class="r">Discount / scholarship</td><td class="r">- {{ number_format((float)$invoice->discount, 2) }}</td></tr>
        <tr><td colspan="2" class="r">Paid to date</td><td class="r">- {{ number_format((float)$invoice->amount_paid, 2) }}</td></tr>
        <tr class="total"><td colspan="2" class="r">BALANCE DUE ({{ $school['currency'] ?? 'NGN' }})</td><td class="r">{{ number_format((float)$invoice->balance, 2) }}</td></tr>
    </tfoot>
</table>
@if($invoice->payments->count())
<h3 style="margin-top:12px">Payments received</h3>
<table class="grid"><thead><tr><th>Date</th><th>Receipt</th><th>Method</th><th class="r">Amount</th></tr></thead><tbody>
@foreach($invoice->payments as $p)<tr><td>{{ $p->paid_at?->format('j M Y') }}</td><td>{{ $p->receipt_no }}</td><td>{{ ucwords(str_replace('_',' ',$p->method)) }}</td><td class="r">{{ number_format((float)$p->amount, 2) }}</td></tr>@endforeach
</tbody></table>
@endif
</body></html>
