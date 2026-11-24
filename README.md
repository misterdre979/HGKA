# HGKA: His Grace Kiddies Academy School Management System

A complete school management system for a Nigerian nursery, primary and secondary school.

| Layer | Technology |
|---|---|
| Frontend | React 19 + TypeScript, Vite, Tailwind CSS 4, React Router, Axios, React Hook Form + Zod, Lucide, Recharts |
| Backend | PHP 8.2+ / Laravel 12, Laravel Sanctum (Bearer tokens), DomPDF, Laravel Excel |
| Database | MySQL 8 |

## Please read first: what has and has not been verified

| Part | Status |
|---|---|
| Frontend | Type-checks and builds with no errors. Every page was loaded in a headless browser against a mocked API with no console errors. It has **not** been run against the real backend. |
| Backend | All PHP files pass `php -l` (syntax). The backend was written in an environment where Composer/Packagist was unreachable, so **Laravel could not be installed and the backend has never been executed**. The migrations, seeders, controllers and PHPUnit tests are untested. |

Expect to fix a few small runtime issues (a wrong column name, a missing relation, a SQL quirk) the first time you run `php artisan migrate --seed` and the test suite. Run the tests first (section 5); they exercise authentication, RBAC, result calculation, invoices, online payment verification and webhooks.

## 1. What is in the box

* **14 roles with RBAC**: Super Administrator, School Administrator, Principal, Vice Principal, Head of Department, Teacher, Accountant, Bursar, Librarian, School Nurse, Transport Manager, Student, Parent/Guardian, HR Officer. Roles are editable under *Administration → Roles & permissions*; every API route and every menu item is permission-gated.
* **Modules**: dashboard · students (admission, profile, photo, documents, promotion) · staff (records, leave, performance, documents) · academics (sessions, terms, classes/arms, subjects, teacher assignments, timetable, calendar) · exams and results (score entry, automatic grading, positions with ties, approval workflow, report-card PDFs) · attendance (students and staff, parent absence alerts) · fees (structures, scholarships, invoice generation, part payments, receipts) · payments (Paystack, Flutterwave, cash, transfer, POS, cheque with approval) · parent and student portal · teacher portal (score entry, attendance, lesson notes, reports) · library · inventory and assets · transport · hostel · HR and payroll (payslip PDFs) · communication (announcements, messages, in-app/email/SMS notifications) · medical and discipline · 13 reports with PDF/Excel export · administration (users, roles, audit logs, backups, settings and branding).
* **Security**: hashed passwords, expiring Sanctum tokens, forced first-login password change, login throttling, per-route rate limits, server-side validation of every input, row-level access (parents only see their own children; teachers only their assigned classes), audit log of sensitive actions, private document storage with random file names and MIME/size checks, security headers, CORS allow-list. Online payments are **only** confirmed by calling the provider's verify API; the redirect URL and webhooks never credit money on their own.
* **Logo and branding**: `frontend/src/components/Logo.tsx` (shield, book and star in navy, royal blue and gold). Colours and logo can be changed in *School settings*.

## 2. Repository layout

```
backend/
  setup.sh                 Creates a fresh Laravel 12 app and applies the overlay
  env.hgka.example         HGKA environment variables (appended to .env by setup.sh)
  overlay/                 Everything that is HGKA-specific
    app/                   Models (64), Http (controllers, middleware), Services, Support, Console
    bootstrap/app.php      API routing, middleware aliases, JSON error handling
    config/                hgka.php, cors.php
    database/migrations/   15 migrations
    database/seeders/      Roles/permissions, super admin, school foundation, grading scales, demo data
    resources/views/pdf/   Report card, receipt, invoice, payslip, generic table
    routes/                api.php (v1), console.php (scheduler)
    tests/                 PHPUnit feature tests
frontend/                  React + TypeScript app
tools/
  spec.py                  Single source of truth for resources, fields, permissions and roles
  generate.py              Generates migrations, models, the CRUD registry, seeder and frontend config
docs/API.md                API reference
```

Most of the ~65 tables share one registry-driven CRUD controller. If you change a resource (add a field, change a permission), edit `tools/spec.py` and run `python3 tools/generate.py`; it rewrites the migrations, models, validation rules, role seeder and the frontend forms/tables together so they cannot drift apart.

## 3. Installation

### Requirements
PHP 8.2+ (extensions: `pdo_mysql, mbstring, xml, gd, zip, bcmath, fileinfo, intl`), Composer 2, MySQL 8, Node 20+ and npm.

### Backend
```bash
mysql -u root -p -e "CREATE DATABASE hgka CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cd backend
./setup.sh                      # creates backend/app-src (Laravel 12 + packages + the overlay)
cd app-src
nano .env                       # set DB_*, APP_URL, FRONTEND_URL, ADMIN_*, and mail/payment keys
php artisan migrate --seed
php artisan serve               # http://localhost:8000/api/v1
```
`setup.sh` runs `composer create-project laravel/laravel:^12.0`, `composer require laravel/sanctum barryvdh/laravel-dompdf maatwebsite/excel`, copies `overlay/` over the project, publishes Sanctum, appends `env.hgka.example` to `.env`, generates the app key and links storage.

### Frontend
```bash
cd frontend
cp .env.example .env            # VITE_API_URL=/api/v1  (dev server proxies /api to :8000)
npm install
npm run dev                     # http://localhost:5173
```
For production set `VITE_API_URL=https://api.yourschool.ng/api/v1` and run `npm run build`; serve `dist/` as static files with an SPA fallback to `index.html`.

