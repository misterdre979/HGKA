<?php

namespace App\Http\Controllers\Api;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\ClassArm;
use App\Models\Expense;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payslip;
use App\Models\ResultSummary;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Term;
use App\Models\TransportAllocation;
use App\Models\TransportRoute;
use App\Support\Audit;
use App\Support\PdfDoc;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Downloadable reports. Each report returns headings + rows and can be viewed as JSON, exported to PDF or Excel.
 */
class ReportController extends Controller
{
    private const MONEY = 2;

    private function catalog(): array
    {
        $term = ['name' => 'term_id', 'label' => 'Term', 'type' => 'fk', 'ref' => 'terms'];
        $class = ['name' => 'school_class_id', 'label' => 'Class', 'type' => 'fk', 'ref' => 'classes'];
        $arm = ['name' => 'class_arm_id', 'label' => 'Class arm', 'type' => 'fk', 'ref' => 'class-arms'];
        $from = ['name' => 'from', 'label' => 'From', 'type' => 'date', 'required' => true];
        $to = ['name' => 'to', 'label' => 'To', 'type' => 'date', 'required' => true];

        return [
            'enrolment' => ['title' => 'Student enrolment', 'perm' => ['students.view'], 'filters' => [
                ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'vals' => ['active', 'graduated', 'transferred', 'withdrawn', 'suspended']]]],
            'student-attendance' => ['title' => 'Student attendance', 'perm' => ['attendance.report', 'attendance.view'], 'filters' => [$from, $to, $class]],
            'staff-attendance' => ['title' => 'Staff attendance', 'perm' => ['attendance.staff'], 'filters' => [$from, $to]],
            'academic-performance' => ['title' => 'Academic performance by class', 'perm' => ['exams.view'], 'filters' => [$term + ['required' => true]]],
            'exam-results' => ['title' => 'Examination results (class)', 'perm' => ['exams.view'], 'filters' => [$term + ['required' => true], $arm + ['required' => true]]],
            'school-fees' => ['title' => 'School fees (invoices)', 'perm' => ['fees.view'], 'filters' => [$term, $class,
                ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'vals' => ['unpaid', 'partial', 'paid', 'cancelled']]]],
            'payment-history' => ['title' => 'Payment history', 'perm' => ['fees.view'], 'filters' => [$from, $to,
                ['name' => 'method', 'label' => 'Method', 'type' => 'enum', 'vals' => ['cash', 'bank_transfer', 'pos', 'cheque', 'paystack', 'flutterwave']]]],
            'outstanding-balances' => ['title' => 'Outstanding balances', 'perm' => ['fees.view'], 'filters' => [$term, $class]],
            'finance-summary' => ['title' => 'Revenue and expenses', 'perm' => ['finance.reports'], 'filters' => [$from, $to,
                ['name' => 'period', 'label' => 'Group by', 'type' => 'enum', 'vals' => ['daily', 'weekly', 'monthly', 'annual']]]],
            'payroll' => ['title' => 'Payroll', 'perm' => ['payroll.view'], 'filters' => [
                ['name' => 'year', 'label' => 'Year', 'type' => 'number', 'required' => true],
                ['name' => 'month', 'label' => 'Month (1-12)', 'type' => 'number', 'required' => true]]],
            'inventory' => ['title' => 'Inventory & stock', 'perm' => ['inventory.view'], 'filters' => []],
            'library' => ['title' => 'Library activity', 'perm' => ['library.view'], 'filters' => []],
            'transport' => ['title' => 'Transport operations', 'perm' => ['transport.view'], 'filters' => [$term]],
            'hostel' => ['title' => 'Hostel occupancy', 'perm' => ['hostel.view'], 'filters' => []],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $out = [];
        foreach ($this->catalog() as $key => $def) {
            if ($user->hasAnyPermission($def['perm'])) {
                $out[] = ['key' => $key, 'title' => $def['title'], 'filters' => $def['filters']];
            }
        }

