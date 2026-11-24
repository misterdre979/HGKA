<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\Staff;
use App\Services\NotificationService;
use App\Support\Audit;
use App\Support\Fmt;
use App\Support\PdfDoc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function structures(): JsonResponse
    {
        return response()->json(
            Staff::with(['salaryStructure', 'salaryComponents', 'designation:id,name'])->whereIn('status', ['active', 'on_leave'])->orderBy('first_name')->get()
        );
    }

    public function saveStructure(Request $request, Staff $staff): JsonResponse
    {
        $d = $request->validate([
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'components' => ['array', 'max:30'],
            'components.*.type' => ['required', Rule::in(['allowance', 'deduction'])],
            'components.*.name' => ['required', 'string', 'max:255'],
            'components.*.amount' => ['required', 'numeric', 'min:0'],
            'components.*.is_percent' => ['boolean'],
        ]);

        foreach ($d['components'] ?? [] as $i => $c) {
            if (! empty($c['is_percent']) && $c['amount'] > 100) {
                abort(422, 'Percentage components cannot exceed 100.');
            }
        }

        DB::transaction(function () use ($staff, $d) {
            SalaryStructure::updateOrCreate(['staff_id' => $staff->id], [
                'basic_salary' => $d['basic_salary'], 'effective_from' => $d['effective_from'] ?? null, 'notes' => $d['notes'] ?? null,
            ]);
            SalaryComponent::where('staff_id', $staff->id)->delete();
            foreach ($d['components'] ?? [] as $c) {
                SalaryComponent::create([
                    'staff_id' => $staff->id, 'type' => $c['type'], 'name' => $c['name'], 'amount' => $c['amount'],
                    'is_percent' => (bool) ($c['is_percent'] ?? false),
                ]);
            }
        });
        Audit::log('payroll.structure_saved', $staff, null, ['basic' => $d['basic_salary']]);

        return response()->json($staff->load(['salaryStructure', 'salaryComponents']));
    }

    public function runs(): JsonResponse
    {
        return response()->json(PayrollRun::withCount('payslips')->orderByDesc('period_year')->orderByDesc('period_month')->get());
    }

    public function showRun(PayrollRun $run): JsonResponse
    {
        return response()->json($run->load(['payslips.staff:id,staff_no,first_name,last_name,bank_name,account_number']));
    }

    /** Processes salaries for a month for every active staff member that has a salary structure. */
    public function process(Request $request): JsonResponse
    {
        $d = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100'], 'month' => ['required', 'integer', 'min:1', 'max:12']]);
        abort_if(PayrollRun::where(['period_year' => $d['year'], 'period_month' => $d['month']])->exists(), 422, 'Payroll for this month has already been processed.');

        $staff = Staff::with(['salaryStructure', 'salaryComponents'])->where('status', 'active')->whereHas('salaryStructure')->get();
        abort_if($staff->isEmpty(), 422, 'No active staff have a salary structure yet.');

        $run = DB::transaction(function () use ($d, $staff, $request) {
            $run = PayrollRun::create([
                'period_year' => $d['year'], 'period_month' => $d['month'], 'status' => 'processed',
                'processed_by' => $request->user()->id, 'processed_at' => now(),
            ]);
            $gross = $deductions = $net = 0;

            foreach ($staff as $s) {
                $basic = (float) $s->salaryStructure->basic_salary;
                $lines = [];
                $allow = $deduct = 0.0;
                foreach ($s->salaryComponents as $c) {
                    $amount = round($c->is_percent ? $basic * ((float) $c->amount) / 100 : (float) $c->amount, 2);
                    $lines[] = ['type' => $c->type, 'name' => $c->name, 'amount' => $amount];
                    $c->type === 'allowance' ? $allow += $amount : $deduct += $amount;
                }
                $g = round($basic + $allow, 2);
                $n = round(max(0, $g - $deduct), 2);

                Payslip::create([
                    'payroll_run_id' => $run->id, 'staff_id' => $s->id, 'basic_salary' => $basic, 'total_allowances' => round($allow, 2),
                    'total_deductions' => round($deduct, 2), 'gross_pay' => $g, 'net_pay' => $n, 'components' => $lines, 'status' => 'pending',
                ]);
                $gross += $g;
                $deductions += $deduct;
                $net += $n;
            }
            $run->update(['total_gross' => round($gross, 2), 'total_deductions' => round($deductions, 2), 'total_net' => round($net, 2)]);

            return $run;
        });
        Audit::log('payroll.processed', $run, null, ['net' => $run->total_net]);

        return response()->json($run->loadCount('payslips'), 201);
    }

    public function markPaid(PayrollRun $run, NotificationService $notify): JsonResponse
    {
        abort_if($run->status === 'paid', 422, 'This payroll has already been paid.');

        DB::transaction(function () use ($run) {
            foreach ($run->payslips()->with('staff:id,staff_no')->get() as $p) {
                $p->update([
                    'status' => 'paid', 'paid_at' => now(),
                    'payment_reference' => sprintf('PAY-%d%02d-%s', $run->period_year, $run->period_month, str_replace('/', '', $p->staff->staff_no)),
                ]);
            }
            $run->update(['status' => 'paid', 'paid_at' => now()]);
        });

        foreach ($run->payslips()->with('staff:id,user_id')->get() as $p) {
            if ($p->staff->user_id) {
                $notify->toUser($p->staff->user_id, 'Salary paid', 'Your salary of '.Fmt::money($p->net_pay).' for '.date('F Y', mktime(0, 0, 0, $run->period_month, 1, $run->period_year)).' has been paid.', 'payroll');
            }
        }
        Audit::log('payroll.paid', $run);

        return response()->json($run->fresh());
    }

    /** Re-open a processed (unpaid) run so it can be re-processed after fixing salary structures. */
    public function destroyRun(PayrollRun $run): JsonResponse
    {
        abort_if($run->status === 'paid', 422, 'A paid payroll cannot be deleted.');
        $run->delete(); // payslips cascade
        Audit::log('payroll.deleted', $run);

        return response()->json(['message' => 'Payroll run removed.']);
    }

    public function myPayslips(Request $request): JsonResponse
    {
        $staff = Staff::where('user_id', $request->user()->id)->first();
        abort_unless($staff, 403, 'Your account is not linked to a staff record.');

        return response()->json(Payslip::with('payrollRun')->where('staff_id', $staff->id)->where('status', 'paid')->latest('id')->get());
    }

    public function payslipPdf(Request $request, Payslip $payslip)
    {
        $own = Staff::where('user_id', $request->user()->id)->value('id') === $payslip->staff_id;
        abort_unless($request->user()->hasPermission('payroll.view') || ($own && $payslip->status === 'paid'), 403, 'You do not have permission to perform this action.');
        $payslip->load(['staff.department', 'staff.designation', 'payrollRun']);

        return PdfDoc::download('pdf.payslip', ['payslip' => $payslip], 'payslip-'.$payslip->payrollRun->period_year.'-'.$payslip->payrollRun->period_month.'-'.str_replace('/', '', $payslip->staff->staff_no).'.pdf');
    }
}
