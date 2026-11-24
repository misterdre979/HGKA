<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Term;
use App\Models\TeacherAssignment;
use App\Models\TimetableEntry;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicController extends Controller
{
    public function current(): JsonResponse
    {
        return response()->json([
            'session' => AcademicSession::where('is_current', true)->first(),
            'term' => Term::where('is_current', true)->first(),
        ]);
    }

    public function setCurrentSession(AcademicSession $session): JsonResponse
    {
        DB::transaction(function () use ($session) {
            AcademicSession::query()->update(['is_current' => false]);
            $session->update(['is_current' => true]);
        });
        Audit::log('academics.session_current', $session);

        return response()->json($session->fresh());
    }

    public function setCurrentTerm(Term $term): JsonResponse
    {
        DB::transaction(function () use ($term) {
            Term::query()->update(['is_current' => false]);
            AcademicSession::query()->update(['is_current' => false]);
            $term->update(['is_current' => true]);
            AcademicSession::whereKey($term->academic_session_id)->update(['is_current' => true]);
        });
        Audit::log('academics.term_current', $term);

        return response()->json($term->fresh('academicSession'));
    }

    public function classTimetable(Request $request, ClassArm $arm): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('academics.view')) {
            abort_unless($this->portalCanSeeArm($request, $arm->id), 403, 'You do not have permission to perform this action.');
        }

        return response()->json($this->entries(['class_arm_id' => $arm->id]));
    }

    public function teacherTimetable(Request $request, Staff $staff): JsonResponse
    {
        $own = Staff::where('user_id', $request->user()->id)->value('id');
        abort_unless($own === $staff->id || $request->user()->hasPermission('academics.view'), 403, 'You do not have permission to perform this action.');

        return response()->json($this->entries(['staff_id' => $staff->id]));
    }

    /** Classes and subjects allocated to the signed-in teacher. */
    public function myAssignments(Request $request): JsonResponse
    {
        $staff = Staff::where('user_id', $request->user()->id)->first();
        abort_unless($staff, 403, 'Your account is not linked to a staff record.');

        $assignments = TeacherAssignment::with(['subject', 'classArm.schoolClass'])->where('staff_id', $staff->id)->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'subject' => $a->subject, 'class_arm' => $a->classArm,
                'students' => Student::where('class_arm_id', $a->class_arm_id)->where('status', 'active')->count(),
            ]);
        $formArms = ClassArm::with('schoolClass')->where('form_teacher_id', $staff->id)->get();

        return response()->json([
            'staff' => $staff, 'assignments' => $assignments, 'form_arms' => $formArms,
            'timetable' => $this->entries(['staff_id' => $staff->id]),
        ]);
    }

    private function entries(array $where)
    {
        return TimetableEntry::with(['subject:id,name,code', 'staff:id,first_name,last_name', 'classArm.schoolClass'])
            ->where($where)->orderBy('day_of_week')->orderBy('start_time')->get();
    }

    /** Parents and students may view the timetable of the arms their own children/they belong to. */
    private function portalCanSeeArm(Request $request, int $armId): bool
    {
        $ids = PortalController::studentIdsFor($request->user());

        return $ids !== [] && Student::whereIn('id', $ids)->where('class_arm_id', $armId)->exists();
    }
}
