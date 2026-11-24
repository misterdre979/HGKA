<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Services\NotificationService;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = LeaveRequest::with(['staff:id,first_name,last_name,staff_no'])->latest('starts_on');

        if (! $user->hasPermission('leave.view')) {
            $staff = Staff::where('user_id', $user->id)->first();
            abort_unless($staff, 403, 'Your account is not linked to a staff record.');
            $q->where('staff_id', $staff->id);
        } elseif ($request->filled('staff_id')) {
            $q->where('staff_id', $request->query('staff_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }

        return response()->json($q->paginate(min((int) $request->query('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'type' => ['required', Rule::in(['annual', 'sick', 'maternity', 'paternity', 'casual', 'study', 'other'])],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $own = Staff::where('user_id', $user->id)->first();
        $staffId = $data['staff_id'] ?? $own?->id;
        abort_unless($staffId, 422, 'Your account is not linked to a staff record.');
        if ($own?->id !== (int) $staffId) {
            abort_unless($user->hasPermission('leave.approve'), 403, 'You can only apply for leave for yourself.');
        } else {
            abort_unless($user->hasAnyPermission(['leave.apply', 'leave.approve']), 403, 'You do not have permission to perform this action.');
        }

        $overlap = LeaveRequest::where('staff_id', $staffId)->whereIn('status', ['pending', 'approved'])
            ->where('starts_on', '<=', $data['ends_on'])->where('ends_on', '>=', $data['starts_on'])->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_on' => 'This staff member already has leave covering part of that period.']);
        }

        $leave = LeaveRequest::create([
            ...$data, 'staff_id' => $staffId, 'status' => 'pending',
            'days' => Carbon::parse($data['starts_on'])->diffInDays(Carbon::parse($data['ends_on'])) + 1,
        ]);

        app(NotificationService::class)->toRoles(
            ['principal', 'hr-officer', 'school-admin'], 'New leave request',
            ($leave->staff->first_name ?? 'A staff member').' requested '.$leave->days.' day(s) of '.$leave->type.' leave.', 'leave'
        );
        Audit::log('leave.requested', $leave);

        return response()->json($leave->load('staff:id,first_name,last_name,staff_no'), 201);
    }

    public function decide(Request $request, LeaveRequest $leave): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'string', 'max:1000']]);
        abort_if($leave->status !== 'pending', 422, 'This request has already been decided.');

        $leave->update([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
            'decided_by' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $data['note'] ?? null,
        ]);

        if ($userId = $leave->staff?->user_id) {
            app(NotificationService::class)->toUser($userId, 'Leave request '.$leave->status,
                'Your '.$leave->type.' leave ('.$leave->starts_on->toDateString().' to '.$leave->ends_on->toDateString().') was '.$leave->status.'.', 'leave');
        }
        Audit::log('leave.'.$leave->status, $leave);

        return response()->json($leave->fresh('staff:id,first_name,last_name,staff_no'));
    }

    public function cancel(Request $request, LeaveRequest $leave): JsonResponse
    {
        $own = Staff::where('user_id', $request->user()->id)->first();
        abort_unless($own && $own->id === $leave->staff_id, 403, 'You can only cancel your own requests.');
        abort_if($leave->status !== 'pending', 422, 'Only pending requests can be cancelled.');
        $leave->update(['status' => 'cancelled']);

        return response()->json($leave);
    }
}
