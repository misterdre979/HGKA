<?php

namespace App\Support\Hooks;

use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HostelAllocationHooks
{
    public static function saving(array $data, ?Model $model, Request $request): array
    {
        $get = fn (string $k) => $data[$k] ?? $model?->{$k};
        $status = $get('status') ?? 'active';

        if ($status === 'vacated') {
            $data['vacated_on'] ??= now()->toDateString();

            return $data;
        }

        $room = HostelRoom::findOrFail($get('hostel_room_id'));
        $bed = (int) $get('bed_no');

        if ($bed > $room->capacity) {
            throw ValidationException::withMessages(['bed_no' => "Room {$room->room_no} only has {$room->capacity} beds."]);
        }

        $active = HostelAllocation::where('status', 'active')->when($model, fn ($q) => $q->where('id', '!=', $model->id));

        if ((clone $active)->where('hostel_room_id', $room->id)->where('bed_no', $bed)->exists()) {
            throw ValidationException::withMessages(['bed_no' => 'That bed is already occupied.']);
        }
        if ((clone $active)->where('student_id', $get('student_id'))->exists()) {
            throw ValidationException::withMessages(['student_id' => 'This student already has an active bed allocation.']);
        }

        return $data;
    }
}
