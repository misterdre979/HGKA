<!doctype html><html><head><meta charset="utf-8">@include('pdf._style')</head><body>
@include('pdf._header')
<div class="doc-title">PAYSLIP &mdash; {{ strtoupper(date('F Y', mktime(0,0,0,$payslip->payrollRun->period_month,1,$payslip->payrollRun->period_year))) }}</div>
<table class="box" style="margin-bottom:8px"><tr>
    <td><span class="label">Employee</span><br><strong>{{ $payslip->staff->first_name }} {{ $payslip->staff->last_name }}</strong><br>{{ $payslip->staff->staff_no }}</td>
    <td><span class="label">Department / Designation</span><br>{{ $payslip->staff->department->name ?? '—' }} / {{ $payslip->staff->designation->name ?? '—' }}</td>
    <td><span class="label">Bank</span><br>{{ $payslip->staff->bank_name ?? '—' }}<br>{{ $payslip->staff->account_number ? '****'.substr($payslip->staff->account_number, -4) : '' }}</td>
</tr></table>
<table><tr>
<td style="width:49%;vertical-align:top"><table class="grid"><thead><tr><th>Earnings</th><th class="r">Amount</th></tr></thead><tbody>
    <tr><td>Basic salary</td><td class="r">{{ number_format((float)$payslip->basic_salary, 2) }}</td></tr>
    @foreach(collect($payslip->components)->where('type','allowance') as $c)<tr><td>{{ $c['name'] }}</td><td class="r">{{ number_format((float)$c['amount'], 2) }}</td></tr>@endforeach
    <tr class="total"><td>Gross pay</td><td class="r">{{ number_format((float)$payslip->gross_pay, 2) }}</td></tr></tbody></table></td>
<td style="width:2%"></td>
<td style="width:49%;vertical-align:top"><table class="grid"><thead><tr><th>Deductions</th><th class="r">Amount</th></tr></thead><tbody>
    @forelse(collect($payslip->components)->where('type','deduction') as $c)<tr><td>{{ $c['name'] }}</td><td class="r">{{ number_format((float)$c['amount'], 2) }}</td></tr>@empty<tr><td colspan="2" class="muted">None</td></tr>@endforelse
    <tr class="total"><td>Total deductions</td><td class="r">{{ number_format((float)$payslip->total_deductions, 2) }}</td></tr></tbody></table></td>
</tr></table>
<div class="doc-title" style="margin-top:12px">NET PAY: {{ $school['currency_symbol'] ?? '₦' }}{{ number_format((float)$payslip->net_pay, 2) }}</div>
<p class="muted">Status: {{ strtoupper($payslip->status) }} @if($payslip->payment_reference)&middot; Ref {{ $payslip->payment_reference }}@endif</p>
</body></html>
