<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Support\Audit;
use App\Support\Fmt;
use App\Support\IdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private InvoiceService $invoices,
        private NotificationService $notify,
        private PaymentGateway $gateway,
    ) {
    }

    public function newReference(): string
    {
        do {
            $ref = 'HGKA-'.strtoupper(Str::random(14));
        } while (Payment::where('reference', $ref)->exists());

        return $ref;
    }

    private function assertPayable(Invoice $invoice, float $amount): void
    {
        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages(['invoice_id' => 'This invoice has been cancelled.']);
        }
        if ((float) $invoice->balance <= 0) {
            throw ValidationException::withMessages(['invoice_id' => 'This invoice is already fully paid.']);
        }
        if ($amount <= 0 || $amount - (float) $invoice->balance > 0.009) {
            throw ValidationException::withMessages(['amount' => 'Amount must be between 0.01 and the outstanding balance of '.Fmt::money($invoice->balance).'.']);
        }
    }

    /**
     * Cash, bank transfer, POS or cheque captured by staff. Staff without approval rights create a payment that
     * stays "awaiting approval" and does not reduce the balance until a bursar/approver confirms it.
     */
    public function recordManual(Invoice $invoice, array $d, User $user): Payment
    {
        $amount = round((float) $d['amount'], 2);
        $this->assertPayable($invoice, $amount);

        $payment = Payment::create([
            'reference' => $this->newReference(), 'invoice_id' => $invoice->id, 'student_id' => $invoice->student_id,
            'method' => $d['method'], 'amount' => $amount, 'status' => 'awaiting_approval',
            'payer_name' => $d['payer_name'] ?? null, 'bank_name' => $d['bank_name'] ?? null, 'cheque_no' => $d['cheque_no'] ?? null,
            'notes' => $d['notes'] ?? null, 'paid_at' => $d['paid_on'] ?? now(), 'recorded_by' => $user->id,
        ]);
        Audit::log('payments.recorded', $payment, null, ['amount' => $amount, 'method' => $d['method'], 'invoice' => $invoice->number]);

        if ($user->hasPermission('fees.approve-payment')) {
            return $this->markSuccessful($payment, $user);
        }
        $this->notify->toRoles(['bursar'], 'Payment awaiting approval', Fmt::money($amount)." {$d['method']} payment for invoice {$invoice->number}.", 'payment');

        return $payment;
    }

    public function approve(Payment $payment, User $by): Payment
    {
        if ($payment->status !== 'awaiting_approval') {
            throw ValidationException::withMessages(['status' => 'Only payments awaiting approval can be approved.']);
        }

        $invoice = Invoice::findOrFail($payment->invoice_id);
        if (round((float) $payment->amount, 2) > round((float) $invoice->balance, 2)) {
            throw ValidationException::withMessages(['amount' => 'This payment exceeds the invoice balance ('.Fmt::money($invoice->balance).'). Reject it or correct the invoice first.']);
        }

        return $this->markSuccessful($payment, $by);
    }

    public function reject(Payment $payment, User $by, ?string $reason): Payment
    {
        if ($payment->status !== 'awaiting_approval') {
            throw ValidationException::withMessages(['status' => 'Only payments awaiting approval can be rejected.']);
        }
        $payment->update(['status' => 'rejected', 'approved_by' => $by->id, 'approved_at' => now(),
            'notes' => trim(($payment->notes ? $payment->notes."\n" : '').'Rejected: '.($reason ?? 'no reason given'))]);
        Audit::log('payments.rejected', $payment, null, ['reason' => $reason]);

        return $payment->fresh();
    }

    /**
     * Idempotently confirms a payment and updates its invoice. Safe to call repeatedly (webhook retries,
     * a customer returning to the callback page and a requery can all race).
     */
    public function markSuccessful(Payment $payment, ?User $by = null, array $gatewayData = []): Payment
    {
        $done = DB::transaction(function () use ($payment, $by, $gatewayData) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($p->status === 'successful') {
                return [$p, false];
            }
            $invoice = Invoice::whereKey($p->invoice_id)->lockForUpdate()->firstOrFail();

            $p->forceFill([
                'status' => 'successful', 'paid_at' => $p->paid_at ?? now(),
                'receipt_no' => IdGenerator::receiptNo((int) now()->format('Y')),
                'approved_by' => $by?->id ?? $p->approved_by, 'approved_at' => now(),
                'gateway_reference' => $gatewayData['gateway_reference'] ?? $p->gateway_reference,
                'gateway_response' => $gatewayData['raw'] ?? $p->gateway_response,
            ])->save();

            $this->invoices->recalculate($invoice);

            return [$p, true];
        });

        [$p, $changed] = $done;
        if ($changed) {
            Audit::log('payments.successful', $p, null, ['amount' => $p->amount, 'method' => $p->method], $by?->id);
            $student = Student::with('guardians')->find($p->student_id);
            if ($student) {
                $this->notify->toGuardians($student, 'Payment received',
                    'We received '.Fmt::money($p->amount)." for {$student->first_name}. Receipt no: {$p->receipt_no}.", 'payment',
                    ['app', 'email'], ['payment_id' => $p->id]);
            }
        }

        return $p;
    }

    public function markFailed(Payment $payment, array $gatewayData = []): Payment
    {
        if ($payment->status === 'pending') {
            $payment->update(['status' => 'failed', 'gateway_response' => $gatewayData['raw'] ?? $payment->gateway_response]);
            Audit::log('payments.failed', $payment);
        }

        return $payment->fresh();
    }

    /** Starts an online payment: records a pending payment and returns the provider's checkout URL. */
    public function startOnline(Invoice $invoice, float $amount, string $gateway, User $user): array
    {
        $amount = round($amount, 2);
        $this->assertPayable($invoice, $amount);
        if (! ($this->gateway->configured()[$gateway] ?? false)) {
            throw ValidationException::withMessages(['gateway' => ucfirst($gateway).' is not available right now.']);
        }

        $payment = Payment::create([
            'reference' => $this->newReference(), 'invoice_id' => $invoice->id, 'student_id' => $invoice->student_id,
            'method' => $gateway, 'amount' => $amount, 'status' => 'pending', 'payer_name' => $user->name,
            'payer_email' => $user->email, 'recorded_by' => $user->id,
        ]);

        $callback = rtrim(config('hgka.frontend_url'), '/').'/payment/callback';
        try {
            $url = $this->gateway->initialize($gateway, $payment, $invoice, $user->email, $user->name, $callback);
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed', 'notes' => mb_substr($e->getMessage(), 0, 500)]);
            throw ValidationException::withMessages(['gateway' => 'Could not start the payment: '.$e->getMessage()]);
        }
        Audit::log('payments.online_started', $payment, null, ['gateway' => $gateway, 'amount' => $amount]);

        return ['payment' => $payment, 'authorization_url' => $url, 'reference' => $payment->reference];
    }

    /**
     * Confirms an online payment with the provider's API. The amount and currency reported by the provider must
     * match what we asked for, otherwise the payment is flagged and never credited.
     */
    public function verifyOnline(Payment $payment): Payment
    {
        if (! in_array($payment->method, PaymentGateway::GATEWAYS, true) || $payment->status === 'successful') {
            return $payment;
        }

        try {
            $res = $this->gateway->verify($payment->method, $payment->reference);
        } catch (\Throwable $e) {
            Audit::log('payments.verify_error', $payment, null, ['error' => mb_substr($e->getMessage(), 0, 300)]);

            return $payment; // stays pending; the customer or a requery can try again
        }

        if ($res['status'] === 'success') {
            if (abs($res['amount'] - (float) $payment->amount) > 0.009 || strtoupper($res['currency']) !== 'NGN') {
                $payment->update([
                    'status' => 'failed', 'gateway_response' => $res['raw'],
                    'notes' => 'Amount/currency mismatch: provider reported '.$res['amount'].' '.$res['currency'].'.',
                ]);
                Audit::log('payments.amount_mismatch', $payment, null, ['provider_amount' => $res['amount'], 'expected' => $payment->amount]);

                return $payment->fresh();
            }

            return $this->markSuccessful($payment, null, $res);
        }

        if ($res['status'] === 'failed') {
            return $this->markFailed($payment, $res);
        }

        return $payment; // still pending
    }
}
