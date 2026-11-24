<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookLoan;
use App\Models\ClassArm;
use App\Models\Exam;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\ResultSummary;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Term;
use App\Models\AcademicSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Every figure comes from live database records. Sections are included only when the user may see them. */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $term = Term::where('is_current', true)->first();
        $out = ['period' => ['session' => AcademicSession::where('is_current', true)->first(), 'term' => $term]];

        if ($user->hasPermission('students.view')) {
            $active = Student::where('status', 'active');
            $out['students'] = [
                'total' => (clone $active)->count(),
                'male' => (clone $active)->where('gender', 'male')->count(),
                'female' => (clone $active)->where('gender', 'female')->count(),
                'total_all_statuses' => Student::count(),
            ];
        }
        if ($user->hasPermission('staff.view')) {
            $staff = Staff::where('status', '!=', 'terminated')->where('status', '!=', 'resigned');
            $out['staff'] = [
                'teaching' => (clone $staff)->where('category', 'teaching')->count(),
                'non_teaching' => (clone $staff)->where('category', 'non_teaching')->count(),
            ];
        }
        if ($user->hasPermission('academics.view')) {
            $out['classes'] = ['active' => SchoolClass::where('is_active', true)->count(), 'arms' => ClassArm::count()];
        }

        if ($user->hasAnyPermission(['attendance.view', 'attendance.report'])) {
            $out['attendance'] = $this->attendance();
        }

        if ($user->hasAnyPermission(['fees.view', 'finance.view'])) {
            $out['finance'] = $this->finance($term, $user->hasPermission('fees.approve-payment'));
        }

        if ($user->hasAnyPermission(['exams.view', 'dashboard.view'])) {
            $out['upcoming_exams'] = Exam::with(['schoolClass:id,name', 'subject:id,name'])->where('exam_date', '>=', now()->toDateString())
                ->where('status', '!=', 'completed')->orderBy('exam_date')->limit(6)->get();
        }
        if ($user->hasPermission('exams.view') && $term) {
            $out['performance'] = $this->performance($term);
        }

        $out['announcements'] = CommunicationController::visibleAnnouncements($user, 5);

        if ($user->hasPermission('library.view')) {
            $out['library'] = [
                'on_loan' => BookLoan::where('status', 'borrowed')->count(),
                'overdue' => BookLoan::where('status', 'borrowed')->where('due_on', '<', now()->toDateString())->count(),
            ];
        }
        if ($user->hasPermission('inventory.view')) {
            $out['inventory'] = ['low_stock' => InventoryItem::whereColumn('quantity', '<=', 'reorder_level')->count()];
        }
        if ($user->hasPermission('leave.approve')) {
            $out['pending_leave'] = LeaveRequest::where('status', 'pending')->count();
        }

        return response()->json($out);
    }

    private function attendance(): array
    {
        $rate = function ($rows) {
            $total = $rows->sum();

            return $total ? round((($rows['present'] ?? 0) + ($rows['late'] ?? 0) + ($rows['excused'] ?? 0)) / $total * 100, 1) : null;
        };

        $today = StudentAttendance::whereDate('date', now()->toDateString())->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $series = [];
        $recent = StudentAttendance::where('date', '>=', now()->subDays(13)->toDateString())->selectRaw('date, status, count(*) as n')
            ->groupBy('date', 'status')->get()->groupBy(fn ($r) => Carbon::parse($r->date)->toDateString());
        foreach ($recent->sortKeys() as $date => $rows) {
            $series[] = ['date' => $date, 'rate' => $rate($rows->pluck('n', 'status'))];
        }

        return [
            'today' => ['present' => (int) ($today['present'] ?? 0), 'absent' => (int) ($today['absent'] ?? 0), 'late' => (int) ($today['late'] ?? 0),
                'excused' => (int) ($today['excused'] ?? 0), 'marked' => (int) $today->sum(), 'rate' => $rate($today)],
            'series' => $series,
        ];
    }

    private function finance(?Term $term, bool $canApprove): array
    {
        $billed = Invoice::where('status', '!=', 'cancelled');
        $monthStart = now()->startOfMonth();
        $yearStart = now()->startOfYear();
        $sixMonths = now()->startOfMonth()->subMonths(5);

        $payments = Payment::where('status', 'successful')->where('paid_at', '>=', $sixMonths)->get(['amount', 'paid_at']);
        $expenses = Expense::where('expense_date', '>=', $sixMonths->toDateString())->get(['amount', 'expense_date']);

        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $m = $sixMonths->copy()->addMonths($i);
            $key = $m->format('Y-m');
            $months[$key] = ['month' => $m->format('M Y'), 'revenue' => 0.0, 'expenses' => 0.0];
        }
        foreach ($payments as $p) {
            $k = $p->paid_at->format('Y-m');
            if (isset($months[$k])) {
                $months[$k]['revenue'] += (float) $p->amount;
            }
        }
        foreach ($expenses as $e) {
            $k = $e->expense_date->format('Y-m');
            if (isset($months[$k])) {
                $months[$k]['expenses'] += (float) $e->amount;
            }
        }

        $sumPayments = fn (Carbon $from) => (float) Payment::where('status', 'successful')->where('paid_at', '>=', $from)->sum('amount');
        $sumExpenses = fn (Carbon $from) => (float) Expense::where('expense_date', '>=', $from->toDateString())->sum('amount');

        return [
            'outstanding' => round((float) (clone $billed)->sum('balance'), 2),
            'billed_term' => $term ? round((float) (clone $billed)->where('term_id', $term->id)->sum('total') - (float) (clone $billed)->where('term_id', $term->id)->sum('discount'), 2) : 0,
            'collected_term' => $term ? round((float) (clone $billed)->where('term_id', $term->id)->sum('amount_paid'), 2) : 0,
            'revenue_month' => $sumPayments($monthStart), 'revenue_year' => $sumPayments($yearStart),
            'expenses_month' => $sumExpenses($monthStart), 'expenses_year' => $sumExpenses($yearStart),
            'monthly' => array_values($months),
            'recent_payments' => Payment::with(['student:id,first_name,last_name', 'invoice:id,number'])->where('status', 'successful')
                ->orderByDesc('paid_at')->limit(8)->get(['id', 'student_id', 'invoice_id', 'amount', 'method', 'receipt_no', 'paid_at']),
            'awaiting_approval' => $canApprove ? Payment::where('status', 'awaiting_approval')->count() : null,
        ];
    }

    /** Average score per class for the current term (approved results only). */
    private function performance(Term $term): array
    {
        return ResultSummary::where(['term_id' => $term->id, 'status' => 'approved'])->get()->groupBy('school_class_id')
            ->map(function ($rows, $classId) {
                return [
                    'class' => SchoolClass::find($classId)?->name, 'students' => $rows->count(),
                    'average' => round($rows->avg(fn ($r) => (float) $r->average), 1),
                    'pass_rate' => round($rows->filter(fn ($r) => (float) $r->average >= 50)->count() / max(1, $rows->count()) * 100, 1),
                ];
            })->sortBy('class')->values()->all();
    }
}
