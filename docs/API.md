# HGKA API reference

Base URL: `/api/v1`. All responses are JSON. Authenticate with `Authorization: Bearer <token>` from `POST /auth/login`.

Errors: `401` unauthenticated · `403` missing permission · `422` validation (`{message, errors:{field:[..]}}`) · `423` password change required · `429` rate limited · `409` record in use.

The *Permission* column lists the permission(s) required (any one of them). A blank value means any signed-in user, with row-level rules applied in the controller (for example parents only reach their own children).

## Generic CRUD resources

The following resources share one controller and the same endpoints: `GET /{resource}` (query: `page`, `per_page`, `search`, `sort`, `dir`, `filter[column]=value`), `GET /{resource}/{id}`, `POST /{resource}`, `PUT /{resource}/{id}`, `DELETE /{resource}/{id}`. Their validation rules and permissions are defined in `app/Support/Resources.php` (generated from `tools/spec.py`). `GET /lookup/{resource}` returns `[{id,label}]` for selects.

`academic-sessions, terms, classes, class-arms, subjects, class-subjects, teacher-assignments, timetable, calendar-events, departments, designations, staff-performance, guardians, exams, grading-scales, fee-structures, scholarships, expenses, books, book-categories, inventory-items, inventory-categories, suppliers, assets, asset-maintenance, vehicles, drivers, transport-routes, transport-allocations, vehicle-maintenance, hostels, hostel-rooms, hostel-allocations, medical-profiles, medical-visits, disciplinary-records, lesson-notes, teacher-reports` (full CRUD); `audit-logs, purchases, stock-movements` (read-only); `students, staff` (list/show/update/delete here; create via the custom endpoints below).

## Endpoints

