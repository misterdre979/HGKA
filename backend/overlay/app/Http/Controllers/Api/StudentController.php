<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentPromotion;
use App\Models\User;
use App\Support\Audit;
use App\Support\IdGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    /** Admission: creates the student, guardians (reusing existing ones by phone) and optional logins. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'other_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'dob' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'state_of_origin' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:20000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'class_arm_id' => ['nullable', 'integer', 'exists:class_arms,id'],
            'admission_date' => ['nullable', 'date'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'create_account' => ['sometimes', 'boolean'],
            'guardians' => ['sometimes', 'array', 'max:4'],
            'guardians.*.first_name' => ['required', 'string', 'max:255'],
            'guardians.*.last_name' => ['required', 'string', 'max:255'],
            'guardians.*.relationship' => ['required', 'string', 'max:60'],
            'guardians.*.phone' => ['required', 'string', 'max:30'],
            'guardians.*.email' => ['nullable', 'email', 'max:255'],
            'guardians.*.occupation' => ['nullable', 'string', 'max:255'],
            'guardians.*.address' => ['nullable', 'string', 'max:20000'],
            'guardians.*.create_account' => ['sometimes', 'boolean'],
        ]);

        $this->assertArmBelongs($data['school_class_id'], $data['class_arm_id'] ?? null);
        if (($data['create_account'] ?? false) && empty($data['email'])) {
            throw ValidationException::withMessages(['email' => 'An email address is required to create a student login.']);
        }

        $credentials = [];

        $student = DB::transaction(function () use ($data, &$credentials) {
            $year = isset($data['admission_date']) ? (int) substr($data['admission_date'], 0, 4) : (int) date('Y');

            $student = Student::create([
                ...collect($data)->except(['guardians', 'create_account'])->filter(fn ($v) => $v !== null)->all(),
                'admission_no' => IdGenerator::studentNo($year),
                'admission_date' => $data['admission_date'] ?? now()->toDateString(),
                'status' => 'active',
            ]);

            if ($data['create_account'] ?? false) {
                [$user, $temp] = $this->makeUser($data['first_name'].' '.$data['last_name'], $data['email'], 'student');
                $student->update(['user_id' => $user->id]);
                $credentials[] = ['role' => 'Student', 'name' => $user->name, 'email' => $user->email, 'temporary_password' => $temp];
            }

            foreach ($data['guardians'] ?? [] as $i => $g) {
                $guardian = Guardian::where('phone', $g['phone'])->first();
                if (! $guardian) {
                    $guardian = Guardian::create(collect($g)->except('create_account')->all());
                }
                if (($g['create_account'] ?? false) && ! $guardian->user_id) {
                    if (empty($g['email'])) {
                        throw ValidationException::withMessages(["guardians.$i.email" => 'An email is required to create a parent login.']);
                    }
                    $existing = User::where('email', $g['email'])->first();
                    if ($existing) {
                        $existing->roles()->syncWithoutDetaching([Role::where('slug', 'parent')->value('id')]);
                        $guardian->update(['user_id' => $existing->id]);
                    } else {
                        [$user, $temp] = $this->makeUser($g['first_name'].' '.$g['last_name'], $g['email'], 'parent', $g['phone']);
                        $guardian->update(['user_id' => $user->id]);
                        $credentials[] = ['role' => 'Parent/Guardian', 'name' => $user->name, 'email' => $user->email, 'temporary_password' => $temp];
                    }
                }
                $student->guardians()->syncWithoutDetaching([$guardian->id => ['is_primary' => $i === 0]]);
            }

            return $student;
        });

        Audit::log('students.admitted', $student, null, ['admission_no' => $student->admission_no]);

        return response()->json([
            'student' => $student->load(['schoolClass', 'classArm', 'guardians']),
            'credentials' => $credentials,
        ], 201);
    }

    public function profile(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        $student->load(['schoolClass', 'classArm', 'guardians']);

        $attendance = StudentAttendance::where('student_id', $student->id)
            ->where('date', '>=', now()->subDays(90)->toDateString())
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $invoices = $student->invoices()->where('status', '!=', 'cancelled');

        $out = [
            'student' => $student,
            'attendance_90_days' => [
                'present' => (int) ($attendance['present'] ?? 0), 'absent' => (int) ($attendance['absent'] ?? 0),
                'late' => (int) ($attendance['late'] ?? 0), 'excused' => (int) ($attendance['excused'] ?? 0),
            ],
            'results' => $user->hasPermission('exams.view')
                ? \App\Models\ResultSummary::with('term')->where('student_id', $student->id)->orderByDesc('term_id')->get()
                : [],
            'fees' => $user->hasAnyPermission(['fees.view'])
                ? [
                    'billed' => (float) (clone $invoices)->sum('total') - (float) (clone $invoices)->sum('discount'),
                    'paid' => (float) (clone $invoices)->sum('amount_paid'),
                    'balance' => (float) (clone $invoices)->sum('balance'),
                ] : null,
            'attachments' => \App\Models\Attachment::where('attachable_type', 'student')->where('attachable_id', $student->id)->latest()->get(),
        ];

        if ($user->hasPermission('medical.view')) {
            $out['medical_profile'] = $student->medicalProfile;
            $out['medical_visits'] = \App\Models\MedicalVisit::where('student_id', $student->id)->latest('visit_date')->limit(20)->get();
        }
        if ($user->hasPermission('discipline.view')) {
            $out['disciplinary_records'] = \App\Models\DisciplinaryRecord::where('student_id', $student->id)->latest('incident_date')->limit(20)->get();
        }

        return response()->json($out);
    }

    public function photo(Request $request, Student $student): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);

        if ($student->photo_path) {
            Storage::disk('public')->delete($student->photo_path);
        }
        $student->update(['photo_path' => $request->file('photo')->store('students', 'public')]);
        Audit::log('students.photo_updated', $student);

        return response()->json($student->fresh());
    }

    /** Promote, repeat, graduate or transfer a group of students. */
    public function promote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'action' => ['required', Rule::in(['promote', 'repeat', 'graduate', 'transfer'])],
            'to_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'to_arm_id' => ['nullable', 'integer', 'exists:class_arms,id'],
            'academic_session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
        ]);

        if ($data['action'] === 'promote' && ! empty($data['to_arm_id']) && ! empty($data['to_class_id'])) {
            $this->assertArmBelongs($data['to_class_id'], $data['to_arm_id'], 'to_arm_id');
        }
        $sessionId = $data['academic_session_id'] ?? AcademicSession::where('is_current', true)->value('id');

        $summary = ['promoted' => 0, 'repeated' => 0, 'graduated' => 0, 'transferred' => 0, 'skipped' => 0];

        DB::transaction(function () use ($data, $sessionId, $request, &$summary) {
            foreach (Student::whereIn('id', $data['student_ids'])->get() as $student) {
                if ($student->status !== 'active') {
                    $summary['skipped']++;
                    continue;
                }

                $record = [
                    'student_id' => $student->id, 'from_class_id' => $student->school_class_id,
                    'from_arm_id' => $student->class_arm_id, 'academic_session_id' => $sessionId,
                    'promoted_by' => $request->user()->id,
                ];

                switch ($data['action']) {
                    case 'promote':
                        $toClass = $data['to_class_id'] ?? $this->nextClassId($student);
                        if (! $toClass) { // top of the school: graduate
                            $student->update(['status' => 'graduated']);
                            StudentPromotion::create([...$record, 'type' => 'graduated']);
                            $summary['graduated']++;
                            break;
                        }
                        $student->update(['school_class_id' => $toClass, 'class_arm_id' => $data['to_arm_id'] ?? null]);
                        StudentPromotion::create([...$record, 'to_class_id' => $toClass, 'to_arm_id' => $data['to_arm_id'] ?? null, 'type' => 'promoted']);
                        $summary['promoted']++;
                        break;
                    case 'repeat':
                        StudentPromotion::create([...$record, 'to_class_id' => $student->school_class_id, 'to_arm_id' => $student->class_arm_id, 'type' => 'repeated']);
                        $summary['repeated']++;
                        break;
                    case 'graduate':
                        $student->update(['status' => 'graduated']);
                        StudentPromotion::create([...$record, 'type' => 'graduated']);
                        $summary['graduated']++;
                        break;
                    case 'transfer':
                        $student->update(['status' => 'transferred']);
                        StudentPromotion::create([...$record, 'type' => 'transferred']);
                        $summary['transferred']++;
                        break;
                }
            }
        });

        Audit::log('students.promotion', null, null, ['action' => $data['action'], ...$summary]);

        return response()->json($summary);
    }

    private function nextClassId(Student $student): ?int
    {
        $current = SchoolClass::find($student->school_class_id);

        return SchoolClass::where('is_active', true)->where('sequence', '>', $current->sequence)
            ->orderBy('sequence')->value('id');
    }

    private function assertArmBelongs(int $classId, ?int $armId, string $field = 'class_arm_id'): void
    {
        if ($armId && ! ClassArm::where('id', $armId)->where('school_class_id', $classId)->exists()) {
            throw ValidationException::withMessages([$field => 'The selected arm does not belong to the selected class.']);
        }
    }

    /** @return array{0: User, 1: string} */
    private function makeUser(string $name, string $email, string $role, ?string $phone = null): array
    {
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => "The email {$email} is already used by another account."]);
        }
        $temp = UserController::temporaryPassword();
        $user = User::create(['name' => $name, 'email' => $email, 'phone' => $phone, 'password' => $temp,
            'is_active' => true, 'must_change_password' => true]);
        $user->roles()->sync([Role::where('slug', $role)->value('id')]);

        return [$user, $temp];
    }
}
