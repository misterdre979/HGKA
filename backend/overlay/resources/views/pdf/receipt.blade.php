<!doctype html><html><head><meta charset="utf-8">@include('pdf._style')</head><body>
@include('pdf._header')
<div class="doc-title">OFFICIAL PAYMENT RECEIPT</div>
<table style="margin-bottom:8px"><tr>
    <td><span class="label">Receipt no</span><br><strong style="font-size:12px">{{ $payment->receipt_no }}</strong></td>
    <td class="r"><span class="label">Date</span><br><strong>{{ ($payment->paid_at ?? now())->format('j M Y, g:i a') }}</strong></td>
</tr></table>
<table class="grid">
    <tr><td style="width:35%" class="muted">Received from</td><td>{{ $payment->payer_name ?: 'Parent/Guardian' }}</td></tr>
    <tr><td class="muted">Student</td><td>{{ $payment->student->first_name }} {{ $payment->student->last_name }} ({{ $payment->student->admission_no }})</td></tr>
    <tr><td class="muted">Class</td><td>{{ $payment->student->schoolClass->name ?? '' }}</td></tr>
    <tr><td class="muted">Invoice</td><td>{{ $payment->invoice->number }} &mdash; {{ $payment->invoice->term->name ?? '' }}, {{ $payment->invoice->term->academicSession->name ?? '' }}</td></tr>
    <tr><td class="muted">Payment method</td><td>{{ ucwords(str_replace('_', ' ', $payment->method)) }} @if($payment->cheque_no)(Cheque {{ $payment->cheque_no }})@endif @if($payment->bank_name)&middot; {{ $payment->bank_name }}@endif</td></tr>
    <tr><td class="muted">Reference</td><td>{{ $payment->reference }}</td></tr>
    <tr class="total"><td>Amount paid</td><td style="font-size:14px">{{ $school['currency_symbol'] ?? '₦' }}{{ number_format((float)$payment->amount, 2) }}</td></tr>
    <tr><td class="muted">Invoice total</td><td>{{ $school['currency_symbol'] ?? '₦' }}{{ number_format((float)$payment->invoice->total - (float)$payment->invoice->discount, 2) }}</td></tr>
    <tr><td class="muted">Balance outstanding</td><td><strong>{{ $school['currency_symbol'] ?? '₦' }}{{ number_format((float)$payment->invoice->balance, 2) }}</strong></td></tr>
</table>
<p class="muted" style="margin-top:14px">This receipt was generated electronically{{ $payment->approvedBy ? ' and approved by '.$payment->approvedBy->name : '' }}. Fees paid are non-refundable.</p>
</body></html>
