<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\MessageLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/** In-app, email and SMS notifications. External channels never throw; failures are logged. */
class NotificationService
{
    public function __construct(private SmsService $sms)
    {
    }

    public function toUser(User|int $user, string $title, string $body = '', string $type = 'general', array $data = []): void
    {
        AppNotification::create([
            'user_id' => $user instanceof User ? $user->id : $user,
            'title' => $title, 'body' => $body, 'type' => $type, 'data' => $data ?: null,
        ]);
    }

    /** @param  string[]  $roleSlugs */
    public function toRoles(array $roleSlugs, string $title, string $body = '', string $type = 'general', array $data = []): int
    {
        $ids = User::where('is_active', true)->whereHas('roles', fn ($q) => $q->whereIn('slug', $roleSlugs))->pluck('id');
        foreach ($ids as $id) {
            $this->toUser($id, $title, $body, $type, $data);
        }

        return $ids->count();
    }

    public function email(string $to, string $subject, string $body): bool
    {
        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
            MessageLog::create(['channel' => 'email', 'recipient' => $to, 'subject' => $subject, 'body' => $body, 'status' => 'sent']);

            return true;
        } catch (\Throwable $e) {
            MessageLog::create(['channel' => 'email', 'recipient' => $to, 'subject' => $subject, 'body' => $body,
                'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return false;
        }
    }

    public function sms(string $phone, string $message): bool
    {
        return $this->sms->send($phone, $message);
    }

    /**
     * Notify every guardian of a student. $channels may include 'app', 'email', 'sms'.
     */
    public function toGuardians(Student $student, string $title, string $body, string $type = 'general', array $channels = ['app'], array $data = []): int
    {
        $count = 0;
        foreach ($student->guardians()->get() as $guardian) {
            if (in_array('app', $channels, true) && $guardian->user_id) {
                $this->toUser($guardian->user_id, $title, $body, $type, $data);
            }
            if (in_array('email', $channels, true) && $guardian->email) {
                $this->email($guardian->email, $title, $body);
            }
            if (in_array('sms', $channels, true) && $guardian->phone) {
                $this->sms($guardian->phone, $body);
            }
            $count++;
        }

        return $count;
    }
}
