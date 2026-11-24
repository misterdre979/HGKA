<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\Student;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ExamReminders extends Command
{
    protected $signature = 'hgka:exam-reminders';

    protected $description = 'Remind students and guardians about examinations happening tomorrow or in a week';

    public function handle(NotificationService $notify): int
    {
        $sent = 0;
        foreach ([1 => 'tomorrow', 7 => 'in one week'] as $days => $when) {
            $date = now()->addDays($days)->toDateString();
            foreach (Exam::with(['subject:id,name', 'schoolClass:id,name'])->whereDate('exam_date', $date)->where('status', '!=', 'completed')->get() as $exam) {
                if (! Cache::add("exam-reminder:{$exam->id}:{$days}", true, now()->addDays(8))) {
                    continue;
                }
                $title = 'Upcoming examination';
                $body = "{$exam->title}".($exam->subject ? " ({$exam->subject->name})" : '')." is {$when}, ".$exam->exam_date->format('D j M')
                    .($exam->start_time ? ' at '.substr($exam->start_time, 0, 5) : '').($exam->venue ? ", {$exam->venue}" : '').'.';

                $students = Student::with('guardians')->where('status', 'active')
                    ->when($exam->school_class_id, fn ($q) => $q->where('school_class_id', $exam->school_class_id))->get();
                foreach ($students as $student) {
                    if ($student->user_id) {
                        $notify->toUser($student->user_id, $title, $body, 'exam');
                    }
                    $notify->toGuardians($student, $title, $body, 'exam', ['app']);
                }
                $sent++;
            }
        }
        $this->info("Reminders sent for {$sent} exam(s).");

        return self::SUCCESS;
    }
}
