<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\Result;
use App\Models\ResultSummary;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\ResultService;
use App\Support\Audit;
use App\Support\PdfDoc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(private ResultService $results)
    {
    }

    /** Subjects the signed-in user may enter scores for in a class arm. */
    public function subjects(Request $request): JsonResponse
    {
        $arm = ClassArm::findOrFail($request->validate(['class_arm_id' => ['required', 'integer']])['class_arm_id']);
        $user = $request->user();

        if ($user->hasPermission('exams.manage')) {
            $ids = ClassSubject::where('school_class_id', $arm->school_class_id)->pluck('subject_id');
        } else {
            $staff = Staff::where('user_id', $user->id)->first();
            $ids = $staff ? TeacherAssignment::where(['staff_id' => $staff->id, 'class_arm_id' => $arm->id])->pluck('subject_id') : collect();
        }

        return response()->json(Subject::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'code']));
    }

    /** Score-entry sheet: every active student in the arm with any saved scores. */
    public function sheet(Request $request): JsonResponse
    {
        $d = $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'class_arm_id' => ['required', 'integer', 'exists:class_arms,id'],
        ]);
        $this->authorizeEntry($request->user(), $d['subject_id'], $d['class_arm_id']);

        $existing = Result::where(['term_id' => $d['term_id'], 'subject_id' => $d['subject_id'], 'class_arm_id' => $d['class_arm_id']])
            ->get()->keyBy('student_id');

        $students = Student::where('class_arm_id', $d['class_arm_id'])->where('status', 'active')
            ->orderBy('last_name')->orderBy('first_name')->get(['id', 'admission_no', 'first_name', 'last_name'])
            ->map(function ($s) use ($existing) {
                $r = $existing->get($s->id);

                return [
                    'student_id' => $s->id, 'admission_no' => $s->admission_no, 'name' => trim($s->first_name.' '.$s->last_name),
                    'ca_score' => $r?->ca_score, 'exam_score' => $r?->exam_score, 'total' => $r?->total,
                    'grade' => $r?->grade, 'position' => $r?->position, 'status' => $r?->status ?? 'new',
                ];
            });

        return response()->json([
            'max_ca' => $this->results->maxCa(), 'max_exam' => $this->results->maxExam(), 'students' => $students,
        ]);
    }

    public function saveScores(Request $request): JsonResponse
    {
        $d = $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'class_arm_id' => ['required', 'integer', 'exists:class_arms,id'],
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'scores.*.ca_score' => ['nullable', 'numeric', 'min:0'],
            'scores.*.exam_score' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->authorizeEntry($request->user(), $d['subject_id'], $d['class_arm_id']);

        $out = $this->results->saveScores($d['term_id'], $d['subject_id'], ClassArm::with('schoolClass')->findOrFail($d['class_arm_id']), $d['scores'], $request->user()->id);
        Audit::log('results.scores_saved', null, null, ['term' => $d['term_id'], 'subject' => $d['subject_id'], 'arm' => $d['class_arm_id'], 'saved' => $out['saved']]);

        return response()->json($out);
    }

    public function submit(Request $request): JsonResponse
    {
        $d = $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'class_arm_id' => ['required', 'integer', 'exists:class_arms,id'],
        ]);
        $this->authorizeEntry($request->user(), $d['subject_id'], $d['class_arm_id']);
        $n = $this->results->submit($d['term_id'], $d['subject_id'], $d['class_arm_id']);
        abort_if($n === 0, 422, 'There are no draft scores to submit.');
        Audit::log('results.submitted', null, null, $d);

        return response()->json(['submitted' => $n]);
    }

    public function approve(Request $request): JsonResponse
    {
        $d = $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id'], 'class_arm_id' => ['required', 'integer', 'exists:class_arms,id']]);
        $n = $this->results->approve($d['term_id'], $d['class_arm_id'], $request->user()->id);
        abort_if($n === 0, 422, 'There are no submitted results to approve.');
        Audit::log('results.approved', null, null, $d);

        return response()->json(['approved' => $n]);
    }

    public function unapprove(Request $request): JsonResponse
    {
        $d = $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id'], 'class_arm_id' => ['required', 'integer', 'exists:class_arms,id']]);
        $n = $this->results->unapprove($d['term_id'], $d['class_arm_id']);
        Audit::log('results.unapproved', null, null, $d);

        return response()->json(['reopened' => $n]);
    }

    /** Class broadsheet: students x subjects with totals, averages and positions. */
    public function classSheet(Request $request): JsonResponse
    {
        $d = $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id'], 'class_arm_id' => ['required', 'integer', 'exists:class_arms,id']]);

        $rows = Result::with('subject:id,name,code')->where(['term_id' => $d['term_id'], 'class_arm_id' => $d['class_arm_id']])->get();
        $subjects = $rows->pluck('subject')->unique('id')->sortBy('name')->values();
        $summaries = ResultSummary::where(['term_id' => $d['term_id'], 'class_arm_id' => $d['class_arm_id']])->get()->keyBy('student_id');

        $students = Student::whereIn('id', $rows->pluck('student_id')->unique())->get(['id', 'admission_no', 'first_name', 'last_name'])
            ->map(function ($s) use ($rows, $summaries) {
                $mine = $rows->where('student_id', $s->id)->keyBy('subject_id');
                $sum = $summaries->get($s->id);

                return [
                    'student_id' => $s->id, 'admission_no' => $s->admission_no, 'name' => trim($s->first_name.' '.$s->last_name),
                    'scores' => $mine->map(fn ($r) => ['total' => $r->total, 'grade' => $r->grade, 'status' => $r->status]),
                    'total' => $sum?->total_score, 'average' => $sum?->average, 'position' => $sum?->position,
                    'teacher_comment' => $sum?->teacher_comment, 'principal_comment' => $sum?->principal_comment,
                ];
            })->sortBy(fn ($s) => $s['position'] ?? 9999)->values();

        $status = $rows->isEmpty() ? 'none' : ($rows->every(fn ($r) => $r->status === 'approved') ? 'approved' : ($rows->contains(fn ($r) => $r->status === 'submitted') ? 'submitted' : 'draft'));

        return response()->json(['subjects' => $subjects, 'students' => $students, 'status' => $status]);
    }

    public function comments(Request $request, Student $student): JsonResponse
    {
        $d = $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'teacher_comment' => ['nullable', 'string', 'max:1000'],
            'principal_comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $user = $request->user();
        $summary = ResultSummary::where(['student_id' => $student->id, 'term_id' => $d['term_id']])->firstOrFail();

        $update = [];
        if (array_key_exists('principal_comment', $d)) {
            abort_unless($user->hasPermission('exams.approve-results'), 403, 'Only the principal can write the principal comment.');
            $update['principal_comment'] = $d['principal_comment'];
        }
        if (array_key_exists('teacher_comment', $d)) {
            $isForm = Staff::where('user_id', $user->id)->whereHas('assignments', fn ($q) => $q->where('class_arm_id', $student->class_arm_id))->exists()
                || ClassArm::where('id', $student->class_arm_id)->whereHas('formTeacher', fn ($q) => $q->where('user_id', $user->id))->exists();
            abort_unless($isForm || $user->hasPermission('exams.manage'), 403, 'Only the class teacher can write this comment.');
            $update['teacher_comment'] = $d['teacher_comment'];
        }
        $summary->update($update);

        return response()->json($summary);
    }

    /** Academic performance history across terms (staff view; portal users go through PortalController). */
    public function history(Student $student): JsonResponse
    {
        return response()->json(self::historyFor($student, false));
    }

    public static function historyFor(Student $student, bool $approvedOnly): array
    {
        $q = Result::with('subject:id,name,code')->where('student_id', $student->id);
        if ($approvedOnly) {
            $q->where('status', 'approved');
        }
        $byTerm = $q->get()->groupBy('term_id');
        $terms = Term::with('academicSession:id,name')->whereIn('id', $byTerm->keys())->orderBy('starts_on')->get();
        $summaries = ResultSummary::where('student_id', $student->id)->get()->keyBy('term_id');

        return $terms->map(fn ($t) => [
            'term' => $t,
            'summary' => $summaries->get($t->id),
            'results' => $byTerm[$t->id]->sortBy(fn ($r) => $r->subject->name)->values(),
        ])->all();
    }

    public function reportCard(Request $request, Student $student, Term $term): JsonResponse
    {
        abort_unless(self::canViewReportCard($request->user(), $student), 403, 'You do not have permission to perform this action.');

        return response()->json($this->results->reportCard($student, $term));
    }

    public function reportCardPdf(Request $request, Student $student, Term $term)
    {
        abort_unless(self::canViewReportCard($request->user(), $student), 403, 'You do not have permission to perform this action.');
        $data = $this->results->reportCard($student, $term);
        Audit::log('results.report_card_downloaded', $student);

        return PdfDoc::download('pdf.report-card', $data, 'report-card-'.str_replace('/', '-', $student->admission_no).'-term'.$term->sequence.'.pdf');
    }

    public static function canViewReportCard(User $user, Student $student): bool
    {
        return $user->hasPermission('exams.view') || in_array($student->id, PortalController::studentIdsFor($user), true);
    }

    private function authorizeEntry(User $user, int $subjectId, int $armId): void
    {
        abort_unless($user->hasAnyPermission(['exams.enter-scores', 'exams.manage']), 403, 'You do not have permission to perform this action.');
        if ($user->hasPermission('exams.manage')) {
            return;
        }
        $staff = Staff::where('user_id', $user->id)->first();
        abort_unless(
            $staff && TeacherAssignment::where(['staff_id' => $staff->id, 'subject_id' => $subjectId, 'class_arm_id' => $armId])->exists(),
            403,
            'You are not assigned to teach this subject in this class.'
        );
    }
}