        return response()->json($out);
    }

    public function run(Request $request, string $key)
    {
        $def = $this->catalog()[$key] ?? null;
        abort_unless($def, 404, 'Unknown report.');
        $user = $request->user();
        abort_unless($user->hasAnyPermission($def['perm']), 403, 'You do not have permission to perform this action.');

        $f = $request->query();
        foreach ($def['filters'] as $filter) {
            if (! empty($filter['required']) && empty($f[$filter['name']])) {
                abort(422, "Please choose: {$filter['label']}.");
            }
        }

        $format = (string) $request->query('format', 'json');
        if (in_array($format, ['pdf', 'xlsx'], true)) {
            abort_unless($user->hasPermission('reports.export'), 403, 'You do not have permission to export reports.');
        }

        [$headings, $rows] = $this->{'r'.Str::studly($key)}($f);
        $slug = $key.'-'.now()->format('Ymd-His');
        Audit::log('reports.run', null, null, ['report' => $key, 'format' => $format]);

        if ($format === 'pdf') {
            return PdfDoc::download('pdf.table', [
                'title' => $def['title'], 'headings' => $headings, 'rows' => $rows, 'filters' => $this->describeFilters($def, $f),
            ], $slug.'.pdf', 'a4', 'landscape');
        }
        if ($format === 'xlsx') {
            return Excel::download(new ArrayExport($headings, $rows, $def['title']), $slug.'.xlsx');
        }

        return response()->json(['key' => $key, 'title' => $def['title'], 'headings' => $headings, 'rows' => $rows, 'generated_at' => now()->toIso8601String()]);
    }

    private function describeFilters(array $def, array $f): string
    {
        $parts = [];
        foreach ($def['filters'] as $filter) {
            if (! empty($f[$filter['name']])) {
                $parts[] = $filter['label'].': '.$f[$filter['name']];
            }
        }

        return implode('  |  ', $parts);
    }

    private function name($s): string
    {
        return $s ? trim($s->first_name.' '.$s->last_name) : '';
    }

    private function money($n): float
    {
        return round((float) $n, self::MONEY);
    }

    // ------------------------------------------------------------------ reports
    private function rEnrolment(array $f): array
    {
        $students = Student::with(['schoolClass:id,name,sequence', 'classArm:id,name'])->where('status', $f['status'] ?? 'active')->get();
        $rows = [];
        foreach ($students->groupBy(fn ($s) => $s->school_class_id.'|'.($s->class_arm_id ?? 0))->sortBy(fn ($g) => $g->first()->schoolClass->sequence) as $group) {
            $first = $group->first();
            $rows[] = [$first->schoolClass->name, $first->classArm->name ?? '-', $group->where('gender', 'male')->count(), $group->where('gender', 'female')->count(), $group->count()];
        }
        $rows[] = ['TOTAL', '', $students->where('gender', 'male')->count(), $students->where('gender', 'female')->count(), $students->count()];

        return [['Class', 'Arm', 'Male', 'Female', 'Total'], $rows];
    }

    private function rStudentAttendance(array $f): array
    {
        $counts = StudentAttendance::whereBetween('date', [$f['from'], $f['to']])
            ->when($f['school_class_id'] ?? null, fn ($q, $c) => $q->where('school_class_id', $c))
            ->selectRaw('class_arm_id, status, count(*) as n')->groupBy('class_arm_id', 'status')->get()->groupBy('class_arm_id');

        $rows = [];
        foreach (ClassArm::with('schoolClass:id,name,sequence')->whereIn('id', $counts->keys())->get()->sortBy(fn ($a) => $a->schoolClass->sequence) as $arm) {
            $c = $counts[$arm->id]->pluck('n', 'status');
            $total = (int) $c->sum();
            $rows[] = [$arm->schoolClass->name, $arm->name, $total, (int) ($c['present'] ?? 0), (int) ($c['absent'] ?? 0), (int) ($c['late'] ?? 0), (int) ($c['excused'] ?? 0),
                $total ? round((($c['present'] ?? 0) + ($c['late'] ?? 0) + ($c['excused'] ?? 0)) / $total * 100, 1).'%' : '-'];
        }

        return [['Class', 'Arm', 'Marks', 'Present', 'Absent', 'Late', 'Excused', 'Attendance rate'], $rows];
    }

    private function rStaffAttendance(array $f): array
    {
        $counts = StaffAttendance::whereBetween('date', [$f['from'], $f['to']])->selectRaw('staff_id, status, count(*) as n')->groupBy('staff_id', 'status')->get()->groupBy('staff_id');
        $rows = [];
        foreach (Staff::orderBy('first_name')->get() as $s) {
            $c = ($counts[$s->id] ?? collect())->pluck('n', 'status');
            $total = (int) $c->sum();
            $rows[] = [$s->staff_no, $this->name($s), (int) ($c['present'] ?? 0), (int) ($c['absent'] ?? 0), (int) ($c['late'] ?? 0), (int) ($c['excused'] ?? 0),
                $total ? round((($c['present'] ?? 0) + ($c['late'] ?? 0) + ($c['excused'] ?? 0)) / $total * 100, 1).'%' : '-'];
        }

        return [['Staff no', 'Name', 'Present', 'Absent', 'Late', 'Excused', 'Attendance rate'], $rows];
    }

    private function rAcademicPerformance(array $f): array
    {
        $rows = [];
        $sums = ResultSummary::with(['classArm:id,name,school_class_id', 'classArm.schoolClass:id,name,sequence'])->where(['term_id' => $f['term_id'], 'status' => 'approved'])->get();
        foreach ($sums->groupBy('class_arm_id')->sortBy(fn ($g) => $g->first()->classArm->schoolClass->sequence ?? 0) as $group) {
            $arm = $group->first()->classArm;
            $avgs = $group->map(fn ($r) => (float) $r->average);
            $rows[] = [$arm->schoolClass->name ?? '', $arm->name, $group->count(), round($avgs->avg(), 1), round($avgs->max(), 1), round($avgs->min(), 1),
                round($avgs->filter(fn ($a) => $a >= 50)->count() / max(1, $group->count()) * 100, 1).'%'];
        }

        return [['Class', 'Arm', 'Students', 'Average', 'Highest', 'Lowest', 'Pass rate (avg >= 50)'], $rows];
    }

    private function rExamResults(array $f): array
    {
        $rows = [];
        foreach (ResultSummary::with('student:id,admission_no,first_name,last_name')->where(['term_id' => $f['term_id'], 'class_arm_id' => $f['class_arm_id']])->orderBy('position')->get() as $r) {
            $rows[] = [$r->position, $r->student->admission_no, $this->name($r->student), $r->subjects_count, $this->money($r->total_score), $this->money($r->average), $r->status];
        }

        return [['Position', 'Admission no', 'Student', 'Subjects', 'Total', 'Average', 'Status'], $rows];
    }

    private function rSchoolFees(array $f): array
    {
        $q = Invoice::with(['student.schoolClass:id,name', 'term:id,name'])->orderBy('id')
            ->when($f['term_id'] ?? null, fn ($q, $v) => $q->where('term_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['school_class_id'] ?? null, fn ($q, $v) => $q->whereHas('student', fn ($s) => $s->where('school_class_id', $v)));
        $rows = [];
        $t = ['total' => 0, 'discount' => 0, 'paid' => 0, 'balance' => 0];
        foreach ($q->get() as $i) {
            $rows[] = [$i->number, $i->student->admission_no, $this->name($i->student), $i->student->schoolClass->name ?? '', $i->term->name ?? '', $this->money($i->total), $this->money($i->discount), $this->money($i->amount_paid), $this->money($i->balance), $i->status];
            if ($i->status !== 'cancelled') {
                $t['total'] += $i->total; $t['discount'] += $i->discount; $t['paid'] += $i->amount_paid; $t['balance'] += $i->balance;
            }
        }
        $rows[] = ['TOTAL', '', '', '', '', $this->money($t['total']), $this->money($t['discount']), $this->money($t['paid']), $this->money($t['balance']), ''];

        return [['Invoice', 'Admission no', 'Student', 'Class', 'Term', 'Billed', 'Discount', 'Paid', 'Balance', 'Status'], $rows];
    }

    private function rPaymentHistory(array $f): array
    {
        $q = Payment::with(['student:id,admission_no,first_name,last_name', 'invoice:id,number'])->where('status', 'successful')
            ->whereDate('paid_at', '>=', $f['from'])->whereDate('paid_at', '<=', $f['to'])
            ->when($f['method'] ?? null, fn ($q, $v) => $q->where('method', $v))->orderBy('paid_at');
        $rows = [];
        $total = 0;
        foreach ($q->get() as $p) {
            $rows[] = [$p->paid_at->format('Y-m-d H:i'), $p->receipt_no, $p->reference, $p->student->admission_no ?? '', $this->name($p->student), $p->invoice->number ?? '', $p->method, $this->money($p->amount)];
            $total += $p->amount;
        }
        $rows[] = ['TOTAL', '', '', '', '', '', '', $this->money($total)];

        return [['Date', 'Receipt', 'Reference', 'Admission no', 'Student', 'Invoice', 'Method', 'Amount'], $rows];
    }

    private function rOutstandingBalances(array $f): array
    {
        $q = Invoice::with(['student.schoolClass:id,name', 'student.guardians', 'term:id,name'])->where('balance', '>', 0)->where('status', '!=', 'cancelled')
            ->when($f['term_id'] ?? null, fn ($q, $v) => $q->where('term_id', $v))
            ->when($f['school_class_id'] ?? null, fn ($q, $v) => $q->whereHas('student', fn ($s) => $s->where('school_class_id', $v)))
            ->orderByDesc('balance');
        $rows = [];
        $total = 0;
        foreach ($q->get() as $i) {
            $guardian = $i->student->guardians->sortByDesc(fn ($g) => $g->pivot->is_primary)->first();
            $rows[] = [$this->name($i->student), $i->student->schoolClass->name ?? '', $i->number, $i->term->name ?? '', $this->money($i->total - $i->discount), $this->money($i->amount_paid), $this->money($i->balance),
                $i->due_date?->format('Y-m-d') ?? '', $guardian ? $guardian->phone : ''];
            $total += $i->balance;
        }
        $rows[] = ['TOTAL', '', '', '', '', '', $this->money($total), '', ''];

        return [['Student', 'Class', 'Invoice', 'Term', 'Billed', 'Paid', 'Balance', 'Due date', 'Guardian phone'], $rows];
    }

    private function rFinanceSummary(array $f): array
    {
        $period = $f['period'] ?? 'monthly';
        $key = fn (Carbon $d) => match ($period) {
            'daily' => $d->format('Y-m-d'), 'weekly' => $d->format('o-\WW'), 'annual' => $d->format('Y'), default => $d->format('Y-m'),
        };
        $buckets = [];
        foreach (Payment::where('status', 'successful')->whereDate('paid_at', '>=', $f['from'])->whereDate('paid_at', '<=', $f['to'])->get(['amount', 'paid_at']) as $p) {
            $buckets[$key($p->paid_at)]['revenue'] = ($buckets[$key($p->paid_at)]['revenue'] ?? 0) + (float) $p->amount;
        }
        foreach (Expense::whereBetween('expense_date', [$f['from'], $f['to']])->get(['amount', 'expense_date']) as $e) {
            $buckets[$key($e->expense_date)]['expenses'] = ($buckets[$key($e->expense_date)]['expenses'] ?? 0) + (float) $e->amount;
        }
        ksort($buckets);
        $rows = [];
        $tr = $te = 0;
        foreach ($buckets as $label => $b) {
            $r = $b['revenue'] ?? 0;
            $e = $b['expenses'] ?? 0;
            $rows[] = [$label, $this->money($r), $this->money($e), $this->money($r - $e)];
            $tr += $r; $te += $e;
        }
        $rows[] = ['TOTAL', $this->money($tr), $this->money($te), $this->money($tr - $te)];

        return [['Period', 'Revenue', 'Expenses', 'Net'], $rows];
    }

    private function rPayroll(array $f): array
    {
        $rows = [];
        $net = 0;
        foreach (Payslip::with('staff:id,staff_no,first_name,last_name')->whereHas('payrollRun', fn ($r) => $r->where(['period_year' => $f['year'], 'period_month' => $f['month']]))->get() as $p) {
            $rows[] = [$p->staff->staff_no, $this->name($p->staff), $this->money($p->basic_salary), $this->money($p->total_allowances), $this->money($p->total_deductions), $this->money($p->gross_pay), $this->money($p->net_pay), $p->status];
            $net += $p->net_pay;
        }
        $rows[] = ['TOTAL', '', '', '', '', '', $this->money($net), ''];

        return [['Staff no', 'Name', 'Basic', 'Allowances', 'Deductions', 'Gross', 'Net pay', 'Status'], $rows];
    }

    private function rInventory(array $f): array
    {
        $rows = [];
        $value = 0;
        foreach (InventoryItem::with('inventoryCategory:id,name')->orderBy('name')->get() as $i) {
            $v = $i->quantity * (float) $i->unit_cost;
            $rows[] = [$i->name, $i->sku, $i->inventoryCategory->name ?? '', $i->unit, $i->quantity, $i->reorder_level, $this->money($i->unit_cost), $this->money($v), $i->quantity <= $i->reorder_level ? 'LOW STOCK' : 'OK'];
            $value += $v;
        }
        $rows[] = ['TOTAL STOCK VALUE', '', '', '', '', '', '', $this->money($value), ''];

        return [['Item', 'SKU', 'Category', 'Unit', 'In stock', 'Reorder level', 'Unit cost', 'Stock value', 'Status'], $rows];
    }

    private function rLibrary(array $f): array
    {
        $rows = [];
        foreach (Book::with('bookCategory:id,name')->withCount('loans')->orderBy('title')->get() as $b) {
            $rows[] = [$b->title, $b->author, $b->bookCategory->name ?? '', $b->copies_total, $b->copies_available, $b->copies_total - $b->copies_available, $b->loans_count];
        }

        return [['Title', 'Author', 'Category', 'Copies', 'Available', 'On loan', 'Times borrowed'], $rows];
    }

    private function rTransport(array $f): array
    {
        $termId = $f['term_id'] ?? Term::where('is_current', true)->value('id');
        $riders = TransportAllocation::where(['term_id' => $termId, 'status' => 'active'])->selectRaw('transport_route_id, count(*) as n')->groupBy('transport_route_id')->pluck('n', 'transport_route_id');
        $rows = [];
        foreach (TransportRoute::with('vehicle.driver')->orderBy('name')->get() as $r) {
            $rows[] = [$r->name, $r->vehicle->reg_no ?? '', $r->vehicle->driver->name ?? '', (int) ($riders[$r->id] ?? 0), $r->vehicle->capacity ?? '', $this->money($r->fee)];
        }

        return [['Route', 'Vehicle', 'Driver', 'Students', 'Vehicle capacity', 'Fee per term'], $rows];
    }

    private function rHostel(array $f): array
    {
        $active = HostelAllocation::where('status', 'active')->selectRaw('hostel_room_id, count(*) as n')->groupBy('hostel_room_id')->pluck('n', 'hostel_room_id');
        $rows = [];
        foreach (Hostel::with('rooms')->orderBy('name')->get() as $h) {
            foreach ($h->rooms as $room) {
                $occ = (int) ($active[$room->id] ?? 0);
                $rows[] = [$h->name, $room->room_no, $room->capacity, $occ, $room->capacity - $occ];
            }
        }

        return [['Hostel', 'Room', 'Capacity', 'Occupied', 'Available'], $rows];
    }
}
