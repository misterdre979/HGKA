<?php

namespace App\Console\Commands;

use App\Models\BookLoan;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class LibraryOverdueNotices extends Command
{
    protected $signature = 'hgka:library-overdue';

    protected $description = 'Notify borrowers (or their guardians) of overdue library books, at most every 3 days per loan';

    public function handle(NotificationService $notify): int
    {
        $count = 0;
        $loans = BookLoan::with(['book:id,title', 'student.guardians', 'staff:id,user_id'])
            ->where('status', 'borrowed')->where('due_on', '<', now()->toDateString())->get();

        foreach ($loans as $loan) {
            if (! Cache::add("library-overdue:{$loan->id}", true, now()->addDays(3))) {
                continue;
            }
            $msg = "\"{$loan->book->title}\" was due on {$loan->due_on->format('j M Y')}. Please return it to the library.";

            if ($loan->student) {
                if ($loan->student->user_id) {
                    $notify->toUser($loan->student->user_id, 'Overdue library book', $msg, 'library');
                }
                $notify->toGuardians($loan->student, 'Overdue library book', "{$loan->student->first_name}: ".$msg, 'library', ['app', 'email']);
            } elseif ($loan->staff?->user_id) {
                $notify->toUser($loan->staff->user_id, 'Overdue library book', $msg, 'library');
            }
            $count++;
        }

        if ($count) {
            $notify->toRoles(['librarian'], 'Overdue books', "{$count} loan(s) are overdue.", 'library');
        }
        $this->info("Overdue notices sent for {$count} loan(s).");

        return self::SUCCESS;
    }
}
