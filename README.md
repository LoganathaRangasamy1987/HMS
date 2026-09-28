# CareDesk Hospital ERP

The first development milestone is a runnable Laravel foundation with a Bootstrap portal and a local SQLite database. The outpatient pilot specification is in [docs/pilot-scope.md](docs/pilot-scope.md).

For the complete sprint/task roadmap, actual completed work, pending items, verification history, and next steps, see **[PROJECT_PROGRESS.md](PROJECT_PROGRESS.md)**. Keep that file updated during every development session; it is the main source of project status.

## Implemented

- Sign in/out, login throttling, password reset, encrypted database sessions, and inactive-account enforcement.
- Hospital details, branches, departments, and staff administration with validation and search.
- Hospital administrator, receptionist, and doctor roles, with explicit assignments across branches of one hospital.
- An active branch selector, role-aware navigation, real organization counts, and a read-only activity log.
- Hospital-scoped JSON endpoints, transactionally recorded administrative changes, and private document upload/download foundations.
- Patient registration, demographic editing, hospital-wide search, duplicate review, stable UHIDs, role permissions, and auditable changes through portal screens and same-origin APIs.
- Doctor-only patient allergy and medical-history records with author/branch attribution and private PDF/JPEG/PNG document upload/download.
- Hospital doctor directory with branch-linked staff profiles, departments, registration numbers, qualifications, specializations, consultation fees, and active status.
- Two fictional hospitals for isolation tests. Appointments, the service catalog, invoice draft/issue flow, and payment collection are available locally; clinical workflows, laboratory, and pharmacy are later milestones.

This implementation gives each user one hospital and multiple branch memberships. Patient identity is stored once per hospital with the original registration branch retained. Hospital provisioning currently uses a CLI command; a platform administrator portal is deferred.

## Requirements

PHP **8.4+** with PDO SQLite, Composer 2, and Node.js 20+. This workspace uses project-local PHP 8.4.25, Composer 2.10.3, Laravel 13.31, and `database/database.sqlite`. Bootstrap is served locally; there are no CDN dependencies.

XAMPP's existing PHP 8.2 cannot run this application. Use the bundled project runtime and development URL below; existing XAMPP applications keep their own PHP installation.

## Windows setup

From `C:\xampp\htdocs\hms`:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/install-tools.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File hms.ps1 composer install
Copy-Item .env.example .env
powershell.exe -NoProfile -ExecutionPolicy Bypass -File hms.ps1 artisan key:generate
```

The first command installs checksum-verified PHP and Composer into `.runtime/`; it does not change system PHP or PATH permanently. Skip copying `.env` and generating the key when using this already configured workspace. Never replace the application key of an existing deployment.

Create the SQLite database file before migrating a fresh local setup:

```powershell
New-Item -ItemType File -Path database/database.sqlite -Force
```

The configured local application no longer needs the XAMPP MariaDB service. Existing MariaDB data is not removed or modified by this SQLite setup.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File hms.ps1 artisan migrate --seed
npm.cmd ci
npm.cmd run build
powershell.exe -NoProfile -ExecutionPolicy Bypass -File hms.ps1 serve
```

Open **http://127.0.0.1:8000**. Use this address consistently for browser sessions. The development server is bound to the local machine.

On other environments with PHP 8.4+ and Composer on PATH, use normal `composer install`, `php artisan ...`, and `npm ci` commands. The application itself does not depend on Windows.

## Local demo accounts

All accounts below use **`CareDesk@2026!`**, for fictional local data only:

| Account | Role / organization |
| --- | --- |
| `admin@lotus.test` | Hospital administrator, Lotus Care Hospital, Coimbatore and Chennai |
| `reception@lotus.test` | Receptionist, Lotus Care Hospital, Coimbatore |
| `doctor@lotus.test` | Doctor, Lotus Care Hospital, Coimbatore |
| `admin@river.test` | Hospital administrator, separate Riverbank Hospital |

The demo seeder runs only in `local` or `testing`. It preserves existing account passwords on repeated runs. For a clean deployment, seed roles and provision a new administrator instead of using demo accounts.

