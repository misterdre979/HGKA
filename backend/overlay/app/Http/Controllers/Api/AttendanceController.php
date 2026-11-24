<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    private const STATUSES = ['present', 'absent', 'late', 'excused'];

    public function __construct(private NotificationService $notify)
    {
    }

    public function sheet(Request $request): JsonResponse
    {
        $d = $request->validate(['class_arm_id' => ['required', 'integer', 'exists:class_arms,id'], 'date' => ['required', 'date']]);
        $this->authorizeArm($request->user(), $d['class_arm_id']);

        $marks = StudentAttendance::where('class_arm_id', $d['class_arm_id'])->whereDate('date', $d['date'])->get()->keyBy('student_id');

        $students = Student::where('class_arm_id', $d['class_arm_id'])->where('status', 'active')
            ->orderBy('last_name')->orderBy('first_name')->get(['id', 'admission_no', 'first_name', 'last_name', 'photo_path'])
            ->map(fn ($s) => [
                'student_id' => $s->id, 'admission_no' => $s->admission_no, 'name' => trim($s->first_name.' '.$s->last_name),
                'photo_url' => $s->photo_url, 'status' => $marks->get($s->id)?->status, 'remark' => $marks->get($s->id)?->remark,
            ]);

        return response()->json(['students' => $students, 'already_marked' => $marks->isNotEmpty()]);
    }

    public function save(Request $request): JsonResponse
    {
        $d = $request->validate([
            'class_arm_id' => ['required', 'integer', 'exists:class_arms,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'records.*.status' => ['required', Rule::in(self::STATUSES)],
            'records.*.remark' => ['nullable', 'string', 'max:255'],
        ]);
        $user = $request->user();
        $this->authorizeArm($user, $d['class_arm_id'], true);

        $arm = ClassArm::findOrFail($d['class_arm_id']);
        $armStudents = Student::where('class_arm_id', $arm->id)->pluck('id')->all();
        $newAbsent = [];

        DB::transaction(function () use ($d, $user, $arm, $armStudents, &$newAbsent) {
            foreach ($d['records'] as $r) {
                if (! in_array((int) $r['student_id'], $armStudents, true)) {
                    continue; // ignore students that are not in this arm
                }
                $existing = StudentAttendance::where('student_id', $r['student_id'])->whereDate('date', $d['date'])->first();
                StudentAttendance::updateOrCreate(
                    ['student_id' => $r['student_id'], 'date' => $d['date']],
                    ['class_arm_id' => $arm->id, 'school_class_id' => $arm->school_class_id, 'status' => $r['status'],
                        'remark' => $r['remark'] ?? null, 'marked_by' => $user->id]
                );
                if ($r['status'] === 'absent' && $existing?->status !== 'absent') {
                    $newAbsent[] = (int) $r['student_id'];
                }
            }
        });

        if ($newAbsent && Settings::get('notify_parents_on_absence', '1') === '1') {
            $when = date('D, j M Y', strtotime($d['date']));
            foreach (Student::with('guardians')->whereIn('id', $newAbsent)->get() as $student) {
                $this->notify->toGuardians(
                    $student, 'Absence alert', "{$student->first_name} {$student->last_name} was marked absent on {$when}.",
                    'attendance', ['app', 'sms', 'email'], ['student_id' => $student->id, 'date' => $d['date']]
                );
            }
        }
        Audit::log('attendance.saved', null, null, ['arm' => $arm->id, 'date' => $d['date'], 'count' => count($d['records'])]);

        return response()->json(['saved' => count($d['records']), 'absence_alerts' => count($newAbsent)]);
    }

    /** Per-student counts and attendance rate for a class arm over a date range. */
    public function summary(Request $request): JsonResponse
    {
        $d = $request->validate([
            'class_arm_id' => ['required', 'integer', 'exists:class_arms,id'],
            'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $counts = StudentAttendance::where('class_arm_id', $d['class_arm_id'])->whereBetween('date', [$d['from'], $d['to']])
            ->selectRaw('student_id, status, count(*) as n')->groupBy('student_id', 'status')->get()->groupBy('student_id');

        $rows = Student::where('class_arm_id', $d['class_arm_id'])->where('status', 'active')->orderBy('last_name')
            ->get(['id', 'admission_no', 'first_name', 'last_name'])->map(function ($s) use ($counts) {
                $c = ($counts[$s->id] ?? collect())->pluck('n', 'status');
                $total = (int) $c->sum();
                $attended = (int) (($c['present'] ?? 0) + ($c['late'] ?? 0) + ($c['excused'] ?? 0));

                return [
                    'student_id' => $s->id, 'admission_no' => $s->admission_no, 'name' => trim($s->first_name.' '.$s->last_name),
                    'present' => (int) ($c['present'] ?? 0), 'absent' => (int) ($c['absent'] ?? 0), 'late' => (int) ($c['late'] ?? 0),
                    'excused' => (int) ($c['excused'] ?? 0), 'days' => $total, 'rate' => $total ? round($attended / $total * 100, 1) : null,
                ];
            });

        return response()->json($rows);
    }

    public function studentHistory(Request $request, Student $student): JsonResponse
    {
        $d = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return response()->json(self::historyFor($student, $d['from'] ?? null, $d['to'] ?? null));
    }

    public static function historyFor(Student $student, ?string $from = null, ?string $to = null): array
    {
        $q = StudentAttendance::where('student_id', $student->id)->orderByDesc('date');
        if ($from) {
            $q->where('date', '>=', $from);
        }
        if ($to) {
            $q->where('date', '<=', $to);
        }
        $rows = $q->limit(400)->get(['date', 'status', 'remark']);
        $c = $rows->groupBy('status')->map->count();
        $total = $rows->count();

        return [
            'records' => $rows,
            'totals' => ['present' => (int) ($c['present'] ?? 0), 'absent' => (int) ($c['absent'] ?? 0), 'late' => (int) ($c['late'] ?? 0), 'excused' => (int) ($c['excused'] ?? 0), 'days' => $total],
            'rate' => $total ? round((($c['present'] ?? 0) + ($c['late'] ?? 0) + ($c['excused'] ?? 0)) / $total * 100, 1) : null,
        ];
    }

    // ---------------------------------------------------------------- staff attendance
    public function staffSheet(Request $request): JsonResponse
    {
        $d = $request->validate(['date' => ['required', 'date']]);
        $marks = StaffAttendance::whereDate('date', $d['date'])->get()->keyBy('staff_id');

        return response()->json(Staff::where('status', 'active')->orderBy('first_name')->get(['id', 'staff_no', 'first_name', 'last_name', 'category'])
            ->map(fn ($s) => [
                'staff_id' => $s->id, 'staff_no' => $s->staff_no, 'name' => trim($s->first_name.' '.$s->last_name), 'category' => $s->category,
                'status' => $marks->get($s->id)?->status, 'check_in' => $marks->get($s->id)?->check_in, 'check_out' => $marks->get($s->id)?->check_out,
                'remark' => $marks->get($s->id)?->remark,
            ]));
    }

    public function staffSave(Request $request): JsonResponse
    {
        $d = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.staff_id' => ['required', 'integer', 'exists:staff,id'],
            'records.*.status' => ['required', Rule::in(self::STATUSES)],
            'records.*.check_in' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'records.*.check_out' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'records.*.remark' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($d, $request) {
            foreach ($d['records'] as $r) {
                StaffAttendance::updateOrCreate(
                    ['staff_id' => $r['staff_id'], 'date' => $d['date']],
                    ['status' => $r['status'], 'check_in' => $r['check_in'] ?? null, 'check_out' => $r['check_out'] ?? null,
                        'remark' => $r['remark'] ?? null, 'marked_by' => $request->user()->id]
                );
            }
        });
        Audit::log('attendance.staff_saved', null, null, ['date' => $d['date'], 'count' => count($d['records'])]);

        return response()->json(['saved' => count($d['records'])]);
    }

    public function staffSummary(Request $request): JsonResponse
    {
        $d = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $counts = StaffAttendance::whereBetween('date', [$d['from'], $d['to']])->selectRaw('staff_id, status, count(*) as n')
            ->groupBy('staff_id', 'status')->get()->groupBy('staff_id');

        return response()->json(Staff::orderBy('first_name')->get(['id', 'staff_no', 'first_name', 'last_name'])->map(function ($s) use ($counts) {
            $c = ($counts[$s->id] ?? collect())->pluck('n', 'status');
            $total = (int) $c->sum();

            return [
                'staff_id' => $s->id, 'staff_no' => $s->staff_no, 'name' => trim($s->first_name.' '.$s->last_name),
                'present' => (int) ($c['present'] ?? 0), 'absent' => (int) ($c['absent'] ?? 0), 'late' => (int) ($c['late'] ?? 0),
                'excused' => (int) ($c['excused'] ?? 0), 'days' => $total,
                'rate' => $total ? round((($c['present'] ?? 0) + ($c['late'] ?? 0) + ($c['excused'] ?? 0)) / $total * 100, 1) : null,
            ];
        }));
    }

    /**
     * Administrators (attendance.staff) may use any class. Teachers may write only for classes they teach or
     * supervise; roles with attendance.report (e.g. heads of department) may additionally read any class.
     */
    private function authorizeArm(User $user, int $armId, bool $forWriting = false): void
    {
        abort_unless(
            $user->hasAnyPermission($forWriting ? ['attendance.take'] : ['attendance.take', 'attendance.view']),
            403,
            'You do not have permission to perform this action.'
        );
        if ($user->hasPermission('attendance.staff')) {
            return;
        }
        if (! $forWriting && $user->hasPermission('attendance.report')) {
            return;
        }
        $staff = Staff::where('user_id', $user->id)->first();
        $allowed = $staff && (
            ClassArm::where('id', $armId)->where('form_teacher_id', $staff->id)->exists()
            || TeacherAssignment::where(['staff_id' => $staff->id, 'class_arm_id' => $armId])->exists()
        );
        abort_unless($allowed, 403, 'You can only take attendance for classes you teach.');
    }
}
