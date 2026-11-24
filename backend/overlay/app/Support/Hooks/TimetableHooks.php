<?php

namespace App\Support\Hooks;

use App\Models\TimetableEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimetableHooks
{
    private static function t(?string $v): ?string
    {
        return $v && strlen($v) === 5 ? $v.':00' : $v;
    }

    public static function saving(array $data, ?Model $model, Request $request): array
    {
        $get = fn (string $k) => $data[$k] ?? $model?->{$k};
        $start = self::t($get('start_time'));
        $end = self::t($get('end_time'));
        $day = $get('day_of_week');
        $arm = $get('class_arm_id');
        $staff = $get('staff_id');

        if ($start && $end && $end <= $start) {
            throw ValidationException::withMessages(['end_time' => 'The end time must be after the start time.']);
        }

        $overlap = fn () => TimetableEntry::where('day_of_week', $day)
            ->where('start_time', '<', $end)->where('end_time', '>', $start)
            ->when($model, fn ($q) => $q->where('id', '!=', $model->id));

        if ($arm && $overlap()->where('class_arm_id', $arm)->exists()) {
            throw ValidationException::withMessages(['start_time' => 'This class already has a lesson at that time.']);
        }
        if ($staff && $overlap()->where('staff_id', $staff)->exists()) {
            throw ValidationException::withMessages(['staff_id' => 'This teacher is already teaching another class at that time.']);
        }

        return $data;
    }
}
