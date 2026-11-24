<?php

namespace App\Support\Hooks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookHooks
{
    public static function saving(array $data, ?Model $model, Request $request): array
    {
        $total = $data['copies_total'] ?? $model?->copies_total ?? 1;

        if (! $model) {
            $data['copies_available'] = $total;

            return $data;
        }

        if (array_key_exists('copies_total', $data)) {
            $onLoan = $model->copies_total - $model->copies_available;
            if ($data['copies_total'] < $onLoan) {
                throw ValidationException::withMessages([
                    'copies_total' => "{$onLoan} copies are currently on loan; total cannot be lower than that.",
                ]);
            }
            $data['copies_available'] = $data['copies_total'] - $onLoan;
        }

        return $data;
    }

    public static function deleting(Model $model, Request $request): void
    {
        if ($model->copies_total !== $model->copies_available) {
            throw ValidationException::withMessages(['id' => 'This book has copies on loan and cannot be deleted.']);
        }
    }
}
