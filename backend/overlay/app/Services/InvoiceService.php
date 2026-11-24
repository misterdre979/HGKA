<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Models\HostelAllocation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Scholarship;
use App\Models\Student;
use App\Models\Term;
use App\Models\TransportAllocation;
use App\Support\IdGenerator;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Creates one invoice per active student for a term from the class fee structure, plus transport and
     * hostel charges where the student is allocated, minus active scholarships/discounts.
     *
     * @return array{created:int, skipped:int, reasons:array<string,int>}
     */
    public function generate(Term $term, ?int $classId, ?array $studentIds, ?string $dueDate, int $userId): array
    {
        $students = Student::where('status', 'active')
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->when($studentIds, fn ($q) => $q->whereIn('id', $studentIds))
            ->get();

        $structures = FeeStructure::where('term_id', $term->id)->where('is_optional', false)->get();
        $created = 0;
        $reasons = ['already_invoiced' => 0, 'no_fees_defined' => 0];

        foreach ($students as $student) {
            if (Invoice::where(['student_id' => $student->id, 'term_id' => $term->id])->exists()) {
                $reasons['already_invoiced']++;
                continue;
            }

            $items = [];
            foreach ($structures as $fee) {
                if ($fee->school_class_id === null || (int) $fee->school_class_id === (int) $student->school_class_id) {
                    $items[] = ['description' => $fee->name, 'category' => $fee->category, 'amount' => (float) $fee->amount];
                }
            }

            $transport = TransportAllocation::with('transportRoute')->where(['student_id' => $student->id, 'term_id' => $term->id, 'status' => 'active'])->first();
            if ($transport && (float) $transport->transportRoute->fee > 0) {
                $items[] = ['description' => 'Transport - '.$transport->transportRoute->name, 'category' => 'transport', 'amount' => (float) $transport->transportRoute->fee];
            }
            $bed = HostelAllocation::with('hostelRoom.hostel')->where(['student_id' => $student->id, 'status' => 'active'])->first();
            if ($bed && (float) $bed->hostelRoom->hostel->fee_per_term > 0) {
                $items[] = ['description' => 'Hostel - '.$bed->hostelRoom->hostel->name, 'category' => 'hostel', 'amount' => (float) $bed->hostelRoom->hostel->fee_per_term];
            }

            if (! $items) {
                $reasons['no_fees_defined']++;
                continue;
            }

            DB::transaction(function () use ($student, $term, $items, $dueDate, $userId) {
                $total = round(array_sum(array_column($items, 'amount')), 2);
                $discount = $this->discountFor($student, $term, $total);

                $invoice = Invoice::create([
                    'number' => IdGenerator::invoiceNo((int) $term->starts_on->format('Y')),
                    'student_id' => $student->id, 'term_id' => $term->id, 'total' => $total, 'discount' => $discount,
                    'amount_paid' => 0, 'balance' => round($total - $discount, 2), 'status' => 'unpaid',
                    'due_date' => $dueDate ?? $term->starts_on->copy()->addDays(14)->toDateString(), 'created_by' => $userId,
                ]);
                foreach ($items as $item) {
                    InvoiceItem::create($item + ['invoice_id' => $invoice->id]);
                }
            });
            $created++;
        }

        return ['created' => $created, 'skipped' => array_sum($reasons), 'reasons' => $reasons];
    }

    public function discountFor(Student $student, Term $term, float $total): float
    {
        $discount = 0.0;
        $active = Scholarship::where('student_id', $student->id)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('term_id')->orWhere('term_id', $term->id))->get();

        foreach ($active as $s) {
            $discount += $s->type === 'percent' ? $total * ((float) $s->value) / 100 : (float) $s->value;
        }

        return round(min($discount, $total), 2);
    }

    /** Recomputes total, amount paid, balance and status from items and successful payments. */
    public function recalculate(Invoice $invoice): Invoice
    {
        $total = round((float) $invoice->items()->sum('amount'), 2);
        $paid = round((float) $invoice->payments()->where('status', 'successful')->sum('amount'), 2);
        $discount = min((float) $invoice->discount, $total);
        $balance = round(max(0, $total - $discount - $paid), 2);

        $status = $invoice->status === 'cancelled' ? 'cancelled'
            : ($paid <= 0 ? 'unpaid' : ($balance > 0 ? 'partial' : 'paid'));

        $invoice->update(['total' => $total, 'discount' => $discount, 'amount_paid' => $paid, 'balance' => $balance, 'status' => $status]);

        return $invoice->fresh();
    }
}