| Method | Path | Handler | Permission |
|---|---|---|---|
| **Public** | | | |
| GET | `/api/v1/settings/public` | Setting::publicSettings |  |
| POST | `/api/v1/auth/login` | Auth::login |  |
| POST | `/api/v1/auth/forgot-password` | Auth::forgotPassword |  |
| POST | `/api/v1/auth/reset-password` | Auth::resetPassword |  |
| POST | `/api/v1/webhooks/paystack` | Webhook::paystack |  |
| POST | `/api/v1/webhooks/flutterwave` | Webhook::flutterwave |  |
| **Authenticated** | | | |
| POST | `/api/v1/auth/logout` | Auth::logout |  |
| GET | `/api/v1/auth/me` | Auth::me |  |
| POST | `/api/v1/auth/change-password` | Auth::changePassword |  |
| POST | `/api/v1/profile` | Auth::updateProfile |  |
| GET | `/api/v1/dashboard` | Dashboard::index | dashboard.view |
| GET | `/api/v1/lookup/{resource}` | Lookup::index |  |
| **Administration** | | | |
| GET | `/api/v1/settings` | Setting::index | settings.view |
| PUT | `/api/v1/settings` | Setting::update | settings.update |
| POST | `/api/v1/settings/logo` | Setting::uploadLogo | settings.update |
| GET | `/api/v1/users` | User::index | users.view |
| GET | `/api/v1/users/{user}` | User::show | users.view |
| POST | `/api/v1/users` | User::store | users.create |
| PUT | `/api/v1/users/{user}` | User::update | users.update |
| POST | `/api/v1/users/{user}/reset-password` | User::resetPassword | users.update |
| DELETE | `/api/v1/users/{user}` | User::destroy | users.delete |
| GET | `/api/v1/roles` | Role::index | roles.view |
| GET | `/api/v1/permissions` | Role::permissions | roles.view |
| POST | `/api/v1/roles` | Role::store | roles.manage |
| PUT | `/api/v1/roles/{role}` | Role::update | roles.manage |
| DELETE | `/api/v1/roles/{role}` | Role::destroy | roles.manage |
| GET | `/api/v1/backups` | Backup::index | backups.manage |
| POST | `/api/v1/backups` | Backup::store | backups.manage |
| GET | `/api/v1/backups/{name}/download` | Backup::download | backups.manage |
| **Students & staff** | | | |
| POST | `/api/v1/students` | Student::store | students.create |
| POST | `/api/v1/students/promote` | Student::promote | students.promote |
| GET | `/api/v1/students/{student}/profile` | Student::profile | students.view |
| POST | `/api/v1/students/{student}/photo` | Student::photo | students.update |
| POST | `/api/v1/staff` | Staff::store | staff.create |
| GET | `/api/v1/staff/{staff}/profile` | Staff::profile | staff.view |
| POST | `/api/v1/staff/{staff}/photo` | Staff::photo | staff.update |
| GET | `/api/v1/leave` | Leave::index | leave.view,leave.apply |
| POST | `/api/v1/leave` | Leave::store | leave.apply,leave.approve |
| POST | `/api/v1/leave/{leave}/decide` | Leave::decide | leave.approve |
| POST | `/api/v1/leave/{leave}/cancel` | Leave::cancel | leave.apply |
| GET | `/api/v1/attachments/{type}/{id}` | Attachment::index |  |
| POST | `/api/v1/attachments/{type}/{id}` | Attachment::store |  |
| GET | `/api/v1/attachment-files/{attachment}` | Attachment::download |  |
| DELETE | `/api/v1/attachment-files/{attachment}` | Attachment::destroy |  |
| **Academics & examinations** | | | |
| GET | `/api/v1/academic/current` | Academic::current |  |
| POST | `/api/v1/academic-sessions/{session}/set-current` | Academic::setCurrentSession | academics.manage |
| POST | `/api/v1/terms/{term}/set-current` | Academic::setCurrentTerm | academics.manage |
| GET | `/api/v1/timetable/class/{arm}` | Academic::classTimetable |  |
| GET | `/api/v1/timetable/staff/{staff}` | Academic::teacherTimetable |  |
| GET | `/api/v1/teacher/assignments` | Academic::myAssignments | academics.view |
| GET | `/api/v1/results/subjects` | Result::subjects | exams.enter-scores,exams.manage |
| GET | `/api/v1/results/sheet` | Result::sheet | exams.enter-scores,exams.manage |
| POST | `/api/v1/results/scores` | Result::saveScores | exams.enter-scores,exams.manage |
| POST | `/api/v1/results/submit` | Result::submit | exams.enter-scores,exams.manage |
| POST | `/api/v1/results/approve` | Result::approve | exams.approve-results |
| POST | `/api/v1/results/unapprove` | Result::unapprove | exams.approve-results |
| GET | `/api/v1/results/class-sheet` | Result::classSheet | exams.view |
| PUT | `/api/v1/results/comments/{student}` | Result::comments | exams.enter-scores,exams.manage,exams.approve-results |
| GET | `/api/v1/results/history/{student}` | Result::history | exams.view |
| GET | `/api/v1/results/report-card/{student}/{term}` | Result::reportCard |  |
| GET | `/api/v1/results/report-card/{student}/{term}/pdf` | Result::reportCardPdf |  |
| **Attendance** | | | |
| GET | `/api/v1/attendance/sheet` | Attendance::sheet | attendance.take,attendance.view |
| POST | `/api/v1/attendance/save` | Attendance::save | attendance.take |
| GET | `/api/v1/attendance/summary` | Attendance::summary | attendance.view,attendance.report |
| GET | `/api/v1/attendance/student/{student}` | Attendance::studentHistory | attendance.view,attendance.report |
| GET | `/api/v1/attendance/staff/sheet` | Attendance::staffSheet | attendance.staff |
| POST | `/api/v1/attendance/staff/save` | Attendance::staffSave | attendance.staff |
| GET | `/api/v1/attendance/staff/summary` | Attendance::staffSummary | attendance.staff |
| **Fees & payments** | | | |
| GET | `/api/v1/invoices` | Invoice::index | fees.view |
| POST | `/api/v1/invoices/generate` | Invoice::generate | fees.manage |
| GET | `/api/v1/invoices/{invoice}` | Invoice::show |  |
| GET | `/api/v1/invoices/{invoice}/pdf` | Invoice::pdf |  |
| PUT | `/api/v1/invoices/{invoice}` | Invoice::update | fees.manage |
| POST | `/api/v1/invoices/{invoice}/items` | Invoice::addItem | fees.manage |
| DELETE | `/api/v1/invoices/{invoice}/items/{item}` | Invoice::removeItem | fees.manage |
| POST | `/api/v1/invoices/{invoice}/cancel` | Invoice::cancel | fees.manage |
| GET | `/api/v1/payments` | Payment::index | fees.view |
| GET | `/api/v1/payments/gateways` | Payment::gateways |  |
| POST | `/api/v1/payments` | Payment::store | fees.record-payment |
| POST | `/api/v1/payments/online/initialize` | Payment::initialize | own-children.view,own-record.view,fees.record-payment |
| GET | `/api/v1/payments/verify/{reference}` | Payment::verify |  |
| POST | `/api/v1/payments/{payment}/approve` | Payment::approve | fees.approve-payment |
| POST | `/api/v1/payments/{payment}/reject` | Payment::reject | fees.approve-payment |
| POST | `/api/v1/payments/{payment}/requery` | Payment::requery | fees.approve-payment,fees.record-payment |
| GET | `/api/v1/payments/{payment}/receipt` | Payment::receipt |  |
| **Parent & student portal** | | | |
| GET | `/api/v1/portal/students` | Portal::students |  |
| GET | `/api/v1/portal/students/{student}` | Portal::show |  |
| GET | `/api/v1/portal/students/{student}/attendance` | Portal::attendance |  |
| GET | `/api/v1/portal/students/{student}/results` | Portal::results |  |
| GET | `/api/v1/portal/students/{student}/invoices` | Portal::invoices |  |
| GET | `/api/v1/portal/students/{student}/payments` | Portal::payments |  |
| **Library, inventory, transport, hostel** | | | |
| GET | `/api/v1/library/summary` | Library::summary | library.view |
| GET | `/api/v1/library/loans` | Library::loans | library.view |
| POST | `/api/v1/library/loans` | Library::borrow | library.manage |
| POST | `/api/v1/library/loans/{loan}/return` | Library::returnBook | library.manage |
| POST | `/api/v1/library/loans/{loan}/lost` | Library::markLost | library.manage |
| GET | `/api/v1/inventory/low-stock` | Inventory::lowStock | inventory.view |
| POST | `/api/v1/inventory/items/{item}/stock` | Inventory::stock | inventory.manage |
| POST | `/api/v1/inventory/purchases` | Inventory::purchase | inventory.manage |
| GET | `/api/v1/hostel/occupancy` | Facilities::hostelOccupancy | hostel.view |
| GET | `/api/v1/hostel/rooms/{room}/occupants` | Facilities::roomOccupants | hostel.view |
| GET | `/api/v1/transport/routes/{transportRoute}/roster` | Facilities::routeRoster | transport.view |
| **Payroll** | | | |
| GET | `/api/v1/payroll/structures` | Payroll::structures | payroll.view |
| PUT | `/api/v1/payroll/structures/{staff}` | Payroll::saveStructure | payroll.manage |
| GET | `/api/v1/payroll/runs` | Payroll::runs | payroll.view |
| GET | `/api/v1/payroll/runs/{run}` | Payroll::showRun | payroll.view |
| POST | `/api/v1/payroll/runs` | Payroll::process | payroll.manage |
| POST | `/api/v1/payroll/runs/{run}/pay` | Payroll::markPaid | payroll.manage |
| DELETE | `/api/v1/payroll/runs/{run}` | Payroll::destroyRun | payroll.manage |
| GET | `/api/v1/payroll/my-payslips` | Payroll::myPayslips |  |
| GET | `/api/v1/payroll/payslips/{payslip}/pdf` | Payroll::payslipPdf |  |
| **Communication** | | | |
| GET | `/api/v1/announcements` | Communication::announcements |  |
| POST | `/api/v1/announcements` | Communication::storeAnnouncement | communication.send |
| DELETE | `/api/v1/announcements/{announcement}` | Communication::destroyAnnouncement | communication.send |
| GET | `/api/v1/notifications` | Communication::notifications |  |
| GET | `/api/v1/notifications/counts` | Communication::counts |  |
| POST | `/api/v1/notifications/read-all` | Communication::markAllRead |  |
| POST | `/api/v1/notifications/{notification}/read` | Communication::markRead |  |
| GET | `/api/v1/messages/inbox` | Communication::inbox |  |
| GET | `/api/v1/messages/sent` | Communication::sent |  |
| GET | `/api/v1/messages/recipients` | Communication::recipients |  |
| POST | `/api/v1/messages` | Communication::sendMessage |  |
| GET | `/api/v1/messages/{message}` | Communication::showMessage |  |
| **Reports** | | | |
| GET | `/api/v1/reports` | Report::index | reports.view |
| GET | `/api/v1/reports/{key}` | Report::run | reports.view |
| **Generic registry-driven crud (must stay last)** | | | |
| GET | `/api/v1/{resource}` | Crud::index |  |
| POST | `/api/v1/{resource}` | Crud::store |  |
| GET | `/api/v1/{resource}/{id}` | Crud::show |  |
| PUT | `/api/v1/{resource}/{id}` | Crud::update |  |
| DELETE | `/api/v1/{resource}/{id}` | Crud::destroy |  |

## Payment webhooks

`POST /api/v1/webhooks/paystack` verifies `x-paystack-signature` (HMAC-SHA512 of the raw body with your secret key). `POST /api/v1/webhooks/flutterwave` verifies the `verif-hash` header against `FLUTTERWAVE_WEBHOOK_HASH`. A webhook never credits an invoice by itself: it only triggers a server-side verification call to the provider, and the amount and currency returned by the provider must match the payment we created.

Set the webhook URLs in your Paystack / Flutterwave dashboards to `https://<api-domain>/api/v1/webhooks/<provider>`. The customer is redirected after payment to `FRONTEND_URL/payment/callback`.
