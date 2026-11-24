<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use Tests\TestCase;

class RbacTest extends TestCase
{
    public function test_roles_are_seeded(): void
    {
        $this->assertSame(14, \App\Models\Role::count());
    }

    public function test_teacher_cannot_manage_users_or_roles(): void
    {
        $h = $this->tokenFor($this->userWithRole('teacher'));

        $this->getJson('/api/v1/users', $h)->assertForbidden();
        $this->postJson('/api/v1/roles', ['name' => 'X'], $h)->assertForbidden();
        $this->getJson('/api/v1/backups', $h)->assertForbidden();
    }

    public function test_parent_cannot_list_all_students(): void
    {
        $this->getJson('/api/v1/students', $this->tokenFor($this->userWithRole('parent')))->assertForbidden();
    }

    public function test_super_admin_can_do_everything(): void
    {
        $h = $this->tokenFor($this->userWithRole('super-admin'));

        $this->getJson('/api/v1/users', $h)->assertOk();
        $this->getJson('/api/v1/roles', $h)->assertOk();
    }

    public function test_accountant_cannot_edit_students_but_can_view_fees(): void
    {
        $h = $this->tokenFor($this->userWithRole('accountant'));

        $this->postJson('/api/v1/students', ['first_name' => 'A', 'last_name' => 'B'], $h)->assertForbidden();
        $this->getJson('/api/v1/invoices', $h)->assertOk();
    }

    public function test_parent_sees_only_their_own_children(): void
    {
        $class = SchoolClass::create(['name' => 'JSS 1', 'level' => 'secondary', 'sequence' => 7]);
        $mine = Student::create(['admission_no' => 'A1', 'first_name' => 'Mine', 'last_name' => 'Child', 'gender' => 'male', 'school_class_id' => $class->id, 'status' => 'active']);
        $other = Student::create(['admission_no' => 'A2', 'first_name' => 'Other', 'last_name' => 'Child', 'gender' => 'male', 'school_class_id' => $class->id, 'status' => 'active']);

        $parent = $this->userWithRole('parent');
        $g = \App\Models\Guardian::create(['first_name' => 'P', 'last_name' => 'Parent', 'relationship' => 'Father', 'phone' => '08000000000', 'user_id' => $parent->id]);
        $mine->guardians()->attach($g->id, ['is_primary' => true]);
        $h = $this->tokenFor($parent);

        $this->getJson("/api/v1/portal/students/{$mine->id}", $h)->assertOk();
        $this->getJson("/api/v1/portal/students/{$other->id}", $h)->assertForbidden();
        $this->assertCount(1, $this->getJson('/api/v1/portal/students', $h)->json());
    }

    public function test_validation_errors_are_422_json(): void
    {
        $h = $this->tokenFor($this->userWithRole('super-admin'));

        $this->postJson('/api/v1/subjects', [], $h)->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }
}
