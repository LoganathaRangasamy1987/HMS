# Hospital ERP pilot scope

Status: implementation baseline. These defaults make the first delivery concrete; pilot feedback may refine them before clinical use.

## Delivery boundaries

The first delivery is the application foundation: authentication, hospital and branch context, departments, staff memberships, permissions, audit history, and the shared administration layout. It must work with two fictional hospital organizations so that isolation can be tested.

The first operational pilot follows with one complete outpatient journey:

1. Reception registers or finds a patient.
2. Reception books an appointment or records a walk-in, then checks the patient in.
3. A doctor records the consultation, vitals, diagnoses, and prescription.
4. Reception issues an invoice, records payment, and prints a receipt.
5. Authorized staff retrieve the patient's appropriate history on the next visit.

Laboratory and batch-based pharmacy follow the outpatient pilot. IPD, nursing, radiology, OT, insurance, emergency, general inventory, HR, patient self-service, and external integrations are later deliveries. Navigation must not imply those modules already work.

## Default assumptions

- Start operations at one branch, but establish hospital isolation and multiple branch memberships immediately.
- Use one application and database, with hospital-scoped records and explicit branch context for branch operations.
- A user signs in with email and password. Email identifies the account; hospital access comes from active memberships rather than from submitted hospital IDs.
- Each staff membership assigns a role for a hospital and branch. A user may have several memberships and may switch only to a membership they are authorized to use.
- The first foundation roles are `HOSPITAL_ADMIN`, `RECEPTIONIST`, and `DOCTOR`. Other roles require their own permission decisions before implementation.
- Hospital administrators manage their hospital's configuration and staff. Access to another hospital is never implied. New hospital provisioning is an explicit operator task until a platform administration flow is built.
- Patient identity and UHID belong to the hospital organization. Encounters, appointments, and invoices belong to a branch. A branch of another hospital never shares that identity.
- The pilot uses INR and the Asia/Kolkata display timezone. Store event timestamps consistently and convert for display; appointment availability uses the branch timezone.
- Use fictional seed data only. Demo passwords are for the local/staging setup and must be changed or demo users removed before real operation.
- Prices and taxes are configured business data; no tax rule or clinical treatment rule is inferred by the application.

## Foundation permissions

Server-side authorization must enforce this matrix even when a user bypasses the UI. "Assigned branches" means active memberships in the currently authenticated hospital context.

| Action | Hospital administrator | Receptionist | Doctor |
| --- | --- | --- | --- |
| Sign in/out and view own account | Yes | Yes | Yes |
| Select an assigned active branch | Yes | Yes | Yes |
| View active branch dashboard | Yes | Yes | Yes |
| View hospital administration settings | Own hospital | No | No |
| Update hospital details | Own hospital | No | No |
| Create/update/deactivate branches | Own hospital | No | No |
| Create/update/deactivate departments | Own hospital | No | No |
| Create/update/deactivate staff and memberships | Own hospital | No | No |
| Assign the three supported roles | Own hospital | No | No |
| View administrative audit history | Own hospital, if exposed in UI | No | No |
| Read another hospital's records | No | No | No |

Branch selection is operational context, not a grant of authority. Changes to membership, user status, or branch status must take effect on subsequent requests. Prevent administrative changes that would leave a hospital without an active administrator.

## Outpatient pilot permissions

These capabilities are requirements for the next deliveries, not features promised in the foundation.

| Action | Hospital administrator | Receptionist | Doctor |
| --- | --- | --- | --- |
| View/search patient demographics | Own hospital | Assigned branches and shared hospital patient identity | Assigned branches and shared hospital patient identity |
| Register/update patient demographics | Yes | Yes | Read only |
| Manage appointments/check-in | Yes | Yes | View own queue |
| View confidential clinical history | Requires a clinical role | No; operational visit status only | Patients in an authorized care relationship |
| Create/finalize consultation or prescription | Requires a doctor role | No | Own encounters |
| Issue invoice and record payment | Yes | Yes | No |
| Approve void/refund or amend financial configuration | Yes | No | No |
| View operational collection reports | Own hospital | Assigned branch, permitted scope | No |

An administrative role alone does not grant unrestricted clinical access. Clinical history access rules must be implemented before Patient 360 is released; its API must omit sections the caller cannot access.

## Hospital and staff rules

- Every branch belongs to exactly one hospital; every department belongs to one hospital and branch.
- Related records must agree on hospital and branch ownership. A department from branch A cannot be assigned to a membership in branch B.
- A staff creation form cannot grant access to another hospital through a hidden input, crafted JSON, or foreign record ID.
- Deactivate referenced branches, departments, and staff rather than deleting their historical records.
- Passwords are stored only as framework-supported hashes. Never return hashes in JSON, forms, logs, or audit payloads.
- Failed sign-ins use generic errors and throttling. Successful sign-in rotates the session; sign-out invalidates it. Browser mutations use CSRF protection.
- An unauthenticated request must never obtain tenant data. A user with no valid membership receives a clear access-unavailable screen rather than an arbitrary default hospital.
- Derive query scope from authenticated context. Apply the same rules to list/detail endpoints, search, exports, files, and queued work.

