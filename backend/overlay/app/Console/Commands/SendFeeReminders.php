<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\NotificationService;
use App\Support\Fmt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendFeeReminders extends Command
{
    protected $signature = 'hgka:fee-reminders {--sms : Also send SMS} {--days=3 : Remind this many days before the due date}';

    protected $description = 'Remind guardians of unpaid or part-paid invoices that are due soon or overdue (at most once a week per invoice)';

    public function handle(NotificationService $notify): int
    {
        $channels = $this->option('sms') ? ['app', 'email', 'sms'] : ['app', 'email'];
        $cutoff = now()->addDays((int) $this->option('days'))->toDateString();
        $sent = 0;

        $invoices = Invoice::with(['student.guardians', 'term:id,name'])->whereIn('status', ['unpaid', 'partial'])
            ->where('balance', '>', 0)->whereDate('due_date', '<=', $cutoff)->get();

        foreach ($invoices as $invoice) {
            if (! Cache::add("fee-reminder:{$invoice->id}", true, now()->addDays(7))) {
                continue;
            }
            $overdue = $invoice->due_date && $invoice->due_date->isPast();
            $notify->toGuardians(
                $invoice->student,
                $overdue ? 'Overdue school fees' : 'School fees reminder',
                "{$invoice->student->first_name}'s {$invoice->term->name} fees balance is ".Fmt::money($invoice->balance)
                    .($overdue ? ' and is overdue.' : ' due on '.$invoice->due_date->format('j M Y').'.').' Invoice '.$invoice->number.'.',
                'fee-reminder', $channels, ['invoice_id' => $invoice->id]
            );
            $sent++;
        }

        $this->info("Fee reminders sent for {$sent} invoice(s).");

        return self::SUCCESS;
    }
}
