<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Services\ResultService;
use Tests\TestCase;

class ResultCalculationTest extends TestCase
{
    private Term $term;
    private ClassArm $arm;
    private Subject $subject;
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        $session = AcademicSession::create(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-01', 'is_current' => true]);
        $this->term = Term::create(['academic_session_id' => $session->id, 'name' => 'First Term', 'sequence' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-18', 'is_current' => true]);
        $class = SchoolClass::create(['name' => 'JSS 1', 'level' => 'secondary', 'sequence' => 7]);
        $this->arm = ClassArm::create(['school_class_id' => $class->id, 'name' => 'A']);
        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'MTH', 'level' => 'all']);
        foreach (['A', 'B', 'C', 'D'] as $i => $n) {
            $this->students[$n] = Student::create(['admission_no' => "S$i", 'first_name' => $n, 'last_name' => 'Test', 'gender' => 'male', 'school_class_id' => $class->id, 'class_arm_id' => $this->arm->id, 'status' => 'active']);
        }
    }

    private function enter(array $scores): array
    {
        $rows = [];
        foreach ($scores as $name => [$ca, $exam]) {
            $rows[] = ['student_id' => $this->students[$name]->id, 'ca_score' => $ca, 'exam_score' => $exam];
        }

        return app(ResultService::class)->saveScores($this->term->id, $this->subject->id, $this->arm->load('schoolClass'), $rows, 1);
    }

    private function result(string $name): Result
    {
        return Result::where(['student_id' => $this->students[$name]->id, 'subject_id' => $this->subject->id])->firstOrFail();
    }

    public function test_total_grade_and_remark_are_computed_on_the_server(): void
    {
        $this->enter(['A' => [38, 55]]);

        $r = $this->result('A');
        $this->assertEquals(93, $r->total);
        $this->assertSame('A1', $r->grade);
    }

    public function test_grade_boundaries_secondary_scale(): void
    {
        $svc = app(ResultService::class);
        foreach ([[75, 'A1'], [74, 'B2'], [60, 'C4'], [45, 'D7'], [40, 'E8'], [39, 'F9'], [0, 'F9']] as [$total, $grade]) {
            $this->assertSame($grade, $svc->grade('secondary', $total)[0], "total $total");
        }
    }

    public function test_scores_above_the_maximum_are_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->enter(['A' => [41, 20]]); // CA max is 40
    }

    public function test_negative_scores_are_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->enter(['A' => [-1, 20]]);
    }

    public function test_positions_handle_ties_as_1_2_2_4(): void
    {
        $this->enter(['A' => [30, 50], 'B' => [30, 40], 'C' => [30, 40], 'D' => [10, 10]]);

        $this->assertSame(1, (int) $this->result('A')->position);
        $this->assertSame(2, (int) $this->result('B')->position);
        $this->assertSame(2, (int) $this->result('C')->position);
        $this->assertSame(4, (int) $this->result('D')->position);
    }

    public function test_approved_results_are_locked_against_edits(): void
    {
        $svc = app(ResultService::class);
        $this->enter(['A' => [30, 50]]);
        $svc->submit($this->term->id, $this->subject->id, $this->arm->id);
        $svc->approve($this->term->id, $this->arm->id, 1);

        $out = $this->enter(['A' => [40, 60]]);

        $this->assertContains($this->students['A']->id, $out['locked']);
        $this->assertEquals(80, $this->result('A')->total);
    }

    public function test_unapprove_reopens_results(): void
    {
        $svc = app(ResultService::class);
        $this->enter(['A' => [30, 50]]);
        $svc->submit($this->term->id, $this->subject->id, $this->arm->id);
        $svc->approve($this->term->id, $this->arm->id, 1);
        $svc->unapprove($this->term->id, $this->arm->id);

        $this->assertSame('draft', $this->result('A')->status);
    }

    public function test_parents_cannot_see_unapproved_results(): void
    {
        $this->enter(['A' => [30, 50]]);
        $parent = $this->userWithRole('parent');
        $g = \App\Models\Guardian::create(['first_name' => 'P', 'last_name' => 'T', 'relationship' => 'Father', 'phone' => '0800', 'user_id' => $parent->id]);
        $this->students['A']->guardians()->attach($g->id, ['is_primary' => true]);

        $res = $this->getJson("/api/v1/portal/students/{$this->students['A']->id}/results", $this->tokenFor($parent))->assertOk();

        $this->assertCount(0, $res->json());
    }
}
