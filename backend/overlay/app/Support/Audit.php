<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    private const REDACT = ['password', 'remember_token', 'account_number', 'gateway_response'];

    public static function log(string $action, ?Model $model = null, ?array $old = null, ?array $new = null, ?int $userId = null): void
    {
        try {
            $request = request();
            AuditLog::create([
                'user_id' => $userId ?? $request?->user()?->id,
                'action' => $action,
                'auditable_type' => $model ? class_basename($model) : null,
                'auditable_id' => $model?->getKey(),
                'old_values' => $old ? self::clean($old) : null,
                'new_values' => $new ? self::clean($new) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 250) : null,
            ]);
        } catch (\Throwable $e) {
            report($e); // auditing must never break the business operation
        }
    }

    private static function clean(array $values): array
    {
        foreach (self::REDACT as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }
}
