<?php

namespace App\Support\Hooks;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LessonNoteHooks
{
    public static function saving(array $data, ?Model $model, Request $request): array
    {
        if (! $model) {
            $staff = Staff::where('user_id', $request->user()->id)->first();
            if ($staff) {
                $data['staff_id'] = $staff->id;
            }
        }

        return $data;
    }
}
