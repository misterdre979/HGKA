<?php

namespace App\Services;

use App\Models\MessageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /** Sends an SMS through the configured driver and records the outcome in message_logs. */
    public function send(string $phone, string $message): bool
    {
        $driver = config('hgka.sms.driver');
        $phone = $this->normalise($phone);

        try {
            if ($driver === 'termii' && config('hgka.sms.api_key')) {
                $res = Http::timeout(15)->post(rtrim(config('hgka.sms.base_url'), '/').'/sms/send', [
                    'to' => $phone,
                    'from' => config('hgka.sms.sender'),
                    'sms' => $message,
                    'type' => 'plain',
                    'channel' => 'generic',
                    'api_key' => config('hgka.sms.api_key'),
                ]);
                $ok = $res->successful();
                $this->record($phone, $message, $ok ? 'sent' : 'failed', $ok ? null : mb_substr($res->body(), 0, 500));

                return $ok;
            }

            Log::info("SMS (driver={$driver}) to {$phone}: {$message}");
            $this->record($phone, $message, 'skipped', 'No SMS provider configured (SMS_DRIVER=log).');

            return false;
        } catch (\Throwable $e) {
            $this->record($phone, $message, 'failed', $e->getMessage());

            return false;
        }
    }

    /** Converts local Nigerian numbers (080...) to international format (23480...). */
    public function normalise(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '234'.substr($digits, 1);
        }

        return $digits;
    }

    private function record(string $to, string $body, string $status, ?string $error): void
    {
        MessageLog::create(['channel' => 'sms', 'recipient' => $to, 'body' => $body, 'status' => $status, 'error' => $error]);
    }
}
