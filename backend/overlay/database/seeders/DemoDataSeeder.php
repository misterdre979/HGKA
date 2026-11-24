<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookLoan;
use App\Models\CalendarEvent;
use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Driver;
use App\Models\Exam;
use App\Models\Expense;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\Scholarship;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Models\Supplier;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\TransportAllocation;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ResultService;
use App\Support\IdGenerator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Sample data for demos and manual testing. Not run by default.
 * All demo accounts use the password in PASSWORD below: never load this seeder in production.
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'Demo@1234';

    private Term $term;
    private array $teachers = [];
    private array $staffByRole = [];
    private array $students = [];

    public function run(): void
    {
        mt_srand(2026);
        config(['mail.default' => 'array']); // never send real email while seeding

        $this->academic();
        $this->subjects();
        $this->staff();
        $this->students();
        $this->assignTeachers();
        $this->fees();
        $this->attendance();
        $this->results();
        $this->library();
        $this->inventory();
        $this->transportAndHostel();
        $this->hr();
        $this->communication();

        $this->command?->info('Demo data loaded. Demo logins use password: '.self::PASSWORD);
        $this->command?->table(['Role', 'Email'], [
            ['Principal', 'principal@hgka.test'], ['Bursar', 'bursar@hgka.test'], ['Accountant', 'accountant@hgka.test'],
            ['Teacher', 'teacher1@hgka.test'], ['Librarian', 'librarian@hgka.test'], ['HR Officer', 'hr@hgka.test'],
            ['Parent', 'parent1@hgka.test'], ['Student', 'student1@hgka.test'],
        ]);
    }

    // ------------------------------------------------------------------ academic structure
    private function academic(): void
    {
        $session = AcademicSession::updateOrCreate(['name' => '2026/2027'], ['starts_on' => '2026-09-14', 'ends_on' => '2027-07-23', 'is_current' => true]);
        foreach ([
            [1, 'First Term', '2026-09-14', '2026-12-18', true],
            [2, 'Second Term', '2027-01-11', '2027-04-02', false],
            [3, 'Third Term', '2027-04-26', '2027-07-23', false],
        ] as [$seq, $name, $start, $end, $current]) {
            Term::updateOrCreate(['academic_session_id' => $session->id, 'sequence' => $seq], ['name' => $name, 'starts_on' => $start, 'ends_on' => $end, 'is_current' => $current]);
        }
        $this->term = Term::where('is_current', true)->firstOrFail();

        foreach (['JSS 1', 'Primary 1'] as $class) {
            ClassArm::firstOrCreate(['school_class_id' => SchoolClass::where('name', $class)->value('id'), 'name' => 'B'], ['capacity' => 30]);
        }
    }

    private function subjects(): void
    {
        $subjects = [
            ['English Language', 'ENG', 'all'], ['Mathematics', 'MTH', 'all'], ['Computer Studies', 'CMP', 'all'],
            ['Civic Education', 'CVE', 'all'], ['Social Studies', 'SOS', 'all'], ['Christian Religious Studies', 'CRS', 'all'],
            ['Physical & Health Education', 'PHE', 'all'],
            ['Basic Science', 'BSC', 'secondary'], ['Agricultural Science', 'AGR', 'secondary'], ['Business Studies', 'BUS', 'secondary'],
            ['Basic Science & Technology', 'BST', 'primary'], ['Creative Arts', 'CRA', 'primary'], ['Verbal Reasoning', 'VRS', 'primary'],
            ['Numeracy', 'NUM', 'nursery'], ['Literacy', 'LIT', 'nursery'], ['Rhymes & Songs', 'RHY', 'nursery'], ['Colouring & Craft', 'COL', 'nursery'],
        ];
        foreach ($subjects as [$name, $code, $level]) {
            Subject::updateOrCreate(['code' => $code], ['name' => $name, 'level' => $level, 'is_core' => in_array($code, ['ENG', 'MTH', 'NUM', 'LIT'], true)]);
        }

        foreach (SchoolClass::all() as $class) {
            $ids = Subject::where(fn ($q) => $class->level === 'nursery'
                ? $q->where('level', 'nursery')
                : $q->where('level', 'all')->orWhere('level', $class->level))->pluck('id');
            foreach ($ids as $id) {
                ClassSubject::firstOrCreate(['school_class_id' => $class->id, 'subject_id' => $id]);
            }
        }
    }

    // ------------------------------------------------------------------ people
    private function person(string $first, string $last, string $gender, string $category, ?string $role, string $email, string $designation, string $dept, float $basic): Staff
    {
        $department = Department::firstOrCreate(['name' => $dept]);
        $desig = Designation::firstOrCreate(['name' => $designation], ['department_id' => $department->id]);

        $user = null;
        if ($role) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => "$first $last", 'password' => self::PASSWORD, 'is_active' => true, 'must_change_password' => false,
            ]);
            $user->roles()->sync([Role::where('slug', $role)->value('id')]);
        }

        $staff = Staff::firstOrCreate(['email' => $email], [
            'user_id' => $user?->id, 'staff_no' => IdGenerator::staffNo(), 'first_name' => $first, 'last_name' => $last,
            'gender' => $gender, 'category' => $category, 'department_id' => $department->id, 'designation_id' => $desig->id,
            'qualification' => 'B.Ed', 'employment_type' => 'permanent', 'employment_date' => '2022-09-05', 'status' => 'active',
            'phone' => '0803'.mt_rand(1000000, 9999999), 'bank_name' => 'GTBank', 'account_number' => (string) mt_rand(2000000000, 2999999999),
        ]);

        $structure = SalaryStructure::firstOrCreate(['staff_id' => $staff->id], ['basic_salary' => $basic, 'effective_from' => '2026-01-01']);
        if (! SalaryComponent::where('staff_id', $staff->id)->exists()) {
            SalaryComponent::create(['staff_id' => $staff->id, 'type' => 'allowance', 'name' => 'Housing', 'amount' => 20, 'is_percent' => true]);
            SalaryComponent::create(['staff_id' => $staff->id, 'type' => 'allowance', 'name' => 'Transport', 'amount' => 15000, 'is_percent' => false]);
            SalaryComponent::create(['staff_id' => $staff->id, 'type' => 'deduction', 'name' => 'Pension (8%)', 'amount' => 8, 'is_percent' => true]);
            SalaryComponent::create(['staff_id' => $staff->id, 'type' => 'deduction', 'name' => 'PAYE tax', 'amount' => 5, 'is_percent' => true]);
        }

        return $staff;
    }

    private function staff(): void
    {
        $teachers = [
            ['Adaeze', 'Okafor', 'female'], ['Tunde', 'Bakare', 'male'], ['Ngozi', 'Eze', 'female'], ['Samuel', 'Adeyemi', 'male'],
            ['Funke', 'Balogun', 'female'], ['Chidi', 'Nwosu', 'male'], ['Amina', 'Yusuf', 'female'], ['Emeka', 'Obi', 'male'],
        ];
        foreach ($teachers as $i => [$f, $l, $g]) {
            $this->teachers[] = $this->person($f, $l, $g, 'teaching', 'teacher', 'teacher'.($i + 1).'@hgka.test', 'Class Teacher', 'Academics', 120000);
        }

        $others = [
            'principal' => ['Grace', 'Adebayo', 'female', 'teaching', 'principal', 'principal@hgka.test', 'Principal', 'Administration', 350000],
            'vice' => ['Daniel', 'Ibe', 'male', 'teaching', 'vice-principal', 'viceprincipal@hgka.test', 'Vice Principal', 'Administration', 280000],
            'admin' => ['Joy', 'Ogunleye', 'female', 'non_teaching', 'school-admin', 'admin@hgka.test', 'School Administrator', 'Administration', 200000],
            'accountant' => ['Kunle', 'Afolabi', 'male', 'non_teaching', 'accountant', 'accountant@hgka.test', 'Accountant', 'Finance', 180000],
            'bursar' => ['Ify', 'Chukwu', 'female', 'non_teaching', 'bursar', 'bursar@hgka.test', 'Bursar', 'Finance', 220000],
            'librarian' => ['Halima', 'Sani', 'female', 'non_teaching', 'librarian', 'librarian@hgka.test', 'Librarian', 'Administration', 110000],
            'nurse' => ['Bola', 'Akinde', 'female', 'non_teaching', 'school-nurse', 'nurse@hgka.test', 'School Nurse', 'Administration', 130000],
            'transport' => ['Musa', 'Danjuma', 'male', 'non_teaching', 'transport-manager', 'transport@hgka.test', 'Transport Manager', 'Administration', 120000],
            'hr' => ['Ruth', 'Okon', 'female', 'non_teaching', 'hr-officer', 'hr@hgka.test', 'HR Officer', 'Administration', 170000],
        ];
        foreach ($others as $key => [$f, $l, $g, $cat, $role, $email, $desig, $dept, $basic]) {
            $this->staffByRole[$key] = $this->person($f, $l, $g, $cat, $role, $email, $desig, $dept, $basic);
        }
    }

    private function students(): void
    {
        $male = ['Chinedu', 'Ibrahim', 'Tobi', 'David', 'Ifeanyi', 'Segun', 'Daniel', 'Kelechi', 'Musa', 'Victor', 'Emmanuel', 'Peter'];
        $female = ['Chioma', 'Aisha', 'Tolu', 'Blessing', 'Ngozi', 'Zainab', 'Favour', 'Kemi', 'Precious', 'Hauwa', 'Esther', 'Mary'];
        $surnames = ['Okafor', 'Bello', 'Adeyemi', 'Eze', 'Lawal', 'Nwachukwu', 'Ogundipe', 'Abubakar', 'Olawale', 'Ikpe', 'Okoro', 'Sanni'];

        $arms = ['JSS 1' => 'A', 'JSS 2' => 'A', 'Primary 1' => 'A', 'Primary 4' => 'A'];
        $ages = ['JSS 1' => 11, 'JSS 2' => 12, 'Primary 1' => 6, 'Primary 4' => 9];
        $n = 0;

        foreach ($arms as $className => $armName) {
            $class = SchoolClass::where('name', $className)->first();
            $arm = ClassArm::where(['school_class_id' => $class->id, 'name' => $armName])->first();

            for ($i = 0; $i < 8; $i++) {
                $n++;
                $isFemale = $i % 2 === 1;
                $first = ($isFemale ? $female : $male)[($n * 3) % 12];
                $last = $surnames[($n * 5) % 12];

                $student = Student::create([
                    'admission_no' => IdGenerator::studentNo(2026), 'first_name' => $first, 'last_name' => $last,
                    'gender' => $isFemale ? 'female' : 'male', 'dob' => Carbon::now()->subYears($ages[$className])->subDays(mt_rand(0, 300))->toDateString(),
                    'nationality' => 'Nigerian', 'state_of_origin' => ['Lagos', 'Ogun', 'Anambra', 'Kano', 'Oyo'][$n % 5], 'religion' => $n % 3 === 0 ? 'Islam' : 'Christianity',
                    'address' => (10 + $n).' School Road, Ikeja, Lagos', 'school_class_id' => $class->id, 'class_arm_id' => $arm->id,
                    'admission_date' => '2026-09-14', 'status' => 'active',
                ]);

                $guardian = Guardian::create([
                    'first_name' => $n % 2 ? 'Mr' : 'Mrs', 'last_name' => $last, 'relationship' => $n % 2 ? 'Father' : 'Mother',
                    'phone' => '0803'.str_pad((string) (1000000 + $n * 137), 7, '0', STR_PAD_LEFT), 'email' => "family{$n}@example.test", 'occupation' => 'Trader',
                ]);
                $student->guardians()->attach($guardian->id, ['is_primary' => true]);

                if ($n <= 5) { // first five families get parent logins
                    $user = User::updateOrCreate(['email' => "parent{$n}@hgka.test"], [
                        'name' => "{$guardian->first_name} {$last}", 'password' => self::PASSWORD, 'is_active' => true, 'must_change_password' => false,
                    ]);
                    $user->roles()->sync([Role::where('slug', 'parent')->value('id')]);
                    $guardian->update(['user_id' => $user->id]);
                }
                if ($n <= 2) { // two student logins
                    $user = User::updateOrCreate(['email' => "student{$n}@hgka.test"], [
                        'name' => "$first $last", 'password' => self::PASSWORD, 'is_active' => true, 'must_change_password' => false,
                    ]);
                    $user->roles()->sync([Role::where('slug', 'student')->value('id')]);
                    $student->update(['user_id' => $user->id]);
                }
                $this->students[$className][] = $student;
            }
        }
    }

    private function assignTeachers(): void
    {
        $t = 0;
        foreach (['JSS 1', 'JSS 2', 'Primary 1', 'Primary 4'] as $i => $className) {
            $class = SchoolClass::where('name', $className)->first();
            $arm = ClassArm::where(['school_class_id' => $class->id, 'name' => 'A'])->first();
            $arm->update(['form_teacher_id' => $this->teachers[$i]->id]);

            foreach (ClassSubject::where('school_class_id', $class->id)->pluck('subject_id') as $subjectId) {
                TeacherAssignment::firstOrCreate([
                    'staff_id' => $this->teachers[$t % count($this->teachers)]->id, 'subject_id' => $subjectId, 'class_arm_id' => $arm->id,
                ]);
                $t++;
            }
        }
    }

    // ------------------------------------------------------------------ finance
    private function fees(): void
    {
        foreach (SchoolClass::all() as $class) {
            $tuition = ['nursery' => 65000, 'primary' => 85000, 'secondary' => 120000][$class->level];
            FeeStructure::firstOrCreate(['term_id' => $this->term->id, 'school_class_id' => $class->id, 'name' => 'Tuition'], ['category' => 'tuition', 'amount' => $tuition]);
            if ($class->level === 'secondary') {
                FeeStructure::firstOrCreate(['term_id' => $this->term->id, 'school_class_id' => $class->id, 'name' => 'Textbooks'], ['category' => 'books', 'amount' => 20000]);
            }
        }
        FeeStructure::firstOrCreate(['term_id' => $this->term->id, 'school_class_id' => null, 'name' => 'PTA Levy'], ['category' => 'pta', 'amount' => 5000]);
        FeeStructure::firstOrCreate(['term_id' => $this->term->id, 'school_class_id' => null, 'name' => 'Development Levy'], ['category' => 'development', 'amount' => 10000]);

        $first = $this->students['JSS 1'][0];
        Scholarship::firstOrCreate(['student_id' => $first->id, 'name' => 'Staff child discount'], ['type' => 'percent', 'value' => 25, 'is_active' => true]);

        $bursar = User::where('email', 'bursar@hgka.test')->first();
        app(InvoiceService::class)->generate($this->term, null, null, now()->addDays(10)->toDateString(), $bursar->id);

        // Part and full payments for most invoices.
        $payments = app(PaymentService::class);
        foreach (Invoice::orderBy('id')->get() as $i => $invoice) {
            if ($i % 5 === 4) {
                continue; // leave some completely unpaid
            }
            $net = (float) $invoice->total - (float) $invoice->discount;
            $amount = $i % 3 === 0 ? $net : round($net / 2, 2);
            $payments->recordManual($invoice->fresh(), [
                'method' => ['cash', 'bank_transfer', 'pos', 'cheque'][$i % 4], 'amount' => $amount, 'payer_name' => 'Parent',
                'cheque_no' => $i % 4 === 3 ? 'CHQ'.(100200 + $i) : null, 'bank_name' => 'GTBank',
            ], $bursar);
        }

        foreach ([
            ['Diesel for generator', 'Utilities', 85000, 3], ['Chalk and markers', 'Stationery', 24500, 12], ['Staff welfare', 'Welfare', 40000, 20],
            ['Water bill', 'Utilities', 18000, 33], ['Internet subscription', 'Utilities', 45000, 40], ['Repairs: classroom doors', 'Maintenance', 62000, 52],
            ['Security services', 'Services', 120000, 61], ['Cleaning supplies', 'Maintenance', 21500, 70],
        ] as [$title, $category, $amount, $daysAgo]) {
            Expense::create(['title' => $title, 'category' => $category, 'amount' => $amount, 'expense_date' => now()->subDays($daysAgo)->toDateString(),
                'payment_method' => 'cash', 'recorded_by' => $bursar->id]);
        }
    }

    // ------------------------------------------------------------------ attendance & results
    private function attendance(): void
    {
        $teacher = $this->teachers[0]->user_id;
        $day = Carbon::today();
        $marked = 0;
        while ($marked < 10) {
            if (! $day->isWeekend() && $day->gte($this->term->starts_on)) {
                foreach ($this->students as $list) {
                    foreach ($list as $student) {
                        $roll = mt_rand(1, 100);
                        StudentAttendance::updateOrCreate(['student_id' => $student->id, 'date' => $day->toDateString()], [
                            'class_arm_id' => $student->class_arm_id, 'school_class_id' => $student->school_class_id,
                            'status' => $roll <= 88 ? 'present' : ($roll <= 94 ? 'late' : ($roll <= 99 ? 'absent' : 'excused')), 'marked_by' => $teacher,
                        ]);
                    }
                }
                $marked++;
            }
            $day->subDay();
        }
    }

    private function results(): void
    {
        $service = app(ResultService::class);
        $principal = User::where('email', 'principal@hgka.test')->first();

        foreach (['JSS 1' => true, 'Primary 4' => true, 'JSS 2' => false] as $className => $approve) {
            $class = SchoolClass::where('name', $className)->first();
            $arm = ClassArm::with('schoolClass')->where(['school_class_id' => $class->id, 'name' => 'A'])->first();
            $subjects = ClassSubject::where('school_class_id', $class->id)->pluck('subject_id');
            if (! $approve) {
                $subjects = $subjects->take(1); // JSS 2 stays in "draft" to demonstrate the workflow
            }

            foreach ($subjects as $subjectId) {
                $rows = collect($this->students[$className])->map(fn ($s) => [
                    'student_id' => $s->id, 'ca_score' => mt_rand(18, 38), 'exam_score' => mt_rand(24, 58),
                ])->all();
                $service->saveScores($this->term->id, $subjectId, $arm, $rows, $this->teachers[0]->user_id);
                if ($approve) {
                    $service->submit($this->term->id, $subjectId, $arm->id);
                }
            }
            if ($approve) {
                $service->approve($this->term->id, $arm->id, $principal->id);
            }
        }
    }

    // ------------------------------------------------------------------ operations
    private function library(): void
    {
        $categories = collect(['Fiction', 'Science', 'Mathematics', 'Reference', 'Religion'])->mapWithKeys(fn ($n) => [$n => BookCategory::firstOrCreate(['name' => $n])->id]);
        $books = [
            ['Things Fall Apart', 'Chinua Achebe', 'Fiction'], ['The Lion and the Jewel', 'Wole Soyinka', 'Fiction'], ['Basic Science Vol. 1', 'NERDC', 'Science'],
            ['New General Mathematics 1', 'M. F. Macrae', 'Mathematics'], ['Oxford Advanced Learner\'s Dictionary', 'A. S. Hornby', 'Reference'],
            ['Children\'s Illustrated Bible', 'Various', 'Religion'], ['Efuru', 'Flora Nwapa', 'Fiction'], ['Integrated Science', 'Ayodele', 'Science'],
            ['Essential Mathematics 2', 'A. J. S. Oluwasanmi', 'Mathematics'], ['Atlas for Nigerian Schools', 'Longman', 'Reference'],
            ['Season of Crimson Blossoms', 'Abubakar Adam Ibrahim', 'Fiction'], ['A Dictionary of Science', 'Oxford', 'Reference'],
        ];
        $created = [];
        foreach ($books as $i => [$title, $author, $cat]) {
            $created[] = Book::firstOrCreate(['title' => $title], [
                'author' => $author, 'book_category_id' => $categories[$cat], 'isbn' => '978-'.(1000000000 + $i), 'copies_total' => 4, 'copies_available' => 4,
                'shelf_location' => 'Shelf '.chr(65 + $i % 4),
            ]);
        }

        $librarian = User::where('email', 'librarian@hgka.test')->value('id');
        foreach ([[0, 'JSS 1', 0, -3], [1, 'JSS 1', 1, 8], [4, 'Primary 4', 0, -11]] as [$bookIdx, $class, $studentIdx, $dueOffset]) {
            $book = $created[$bookIdx];
            BookLoan::create([
                'book_id' => $book->id, 'borrower_type' => 'student', 'student_id' => $this->students[$class][$studentIdx]->id,
                'issued_on' => now()->addDays($dueOffset - 14)->toDateString(), 'due_on' => now()->addDays($dueOffset)->toDateString(),
                'status' => 'borrowed', 'issued_by' => $librarian,
            ]);
            $book->decrement('copies_available');
        }
    }

    private function inventory(): void
    {
        $cats = collect(['Stationery', 'Cleaning', 'Laboratory', 'Sports', 'Furniture'])->mapWithKeys(fn ($n) => [$n => InventoryCategory::firstOrCreate(['name' => $n])->id]);
        $supplier = Supplier::firstOrCreate(['name' => 'Ola Stationers Ltd'], ['contact_person' => 'Mr Ola', 'phone' => '08031234567', 'email' => 'sales@olastationers.test']);
        Supplier::firstOrCreate(['name' => 'LabTech Nigeria'], ['contact_person' => 'Mrs Ada', 'phone' => '08098765432']);

        foreach ([
            ['A4 paper (ream)', 'STA-001', 'Stationery', 'ream', 40, 20, 4500], ['Whiteboard markers', 'STA-002', 'Stationery', 'box', 6, 10, 2800],
            ['Exercise books (40 leaves)', 'STA-003', 'Stationery', 'piece', 300, 100, 250], ['Disinfectant (5L)', 'CLN-001', 'Cleaning', 'gallon', 8, 5, 6500],
            ['Beakers (250ml)', 'LAB-001', 'Laboratory', 'piece', 24, 10, 1200], ['Footballs', 'SPT-001', 'Sports', 'piece', 3, 4, 9500],
            ['Student desks', 'FUR-001', 'Furniture', 'piece', 60, 10, 28000], ['Chalk (box)', 'STA-004', 'Stationery', 'box', 18, 10, 900],
        ] as [$name, $sku, $cat, $unit, $qty, $reorder, $cost]) {
            InventoryItem::firstOrCreate(['sku' => $sku], ['name' => $name, 'inventory_category_id' => $cats[$cat], 'unit' => $unit,
                'quantity' => $qty, 'reorder_level' => $reorder, 'unit_cost' => $cost, 'location' => 'Main store']);
        }

        foreach ([['AST-0001', 'Projector (Epson)', 'Furniture', 380000], ['AST-0002', 'School bus (Toyota Hiace)', 'Furniture', 18500000],
            ['AST-0003', 'Generator 20kVA', 'Furniture', 4200000], ['AST-0004', 'Laptop (Teachers\' room)', 'Furniture', 650000]] as [$tag, $name, $cat, $cost]) {
            $asset = Asset::firstOrCreate(['tag' => $tag], ['name' => $name, 'inventory_category_id' => $cats[$cat], 'purchase_date' => '2024-01-15', 'cost' => $cost, 'location' => 'Main building', 'status' => 'active']);
            if ($tag === 'AST-0003') {
                AssetMaintenance::firstOrCreate(['asset_id' => $asset->id, 'maintenance_date' => now()->subDays(40)->toDateString()], ['description' => 'Routine servicing and oil change', 'cost' => 65000, 'performed_by' => 'PowerPro Engineers', 'next_due' => now()->addDays(50)->toDateString()]);
            }
        }
    }

    private function transportAndHostel(): void
    {
        $driver = Driver::firstOrCreate(['name' => 'Mr Sunday Eze'], ['role' => 'driver', 'phone' => '08055501234', 'license_no' => 'LAG-2231-A', 'license_expiry' => now()->addMonths(10)->toDateString(), 'status' => 'active']);
        $attendant = Driver::firstOrCreate(['name' => 'Mrs Patience Udo'], ['role' => 'attendant', 'phone' => '08055505678', 'status' => 'active']);
        $bus = Vehicle::firstOrCreate(['reg_no' => 'LSD-482-KJ'], ['make' => 'Toyota', 'model' => 'Hiace', 'capacity' => 18, 'status' => 'active', 'insurance_expiry' => now()->addMonths(5)->toDateString(), 'driver_id' => $driver->id, 'attendant_id' => $attendant->id]);
        VehicleMaintenance::firstOrCreate(['vehicle_id' => $bus->id, 'maintenance_date' => now()->subDays(25)->toDateString()], ['description' => 'Brake pad replacement', 'cost' => 48000, 'next_due' => now()->addDays(65)->toDateString()]);

        $route = TransportRoute::firstOrCreate(['name' => 'Ikeja - Maryland - School'], ['pickup_points' => "Ikeja Along\nMaryland Mall\nAnthony", 'vehicle_id' => $bus->id, 'fee' => 25000]);
        foreach (array_slice($this->students['JSS 1'], 0, 3) as $s) {
            TransportAllocation::firstOrCreate(['student_id' => $s->id, 'term_id' => $this->term->id], ['transport_route_id' => $route->id, 'pickup_point' => 'Maryland Mall', 'status' => 'active']);
        }

        $hostel = Hostel::firstOrCreate(['name' => 'Grace Boys Hostel'], ['gender' => 'boys', 'fee_per_term' => 90000, 'warden_id' => $this->teachers[1]->id]);
        foreach (['101', '102', '103', '104'] as $no) {
            HostelRoom::firstOrCreate(['hostel_id' => $hostel->id, 'room_no' => $no], ['capacity' => 4, 'type' => 'Dormitory']);
        }
        $room = HostelRoom::where(['hostel_id' => $hostel->id, 'room_no' => '101'])->first();
        $boys = collect($this->students['JSS 2'])->where('gender', 'male')->take(3)->values();
        foreach ($boys as $i => $boy) {
            HostelAllocation::firstOrCreate(['student_id' => $boy->id, 'status' => 'active'], ['hostel_room_id' => $room->id, 'bed_no' => $i + 1, 'allocated_on' => '2026-09-14']);
        }
    }

    private function hr(): void
    {
        // Salary structures were created with each staff record; payroll runs are processed from the Payroll screen.
    }

    private function communication(): void
    {
        $author = User::where('email', 'principal@hgka.test')->value('id');
        Announcement::create(['title' => 'Welcome to the 2026/2027 session', 'body' => 'We warmly welcome all students and parents. First term runs from 14 September to 18 December 2026.', 'audience' => [], 'priority' => 'important', 'is_pinned' => true, 'published_at' => now()->subDays(60), 'created_by' => $author]);
        Announcement::create(['title' => 'First term examinations', 'body' => 'Examinations begin on 30 November. Please ensure all outstanding fees are cleared before then.', 'audience' => ['parent', 'student'], 'priority' => 'urgent', 'published_at' => now()->subDays(2), 'created_by' => $author]);
        Announcement::create(['title' => 'Staff meeting', 'body' => 'All teaching staff: meeting in the staff room on Friday at 2pm.', 'audience' => ['teacher', 'head-of-department', 'vice-principal'], 'priority' => 'normal', 'published_at' => now()->subDay(), 'created_by' => $author]);

        foreach ([
            ['Mid-term break', 'holiday', '2026-10-26', '2026-10-30'], ['PTA meeting', 'meeting', '2026-11-28', null],
            ['First term examinations', 'exam', '2026-11-30', '2026-12-11'], ['Carol service', 'event', '2026-12-16', null], ['End of first term', 'term_end', '2026-12-18', null],
        ] as [$title, $type, $start, $end]) {
            CalendarEvent::firstOrCreate(['title' => $title, 'starts_on' => $start], ['type' => $type, 'ends_on' => $end, 'term_id' => $this->term->id]);
        }

        $eng = Subject::where('code', 'ENG')->value('id');
        $mth = Subject::where('code', 'MTH')->value('id');
        foreach ([
            ['English Language exam', 'JSS 1', $eng, '2026-11-30'], ['Mathematics exam', 'JSS 1', $mth, '2026-12-01'],
            ['English Language exam', 'Primary 4', $eng, '2026-11-30'], ['Mathematics exam', 'Primary 4', $mth, '2026-12-02'],
        ] as [$title, $class, $subject, $date]) {
            Exam::firstOrCreate(['title' => $title, 'school_class_id' => SchoolClass::where('name', $class)->value('id'), 'exam_date' => $date], [
                'type' => 'exam', 'term_id' => $this->term->id, 'subject_id' => $subject, 'max_score' => 60, 'start_time' => '09:00', 'venue' => 'Main hall', 'status' => 'scheduled',
            ]);
        }
    }
}
