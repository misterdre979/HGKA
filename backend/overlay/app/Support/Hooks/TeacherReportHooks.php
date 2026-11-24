<?php

namespace App\Support\Hooks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TeacherReportHooks
{
    public static function saving(array $data, ?Model $model, Request $request): array
    {
        $reviewer = $request->user()->hasPermission('teacher-reports.review');

        if (! $reviewer) {
            unset($data['status'], $data['reviewer_note']); // authors cannot mark their own report reviewed
        } elseif (($data['status'] ?? null) === 'reviewed') {
            $data['reviewed_by'] = $request->user()->id;
        }

        return $data;
    }
}
