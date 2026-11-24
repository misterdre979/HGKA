<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ResultSummary;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Parent/guardian and student self-service. Every endpoint is scoped to the students linked to the signed-in user. */
class PortalController extends Controller
{
    /** @return int[] ids of students the user may see as a parent/guardian or as the student themself */
    public static function studentIdsFor(User $user): array
    {
        $ids = [];
        if ($user->hasRole('parent')) {
            $guardianIds = Guardian::where('user_id', $user->id)->pluck('id');
            $ids = array_merge($ids, \DB::table('guardian_student')->whereIn('guardian_id', $guardianIds)->pluck('student_id')->all());
        }
        if ($user->hasRole('student')) {
            $ids = array_merge($ids, Student::where('user_id', $user->id)->pluck('id')->all());
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function mine(Request $request, Student $student): Student
    {
        abort_unless(in_array($student->id, self::studentIdsFor($request->user()), true), 403, 'You do not have permission to perform this action.');

        return $student;
    }

    /** Children (or the student's own record) with headline numbers. */
    public function students(Request $request): JsonResponse
    {
        $students = Student::with(['schoolClass', 'classArm'])->whereIn('id', self::studentIdsFor($request->user()))->get()
            ->map(function ($s) {
                $inv = Invoice::where('student_id', $s->id)->where('status', '!=', 'cancelled');
                $latest = ResultSummary::with('term')->where(['student_id' => $s->id, 'status' => 'approved'])->orderByDesc('term_id')->first();

                return [
                    'id' => $s->id, 'admission_no' => $s->admission_no, 'name' => trim($s->first_name.' '.$s->last_name),
                    'photo_url' => $s->photo_url, 'class' => $s->schoolClass?->name, 'arm' => $s->classArm?->name, 'status' => $s->status,
                    'outstanding' => (float) (clone $inv)->sum('balance'),
                    'attendance_rate' => AttendanceController::historyFor($s, now()->subDays(90)->toDateString())['rate'],
                    'latest_result' => $latest ? ['term' => $latest->term->name, 'average' => $latest->average, 'position' => $latest->position, 'class_size' => $latest->class_size] : null,
                ];
            });

        return response()->json($students);
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        $this->mine($request, $student);

        return response()->json($student->load(['schoolClass', 'classArm', 'guardians']));
    }

    public function attendance(Request $request, Student $student): JsonResponse
    {
        $this->mine($request, $student);
        $d = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return response()->json(AttendanceController::historyFor($student, $d['from'] ?? null, $d['to'] ?? null));
    }

    /** Approved results only. */
    public function results(Request $request, Student $student): JsonResponse
    {
        $this->mine($request, $student);

        return response()->json(ResultController::historyFor($student, true));
    }

    public function invoices(Request $request, Student $student): JsonResponse
    {
        $this->mine($request, $student);

        return response()->json(
            Invoice::with(['items', 'term:id,name', 'payments' => fn ($q) => $q->orderByDesc('id')])
                ->where('student_id', $student->id)->where('status', '!=', 'cancelled')->orderByDesc('id')->get()
        );
    }

    public function payments(Request $request, Student $student): JsonResponse
    {
        $this->mine($request, $student);

        return response()->json(Payment::with('invoice:id,number')->where('student_id', $student->id)->orderByDesc('id')->limit(100)->get());
    }
}