### Default administrator
`php artisan db:seed` creates the Super Administrator from `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` (defaults `admin@hgka.test` / `ChangeMe!2026`). **You are forced to change this password at first sign-in.** Set real values before seeding in production. If `ADMIN_*` are missing the seeder skips creating the account.

### Demo data (development only)
Set `SEED_DEMO_DATA=true` and run `php artisan migrate:fresh --seed` to load a sample school: a session with three terms, classes, subjects, 32 students with parents, 17 staff, fee structures, invoices and payments, attendance, approved results, library, inventory, transport, hostel and announcements. All demo logins use the password `Demo@1234`:

`principal@hgka.test`, `bursar@hgka.test`, `accountant@hgka.test`, `teacher1@hgka.test` … `teacher8@hgka.test`, `librarian@hgka.test`, `hr@hgka.test`, `nurse@hgka.test`, `transport@hgka.test`, `admin@hgka.test`, `parent1@hgka.test` … `parent5@hgka.test`, `student1@hgka.test`, `student2@hgka.test`.

Never enable this in production.

## 4. Configuration

| Variable | Purpose |
|---|---|
| `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS` | Frontend origin(s); used for CORS, password-reset links and the payment callback |
| `TOKEN_LIFETIME_HOURS` | Login token lifetime (default 12) |
| `ADMIN_NAME/EMAIL/PASSWORD` | First Super Administrator |
| `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY` | Enables Paystack |
| `FLUTTERWAVE_SECRET_KEY`, `FLUTTERWAVE_PUBLIC_KEY`, `FLUTTERWAVE_WEBHOOK_HASH` | Enables Flutterwave |
| `SMS_DRIVER` (`log` or `termii`), `SMS_API_KEY`, `SMS_SENDER` | SMS. `log` writes messages to the Laravel log |
| `MAIL_*` | Standard Laravel mail settings for email notifications and password resets |
| `BACKUP_KEEP` | Number of nightly database backups to keep |

Payment gateway buttons only appear to parents when the matching secret key is set.

## 5. Testing

```bash
cd backend/app-src
php artisan test                # uses in-memory SQLite by default (see phpunit.xml)
```
Tests live in `backend/overlay/tests` and cover: login, throttling, forced password change; role/permission enforcement and parent row-level isolation; grade boundaries, score validation, tie-aware positions, approval locking; invoice generation, idempotency and scholarships; online payment initialise → verify, idempotent crediting, amount/currency mismatch, webhook signatures, manual payment approval and overpayment protection (provider calls are mocked with `Http::fake`).

If the migrations use anything SQLite rejects, point PHPUnit at a throwaway MySQL database in `phpunit.xml` instead.

Frontend checks: `npm run build` (runs `tsc` and Vite).

**Manual end-to-end checklist** (with demo data): sign in as `teacher1` → enter scores → submit; as `principal` → approve results → download a report card; as `bursar` → generate invoices, record a cheque payment; as `parent1` → open the child, view results, pay online (use Paystack test keys and set the webhook URL); as `librarian` → issue and return a book.

## 6. Deployment (Ubuntu + Nginx + PHP-FPM)

1. **Server**: PHP 8.2-fpm with the extensions above, MySQL 8, Nginx, Certbot (HTTPS is required for payments).
2. **Backend**: deploy `backend/app-src` to `/var/www/hgka-api`, then
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env   # set APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, FRONTEND_URL, mail, gateway keys, ADMIN_*
   php artisan key:generate && php artisan migrate --force --seed
   php artisan storage:link
   php artisan config:cache && php artisan route:cache
   chown -R www-data:www-data storage bootstrap/cache
   ```
   Nginx: point `root` at `public/`, `try_files $uri /index.php?$query_string;`, PHP-FPM `fastcgi_pass`. Set `client_max_body_size 12M;`.
3. **Scheduler**: add `* * * * * www-data php /var/www/hgka-api/artisan schedule:run >> /dev/null 2>&1` to cron. It runs nightly backups, fee reminders, library overdue notices, exam reminders and token pruning.
4. **Frontend**: build with `VITE_API_URL=https://api.yourschool.ng/api/v1`, copy `dist/` to the web root with `try_files $uri /index.html;`.
5. **Payments**: in the Paystack/Flutterwave dashboards set the webhook to `https://api.yourschool.ng/api/v1/webhooks/paystack` (or `/flutterwave`). Flutterwave's "secret hash" must equal `FLUTTERWAVE_WEBHOOK_HASH`. Test with test keys before going live.
6. **Backups**: the nightly job writes compressed SQL dumps to `storage/app/private/backups` (needs `mysqldump` on the server). Also copy them off the server (cron + rsync/S3) and back up `storage/app/private` (uploaded documents).
7. **Hardening checklist**: `APP_DEBUG=false`; strong `DB` password; firewall allowing only 80/443/SSH; change the default admin password; review roles before go-live; set up log rotation and uptime monitoring.

## 7. Known limitations

* Backend untested (see the top of this file).
* No online class timetable drag-and-drop; the timetable is a table of slots.
* Email/SMS run synchronously; for large schools switch `QUEUE_CONNECTION` to `database` or `redis` and run a queue worker, then dispatch notifications as queued jobs.
* Report-card PDFs use DejaVu Sans (supports ₦) and a fixed layout; customising the layout means editing `resources/views/pdf/report-card.blade.php`.
* Bulk student import (CSV) is not included.
* The frontend admin screens list audit-log values as stored; there is no diff viewer.
