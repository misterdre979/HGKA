<?php

namespace App\Support\Hooks;

use App\Models\AcademicSession;
use App\Models\Term;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Shared by academic sessions and terms: date sanity and protection of the current period. */
class AcademicHooks
{
    public static function saving(array $data, ?Model $model, Request $request): array
    {
        $start = $data['starts_on'] ?? $model?->starts_on?->toDateString();
        $end = $data['ends_on'] ?? $model?->ends_on?->toDateString();

        if ($start && $end && $end <= $start) {
            throw ValidationException::withMessages(['ends_on' => 'The end date must be after the start date.']);
        }

        return $data;
    }

    public static function deleting(Model $model, Request $request): void
    {
        if (($model instanceof AcademicSession || $model instanceof Term) && $model->is_current) {
            throw ValidationException::withMessages(['id' => 'The current period cannot be deleted. Make another one current first.']);
        }
    }
}