## Reception workflow

Patient registration requires first name, date of birth or a defined unknown-date workflow, gender, and a usable contact method. A phone number is not a unique patient identifier because family members may share it. Show possible duplicates before creation; do not merge automatically. Generate a hospital-unique UHID atomically and keep it stable.

### Patient identity implementation — HMS-201

- Patient identity belongs to `hospital_id`; `patients.branch_id` records the original registration branch and does not change when the patient attends another branch. Future encounters carry their own branch. `registered_by` optionally retains the original staff actor.
- The pilot UHID format is `HSP-{hospital database ID}-{Asia/Kolkata calendar year}-{sequence padded to at least six digits}`. For example, the first patient in hospital 1 in 2026 receives `HSP-1-2026-000001`. Each hospital/year has its own counter, shared across branches. A hospital name/code change cannot change an existing UHID, and numbers beyond 999999 expand without truncation. Configurable prefixes remain an onboarding/product decision; issued identifiers must stay stable.
- The internal `PatientIdentityService::create` operation locks the hospital row before initializing/incrementing its counter, then inserts the patient in the same retried transaction. A failed transaction rolls back both. [Laravel transaction and retry documentation](https://laravel.com/docs/13.x/database#database-transactions) describes the underlying framework behavior.
- Composite foreign keys enforce hospital agreement for the registration branch and optional staff actor. A hospital/UHID unique constraint prevents duplicate identifiers. Patient identity/provenance fields are excluded from mass assignment and cannot be changed by model saves; direct SQL/bulk updates are trusted operations that bypass model events and must not be used to rewrite identity.
- `Patient::forHospital($hospitalId)` is an explicit query scope. The service is an internal persistence boundary, not an HTTP authorization layer. HMS-202 must derive hospital/branch/staff from active authenticated context, enforce patient permissions for every endpoint, and use scoped lookups. No patient routes or screens are exposed by HMS-201.
- Initial storage permits an unknown date of birth only with `date_of_birth_unknown=true` and no supplied date. Do not invent a date from age. Accepted gender values are `male`, `female`, `other`, and `unknown`. At least one contact value is required: mobile, email, or emergency contact mobile. Shared mobiles/emails are permitted. The registration UI, contact-format rules, duplicate suggestions, and audited edits are HMS-202.
- Patient and branch factories supply isolated test fixtures; factory UHIDs use a `TEST-` UUID prefix and do not exercise real allocation. `PatientSeeder` uses the actual creation service for fictional demo hospitals, is repeatable, and refuses production execution. Patient photographs/documents belong to HMS-203; there are no public file paths on the patient record.

### Patient clinical history and documents — HMS-203

- Allergies, medical history, and patient documents are confidential clinical data. In the current three-role foundation, doctors receive clinical view/manage permissions. Receptionists retain demographic access only, and a hospital-administrator role alone does not grant clinical access.
- Each clinical entry belongs to the hospital patient and records the active branch and staff author. Updates retain the original author/branch and create an audit entry by the current actor; `entered_in_error` preserves mistaken entries instead of deleting them.
- Patient documents accept PDF, JPEG, and PNG files up to 10 MB. Files use private local storage under a hospital/patient namespace; responses hide storage paths, and every download rechecks hospital and clinical permission.

Appointments require patient, doctor, department, branch-local date/time, type, and reason when available. Supported types are `NEW`, `FOLLOWUP`, `WALK_IN`, and `ONLINE`. Online self-booking is deferred; the type can be recorded by staff.

Allowed state transitions:

| Current state | Allowed next states | Normal actor |
| --- | --- | --- |
| `BOOKED` | `CONFIRMED`, `CHECKED_IN`, `CANCELLED`, `NO_SHOW` | Reception |
| `CONFIRMED` | `CHECKED_IN`, `CANCELLED`, `NO_SHOW` | Reception |
| `CHECKED_IN` | `WAITING`, `CANCELLED` | Reception |
| `WAITING` | `CONSULTING`, `CANCELLED` | Doctor starts consultation; reception cancels |
| `CONSULTING` | `COMPLETED` | Assigned doctor |
| `COMPLETED`, `CANCELLED`, `NO_SHOW` | None; create a new booking or an audited correction workflow | Authorized staff |

Cancellation requires a reason. Do not classify an appointment as `NO_SHOW` before its scheduled time. Rescheduling retains an audit trail and revalidates availability. Define one token sequence per branch, doctor, and appointment date; prevent collisions and concurrent slot overbooking with database-backed transactions and constraints. Walk-ins receive the next valid queue position.

## Consultation workflow

- Opening a waiting patient creates or retrieves a single encounter linked to the appointment; repeated clicks cannot create duplicate consultations.
- A draft consultation can be saved by its assigned doctor. Finalization records the author and timestamp and completes the appointment.
- A finalized clinical record is amended through an attributed correction with a reason; do not overwrite its history silently.
- Record vitals with units, measured time, and recording staff member. Reject malformed values; avoid inventing clinical interpretation or treatment advice.
- Keep patient-reported allergies visible in the consultation header. Preserve who recorded or changed each allergy.
- Prescriptions capture medicine, strength, dose, frequency, duration, timing, route, and instructions. Prescription printing reproduces the saved record and identifies patient, doctor, and encounter.
- Patient history separates clinical events from billing events and respects permission checks for every returned section.

## Basic billing workflow

- Configure consultation services and prices; calculate invoice totals on the server with decimal arithmetic.
- Invoice states are `DRAFT`, `ISSUED`, `PARTIALLY_PAID`, `PAID`, and `VOID`. Record refunds separately and derive refunded amounts; a refund is not permission to erase a payment.
- Draft invoices may be edited. Issuing assigns a unique invoice number and fixes the issued charges. Later corrections require the defined void/refund or adjustment workflow.
- Support partial payments. Reject non-positive amounts and amounts greater than the remaining balance in the pilot; advances and overpayments are later work.
- Record payment mode, amount, date, receiver, and reference when applicable. A repeated submission of the same payment request must not collect it twice.
- Pilot modes are `CASH`, `UPI`, `CARD`, and `BANK_TRANSFER`, entered by staff. Selecting a mode does not claim an external gateway confirmed payment.
- Voids and refunds require an administrator, a reason, and retained history. Refunds cannot exceed eligible settled payments.
- Daily collections sum recorded payment transactions, with refunds shown explicitly. Invoice issuance and cash collection are separate report measures.

## Audit and data handling

Capture actor, hospital, branch when applicable, action, module, record identifier, timestamp, source address, and a deliberately selected change summary. Audit successful administrative mutations transactionally with the mutation where practical. Failed authorization events belong in a security log without exposing submitted secrets.

Never audit passwords, reset tokens, session identifiers, or complete unfiltered request bodies. Clinical amendment history needs sufficient previous/new content for authorized review, but ordinary application logs should avoid patient details. Audit records cannot be modified through ordinary administration screens.

Store patient documents privately with authorized download endpoints. Validate size and permitted file types. Do not publish raw storage paths. Test backup restoration before a pilot uses real records.

## Acceptance gates

### Foundation delivery

1. A documented fresh installation can migrate and seed two fictional hospitals with separate branches and staff.
2. A hospital administrator signs in and manages their hospital, branches, departments, and staff.
3. Receptionist and doctor accounts can open their dashboards but receive a denial for administration routes and forged mutation requests.
4. Hospital A cannot list, read, update, or assign hospital B's records, including by guessed IDs or altered branch/membership inputs.
5. A user assigned to two branches can switch between those branches; an unassigned or disabled branch is rejected.
6. Disabling an account or membership blocks its next protected request. Logout makes the prior authenticated session unusable.
7. Invalid submissions return useful validation messages without changing data. Successful configuration changes retain an attributable audit record.
8. The last active hospital administrator cannot be removed or disabled through staff management.
9. Secrets remain outside source control, and the application does not expose debugging details in the deployment configuration.

### Outpatient pilot

1. Reception registers a patient, books and checks in an appointment, and the correct doctor's branch queue updates.
2. Two simultaneous booking attempts cannot reserve the same exclusive slot or token.
3. The doctor finalizes an encounter and prints its saved prescription; later correction retains the original and amendment author.
4. Reception issues a consultation invoice, records two partial payments, and prints a receipt with the correct remaining balance.
5. A duplicated payment request produces one payment. A failed transaction leaves neither a partial payment nor an incorrect invoice balance.
6. The patient returns at another authorized branch of the same hospital under the same UHID. Another hospital cannot retrieve the record.
7. Patient 360 returns clinical and financial sections only to authorized roles.
8. Collection reports reconcile to payments and refunds; application backups restore successfully into an isolated environment.

## Decisions to confirm during pilot onboarding

Confirm the hospital's legal/display details, branch names, staff roster, service prices, invoice/UHID prefixes, working hours, holiday/leave rules, permitted appointment overrides, demographic requirements for unknown patients, clinical amendment access, and refund approval policy. None prevents implementing the foundation with the explicit defaults above.
