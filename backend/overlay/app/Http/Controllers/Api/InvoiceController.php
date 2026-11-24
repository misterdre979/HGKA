<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Term;
use App\Services\InvoiceService;
use App\Support\Audit;
use App\Support\PdfDoc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $q = Invoice::with(['student:id,admission_no,first_name,last_name,school_class_id', 'student.schoolClass:id,name', 'term:id,name'])->orderByDesc('id');

        foreach (['student_id', 'term_id', 'status'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($request->filled('school_class_id')) {
            $q->whereHas('student', fn ($s) => $s->where('school_class_id', $request->query('school_class_id')));
        }
        if ($request->boolean('outstanding')) {
            $q->where('balance', '>', 0)->where('status', '!=', 'cancelled');
        }
        if (($s = trim((string) $request->query('search', ''))) !== '') {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhereHas('student', fn ($st) => $st
                ->where('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")->orWhere('admission_no', 'like', "%$s%")));
        }

        return response()->json($q->paginate(min((int) $request->query('per_page', 15), 100)));
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeView($request, $invoice);

        return response()->json($invoice->load(['items', 'payments' => fn ($q) => $q->orderByDesc('id'), 'student.schoolClass', 'term']));
    }

    public function generate(Request $request): JsonResponse
    {
        $d = $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        $result = $this->invoices->generate(Term::findOrFail($d['term_id']), $d['school_class_id'] ?? null, $d['student_ids'] ?? null, $d['due_date'] ?? null, $request->user()->id);
        Audit::log('invoices.generated', null, null, $d + $result);

        return response()->json($result);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if($invoice->status === 'cancelled', 422, 'A cancelled invoice cannot be edited.');
        $d = $request->validate(['discount' => ['sometimes', 'numeric', 'min:0'], 'due_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $invoice->update($d);
        Audit::log('invoices.updated', $invoice, null, $d);

        return response()->json($this->invoices->recalculate($invoice)->load('items'));
    }

    public function addItem(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if($invoice->status === 'cancelled', 422, 'A cancelled invoice cannot be edited.');
        $d = $request->validate(['description' => ['required', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:40'], 'amount' => ['required', 'numeric', 'min:0.01']]);
        InvoiceItem::create($d + ['invoice_id' => $invoice->id]);
        Audit::log('invoices.item_added', $invoice, null, $d);

        return response()->json($this->invoices->recalculate($invoice)->load('items'), 201);
    }

    public function removeItem(Invoice $invoice, InvoiceItem $item): JsonResponse
    {
        abort_unless($item->invoice_id === $invoice->id, 404);
        abort_if($invoice->status === 'cancelled', 422, 'A cancelled invoice cannot be edited.');
        abort_if($invoice->items()->count() <= 1, 422, 'An invoice must keep at least one item. Cancel the invoice instead.');
        $item->delete();
        Audit::log('invoices.item_removed', $invoice);

        return response()->json($this->invoices->recalculate($invoice)->load('items'));
    }

    public function cancel(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if($invoice->payments()->whereIn('status', ['successful', 'awaiting_approval', 'pending'])->exists(), 422,
            'This invoice has payments and cannot be cancelled.');
        $invoice->update(['status' => 'cancelled']);
        Audit::log('invoices.cancelled', $invoice);

        return response()->json($invoice);
    }

    public function pdf(Request $request, Invoice $invoice)
    {
        $this->authorizeView($request, $invoice);
        $invoice->load(['items', 'payments' => fn ($q) => $q->where('status', 'successful'), 'student.schoolClass', 'term.academicSession']);

        return PdfDoc::download('pdf.invoice', ['invoice' => $invoice], 'invoice-'.$invoice->number.'.pdf');
    }

    private function authorizeView(Request $request, Invoice $invoice): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermission('fees.view') || in_array($invoice->student_id, PortalController::studentIdsFor($user), true),
            403,
            'You do not have permission to perform this action.'
        );
    }
}
