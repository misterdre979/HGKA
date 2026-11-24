<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\FeeStructure;
use App\Models\Scholarship;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Services\InvoiceService;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    private Term $term;
    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        $session = AcademicSession::create(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-01', 'is_current' => true]);
        $this->term = Term::create(['academic_session_id' => $session->id, 'name' => 'First Term', 'sequence' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-18', 'is_current' => true]);
        $this->class = SchoolClass::create(['name' => 'JSS 1', 'level' => 'secondary', 'sequence' => 7]);
        FeeStructure::create(['term_id' => $this->term->id, 'school_class_id' => $this->class->id, 'name' => 'Tuition', 'category' => 'tuition', 'amount' => 100000]);
        FeeStructure::create(['term_id' => $this->term->id, 'school_class_id' => null, 'name' => 'PTA Levy', 'category' => 'pta', 'amount' => 5000]);
    }

    private function student(string $no): Student
    {
        return Student::create(['admission_no' => $no, 'first_name' => $no, 'last_name' => 'T', 'gender' => 'male', 'school_class_id' => $this->class->id, 'status' => 'active']);
    }

    public function test_generates_invoice_from_class_and_global_fee_structures(): void
    {
        $s = $this->student('S1');
        $out = app(InvoiceService::class)->generate($this->term, null, null, null, 1);

        $this->assertSame(1, $out['created']);
        $inv = $s->invoices()->firstOrFail();
        $this->assertEquals(105000, $inv->total);
        $this->assertEquals(105000, $inv->balance);
        $this->assertSame('unpaid', $inv->status);
    }

    public function test_generation_is_idempotent(): void
    {
        $this->student('S1');
        $svc = app(InvoiceService::class);
        $svc->generate($this->term, null, null, null, 1);
        $second = $svc->generate($this->term, null, null, null, 1);

        $this->assertSame(0, $second['created']);
        $this->assertSame(1, \App\Models\Invoice::count());
    }

    public function test_percent_scholarship_reduces_balance(): void
    {
        $s = $this->student('S1');
        Scholarship::create(['student_id' => $s->id, 'name' => 'Staff child', 'type' => 'percent', 'value' => 25, 'is_active' => true]);

        app(InvoiceService::class)->generate($this->term, null, null, null, 1);

        $inv = $s->invoices()->firstOrFail();
        $this->assertEquals(26250, $inv->discount);
        $this->assertEquals(78750, $inv->balance);
    }

    public function test_inactive_students_are_not_invoiced(): void
    {
        $s = $this->student('S1');
        $s->update(['status' => 'graduated']);

        $this->assertSame(0, app(InvoiceService::class)->generate($this->term, null, null, null, 1)['created']);
    }
}
