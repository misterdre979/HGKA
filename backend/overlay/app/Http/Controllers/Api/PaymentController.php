<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentGateway;
use App\Services\PaymentService;
use App\Support\PdfDoc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments, private PaymentGateway $gateways)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $q = Payment::with(['student:id,admission_no,first_name,last_name', 'invoice:id,number', 'recordedBy:id,name'])->orderByDesc('id');

        foreach (['status', 'method', 'student_id', 'invoice_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->query('to'));
        }
        if (($s = trim((string) $request->query('search', ''))) !== '') {
            $q->where(fn ($w) => $w->where('reference', 'like', "%$s%")->orWhere('receipt_no', 'like', "%$s%")->orWhere('payer_name', 'like', "%$s%"));
        }

        return response()->json($q->paginate(min((int) $request->query('per_page', 15), 100)));
    }

    /** Which online gateways are configured (never exposes keys). */
    public function gateways(): JsonResponse
    {
        return response()->json($this->gateways->configured());
    }

    public function store(Request $request): JsonResponse
    {
        $d = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'pos', 'cheque'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'cheque_no' => ['nullable', 'string', 'max:255', 'required_if:method,cheque'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payment = $this->payments->recordManual(Invoice::findOrFail($d['invoice_id']), $d, $request->user());

        return response()->json($payment->load(['student:id,first_name,last_name', 'invoice:id,number']), 201);
    }

    public function approve(Request $request, Payment $payment): JsonResponse
    {
        return response()->json($this->payments->approve($payment, $request->user()));
    }

    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $d = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return response()->json($this->payments->reject($payment, $request->user(), $d['reason'] ?? null));
    }

    /** Parent/student (or authorised staff) starts an online payment for an invoice. */
    public function initialize(Request $request): JsonResponse
    {
        $d = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'gateway' => ['required', Rule::in(PaymentGateway::GATEWAYS)],
        ]);
        $invoice = Invoice::findOrFail($d['invoice_id']);
        $this->authorizeInvoice($request->user(), $invoice);

        $out = $this->payments->startOnline($invoice, (float) ($d['amount'] ?? $invoice->balance), $d['gateway'], $request->user());

        return response()->json(['authorization_url' => $out['authorization_url'], 'reference' => $out['reference']], 201);
    }

    /**
     * Called by the callback page after the customer returns from the provider. The outcome comes from the
     * provider's server-side API, not from anything the browser reports.
     */
    public function verify(Request $request, string $reference): JsonResponse
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();
        $this->authorizePayment($request->user(), $payment);

        $payment = $this->payments->verifyOnline($payment);

        return response()->json($payment->only(['id', 'reference', 'status', 'amount', 'method', 'receipt_no', 'paid_at', 'invoice_id']));
    }

    /** Staff re-check of a pending online payment (e.g. webhook missed). */
    public function requery(Payment $payment): JsonResponse
    {
        abort_unless(in_array($payment->method, PaymentGateway::GATEWAYS, true), 422, 'Only online payments can be re-checked.');

        return response()->json($this->payments->verifyOnline($payment));
    }

    public function receipt(Request $request, Payment $payment)
    {
        $this->authorizePayment($request->user(), $payment);
        abort_unless($payment->status === 'successful', 422, 'A receipt is only available for successful payments.');
        $payment->load(['invoice.items', 'student.schoolClass', 'invoice.term.academicSession', 'approvedBy:id,name']);

        return PdfDoc::download('pdf.receipt', ['payment' => $payment], 'receipt-'.$payment->receipt_no.'.pdf', 'a5', 'portrait');
    }

    private function authorizeInvoice(User $user, Invoice $invoice): void
    {
        abort_unless(
            $user->hasPermission('fees.record-payment') || in_array($invoice->student_id, PortalController::studentIdsFor($user), true),
            403,
            'You do not have permission to perform this action.'
        );
    }

    private function authorizePayment(User $user, Payment $payment): void
    {
        abort_unless(
            $user->hasPermission('fees.view') || in_array($payment->student_id, PortalController::studentIdsFor($user), true),
            403,
            'You do not have permission to perform this action.'
        );
    }
}
