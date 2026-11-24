<?php

namespace App\Services;

use App\Models\ClassArm;
use App\Models\GradingScaleItem;
use App\Models\Result;
use App\Models\ResultSummary;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Term;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResultService
{
    private array $scaleCache = [];

    public function __construct(private NotificationService $notify)
    {
    }

    public function maxCa(): float
    {
        return Settings::float('max_ca_score', 40);
    }

    public function maxExam(): float
    {
        return Settings::float('max_exam_score', 60);
    }

    public function scale(string $level): Collection
    {
        return $this->scaleCache[$level] ??= GradingScaleItem::where('level', $level)->orderByDesc('min_score')->get();
    }

    /** @return array{0: string, 1: string} grade and remark for a total score on the level's scale */
    public function grade(string $level, float $total): array
    {
        $rounded = (int) round($total);
        foreach ($this->scale($level) as $item) {
            if ($rounded >= $item->min_score && $rounded <= $item->max_score) {
                return [$item->grade, (string) $item->remark];
            }
        }

        return ['-', ''];
    }

    /**
     * Saves CA and exam scores for one subject in one class arm.
     * Approved rows are locked and reported back instead of being overwritten.
     *
     * @param  array<int, array{student_id:int, ca_score:mixed, exam_score:mixed}>  $rows
     * @return array{saved:int, locked:int[]}
     */
    public function saveScores(int $termId, int $subjectId, ClassArm $arm, array $rows, int $userId): array
    {
        $level = $arm->schoolClass->level;
        $armStudents = Student::where('class_arm_id', $arm->id)->pluck('id')->all();
        $maxCa = $this->maxCa();
        $maxExam = $this->maxExam();
        $errors = [];

        foreach ($rows as $i => $row) {
            if (! in_array((int) $row['student_id'], $armStudents, true)) {
                $errors["scores.$i.student_id"] = 'This student is not in the selected class arm.';
            }
            foreach (['ca_score' => [$maxCa, 'CA score'], 'exam_score' => [$maxExam, 'Exam score']] as $field => [$max, $label]) {
                $v = $row[$field] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
                if (! is_numeric($v) || (float) $v < 0) {
                    $errors["scores.$i.$field"] = "$label must be a number from 0 to {$max}.";
                } elseif ((float) $v > $max) {
                    $errors["scores.$i.$field"] = "$label cannot exceed {$max}.";
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $saved = 0;
        $locked = [];

        DB::transaction(function () use ($rows, $termId, $subjectId, $arm, $level, $userId, &$saved, &$locked) {
            foreach ($rows as $row) {
                $existing = Result::where(['student_id' => $row['student_id'], 'term_id' => $termId, 'subject_id' => $subjectId])->first();
                if ($existing && $existing->status === 'approved') {
                    $locked[] = (int) $row['student_id'];
                    continue;
                }
                $ca = round((float) ($row['ca_score'] ?? 0), 2);
                $exam = round((float) ($row['exam_score'] ?? 0), 2);
                $total = round($ca + $exam, 2);
                [$grade, $remark] = $this->grade($level, $total);

                Result::updateOrCreate(
                    ['student_id' => $row['student_id'], 'term_id' => $termId, 'subject_id' => $subjectId],
                    [
                        'school_class_id' => $arm->school_class_id, 'class_arm_id' => $arm->id,
                        'ca_score' => $ca, 'exam_score' => $exam, 'total' => $total,
                        'grade' => $grade, 'remark' => $remark, 'status' => 'draft', 'entered_by' => $userId,
                    ]
                );
                $saved++;
            }
        });

        return ['saved' => $saved, 'locked' => $locked];
    }

    /** Teacher hands in a subject's scores for approval. */
    public function submit(int $termId, int $subjectId, int $armId): int
    {
        $n = Result::where(['term_id' => $termId, 'subject_id' => $subjectId, 'class_arm_id' => $armId, 'status' => 'draft'])
            ->update(['status' => 'submitted']);
        if ($n > 0) {
            $this->recompute($termId, $armId);
        }

        return $n;
    }

    public function approve(int $termId, int $armId, int $userId): int
    {
        $n = Result::where(['term_id' => $termId, 'class_arm_id' => $armId, 'status' => 'submitted'])
            ->update(['status' => 'approved', 'approved_by' => $userId, 'approved_at' => now()]);
        if ($n === 0) {
            return 0;
        }

        $this->recompute($termId, $armId);

        $term = Term::find($termId);
        $studentIds = Result::where(['term_id' => $termId, 'class_arm_id' => $armId, 'status' => 'approved'])->distinct()->pluck('student_id');
        foreach (Student::with('guardians')->whereIn('id', $studentIds)->get() as $student) {
            $this->notify->toGuardians(
                $student, 'Results published',
                "{$student->first_name}'s {$term->name} results are now available in the parent portal.", 'results',
                ['app', 'email'], ['student_id' => $student->id, 'term_id' => $termId]
            );
            if ($student->user_id) {
                $this->notify->toUser($student->user_id, 'Results published', "Your {$term->name} results are now available.", 'results');
            }
        }

        return $n;
    }

    public function unapprove(int $termId, int $armId): int
    {
        $n = Result::where(['term_id' => $termId, 'class_arm_id' => $armId, 'status' => 'approved'])
            ->update(['status' => 'submitted', 'approved_by' => null, 'approved_at' => null]);
        $this->recompute($termId, $armId);

        return $n;
    }

    /**
     * Recalculates subject positions and per-student totals, averages and class positions for a class arm.
     * Equal scores share a position (1, 2, 2, 4).
     */
    public function recompute(int $termId, int $armId): void
    {
        $results = Result::where(['term_id' => $termId, 'class_arm_id' => $armId])
            ->whereIn('status', ['submitted', 'approved'])->get();

        foreach ($results->groupBy('subject_id') as $group) {
            foreach ($this->rank($group->map(fn ($r) => [$r->id, (float) $r->total])) as $id => $pos) {
                Result::whereKey($id)->update(['position' => $pos]);
            }
        }

        $byStudent = $results->groupBy('student_id');
        $totals = $byStudent->map(fn ($g) => round($g->sum(fn ($r) => (float) $r->total), 2));
        $positions = $this->rank($totals->map(fn ($t, $sid) => [$sid, $t])->values());
        $arm = ClassArm::find($armId);

        foreach ($byStudent as $studentId => $group) {
            $count = $group->count();
            ResultSummary::updateOrCreate(
                ['student_id' => $studentId, 'term_id' => $termId],
                [
                    'school_class_id' => $arm->school_class_id, 'class_arm_id' => $armId,
                    'total_score' => $totals[$studentId], 'subjects_count' => $count,
                    'average' => $count ? round($totals[$studentId] / $count, 2) : 0,
                    'position' => $positions[$studentId] ?? null, 'class_size' => $byStudent->count(),
                    'status' => $group->every(fn ($r) => $r->status === 'approved') ? 'approved' : 'draft',
                ]
            );
        }
    }

    /**
     * @param  Collection<int, array{0:int,1:float}>  $items  [id, score]
     * @return array<int,int> id => position
     */
    public function rank(Collection $items): array
    {
        $sorted = $items->sortByDesc(fn ($i) => $i[1])->values();
        $out = [];
        $prev = null;
        $rank = 0;
        foreach ($sorted as $i => [$id, $score]) {
            if ($prev === null || $score !== $prev) {
                $rank = $i + 1;
                $prev = $score;
            }
            $out[$id] = $rank;
        }

        return $out;
    }

    /** Everything needed to render a report card (approved results only). */
    public function reportCard(Student $student, Term $term): array
    {
        $term->loadMissing('academicSession');
        $student->loadMissing(['schoolClass', 'classArm']);

        $rows = Result::with('subject:id,name,code')->where(['student_id' => $student->id, 'term_id' => $term->id, 'status' => 'approved'])->get()
            ->sortBy(fn ($r) => $r->subject->name)->values();
        abort_if($rows->isEmpty(), 404, 'No approved results are available for this term.');

        $summary = ResultSummary::where(['student_id' => $student->id, 'term_id' => $term->id])->first();
        $att = StudentAttendance::where('student_id', $student->id)
            ->whereBetween('date', [$term->starts_on->toDateString(), $term->ends_on->toDateString()])
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $marked = (int) $att->sum();
        $attended = (int) (($att['present'] ?? 0) + ($att['late'] ?? 0));
        $level = $student->schoolClass->level;

        return [
            'student' => $student, 'term' => $term, 'session' => $term->academicSession,
            'rows' => $rows, 'summary' => $summary,
            'attendance' => ['marked' => $marked, 'attended' => $attended, 'absent' => (int) ($att['absent'] ?? 0)],
            'scale' => $this->scale($level),
            'settings' => Settings::all(),
        ];
    }
}
