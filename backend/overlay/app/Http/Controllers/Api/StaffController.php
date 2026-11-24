<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffPerformanceRecord;
use App\Models\User;
use App\Support\Audit;
use App\Support\IdGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'other_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'dob' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:20000'],
            'category' => ['required', Rule::in(['teaching', 'non_teaching'])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', Rule::in(['permanent', 'contract', 'part_time'])],
            'employment_date' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:20'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'create_account' => ['sometimes', 'boolean'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'slug')],
        ]);

        $wantsAccount = (bool) ($data['create_account'] ?? false);
        if ($wantsAccount && empty($data['email'])) {
            throw ValidationException::withMessages(['email' => 'An email address is required to create a login.']);
        }
        if ($wantsAccount && User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'That email is already used by another account.']);
        }
        $role = $data['role'] ?? ($data['category'] === 'teaching' ? 'teacher' : null);
        if ($role === 'super-admin' && ! $request->user()->hasRole('super-admin')) {
            abort(403, 'Only a super administrator can assign the super administrator role.');
        }

        $credentials = null;
        $staff = DB::transaction(function () use ($data, $wantsAccount, $role, &$credentials) {
            $staff = Staff::create([
                ...collect($data)->except(['create_account', 'role'])->filter(fn ($v) => $v !== null)->all(),
                'staff_no' => IdGenerator::staffNo(),
                'status' => 'active',
            ]);

            if ($wantsAccount) {
                $temp = UserController::temporaryPassword();
                $user = User::create([
                    'name' => $staff->first_name.' '.$staff->last_name, 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                    'password' => $temp, 'is_active' => true, 'must_change_password' => true,
                ]);
                if ($role) {
                    $user->roles()->sync([Role::where('slug', $role)->value('id')]);
                }
                $staff->update(['user_id' => $user->id]);
                $credentials = ['name' => $user->name, 'email' => $user->email, 'temporary_password' => $temp, 'role' => $role];
            }

            return $staff;
        });

        Audit::log('staff.created', $staff, null, ['staff_no' => $staff->staff_no]);

        return response()->json(['staff' => $staff->load(['department', 'designation']), 'credentials' => $credentials], 201);
    }

    public function profile(Request $request, Staff $staff): JsonResponse
    {
        $user = $request->user();
        $staff->load(['department', 'designation', 'assignments.subject', 'assignments.classArm.schoolClass']);

        $out = [
            'staff' => $staff,
            'leave_requests' => LeaveRequest::where('staff_id', $staff->id)->latest('starts_on')->limit(20)->get(),
            'performance' => StaffPerformanceRecord::with('term')->where('staff_id', $staff->id)->latest()->limit(10)->get(),
            'attachments' => Attachment::where('attachable_type', 'staff')->where('attachable_id', $staff->id)->latest()->get(),
        ];
        if ($user->hasAnyPermission(['payroll.view'])) {
            $out['salary'] = $staff->salaryStructure()->first()?->toArray();
            $out['salary_components'] = $staff->salaryComponents()->get();
        }

        return response()->json($out);
    }

    public function photo(Request $request, Staff $staff): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);

        if ($staff->photo_path) {
            Storage::disk('public')->delete($staff->photo_path);
        }
        $staff->update(['photo_path' => $request->file('photo')->store('staff', 'public')]);

        return response()->json($staff->fresh());
    }
}
