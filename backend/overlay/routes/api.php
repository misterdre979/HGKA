<?php

use App\Http\Controllers\Api\AcademicController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\CommunicationController;
use App\Http\Controllers\Api\CrudController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FacilitiesController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ResultController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WebhookController;
use App\Support\Resources;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ------------------------------------------------------------ public
    Route::get('/health', function () {
        DB::connection()->getPdo();

        return response()->json(['status' => 'ok', 'app' => config('app.name'), 'database' => DB::connection()->getDriverName()]);
    })->middleware('throttle:60,1');

    Route::get('/settings/public', [SettingController::class, 'publicSettings'])->middleware('throttle:60,1');

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    // Payment provider webhooks (authenticated by signature, not by session)
    Route::post('/webhooks/paystack', [WebhookController::class, 'paystack'])->middleware('throttle:webhook');
    Route::post('/webhooks/flutterwave', [WebhookController::class, 'flutterwave'])->middleware('throttle:webhook');

    // ------------------------------------------------------------ authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        // Everything below requires the temporary password to have been changed.
        Route::middleware('password.changed')->group(function () {
            Route::post('/profile', [AuthController::class, 'updateProfile']);

            Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view');
            Route::get('/lookup/{resource}', [LookupController::class, 'index']);

            // ---------------------------------------------------- administration
            Route::get('/settings', [SettingController::class, 'index'])->middleware('permission:settings.view');
            Route::put('/settings', [SettingController::class, 'update'])->middleware('permission:settings.update');
            Route::post('/settings/logo', [SettingController::class, 'uploadLogo'])->middleware('permission:settings.update');

            Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view');
            Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
            Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create');
            Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.update');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete');

            Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
            Route::get('/permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view');
            Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.manage');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage');
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage');

            Route::get('/backups', [BackupController::class, 'index'])->middleware('permission:backups.manage');
            Route::post('/backups', [BackupController::class, 'store'])->middleware('permission:backups.manage');
            Route::get('/backups/{name}/download', [BackupController::class, 'download'])->middleware('permission:backups.manage');

            // ---------------------------------------------------- students & staff
            Route::post('/students', [StudentController::class, 'store'])->middleware('permission:students.create');
            Route::post('/students/promote', [StudentController::class, 'promote'])->middleware('permission:students.promote');
            Route::get('/students/{student}/profile', [StudentController::class, 'profile'])->middleware('permission:students.view');
            Route::post('/students/{student}/photo', [StudentController::class, 'photo'])->middleware('permission:students.update');

            Route::post('/staff', [StaffController::class, 'store'])->middleware('permission:staff.create');
            Route::get('/staff/{staff}/profile', [StaffController::class, 'profile'])->middleware('permission:staff.view');
            Route::post('/staff/{staff}/photo', [StaffController::class, 'photo'])->middleware('permission:staff.update');

            Route::get('/leave', [LeaveController::class, 'index'])->middleware('permission:leave.view,leave.apply');
            Route::post('/leave', [LeaveController::class, 'store'])->middleware('permission:leave.apply,leave.approve');
            Route::post('/leave/{leave}/decide', [LeaveController::class, 'decide'])->middleware('permission:leave.approve');
            Route::post('/leave/{leave}/cancel', [LeaveController::class, 'cancel'])->middleware('permission:leave.apply');

            Route::get('/attachments/{type}/{id}', [AttachmentController::class, 'index'])->whereNumber('id');
            Route::post('/attachments/{type}/{id}', [AttachmentController::class, 'store'])->whereNumber('id');
            Route::get('/attachment-files/{attachment}', [AttachmentController::class, 'download']);
            Route::delete('/attachment-files/{attachment}', [AttachmentController::class, 'destroy']);

            // ---------------------------------------------------- academics & examinations
            Route::get('/academic/current', [AcademicController::class, 'current']);
            Route::post('/academic-sessions/{session}/set-current', [AcademicController::class, 'setCurrentSession'])->middleware('permission:academics.manage');
            Route::post('/terms/{term}/set-current', [AcademicController::class, 'setCurrentTerm'])->middleware('permission:academics.manage');
            Route::get('/timetable/class/{arm}', [AcademicController::class, 'classTimetable']);
            Route::get('/timetable/staff/{staff}', [AcademicController::class, 'teacherTimetable']);
            Route::get('/teacher/assignments', [AcademicController::class, 'myAssignments'])->middleware('permission:academics.view');

            Route::get('/results/subjects', [ResultController::class, 'subjects'])->middleware('permission:exams.enter-scores,exams.manage');
            Route::get('/results/sheet', [ResultController::class, 'sheet'])->middleware('permission:exams.enter-scores,exams.manage');
            Route::post('/results/scores', [ResultController::class, 'saveScores'])->middleware('permission:exams.enter-scores,exams.manage');
            Route::post('/results/submit', [ResultController::class, 'submit'])->middleware('permission:exams.enter-scores,exams.manage');
            Route::post('/results/approve', [ResultController::class, 'approve'])->middleware('permission:exams.approve-results');
            Route::post('/results/unapprove', [ResultController::class, 'unapprove'])->middleware('permission:exams.approve-results');
            Route::get('/results/class-sheet', [ResultController::class, 'classSheet'])->middleware('permission:exams.view');
            Route::put('/results/comments/{student}', [ResultController::class, 'comments'])->middleware('permission:exams.enter-scores,exams.manage,exams.approve-results');
            Route::get('/results/history/{student}', [ResultController::class, 'history'])->middleware('permission:exams.view');
            Route::get('/results/report-card/{student}/{term}', [ResultController::class, 'reportCard']);
            Route::get('/results/report-card/{student}/{term}/pdf', [ResultController::class, 'reportCardPdf']);

            // ---------------------------------------------------- attendance
            Route::get('/attendance/sheet', [AttendanceController::class, 'sheet'])->middleware('permission:attendance.take,attendance.view');
            Route::post('/attendance/save', [AttendanceController::class, 'save'])->middleware('permission:attendance.take');
            Route::get('/attendance/summary', [AttendanceController::class, 'summary'])->middleware('permission:attendance.view,attendance.report');
            Route::get('/attendance/student/{student}', [AttendanceController::class, 'studentHistory'])->middleware('permission:attendance.view,attendance.report');
            Route::get('/attendance/staff/sheet', [AttendanceController::class, 'staffSheet'])->middleware('permission:attendance.staff');
            Route::post('/attendance/staff/save', [AttendanceController::class, 'staffSave'])->middleware('permission:attendance.staff');
            Route::get('/attendance/staff/summary', [AttendanceController::class, 'staffSummary'])->middleware('permission:attendance.staff');

            // ---------------------------------------------------- fees & payments
            Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:fees.view');
            Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->middleware('permission:fees.manage');
            Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
            Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
            Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->middleware('permission:fees.manage');
            Route::post('/invoices/{invoice}/items', [InvoiceController::class, 'addItem'])->middleware('permission:fees.manage');
            Route::delete('/invoices/{invoice}/items/{item}', [InvoiceController::class, 'removeItem'])->middleware('permission:fees.manage');
            Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:fees.manage');

            Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:fees.view');
            Route::get('/payments/gateways', [PaymentController::class, 'gateways']);
            Route::post('/payments', [PaymentController::class, 'store'])->middleware('permission:fees.record-payment');
            Route::post('/payments/online/initialize', [PaymentController::class, 'initialize'])->middleware('permission:own-children.view,own-record.view,fees.record-payment');
            Route::get('/payments/verify/{reference}', [PaymentController::class, 'verify']);
            Route::post('/payments/{payment}/approve', [PaymentController::class, 'approve'])->middleware('permission:fees.approve-payment');
            Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject'])->middleware('permission:fees.approve-payment');
            Route::post('/payments/{payment}/requery', [PaymentController::class, 'requery'])->middleware('permission:fees.approve-payment,fees.record-payment');
            Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt']);

            // ---------------------------------------------------- parent & student portal
            Route::prefix('portal')->middleware('permission:own-children.view,own-record.view')->group(function () {
                Route::get('/students', [PortalController::class, 'students']);
                Route::get('/students/{student}', [PortalController::class, 'show']);
                Route::get('/students/{student}/attendance', [PortalController::class, 'attendance']);
                Route::get('/students/{student}/results', [PortalController::class, 'results']);
                Route::get('/students/{student}/invoices', [PortalController::class, 'invoices']);
                Route::get('/students/{student}/payments', [PortalController::class, 'payments']);
            });

            // ---------------------------------------------------- library, inventory, transport, hostel
            Route::get('/library/summary', [LibraryController::class, 'summary'])->middleware('permission:library.view');
            Route::get('/library/loans', [LibraryController::class, 'loans'])->middleware('permission:library.view');
            Route::post('/library/loans', [LibraryController::class, 'borrow'])->middleware('permission:library.manage');
            Route::post('/library/loans/{loan}/return', [LibraryController::class, 'returnBook'])->middleware('permission:library.manage');
            Route::post('/library/loans/{loan}/lost', [LibraryController::class, 'markLost'])->middleware('permission:library.manage');

            Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->middleware('permission:inventory.view');
            Route::post('/inventory/items/{item}/stock', [InventoryController::class, 'stock'])->middleware('permission:inventory.manage');
            Route::post('/inventory/purchases', [InventoryController::class, 'purchase'])->middleware('permission:inventory.manage');

            Route::get('/hostel/occupancy', [FacilitiesController::class, 'hostelOccupancy'])->middleware('permission:hostel.view');
            Route::get('/hostel/rooms/{room}/occupants', [FacilitiesController::class, 'roomOccupants'])->middleware('permission:hostel.view')->whereNumber('room');
            Route::get('/transport/routes/{transportRoute}/roster', [FacilitiesController::class, 'routeRoster'])->middleware('permission:transport.view');

            // ---------------------------------------------------- payroll
            Route::get('/payroll/structures', [PayrollController::class, 'structures'])->middleware('permission:payroll.view');
            Route::put('/payroll/structures/{staff}', [PayrollController::class, 'saveStructure'])->middleware('permission:payroll.manage');
            Route::get('/payroll/runs', [PayrollController::class, 'runs'])->middleware('permission:payroll.view');
            Route::get('/payroll/runs/{run}', [PayrollController::class, 'showRun'])->middleware('permission:payroll.view');
            Route::post('/payroll/runs', [PayrollController::class, 'process'])->middleware('permission:payroll.manage');
            Route::post('/payroll/runs/{run}/pay', [PayrollController::class, 'markPaid'])->middleware('permission:payroll.manage');
            Route::delete('/payroll/runs/{run}', [PayrollController::class, 'destroyRun'])->middleware('permission:payroll.manage');
            Route::get('/payroll/my-payslips', [PayrollController::class, 'myPayslips']);
            Route::get('/payroll/payslips/{payslip}/pdf', [PayrollController::class, 'payslipPdf']);

            // ---------------------------------------------------- communication
            Route::get('/announcements', [CommunicationController::class, 'announcements']);
            Route::post('/announcements', [CommunicationController::class, 'storeAnnouncement'])->middleware('permission:communication.send');
            Route::delete('/announcements/{announcement}', [CommunicationController::class, 'destroyAnnouncement'])->middleware('permission:communication.send');

            Route::get('/notifications', [CommunicationController::class, 'notifications']);
            Route::get('/notifications/counts', [CommunicationController::class, 'counts']);
            Route::post('/notifications/read-all', [CommunicationController::class, 'markAllRead']);
            Route::post('/notifications/{notification}/read', [CommunicationController::class, 'markRead']);

            Route::prefix('messages')->middleware('permission:communication.view')->group(function () {
                Route::get('/inbox', [CommunicationController::class, 'inbox']);
                Route::get('/sent', [CommunicationController::class, 'sent']);
                Route::get('/recipients', [CommunicationController::class, 'recipients']);
                Route::post('/', [CommunicationController::class, 'sendMessage']);
                Route::get('/{message}', [CommunicationController::class, 'showMessage'])->whereNumber('message');
            });

            // ---------------------------------------------------- reports
            Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:reports.view');
            Route::get('/reports/{key}', [ReportController::class, 'run'])->middleware('permission:reports.view');

            // ---------------------------------------------------- generic registry-driven CRUD (must stay last)
            $keys = implode('|', array_map('preg_quote', array_keys(Resources::all())));
            Route::get('/{resource}', [CrudController::class, 'index'])->where('resource', $keys);
            Route::post('/{resource}', [CrudController::class, 'store'])->where('resource', $keys);
            Route::get('/{resource}/{id}', [CrudController::class, 'show'])->where('resource', $keys)->whereNumber('id');
            Route::put('/{resource}/{id}', [CrudController::class, 'update'])->where('resource', $keys)->whereNumber('id');
            Route::delete('/{resource}/{id}', [CrudController::class, 'destroy'])->where('resource', $keys)->whereNumber('id');
        });
    });
});