Password reset notifications use `MAIL_MAILER=log` locally, so reset links are written to `storage/logs/laravel.log`, not sent to real inboxes. Configure SMTP and a real `APP_URL` for delivered email. Reset tokens expire after 60 minutes and can be used once. The default session idle lifetime is 30 minutes; remember-me sign-in can restore an authorized account after a session expires.

## Checks

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File hms.ps1 test --compact
npm.cmd run build
node --check public/js/hms.js
npm.cmd run test:browser
```

The default PHP suite uses in-memory SQLite. Opt-in concurrency tests start eight independent PHP workers against temporary MySQL/MariaDB databases to verify UHID allocation and appointment slot capacity/token allocation. They are skipped in the default suite. With local XAMPP MySQL running:

```powershell
$env:HMS_MYSQL_TEST = '1'
try {
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File hms.ps1 test --compact tests/Feature/PatientIdentityConcurrencyTest.php
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File hms.ps1 test --compact tests/Feature/AppointmentConcurrencyTest.php
} finally {
    Remove-Item Env:HMS_MYSQL_TEST
}
```

These tests need an account allowed to create/drop their own test databases. They default to local XAMPP root access; `HMS_MYSQL_HOST`, `HMS_MYSQL_PORT`, `HMS_MYSQL_USERNAME`, and `HMS_MYSQL_PASSWORD` can override the test connection. Each creates a random, task-specific schema, stops its workers before cleanup, and drops only that schema. They do not reset the application database or change `.env`.

The browser suite needs the local server and fictional demo accounts; it opens forms, saves the existing branch details, switches branch context, checks CSRF protection and role restrictions, and verifies mobile navigation. It uses installed Chrome on Windows; on other platforms install Playwright Chromium with `npx playwright install chromium`. Screenshots and failure traces are under `test-results/` and are ignored by Git.

`.github/workflows/ci.yml` defines PHP tests, frontend asset building, and cache compilation for a future GitHub repository. This is a workflow definition; no remote CI or staging deployment has been run from this workspace.

## API foundation

The same-origin API uses browser sessions and CSRF protection. It is not a JWT/mobile token API. Fetch `GET /api/v1/csrf-token` with cookies, then send its `csrf_token` as `X-CSRF-TOKEN` on mutations. Keep cookies on subsequent requests.

| Endpoint | Purpose |
| --- | --- |
| `POST /api/v1/auth/login`, `POST /api/v1/auth/logout` | Authenticate or end the session |
| `GET /api/v1/me`, `GET /api/v1/dashboard` | Current user/context and authorized summary |
| `POST /api/v1/context` | Select an assigned `membership_id` |
| `GET/PUT /api/v1/organization` | Current hospital details, administrator only |
| `GET/POST /api/v1/branches`, `/departments`, `/staff` | List/create, administrator only |
| `GET/PUT /api/v1/branches/{id}`, `/departments/{id}`, `/staff/{id}` | Read/update, administrator only |
| `GET /api/v1/audit` | Current hospital's audit history, administrator only |
| `GET/POST /api/v1/patients`, `GET/PUT /api/v1/patients/{id}` | Hospital patient search, registration, detail, and demographic updates |
| `GET /api/v1/patients/duplicates` | Hospital-scoped possible duplicate suggestions before registration |
| `GET /api/v1/patients/{id}/clinical-history` | Doctor-authorized allergies, medical history, and document metadata |
| `POST/PUT /api/v1/patients/{id}/allergies/{allergy?}` | Create or update an attributed allergy record |
| `POST/PUT /api/v1/patients/{id}/medical-history/{history?}` | Create or update an attributed medical-history record |
| `POST/GET /api/v1/patients/{id}/documents/{document?}` | Upload or download an authorized private patient document |
| `GET/POST /api/v1/doctors`, `GET/PUT /api/v1/doctors/{id}` | List and administratively manage branch-consistent doctor profiles |
| `GET /api/v1/doctors/{id}/availability` | View weekly hours, closures, date exceptions, capacity, and branch timezone |
| `POST /api/v1/doctors/{id}/availability/schedules` | Add non-overlapping weekly hours; administrator or owning doctor |
| `POST /api/v1/doctors/{id}/availability/closures` | Record inclusive holiday or leave date ranges |
| `POST /api/v1/doctors/{id}/availability/exceptions` | Create or replace one date-specific availability rule |
| `DELETE /api/v1/doctors/{id}/availability/{type}/{record}` | Remove an owned schedule, closure, or exception |
| `GET/POST /api/v1/appointments` | View the active-branch queue or book against doctor availability |
| `PUT /api/v1/appointments/{id}/reschedule` | Revalidate and move a booked or confirmed appointment |
| `PUT /api/v1/appointments/{id}/cancel` | Cancel an eligible appointment with a required reason |
| `PUT /api/v1/appointments/{id}/status` | Apply an authorized reception or assigned-doctor queue transition |
| `GET/POST /api/v1/services`, `GET/PUT /api/v1/services/{id}` | List/view services or administratively create/update hospital catalog entries |
| `PUT /api/v1/services/{id}/branch-price` | Set branch-specific price, tax, discount, or availability overrides |
| `GET /api/v1/services/{id}/quote` | Get the effective branch price, discount, tax, and total in INR |
| `GET/POST /api/v1/invoices` | List active-branch invoices or create a draft with `patient_id`, optional `appointment_id`, and `lines` (`service_item_id`, `quantity`) |
| `GET/PUT /api/v1/invoices/{id}` | Read an invoice or replace an editable draft's lines and linkage |
| `POST /api/v1/invoices/{id}/issue` | Assign a hospital/year-unique number and lock charges |
| `POST /api/v1/invoices/{id}/payments` | Record a cash, UPI, card, or bank-transfer payment with `amount`, `mode`, optional/required `reference`, and UUID `request_key` |
| `POST /api/v1/documents`, `GET /api/v1/documents/{id}` | Private administrative files in current branch, administrator only |

List endpoints paginate 15 records; supported management filters are `q` and `status`. Server-derived tenant scope overrides submitted hospital IDs. An unassigned record ID returns 404, an unauthorized role returns 403, and invalid fields return 422. Missing authentication returns 401; missing/invalid browser CSRF returns 419. Staff mutations accept `memberships` containing `branch_id`, `role_id`, and optional `status`. Omitted memberships are deactivated while their history remains.

The appointment workspace opens on the active branch's current date and supports date, doctor, department, and status filters. Reception can register a patient, book scheduled or walk-in visits, check in patients, and move them to the waiting queue. Assigned doctors can move their own waiting appointments into consultation and complete them.

Administrators manage consultation and general services under **Services**. Each service has a hospital default price, an explicitly configured tax rate and discount rule, and optional branch overrides or branch unavailability. Receptionists can view available services and quotes for their active branch. Quotes use integer-cent arithmetic, apply the configured discount before tax, and round tax to the nearest cent. The fictional consultation seed uses zero tax and no discount; actual tax and price rules require hospital approval. A quote is not an issued invoice.

Administrators and receptionists create drafts under **Invoices** for patients in their hospital and optional appointments at the active branch. Invoice lines snapshot the configured branch quote multiplied by quantity. Editing a draft recalculates its lines; issuing assigns an `INV-{hospital ID}-{year}-{sequence}` number and locks charges. On an issued invoice they can record cash, UPI, card, or bank-transfer payments. Non-cash modes require a reference. The server accepts partial payments up to the remaining balance, shows received and outstanding amounts, and treats a repeated UUID request key with identical details as the same payment. A changed payload with the same key is rejected. Voids, refunds, receipt printing, and reconciliation follow in later billing tasks.

Private uploads accept PDF/JPEG/PNG up to 10 MB. Stored paths are hidden; downloads require current branch authorization. These are administrative file foundations; patient document permissions and clinical access rules come with the patient/EMR modules.

## Deployment

See [docs/deployment.md](docs/deployment.md). Production must serve **only `public/`**, use HTTPS, configure real email delivery, and preserve `.env`, the application key, database, and private storage appropriately. The repository-root `.htaccess` blocks source-file access when this project is under XAMPP's document root.
