# Hospital ERP — project progress and delivery plan

Last updated: **2026-09-28**

This is the main progress record for the whole application. Update it whenever work starts, changes, passes verification, fails, or stops. A planned task is not an implemented feature, and an implemented feature is not proof of deployment.

## Current position

| Item | Actual position |
| --- | --- |
| Product | CareDesk Hospital ERP; the verified Laravel application remains the reference implementation while an approved React + Spring Boot + MongoDB replacement is built locally |
| Current sprint | **Technology migration foundation is in progress; the Laravel ERP Lite implementation remains operational and its external release closeout is deferred until local rewrite parity** |
| Current task | **MIG-003 — authentication, tenant context, authorization, audit, and cross-tenant rejection foundation (DONE locally); remote CI verification is pending.** |
| Latest application verification | **Migration workspace:** MIG-003 Spring tests pass for CSRF, BCrypt login, session continuity, assigned branch switching, cross-hospital rejection, and audit permission denial; Gradle build, React lint/build, and a live cookie/CSRF/login/tenant HTTP flow passed locally. GitHub Actions run 36422296390 remains the latest recorded remote pass pending the MIG-003 push. **Laravel reference:** 272 PHP tests / 2141 assertions pass with 3 opt-in skips. |
| Deployment | Source is pushed to the public GitHub repository and remote CI passes. Local development uses persistent SQLite at `database/database.sqlite`; all migrations and fictional demo seeds are applied and the local server returns HTTP 200. Existing MariaDB files were not modified or removed. External staging and production are not deployed |
| Next coding task | **MIG-004 — patients, doctors, availability, appointments, slot picker, tokens, and reception parity** |
| Next foundation closeout tasks | HMS-002 and HMS-012 are complete; finish HMS-010 staging verification and configure real mail delivery in HMS-011 when their external prerequisites are available |
| First operational pilot | End of Sprint 4: registration → appointment/check-in → consultation/prescription → invoice/payment → patient history |
| ERP Lite release | End of Sprint 7, after laboratory, pharmacy, reporting, and release acceptance |
| Full application | Advanced hospital modules, patient access, product administration, integrations, and final release gates in Sprints 8–18 |

Local development URL: `http://127.0.0.1:8000`. This is the configured address; server availability must be checked when resuming work. Setup and demo access are documented in [README.md](README.md). Do not copy passwords, tokens, connection strings, or patient information into this tracker.

## How to maintain this file

1. **Before implementation:** read this file, identify the task ID, check its dependencies, and set the current task and its status. For new work, add a stable task ID before starting.
2. **During work:** update the task after meaningful progress, scope changes, failed checks, or a blocker. Record what actually changed and what still needs work.
3. **Before ending a work session:** update task/sprint status, verification evidence, blockers, next steps, last-updated date, and the dated work log. Do this even when work is incomplete.
4. **On completion:** state the delivered behavior and evidence. For an application feature, normally include schema, business rules, authorization, API, usable UI, and appropriate checks. Explicitly identify narrower tasks such as documentation or backend-only infrastructure.
5. **Preserve history:** retain task IDs and dated entries. If scope changes, explain the change and move unfinished work to named tasks; do not silently remove it or renumber completed tickets.
6. **Be precise about checks:** record commands, results, environment, and date. Never turn an old result into a claim that tests were rerun. Do not rerun application suites solely to update documentation.
7. **Keep one status source:** README explains operation, the pilot scope explains requirements, and deployment documentation explains deployment. This file records progress across all of them.
8. **Keep instructions durable:** [AGENTS.md](AGENTS.md) and [CLAUDE.md](CLAUDE.md) must continue to point here. Preserve the project tracking instruction outside generated Laravel Boost blocks when those files are regenerated.

### Status definitions

| Status | Meaning |
| --- | --- |
| PENDING | Required work that has not started |
| IN PROGRESS | Work is actively being implemented or checked |
| PARTIAL | Some deliverables exist; named completion conditions remain |
| BLOCKED | A specific missing dependency prevents the next action; record what resolves it |
| DONE | The task's stated scope is implemented and verified to the extent recorded |
| DEFERRED | Intentionally moved out of the current release, with its destination recorded |

`DONE` applies to the stated task scope. For example, a local password-reset implementation can be done while delivery through a real email provider remains a separate pending task. A sprint closes only when its own exit conditions pass or unfinished items are explicitly reassigned with a reason. Do not estimate completion percentages from the number of tables or screens.

## Sprint and release overview

These are working delivery batches, expanded from the earlier M0–M10 milestones so that the later modules have explicit tasks. They are **not promises that each batch fits a two-week sprint**. Refine effort and capacity before setting dates; split large batches while preserving task IDs.

| Sprint | Scope | Earlier milestone | Dependencies | Status |
| --- | --- | --- | --- | --- |
| 1 | Environment, authentication, tenants, organization, staff, shared UI | M0–M1 | None | PARTIAL — local foundation done |
| 2 | Patients, doctors, schedules, appointments, reception | M2 | Local Sprint 1 foundation | DONE — HMS-201 through HMS-208 passed local acceptance; external staging is separately tracked |
| 3 | Basic billing, payments, receipts, refunds | M3 | Patient identity in Sprint 2 | DONE — HMS-301 through HMS-306 passed local acceptance; external staging remains separately tracked |
| 4 | OPD, EMR, vitals, diagnosis, prescriptions, Patient 360 | M4 | Sprint 2; Sprint 3 for complete pilot | DONE — HMS-401 through HMS-407 passed local acceptance |
| 5 | Laboratory ordering, specimens, results, verification | M5 | Clinical and billing interfaces | DONE — HMS-501 through HMS-506 passed local acceptance |
| 6 | Pharmacy purchases, batches, dispensing, sales, returns | M6 | Clinical and billing interfaces | DONE — HMS-601 through HMS-606 passed local acceptance |
| 7 | Reports, search, notifications, ERP Lite release | M7 | Sprints 2–6 | IN PROGRESS — HMS-701 through HMS-704 complete locally; HMS-705 local tooling complete with external gates pending |
| 8 | IPD, beds, nursing, medication administration, discharge | M8 | Clinical and billing foundation | PENDING |
| 9 | Radiology | M9 | Clinical and billing foundation | PENDING |
| 10 | Operation theatre and surgery | M9 | Sprint 8 | PENDING |
| 11 | Emergency | M9 | Patient/clinical workflows; Sprint 8 for admission | PENDING |
| 12 | Insurance and claims | M9 | Billing and IPD | PENDING |
| 13 | General inventory and purchasing | M9 | Shared supplier/billing interfaces | PENDING |
| 14 | HR, attendance, and staff rosters | M9 | Staff identity and branch access | PENDING |
| 15 | Patient portal and mobile access | M10 | Stable clinical, booking, billing, and report APIs | PENDING |
| 16 | Platform administration, multi-branch operations, editions | M10 | Stable modules and tenant isolation | PENDING |
| 17 | Messaging, payments, insurance, devices, PACS integrations | M10 | Relevant module and provider specifications | PENDING |
| 18 | Full application acceptance and rollout | Final release gate | Required modules/integrations for selected edition | PENDING |
| Migration | React + Spring Boot + MongoDB replacement and parity cutover | Existing Sprints 1–7 provide the behavioral reference | Approved local rewrite; Laravel remains available until acceptance | IN PROGRESS — MIG-003 complete locally; remote CI pending |

Laboratory and pharmacy can proceed in parallel after their shared clinical and billing contracts are defined. Other independent modules can overlap when the team has capacity. External setup tasks do not prevent independent local implementation.

## Approved React, Spring Boot, and MongoDB migration

The user approved a local full-stack rewrite on 2026-09-28. The existing Laravel application and its tests remain the behavioral reference and must not be removed until replacement acceptance passes. MongoDB Compass is an administration client; MongoDB Server is the application database. Financial, appointment-capacity, numbering, and stock operations require transaction-safe designs and a local replica set before those modules are accepted.

| ID | Task and completion condition | Status |
| --- | --- | --- |
| MIG-001 | Workspace/runtime foundation: verify Java, Node, MongoDB Server and Compass; scaffold Spring Boot API and React UI; configure local environment templates, health checks, builds, and tests without committing secrets | DONE — Java 25, Node 22, MongoDB Server 8.2.1 and Compass 1.49.5 verified; Spring Boot 4.1 API and React 19/TypeScript workspace, environment defaults, secured routing baseline, live health UI, Gradle/frontend builds, tests, lint, local Mongo connection and CI job completed |
| MIG-002 | MongoDB architecture and migration contracts: collection boundaries, indexes, references/snapshots, audit history, decimal/date conventions, transaction/replica-set requirements, and repeatable fictional SQLite-to-Mongo import validation | DONE — isolated `caredesk-rs` on port 27018, transaction manager and rollback proof, versioned named indexes, tenant/reference/snapshot/Decimal128/UTC/append-only conventions, count-only allowlisted SQLite inventory, Compass URI, repeatable commands, build and live health verified without altering legacy data |
| MIG-003 | Authentication and tenant foundation: users, hospitals, branches, memberships, roles/permissions, secure browser authentication, active context, audit, and cross-tenant rejection | DONE locally — Mongo identity collections and indexes, BCrypt login, HTTP-only session, CSRF, session rotation/expiry, active membership switching, server permissions, append-only audit, cross-hospital rejection, local demo UI/data, automated tests, live HTTP verification |
| MIG-004 | Patients, doctors, availability, appointments, slot picker, tokens, and reception parity | PENDING |
| MIG-005 | Service catalog, invoices, payments, adjustments, receipts, and reconciliation parity | PENDING |
| MIG-006 | Encounters, consultations, vitals, diagnoses, prescriptions, documents, and Patient 360 parity | PENDING |
| MIG-007 | Laboratory catalogs, orders, specimens, results, verification, reports, and worklists parity | PENDING |
| MIG-008 | Pharmacy catalogs, purchases, batches, dispensing, returns, adjustments, alerts, and reconciliation parity | PENDING |
| MIG-009 | Operational reports, global search/export, notifications, dashboards, and role-aware navigation parity | PENDING |
| MIG-010 | Local acceptance and cutover: full regression/browser journeys, concurrency and transaction checks, data migration rehearsal, operating guide, and explicit approval before Laravel retirement | PENDING |

**Migration exit:** the React/Spring Boot/MongoDB implementation passes equivalent tenant, role, workflow, financial, clinical, laboratory, pharmacy, reporting, and recovery acceptance. Laravel remains intact until MIG-010.

## Sprint 1 — application foundation

**Goal:** an administrator can sign in and manage their hospital, branches, departments, and staff; permissions and hospital isolation are enforced on the server.

| ID | Task | Status | Actual work completed | Pending / completion condition |
| --- | --- | --- | --- | --- |
| HMS-001 | Document pilot scope and business rules | DONE | [Pilot scope](docs/pilot-scope.md) defines foundation boundaries, roles, outpatient states, billing rules, audit principles, and acceptance gates | Validate real hospital details and operating choices during pilot onboarding; no claim of stakeholder sign-off |
| HMS-002 | Project environment and reproducible setup | DONE | Project-local PHP/Composer, Laravel dependencies and lockfiles, Bootstrap build, dedicated local database/account, environment example, setup scripts, README and ignore rules; public GitHub `main` established with excluded secrets/runtime data, and clean-runner checkout/install/build/test/cache compilation passed in GitHub Actions run 36416408904 | No remaining work for this scope; staging deployment is HMS-010/HMS-705 |
| HMS-003 | Foundation schema and demo data | DONE | Hospital, branch, department, user, membership, role, permission, audit, private-file, session, queue, and cache structures; ownership constraints; two fictional hospitals seeded | Future clinical/financial tables belong to later sprints |
| HMS-004 | Authentication and password-reset application flow | DONE — local scope | Sign in/out, hashing, throttling, encrypted sessions, disabled-account handling, reset notifications, token expiry/reuse checks, and browser CSRF checks | Real mail delivery is HMS-011; actual elapsed session-idle expiry verification is HMS-012 |
| HMS-005 | Authorized hospital/branch context | DONE | Active memberships determine tenant scope; assigned-branch switching; inactive, revoked, and foreign membership rejection | Multi-hospital identities and platform access are not implemented; see Sprint 16 |
| HMS-006 | Server-side role authorization | DONE — foundation roles | Administrator, receptionist, and doctor role seeds; server-enforced administration restrictions; role-aware navigation | Nurse, lab, pharmacy, accountant, patient, and platform roles are added with their modules; arbitrary custom-role management is pending |
| HMS-007 | Organization and staff management | DONE — local scope | Hospital details; branch/department/staff create/edit/search/deactivation; branch role assignments; retained membership history; administrator safeguards; provisioning CLI implemented | Platform provisioning UI is Sprint 16. Exercise CLI provisioning on clean staging as part of HMS-010 |
| HMS-008 | Shared Bootstrap portal | DONE | Sign-in/reset pages; responsive sidebar/header; active branch indicator; dashboard counts; administration forms/tables/messages and access error pages | New module screens will extend this layout |
| HMS-009 | Audit and private administrative files | DONE — foundation scope | Allowlisted transactional change audit; read-only log; authorized PDF/JPEG/PNG upload/download API with branch/hospital scope and hidden storage paths | Patient document screen and clinical access are Sprint 2/4. This is not a finished clinical document module |
| HMS-010 | Automated checks, CI, and staging verification | PARTIAL | PHP and browser suites, [CI definition](.github/workflows/ci.yml), deployment guide and local checks; GitHub Actions “Foundation checks” run 36416408904 passed on pushed commit `d1cd796` | Deploy staging, provision a clean organization, and record staging smoke/access checks |
| HMS-011 | Configure real password-reset delivery | PENDING | Local mailer writes reset notifications to the development log | Configure provider/domain/sender/credentials and verify delivery plus reset flow on staging; never put those secrets in this file |
| HMS-012 | Verify idle-session expiration with database sessions | DONE — local scope | Dedicated database-handler coverage proves payload rejection after the configured 30-minute idle lifetime; remember-me HTTP coverage restores an authorized user and safely re-establishes the first active branch only when session context is absent; stale/revoked selected memberships remain forbidden | Repeat against staging after HMS-705 infrastructure is available; local behavior and regression are verified |
| TRACK-001 | Create persistent roadmap and progress tracking | DONE | Full roadmap, actual statuses, verification history, pending dependencies, next steps, README link, and persistent instructions in AGENTS.md/CLAUDE.md; checked 105 unique task IDs, 18 sprint sections, and all local links | Maintain this file during every future project work session |

**Sprint exit:** local administration and isolation work; a fresh installation is demonstrated; remaining setup/delivery checks above pass or are explicitly carried forward. **Current result: local foundation accepted by automated checks; sprint closure pending.**

## Sprint 2 — patients, doctors, appointments, and reception

HMS-201 is complete: patient/sequence migrations, model relationships and identity guards, a transactional creation service, baseline demographic validation, and fictional factories/seeder are implemented and verified. The tables and fictional seeds are applied locally. This is a backend-only identity foundation; registration/edit/search screens, HTTP authorization, duplicate suggestions, and demographic audit are HMS-202. Existing staff users are not doctor clinical profiles, and private administrative uploads are not patient documents.

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-201 | Patient migrations/models and hospital-level UHID: generate stable unique identifiers atomically; retain registration branch; enforce hospital ownership and meaningful indexes | DONE — backend-only scope; 47 patient feature tests plus isolated MariaDB concurrency test passed; migrated/seeded locally |
| HMS-202 | Registration/edit/search: demographics, emergency contacts, unknown-date policy, duplicate suggestions, search by UHID/name/mobile; shared phones are allowed; audit changes | DONE — portal/API registration, detail/edit/search, role permissions, explicit duplicate confirmation, normalized contacts, and transactional demographic audit verified locally |
| HMS-203 | Patient allergies, medical history, and documents: attributed entries, validated private uploads, authorized retrieval, and role-appropriate visibility | DONE — doctor-only clinical screen/API, attributed allergy/history records, audited status updates, and private validated patient documents verified locally |
| HMS-204 | Doctor profiles: staff link, department, registration/qualification/specialization, fees, and active status; branch assignments remain consistent | DONE — administrator-managed branch profiles with doctor-membership/department consistency, directory access, audit, and local verification |
| HMS-205 | Availability: weekly schedules, slot durations/capacity, holidays, leave, and exceptions; define branch timezone and date handling | DONE — branch-timezone schedules, overlap controls, inclusive closures, per-date exceptions, audited owner/admin management, and role-aware directory scope; doctors see only their own active-branch profile |
| HMS-206 | Appointments: create/reschedule/cancel, types and allowed states, schedule validation, transaction-safe slot and token allocation, duplicate-request handling | DONE — branch appointments, availability/capacity enforcement, serialized tokens, request idempotency, audited reschedule/cancel, role-scoped queue, UI/API, and local verification |
| HMS-207 | Reception workspace: new patient, booking, walk-in, check-in, daily queue, token/status updates, and doctor/department filters | DONE — current-day branch queue, registration shortcut, booking/walk-in controls, doctor/department/status filters, audited reception and assigned-doctor transitions, and local verification |
| HMS-208 | Acceptance: complete reception journey; cross-hospital and branch access checks; simultaneous booking/token tests against the intended database; useful API validation | DONE — registration-to-doctor-queue feature test, tenant/branch/doctor isolation, duplicate-key validation, isolated eight-worker MariaDB slot/token test, and full local regression/browser verification |
| HMS-209 | Reception appointment availability picker: selecting a doctor shows bookable dates and capacity-aware time slots | DONE — computed dates/slots, remaining capacity, schedule exceptions/leave, accessible dynamic controls, tenant authorization, and local verification complete |

**Sprint exit:** reception registers a patient and places them in the correct doctor's queue without cross-tenant exposure or slot/token collisions.

HMS-201 implementation: [migration](database/migrations/2026_09_13_171148_create_patient_identity_tables.php), [Patient model](app/Models/Patient.php), [creation service](app/Services/PatientIdentityService.php), [patient feature tests](tests/Feature/PatientIdentityTest.php), and [MariaDB concurrency test](tests/Feature/PatientIdentityConcurrencyTest.php). Default IDs use `HSP-{hospital ID}-{India calendar year}-{sequence padded to at least six digits}`; hospital/year counters span branches. Issued UHIDs and registration provenance stay fixed. The [pilot scope](docs/pilot-scope.md) records the unknown-date/contact defaults, trusted service boundary, explicit hospital query scope, and future endpoint requirements.

## Sprint 3 — basic billing and payment collection

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-301 | Service catalog and prices: consultation/service definitions, configurable taxes/discount rules, hospital/branch availability | DONE — hospital catalog, explicit INR prices/tax/discount rules, branch overrides and exclusions, exact quote arithmetic, admin UI/API, reception view, audit, and local verification |
| HMS-302 | Invoice schema and issue flow: line items, decimal calculations, numbering, draft/issued states, immutable issued charges, and patient/visit linkage | DONE — active-branch portal/API draft creation/edit/issue, exact line snapshots, hospital/year numbering, patient/optional appointment linkage, issued-charge guard, audit, and local verification |
| HMS-303 | Payments: cash/UPI/card/bank entries, references, partial payments, balances, and duplicate submission protection using database transactions | DONE — immutable payment entries, actor/time/reference, exact partial/full balances, issued-invoice status updates, UUID replay protection under transactional locks, portal/API, authorization, audit, and local verification |
| HMS-304 | Voids/refunds/adjustments: permitted actors, required reasons, eligible amounts, and preserved original financial entries | DONE — immutable admin-only void/refund/credit/debit ledger, eligibility rules, idempotency, audit, UI/API, MariaDB migration/permissions, focused tests, and live browser acceptance verified locally |
| HMS-305 | Billing screens and printouts: invoice list/search/detail, payment collection, invoice/receipt printing, and outstanding balances | DONE — branch-scoped search/status/date/outstanding filters, adjusted totals and balances, existing payment collection, printable issued invoices and per-payment receipts, authorization, and responsive browser verification completed locally |
| HMS-306 | Reconciliation and acceptance: daily collections reconcile to payments/refunds; test duplicate/concurrent payments, failed transactions, and permission boundaries | DONE — branch-local daily gross/refund/net and mode reconciliation, immutable ledgers, admin isolation, failed/replayed payment checks, isolated MariaDB concurrency, full regression, and browser acceptance verified locally |

**Sprint exit:** staff issue a consultation invoice, collect partial/full payment, and print a correct receipt; refunds and repeated requests preserve the ledger.

## Sprint 4 — OPD, EMR, prescriptions, and Patient 360

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-401 | Encounter foundation: a consistent OPD/IPD relationship for later clinical entries; assigned care-team access; one encounter per appointment where required | DONE — immutable generic encounter root, one OPD encounter per appointment, atomic/idempotent consultation opening, assigned-doctor clinical access, local migration/permissions/backfill, and verification complete |
| HMS-402 | Doctor queue and consultation: complaint/history/examination/notes, draft saves, finalization, follow-up, appointment transitions, attributed amendments | DONE — assigned-doctor portal/API, drafts, required-content finalization, follow-up, atomic appointment/encounter closure, immutable finalized source, and attributed correction history verified locally |
| HMS-403 | Vitals: units, measurement time, recorder, validation, and links that support both consultations and future admissions | DONE — immutable patient/branch/encounter observations, explicit persisted units, branch-local measurement time, recorder attribution, validation, assigned-doctor UI/API, factory, and local verification complete |
| HMS-404 | Diagnoses: provisional/final entries, optional coding, author, dates, and correction history | DONE — immutable attributed encounter diagnoses, optional paired coding, branch-local time, reasoned correction history, UI/API, and local verification complete |
| HMS-405 | Medicine master and prescriptions: medicine/strength/dose/frequency/duration/route/timing/advice, allergy visibility, and saved-record printing | DONE — hospital medicine catalog, immutable multi-line encounter prescriptions, allergy visibility, snapshot preservation, printing, authorization, and local verification complete |
| HMS-406 | Patient 360: authorized summary and paginated timeline; fetch only permitted clinical/financial sections; index queries and control payload size | DONE — permission-aware summary and database-paginated operational/clinical/financial timeline verified locally |
| HMS-407 | Operational pilot acceptance: registration through consultation, prescription, payment, and return visit; validate clinical amendments and complete care/administration permission boundaries | DONE — automated full journey, immutable correction/amendment history, receipt, return booking, Patient 360 separation, and role boundaries passed locally |

**Sprint exit / first pilot:** the complete outpatient journey works with saved records and verified clinical/financial boundaries. Real hospital workflow review is still required before operational use.

## Sprint 5 — laboratory

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-501 | Test/parameter catalogs: categories, sample types, units, reference ranges/text, prices, and active versions | DONE — hospital-scoped lookup masters and immutable active/draft/archived test versions with parameters, reference values, INR prices, portal/API, permissions, audit, and local verification |
| HMS-502 | Lab orders and billing: patient/encounter/doctor linkage, items, charge creation, permitted cancellation and payment-dependent rules | DONE — branch-scoped orders, immutable active-version/price snapshots, automatically issued invoices, doctor self-scope, cancellation/payment safeguards, portal/API, audit, and local verification |
| HMS-503 | Specimens: collection labels/identifiers, collected-by/time, receipt, processing, rejection/recollection, and status history | DONE — unique specimen attempts and printable labels, attributed state transitions, rejection/recollection, immutable event history, laboratory technician role, portal/API, audit, and local verification |
| HMS-504 | Results and verification: values/flags, technician entry, authorized verifier, finalization, corrections, and report printing | DONE — typed version-bound result revisions, server reference flags, independent verification, immutable finalization, reasoned correction history, printable reports, permissions, audit, and local verification |
| HMS-505 | Lab portal and notifications: order worklist, collection/processing/verification queues, and authorized report availability | DONE — role-aware worklists, event notifications/read state, report discovery, permissions, UI/API, and local verification complete |
| HMS-506 | Acceptance: order through verified report and billing; unverified/corrected results, role checks, and patient/hospital isolation | DONE — complete paid order-to-report journey, retained corrections, report gating, role/doctor/patient/tenant isolation, and local regression verified |

**Sprint exit:** an ordered test becomes an attributable verified report with reconciled charges and retained corrections.

## Sprint 6 — pharmacy

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-601 | Extend medicine/supplier catalogs: manufacturer, brand/generic, type/unit, reorder levels, and configured tax data | DONE — normalized masters, suppliers, medicine extensions, pharmacist role, portal/API, audit, isolation, and local verification complete |
| HMS-602 | Batch stock and receipts: supplier purchases, batch/expiry, cost/sale prices, receipt items, and a stock movement ledger | DONE — branch-scoped idempotent receipts, atomic batch balances, immutable attributable movements, portal/API, authorization, audit, and local verification complete |
| HMS-603 | Dispensing and sales: prescription linkage, eligible batch selection, quantity validation, expiry blocking, transactional deductions, and invoice/receipt integration | DONE — prescription queue, FEFO-ordered eligible batches, atomic deductions, immutable sale/movement history, issued invoices, portal/API, and local verification complete |
| HMS-604 | Returns and adjustments: sale/purchase returns, damaged/expired stock, reversal references, approval rules, and retained ledger history | DONE — source-linked immutable requests, separate administrator decisions, bounded stock changes, permanent reversal movements, portal/API, and local verification complete |
| HMS-605 | Pharmacy portal and alerts: purchases, stock lookup, dispensing, low stock, and configurable expiry windows | DONE — consolidated workspace, search/status filtering, operational counts, reorder and expiry alerts, branch configuration, authorization, API, and local verification complete |
| HMS-606 | Acceptance: reconcile receipts/issues/returns to batch quantities; prevent concurrent overselling and duplicate sales; verify role and tenant boundaries | DONE — administrator reconciliation UI/API, immutable-ledger comparisons, discrepancy detection, row-locked stock deductions, replay protection, and role/tenant acceptance verified locally |

**Sprint exit:** pharmacy operations preserve batch-level stock and financial history and cannot dispense expired or unavailable quantities.

## Sprint 7 — reports, search, notifications, and ERP Lite release

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-701 | Operational reports: appointments, consultations, revenue versus collections, receivables, department/doctor activity, and lab/pharmacy reports with date/branch filters | DONE — administrator portal/API, bounded branch-timezone ranges, source-ledger financial summaries, activity breakdowns, tenant isolation, and local regression verified |
| HMS-702 | Global search and exports: authorized patient/UHID/mobile/invoice lookup, bounded queries, useful result summaries, and permission-checked exports | DONE — active-branch portal/API lookup, category-level permissions, bounded results, safe CSV export, tenant isolation, and local regression verified |
| HMS-703 | Notification service: in-app records/read state, event producers, queued delivery, retries, failure visibility, and configured email notifications | DONE — shared center, laboratory event bridge, deduplication, queued email ledger/job, retries/failure visibility, recipient/admin boundaries, and local verification complete |
| HMS-704 | Role dashboards and alerts: real workflow counts, pending verification, stock/expiry, collections, and operational drill-downs | DONE — permission-aware active-branch metrics, role-specific priorities, direct drill-downs, tenant isolation, and local regression verified |
| HMS-705 | Release engineering: complete outstanding foundation setup, performance checks, monitoring, backup and isolated restore rehearsal, deployment/rollback checks | PARTIAL — local readiness/release commands, bounded performance smoke, protected SQLite backup, isolated restore, cache/build and rollback procedure verified; production configuration, remote CI/staging deployment and managed-database restore remain pending |
| HMS-706 | Pilot onboarding and Lite acceptance: agreed hospital configuration, fictional rehearsal/data-import validation, staff walkthroughs, defect resolution, and recorded acceptance | PENDING |

**Sprint exit / ERP Lite:** the agreed outpatient, lab, pharmacy, billing, and reporting workflows pass acceptance in the deployed environment. Backup restoration and operational ownership are demonstrated.

## Sprint 8 — IPD, beds, and nursing

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-801 | Wards/rooms/beds: types, rates, availability/reservation/cleaning/maintenance states and management screens | PENDING |
| HMS-802 | Admissions and allocation: admission numbering, responsible doctor/department, reason/diagnosis, transaction-safe bed allocation, transfers, and occupancy history | PENDING |
| HMS-803 | Nursing workspace: assigned care-team access, notes, inpatient vitals, handovers, and attributable entries | PENDING |
| HMS-804 | Medication administration: inpatient orders, schedules, given/skipped/refused events, recorder/times/reasons, and amendment history | PENDING |
| HMS-805 | Inpatient billing: bed rate snapshots, charge periods, transfers, services, deposits/settlement rules, and reconciliation | PENDING |
| HMS-806 | Discharge: summary, medications/advice, disposition, settlement, bed release/cleaning, and admission closure | PENDING |
| HMS-807 | Acceptance: admission through transfer/discharge; simultaneous bed allocation, medication records, nursing access, and bed charge correctness | PENDING |

**Sprint exit:** admission-to-discharge works without conflicting bed assignments or lost nursing, medication, and billing history.

## Sprint 9 — radiology

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-901 | Modality/exam/service catalogs, pricing, locations, and authorized staff roles | PENDING |
| HMS-902 | Orders, scheduling, examination worklist, status changes, and billing integration | PENDING |
| HMS-903 | Report drafting/finalization/amendments, authorized attachments, and Patient 360 publication | PENDING |
| HMS-904 | Acceptance for ordering through report/charges; define external study identifiers and PACS interface for HMS-1705 | PENDING |

**Sprint exit:** a radiology order produces an authorized final report and correct charge history; PACS connectivity is separately tracked.

## Sprint 10 — operation theatre

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-1001 | Theatre/resources and scheduling: surgeon, anaesthetist, patient/admission, procedure, timing, and conflict checks | PENDING |
| HMS-1002 | Preoperative records/checklists and document access, with hospital-defined templates and attribution | PENDING |
| HMS-1003 | Procedure start/end, operation notes, consumables/medications, recovery handoff, and charge capture | PENDING |
| HMS-1004 | Acceptance for planned/cancelled/completed surgery, schedule conflicts, linked records, and billing | PENDING |

**Sprint exit:** surgery scheduling, documentation, resource use, and charges remain linked to the correct patient/admission.

## Sprint 11 — emergency

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-1101 | Rapid registration including defined unknown-patient identity/reconciliation, arrival details, and emergency case numbering | PENDING |
| HMS-1102 | Triage recording, assessment timestamps, care-team assignment, emergency worklist, and escalation notifications using hospital-defined rules | PENDING |
| HMS-1103 | Emergency encounters, orders, medications, observation, admission/transfer/discharge disposition, and charges | PENDING |
| HMS-1104 | Acceptance for patient identity reconciliation, authorized access, event timing, and continuity into OPD/IPD | PENDING |

**Sprint exit:** emergency care records continue into admission, transfer, or discharge without duplicating or losing patient history.

## Sprint 12 — insurance and claims

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-1201 | Insurer/TPA catalogs, patient policies, validity/coverage fields, and protected supporting documents | PENDING |
| HMS-1202 | Eligibility/preauthorization recording, requested/approved amounts, evidence, and status history | PENDING |
| HMS-1203 | Claim preparation/submission tracking, queries/responses, approvals/rejections, settlement, and patient liability reconciliation | PENDING |
| HMS-1204 | Ageing reports, pending alerts, financial/role checks, and claim lifecycle acceptance; external exchange is HMS-1706 | PENDING |

**Sprint exit:** staff can trace a claim and reconcile insurer settlement and patient liability to the underlying invoice.

## Sprint 13 — general inventory and purchasing

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-1301 | Non-medicine item/category/unit masters, stores, suppliers, and minimum stock rules; reuse shared supplier identity where appropriate | PENDING |
| HMS-1302 | Requisitions, purchase orders/items, approvals, receipts/items, and procurement state history | PENDING |
| HMS-1303 | Store issues, consumption, transfers, returns, adjustments and counts, with an attributable stock ledger | PENDING |
| HMS-1304 | Stock/valuation/reorder reports and acceptance: concurrent issues, reconciled stock and procurement totals, and branch/store authorization | PENDING |

**Sprint exit:** general supplies can be ordered, received, issued, and reconciled independently of medicine batches.

## Sprint 14 — HR and staff rosters

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-1401 | Employee profiles linked to application users where applicable; employment details and protected HR documents | PENDING |
| HMS-1402 | Shift/roster management, branch assignments, leave requests/approvals, and availability interfaces | PENDING |
| HMS-1403 | Attendance, corrections, summaries, and payroll inputs/export; define the intended payroll scope before adding calculation rules | PENDING |
| HMS-1404 | HR role separation, reports, and acceptance of roster/leave/attendance workflows and restricted employee data | PENDING |

**Sprint exit:** authorized HR staff manage employee records and availability, with agreed payroll boundaries recorded explicitly.

## Sprint 15 — patient portal and mobile access

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-1501 | Patient-account verification, patient linkage, recovery, and explicit guardian/dependent access rules | PENDING |
| HMS-1502 | Self-service booking/rescheduling/cancellation and appointment history within hospital rules | PENDING |
| HMS-1503 | Authorized finalized prescriptions/reports, invoice/receipt access, and patient notification preferences | PENDING |
| HMS-1504 | Mobile API authentication: choose and implement token issuance/expiry/revocation/device sessions; current browser-session API does not supply mobile tokens | PENDING |
| HMS-1505 | Responsive patient portal/mobile client, error/loading/accessibility states, and acceptance proving one patient cannot access another's records | PENDING |

**Sprint exit:** verified patients can use agreed self-service functions and retrieve only their authorized records.

## Sprint 16 — platform administration, multi-branch operations, and editions

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-1601 | Platform/SUPER_ADMIN organization provisioning, lifecycle, and explicit support-access boundaries with audit | PENDING |
| HMS-1602 | Permission administration and advanced role assignments; validate server enforcement for every released module | PENDING |
| HMS-1603 | Multi-branch operations: shared patient identity, branch-specific services, authorized referrals/transfers, consolidated reporting, and branch rules | PENDING |
| HMS-1604 | Lite/Professional/Enterprise entitlement model, administrative controls, and server-enforced module availability | PENDING |
| HMS-1605 | Tenant onboarding/import validation and acceptance; decide whether a single identity spanning multiple hospitals is needed before extending the current single-hospital user model | PENDING |

**Sprint exit:** organizations and editions can be administered without bypassing tenant, clinical, or financial access rules.

## Sprint 17 — external integrations

Provider adapters remain pending until provider contracts, credentials, test environments, and workflow mappings are available. Missing external access blocks that adapter, not all local development.

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-1701 | Shared integration layer: external identifiers, credentials/configuration, job retries, idempotency, signed callbacks/webhooks where supported, failure/reconciliation logs | PENDING |
| HMS-1702 | SMS/WhatsApp/production email: templates, recipient preferences/consent rules, scheduling, delivery callbacks, and provider sandbox acceptance | PENDING |
| HMS-1703 | Payment gateway: payment intent/order, verified callback, duplicate protection, refunds, failure recovery, and ledger reconciliation | PENDING |
| HMS-1704 | Lab devices: sample/test/parameter mapping, result ingestion, units, duplicate/correction handling, and human verification before final publication | PENDING |
| HMS-1705 | PACS/DICOM: study association, authorized viewer/access flow, endpoint failures, and radiology record reconciliation | PENDING |
| HMS-1706 | Insurer/TPA interfaces: supported eligibility/preauthorization/claim exchanges, status callbacks, attachments, and settlement reconciliation | PENDING |
| HMS-1707 | Integration acceptance: provider sandbox and controlled live checks, secrets handling, timeout/retry behavior, incident ownership, and operational documentation | PENDING |

**Sprint exit:** each selected integration has recorded working exchanges and reconciliation, including failure behavior. A mock adapter is not a completed live integration.

## Sprint 18 — full application acceptance and rollout

| ID | Task and completion condition | Status |
| --- | --- | --- |
| HMS-1801 | Confirm selected edition/modules and full workflow acceptance matrix with hospital representatives; record excluded functionality explicitly | PENDING |
| HMS-1802 | Complete cross-module and concurrent-operation checks on representative data and the deployment database; validate performance targets and role/tenant boundaries | PENDING |
| HMS-1803 | Operational readiness: verified restores, upgrade/rollback rehearsal, monitoring/alerts, access review, environment configuration, and support procedures | PENDING |
| HMS-1804 | Data migration rehearsal, staff training, controlled rollout, defect resolution, and documented hospital acceptance | PENDING |
| HMS-1805 | Reconcile this tracker with the released application, record release/version and known limitations, and establish the maintenance backlog | PENDING |

**Full application completion:** every task required for the agreed release has acceptance evidence; selected modules and integrations are deployed and exercised; remaining exclusions are explicitly accepted and recorded. Completing screens alone does not close this gate.

## Supporting documentation tasks

| ID | Task and completion condition | Status |
| --- | --- | --- |
| DOC-001 | Create a separate guide for application startup, demo access, current feature workflows, patient identity usage, troubleshooting, and clear pending-feature boundaries; link it from README and this tracker | IN PROGRESS — checking source behavior and writing the guide |

## Verification record

Foundation rows retain historical results from the **2026-09-13 foundation implementation session**. Rows labeled HMS-201 are new checks from the patient identity implementation session. Documentation and run-instruction checks are identified separately.

| Check | Recorded result | Practical limit / evidence |
| --- | --- | --- |
| `php artisan test --compact` using the project PHP | **33 passed, 246 assertions** | In-memory SQLite; includes two scaffold example tests. Feature coverage: [OrganizationAccessTest](tests/Feature/OrganizationAccessTest.php), [AuthFlowTest](tests/Feature/AuthFlowTest.php) |
| `npm.cmd run test:browser` and targeted rerun | **All 4 scenarios passed across runs** | Three passed in the final broader run; branch switch/CSRF/logout passed after a test locator fix on the targeted rerun. [Browser suite](tests/browser/foundation.spec.js) |
| Local migrations and demo seed | Passed against the dedicated XAMPP MariaDB database | This is not a fresh checkout or deployed-staging test |
| Frontend asset build / JavaScript syntax | Passed | Bootstrap assets copied locally; `node --check public/js/hms.js` passed |
| Composer validation / Pint | Composer strict validation passed; PHP formatting applied | `--dirty` was unavailable without Git, so Pint ran across the project |
| Configuration, route, and Blade caches | Compilation passed | Configuration cache was subsequently cleared for local development |
| HTTP smoke checks | Login page and `/up` returned 200 | Historical checks of the local development server |
| Documentation audit for this tracker — 2026-09-13 | **Passed:** 105 unique task IDs, 18 sprint sections, zero broken local links, tracking instructions outside generated blocks | Source/test files and existing guides reviewed; Git absence confirmed. No application test suites rerun |
| Run-instruction check — 2026-09-13 | **HTTP 200** from `http://127.0.0.1:8000/login`; `hms.ps1` launch command verified | Existing local server was responding; no new server process started and no credentials/configuration changed |
| HMS-201 foundation regression — 2026-09-13 | **31 passed, 243 assertions** using `php artisan test --compact tests/Feature/OrganizationAccessTest.php tests/Feature/AuthFlowTest.php` | In-memory SQLite, including new migration and repeatable fictional patient seeding; new patient-specific checks recorded separately when complete |
| HMS-201 MariaDB concurrency — 2026-09-13 | **1 passed, 42 assertions** with `HMS_MYSQL_TEST=1` and `php artisan test --compact tests/Feature/PatientIdentityConcurrencyTest.php` | Eight independent processes/connections synchronized before first allocation across two branches. All eight UHIDs unique/sequential, one counter at 8. Test-owned random schema removed; application database untouched. This opt-in check is skipped in the default suite |
| HMS-201 local schema — 2026-09-13 | `php artisan migrate --no-interaction`, `php artisan db:seed --class=PatientSeeder --no-interaction`, and migration status **passed** | Added patient/sequence tables to the configured XAMPP database and ran fictional patient seeding; no database reset or existing configuration replacement |
| HMS-201 patient feature suite — 2026-09-13 | **47 passed, 149 assertions** with `php artisan test --compact tests/Feature/PatientIdentityTest.php` | SQLite coverage includes shared-branch/independent-hospital counters, India-year rollover, numbers beyond six digits, failed/cancelled insert and enclosing transaction rollback, immutable identity, scoped queries, ownership FKs, validation, and demo seeding safeguards |
| HMS-201 final combined suite — 2026-09-13 | **80 passed, 395 assertions; 1 skipped** with `php artisan test --compact` | Includes 47 new patient tests and all 33 existing tests. The skipped test is the opt-in MariaDB concurrency test, which passed separately above. No browser/frontend build rerun because no routes, templates, JavaScript, CSS, or dependencies changed |
| HMS-201 formatting and HTTP — 2026-09-13 | Changed PHP files formatted with Pint; local `/login` and `/up` both returned **200** after migration | `--dirty` remains unavailable without Git; selected-file Pint succeeded. No staging/remote CI claim |
| HMS-202 focused feature suite — 2026-09-15 | **39 passed, 328 assertions** with `php artisan test --compact tests/Feature/PatientRegistrationTest.php` | SQLite HTTP coverage for all pilot roles, permission revocation, hospital/branch scope, identity tampering, validation, normalized contacts, duplicate confirmation, search, escaped Activity-log details, no-op edits, and transaction rollback |
| HMS-202 final combined suite — 2026-09-15 | **119 passed, 723 assertions; 1 skipped** with `php artisan test --compact` | All PHP feature/unit tests passed. The skipped test remains the opt-in MariaDB concurrency check that passed separately for HMS-201 |
| HMS-202 browser suite — 2026-09-15 | **8 passed** with `npm.cmd run test:browser` | Existing four foundation scenarios plus receptionist search/edit, no-JavaScript duplicate review, doctor read-only access, and mobile unknown-DOB/contact validation. Initial attempts failed only while the local server was stopped; one forced-click locator correction then passed targeted and full reruns |
| HMS-202 application checks — 2026-09-15 | Patient permission seeder applied to local MariaDB; 12 patient portal/API routes listed; Blade cache, frontend build, JS syntax, selected-file Pint, and `/login` HTTP 200 passed | Local development evidence only; no remote CI, staging, or production claim |
| HMS-203 focused feature suite — 2026-09-15 | **7 passed, 52 assertions** with `php artisan test --compact tests/Feature/PatientClinicalHistoryTest.php`; combined patient suites **46 passed, 380 assertions** | SQLite coverage includes doctor access, receptionist/admin denial, tenant and nested-record isolation, attribution, validation, private storage/path hiding, download audit, file type/size restrictions, and escaped clinical UI |
| HMS-203 final combined suite — 2026-09-15 | **126 passed, 775 assertions; 1 skipped** with `php artisan test --compact` | All PHP tests passed; opt-in MariaDB concurrency remains the intentional skip already verified separately. Full Playwright suite passed **8 scenarios**, including doctor clinical-screen access |
| HMS-203 local application checks — 2026-09-15 | Migration and permission seeder applied to local MariaDB; fictional clinical-history seeder passed; Pint, Blade cache, route checks, browser JS syntax, and full browser suite passed | Private files remain local-development storage; no remote CI/staging/production or restore evidence claimed |
| HMS-204 doctor-profile suite — 2026-09-15 | **5 passed, 22 assertions** with `php artisan test --compact tests/Feature/DoctorProfileTest.php` | SQLite coverage includes create/update audit, doctor membership and department/branch consistency, registration uniqueness, role restrictions, hospital isolation, and shared directory access |
| HMS-204 final combined suite — 2026-09-15 | **131 passed, 797 assertions; 1 skipped** with `php artisan test --compact`; Playwright **8 passed** | Full regression passed; the intentional skip remains the separately verified MariaDB UHID concurrency test. Browser coverage includes administrator doctor forms and doctor directory access/management denial |
| HMS-204 local application checks — 2026-09-15 | Doctor-profile migration, permission seeder, and fictional profile seeder passed on local MariaDB; 9 routes listed; Pint, Blade cache, and browser syntax passed | Local-only evidence recorded at HMS-204 completion; availability was subsequently delivered in HMS-205 |
| HMS-205 focused feature suite — 2026-09-15 | **5 passed, 24 assertions** with `php artisan test --compact tests/Feature/DoctorAvailabilityTest.php` | SQLite HTTP coverage includes weekly hours and overlap rejection, date validation, closure/exception persistence and replacement, role/owner authorization, hospital isolation, timezone output, and read-only access |
| HMS-205 final combined suite — 2026-09-15 | **136 passed, 821 assertions; 1 skipped** with `php artisan test --compact`; Playwright **8 passed** | Full regression and browser suite passed. Browser coverage includes the owning doctor opening the availability screen and seeing management controls and the branch timezone; the final branch-timezone form change also passed 6 focused PHP checks / 42 assertions and its targeted browser scenario |
| HMS-205 local application checks — 2026-09-15 | Migration batch 5, permission seeder, repeatable profile/availability seeders, 19 doctor routes, Pint, Blade cache, browser JavaScript syntax, and MariaDB migration status passed | First MariaDB attempt exposed an overlong generated index name and stopped before migration registration; incomplete HMS-205 objects were removed, the index was explicitly shortened, and the clean rerun passed. Local-only evidence; no staging/production claim |
| HMS-205 doctor self-scope correction — 2026-09-15 | Full suite **137 passed, 825 assertions; 1 skipped**; targeted doctor Playwright scenario passed | Doctor-role directory/API queries now return only the signed-in doctor's profile for the active branch. Administrator and receptionist behavior remains hospital-directory access |
| HMS-206 appointment feature suite — 2026-09-15 | **5 passed, 28 assertions** with `php artisan test --compact tests/Feature/AppointmentTest.php` | SQLite HTTP coverage includes capacity and token allocation, duplicate request keys, schedule boundaries, closures, reschedule/cancel lifecycle, audit, and doctor read-only queue permissions |
| HMS-206 final combined and browser suites — 2026-09-15 | **142 passed, 853 assertions; 1 skipped** with `php artisan test --compact`; Playwright **9 passed** | Full regression passed. Browser coverage includes receptionist booking controls and doctor read-only appointment access. The intentional skip remains the previously verified UHID concurrency test |
| HMS-206 local application checks — 2026-09-15 | MariaDB migration batch 6, permissions, repeatable fictional appointment seeder, 8 appointment routes, Pint, Blade cache, JavaScript syntax, and full browser suite passed | Allocation is protected by database transactions, doctor-row locks, capacity checks, idempotency uniqueness, and token uniqueness. Simultaneous appointment load testing on MariaDB remains HMS-208 acceptance evidence |
| HMS-207 reception workflow suite — 2026-09-15 | **7 passed, 48 assertions** with `php artisan test --compact tests/Feature/AppointmentTest.php` | Covers walk-in booking, daily doctor/department/date filtering, reception check-in/waiting transitions, assigned-doctor consultation/completion, invalid terminal transitions, future no-show rejection, cancellation, and permissions |
| HMS-207 final combined and browser suites — 2026-09-15 | **144 passed, 873 assertions; 1 skipped** with `php artisan test --compact`; Playwright **9 passed** | Full browser coverage confirms receptionist queue controls and filters plus doctor read-only booking access. Ten appointment routes, Pint, Blade cache, JS syntax, and local permission reseeding passed |
| HMS-208 reception acceptance — 2026-09-16 | **4 passed, 35 assertions** with `php artisan test --compact tests/Feature/ReceptionAcceptanceTest.php` | Registration → walk-in booking → check-in → waiting → assigned-doctor consultation; cross-hospital/branch and other-doctor isolation; changed-payload idempotency key and input validation |
| HMS-208 isolated MariaDB booking concurrency — 2026-09-16 | **1 passed, 28 assertions** with `HMS_MYSQL_TEST=1` and `php artisan test --compact tests/Feature/AppointmentConcurrencyTest.php` | Eight independent PHP processes/connections entered a common barrier for one capacity-two slot; exactly two booked with distinct tokens 1 and 2, six received full-slot validation. Random test schema was removed; application database untouched |
| HMS-208 final local regression — 2026-09-16 | **148 passed, 909 assertions; 2 skipped** with `php artisan test --compact`; Playwright **9 passed** | First browser attempt failed because local server was stopped; after starting it and checking `/login` returned HTTP 200, all nine scenarios passed. Pint passed. No remote CI/staging/production claim |
| HMS-301 focused catalog suite — 2026-09-16 | **4 passed, 42 assertions** with `php artisan test --compact tests/Feature/ServiceCatalogTest.php` | SQLite HTTP coverage includes exact discounted/taxed totals, branch overrides/availability, admin/reception/doctor permissions, hospital isolation, duplicate codes, discount validation, and audited changes |
| HMS-301 final local checks — 2026-09-16 | **152 passed, 951 assertions; 2 skipped** with `php artisan test --compact`; Playwright **11 passed** | MariaDB migration batch 7, permission and fictional catalog seeders, 13 service routes, Pint, Blade cache, browser JS syntax passed. First combined browser attempt timed out once while PHP regression ran concurrently; the complete standalone browser rerun passed |
| HMS-302 focused invoice suite — 2026-09-16 | **4 passed, 53 assertions** with `php artisan test --compact tests/Feature/InvoiceTest.php` after final model change | SQLite HTTP coverage includes exact line/total math, draft edits, issue snapshots and numbering, immutable charges, appointment uniqueness/linkage, invalid lines, tenant and role isolation, and portal pages |
| HMS-302 local regression — 2026-09-16 | **156 passed, 1004 assertions; 2 skipped** with `php artisan test --compact`; browser: **11/12 in complete run, remaining test passed on isolated rerun** | Local MariaDB invoice migration and permission seeder, 12 invoice routes, Pint on changed PHP files, Blade cache, browser JS syntax, focused invoice browser journey passed. An older administrator navigation scenario timed out at `/doctors/create` in the full browser run and passed alone; no remote CI/staging/production claim |
| HMS-303 focused payment suite — 2026-09-16 | **3 passed, 60 assertions** with `php artisan test --compact tests/Feature/PaymentTest.php` | Covers exact partial/full balances, cash/UPI/card validation, required noncash reference, repeat request replay before/after full settlement, changed-payload rejection, overpayment/zero rejection, immutable rows, draft/role/branch/hospital boundaries, and payment audit |
| HMS-303 local regression — 2026-09-16 | **159 passed, 1064 assertions; 2 skipped** with `php artisan test --compact`; focused Playwright invoice/payment journey **1 passed** | Local MariaDB payment migration and permission seeder, payment routes, Pint, Blade cache, and browser JavaScript syntax passed. The focused browser journey records two payments and verifies the zero balance. No fresh full browser suite, remote CI, staging, or production claim |

Earlier browser failures exposed a staff Blade parsing error and a mobile menu stacking issue; both were fixed before the successful scenarios. Do not report those resolved failures as current blockers.

## Pending dependencies and immediate next steps

| Item | What is missing | Affected task / next action |
| --- | --- | --- |
| Remote CI / staging | GitHub remote and CI are working; no staging target or deployment credentials are recorded | HMS-010/HMS-705: establish the staging target and run the documented deployment and acceptance steps |
| Real mail | Development log transport only | HMS-011: configure SMTP/provider and verify delivered resets |
| Hospital onboarding details | Actual branch/staff roster, patient requirements, numbering, fees, scheduling, and approval policies need validation | Use explicit pilot defaults for implementation; confirm during onboarding before operational release |
| Provider-specific integrations | Provider selection/specifications/test access not recorded | Sprint 17: define contracts and test each adapter when access exists |
| Operational restore | Local isolated SQLite restore passed; managed database/private-file restoration is not recorded | Complete the external HMS-705 rehearsal, then repeat for final rollout scope in HMS-1803 |

Next execution order:

1. Complete the external **HMS-705** release gates when staging, production configuration, mail-provider, and managed-restore access are available; remote CI is complete.
2. Run **HMS-706** pilot onboarding and ERP Lite acceptance with agreed hospital configuration and staff participants.
3. Continue HMS-010/HMS-011 external foundation closeout alongside those release activities; HMS-002 is complete.
4. Start Sprint 8 with HMS-801 after ERP Lite acceptance, while maintaining this tracker at every work session.

## Dated work log

### 2026-09-28 — React/Spring authentication and tenant foundation completed locally

- **Task:** MIG-003 — DONE for local implementation and acceptance; remote CI verification is pending the public push.
- **Actual changes:** added Mongo-backed hospitals, branches, users, roles/permissions, and memberships with idempotent fictional local identities and versioned `MIG-003-v1` indexes. Added BCrypt authentication, generic 401/403 responses, HTTP-only 30-minute server sessions, login session-ID rotation, cookie-backed CSRF, active assigned-membership context, cross-hospital consistency checks, permission-gated hospital audit history, and sign-in/sign-out/branch-selection audit events. Replaced the React migration placeholder with a responsive login and role/context-aware portal shell. Updated migration operating instructions; the `local` Spring profile must not be used for deployment.
- **Verification:** all seven Spring tests passed after the implementation, including four authentication/tenant tests covering missing CSRF, login/session continuity, allowed branch switching, foreign-hospital membership rejection, administrator audit access, and receptionist audit denial. The Gradle application artifact was built, React lint and production build passed, and a fresh API on port 8082 completed a real CSRF-token → BCrypt login → session cookie → `/me` flow returning the correct Lotus hospital, Coimbatore branch, administrator role, and two assigned memberships. No Laravel data was modified.
- **Implementation note:** the first run exposed Spring 7 constructor selection and TypeScript type-only import requirements, both corrected. Authentication itself is intentionally performed before any Mongo transaction; placing the password lookup inside a transaction caused Spring Security to translate the database authentication failure to 401. Post-authentication writes use the transaction-capable MIG-002 database foundation where a workflow requires atomicity.
- **Next:** push and verify the migration CI job, then start MIG-004 patient, doctor, availability, slot-picker, appointment, token, and reception parity.

### 2026-09-28 — MongoDB architecture and migration contracts completed

- **Task:** MIG-002 — DONE locally. The existing MongoDB Windows service on port 27017 and Laravel SQLite database were not modified.
- **Actual changes:** added a project-managed MongoDB 8.2 single-node replica set (`caredesk-rs`) on loopback port 27018 with ignored data/log/PID files, idempotent Java initialization, Spring transaction manager, and an executable rollback proof. Added versioned startup creation of named compound/unique indexes for users, memberships, patients, appointments, invoices, payment idempotency, audit timelines, and FEFO medicine batches, plus `schemaMigrations` marker `MIG-002-v1`. Added an allowlisted JDBC SQLite inventory command that reports only table counts. Recorded tenant scoping, immutable reference/snapshot, Decimal128 money, UTC instant/local-date, and append-only audit/ledger conventions. Updated local commands, Compass URI, and CI replica-set verification.
- **Verification:** replica set initialized and elected `127.0.0.1:27018` primary; an inserted document inside an aborted transaction left zero records; the legacy inventory read 19 allowlisted fictional-data tables and printed counts only; Gradle build/tests passed; a fresh Spring process using the replica-set URI started on port 8081, applied indexes, and returned actuator health `UP`.
- **Remote CI follow-up:** GitHub Actions run 36421577700 passed the Laravel `test` job but failed before migration tests because the MongoDB service container had not been started with `--replSet`. The workflow now starts an explicit MongoDB 8.2 container with `--replSet caredesk-rs --bind_ip_all`, waits for ping, initiates the replica set, and waits for a writable primary. Corrective commit `e34675c` was verified by run 36422296390: both `test` and `migration-workspace` completed successfully, including the transaction rollback proof.
- **Next:** MIG-003 — implement secure browser authentication plus hospital, branch, user, membership, role/permission, active-context, audit, and tenant-isolation foundations in Spring Boot and React.

### 2026-09-28 — React, Spring Boot, and MongoDB migration foundation completed

- **Task:** MIG-001 — DONE for local scope. The approved rewrite is additive: the verified Laravel application remains intact at the repository root until MIG-010 acceptance.
- **Actual changes:** added stable MIG-001 through MIG-010 migration tasks. Generated an official Spring Boot 4.1.1 Gradle/Java 25 API in `backend/` with Web MVC, Security, Validation, Actuator, and Spring Data MongoDB; configured environment-driven Mongo/API settings, public readiness endpoints, protected-by-default placeholder routes, credentialed CORS, and endpoint tests. Generated a React 19 + TypeScript + Vite frontend in `frontend/`, replaced the starter page with a responsive CareDesk migration shell and live API status, and added the development proxy/environment template. Added local execution instructions and a GitHub Actions migration-workspace job with Java 25, Node 22, MongoDB 8.2, backend tests, frontend lint, and builds.
- **Environment evidence:** Java 25 LTS and Node 22.20 were present. MongoDB Server 8.2.1 was installed as an automatic running Windows service on `127.0.0.1:27017`; Compass 1.49.5 was installed. Spring’s MongoDB driver connected successfully to the standalone local server. A replica set is intentionally deferred to MIG-002 before transaction-dependent modules.
- **Verification:** Spring controller/security tests passed and `gradlew build` completed successfully. `npm run lint` and `npm run build` passed with no reported dependency vulnerabilities. With hidden local development processes, `GET :8080/api/actuator/health` returned `UP`, `GET :8080/api/v1/system` returned `ready`, React returned HTTP 200 on port 5173, and its proxied health request returned `UP`. GitHub Actions run 36420226526 passed both the existing Laravel `test` job and the new Mongo/Spring/React `migration-workspace` job on commit `6d76df8`. The first backend test attempt exposed and then resolved an incorrect Spring Boot 4 test import; a sandbox-only Gradle download denial was avoided by using the installed user-scoped Gradle cache.
- **Next:** MIG-002 — define transaction-safe MongoDB collection/index/reference/snapshot conventions, configure and verify a local single-node replica set, and implement a repeatable fictional-data migration contract before authentication/tenant work in MIG-003.

### 2026-09-28 — HMS-705 external completion requested; access still unavailable

- **Task:** HMS-705 remains PARTIAL. The local implementation and checks are complete, but the remaining completion conditions are operations on real external systems and cannot be truthfully reproduced inside the local workspace.
- **Required access:** an HTTPS staging host with PHP 8.4, Node 20+, a dedicated MySQL/MariaDB database and deployment credentials; SMTP/provider configuration and a test recipient; queue-worker/scheduler supervision; backup/private-file storage; and permission to perform an isolated restore plus rollback rehearsal. Source control and remote CI are now available and passing.
- **Repository and CI result:** after the user made `github.com/LoganathaRangasamy1987/HMS.git` public, the project was initialized on `main`, audited, committed as `d1cd796`, and pushed after explicit public-egress authorization and browser reauthentication. `.env`, SQLite data/backups, logs, private uploads, dependencies, build output, and the project-local runtime were excluded. Remote `main` matched the local commit exactly. GitHub Actions “Foundation checks” run 36416408904 completed successfully, proving clean checkout, dependency installation, asset build, PHP regression, and Laravel cache compilation. HMS-002 is DONE; HMS-010 now retains staging-only acceptance work.
- **Next:** once those non-secret access details are supplied through the appropriate deployment environment, run the documented deployment procedure, strict release check, delivered password-reset test, smoke/access checks, monitored queue test, managed restore, and rollback rehearsal; record the resulting evidence before marking HMS-705 DONE.

### 2026-09-28 — database-session idle expiration verification completed

- **Task:** HMS-012 — DONE for local scope. HMS-705 remains PARTIAL because its staging/production, mail-provider, remote-CI, and managed-restore gates require external systems not present in this workspace.
- **Actual changes:** added database-backed session tests for rejection after the configured 30-minute idle lifetime and remember-me restoration after the expired session is unavailable. Updated tenant context recovery so an authenticated remembered user with no session context receives the first active membership; an explicitly selected membership that becomes invalid or revoked is still rejected rather than silently switched.
- **Verification:** dedicated session coverage passed 2 tests; combined session/authentication coverage passed 14 tests / 85 assertions. Full PHP regression passed 272 tests / 2141 assertions with three intentional opt-in concurrency skips. Explicit-file Pint passed after the required Git-only `--dirty` invocation reported the known non-repository limitation.
- **Next:** provide the external environment/provider access needed to complete HMS-705, then run HMS-706 pilot onboarding and ERP Lite acceptance. HMS-002, HMS-010, and HMS-011 remain external foundation closeout work.

### 2026-09-27 — release engineering local gates completed; external gates remain

- **Task:** HMS-705 — PARTIAL. All safe local implementation and verification are complete; external environment work is still required before this release task can be marked DONE.
- **Actual changes:** added a public `/ready` endpoint that reports database latency plus cache, queue-table, and writable-storage readiness without exposing configuration values. Added `hms:release-check` with migration, key, database, cache, queue, storage and optional strict production checks. Added non-overwriting `hms:backup-sqlite` snapshots restricted to protected storage/simple filenames and `hms:verify-sqlite-backup`, which copies a backup into an isolated temporary restore, runs SQLite integrity/schema checks, reports representative counts, and removes the restore. Updated the existing deployment guide with readiness, strict release, backup/restore and non-destructive rollback procedures. Added automated readiness, command-safety, dashboard query-budget, and latency coverage.
- **Verification:** focused HMS-705 coverage passed 4 tests / 17 assertions. Full PHP regression passed 270 / 2125 with three intentional opt-in concurrency skips. Asset build, readiness route inspection, explicit-file Pint, `config:cache`, `route:cache`, `view:cache`, and subsequent `optimize:clear` passed. Non-strict `hms:release-check` passed before and after cache compilation; database latency was 8.29 ms and 12.36 ms in those runs. After starting the local server, `/ready` returned HTTP 200. A protected 1,204,224-byte backup was created as `hms-20260927-172026-928843.sqlite`; isolated restore verified SHA-256 integrity, SQLite integrity, all required tables, and 35 applied migrations without replacing the active database.
- **Open external gates:** `hms:release-check --strict` correctly failed because this is not a production environment and `MAIL_MAILER=log`; debug-disabled and asynchronous database-queue checks passed. Real mail/provider configuration remains HMS-011. Remote CI has not run, staging/production are not provisioned or deployed, supervised workers/monitoring are not demonstrated, and a managed MySQL/MariaDB plus private-file restore has not been rehearsed. These require deployment infrastructure and credentials not present in this workspace.
- **Next:** provide/configure the staging environment and mail provider, run remote CI and `hms:release-check --strict`, deploy with supervised workers, rehearse managed database/private-file restore and traffic rollback, then mark HMS-705 DONE and proceed to HMS-706 pilot onboarding.

### 2026-09-27 — release engineering and recovery verification started

- **Task:** HMS-705 — IN PROGRESS.
- **Scope:** production-oriented readiness monitoring, repeatable release checks, bounded performance smoke checks, recoverable local database backup, isolated restore/integrity rehearsal, and deployment/rollback guidance and evidence without modifying the active database.
- **Initial checks:** CI already runs PHP/browser asset prerequisites and Laravel cache builds; `/up` is Laravel's liveness route; the deployment guide describes manual backup/rollback expectations but no application readiness endpoint or executable backup/restore verification exists. Local development uses persistent SQLite; external staging and production remain unavailable. `.ai/rules` is absent.
- **Next:** add readiness and release-check commands, safe SQLite backup/isolated restore verification, automated coverage and CI hooks, run the local rehearsal and performance checks, then record exact local versus external evidence.

### 2026-09-27 — role dashboards and workflow alerts completed

- **Task:** HMS-704 — DONE for its documented local scope; HMS-705 is next.
- **Actual changes:** added a reusable active-branch dashboard metrics service and responsive operational-priority cards to the existing portal/API. Appointment users see today's schedule and waiting queue, with doctor totals restricted to their own profile. Billing users see outstanding invoices and branch-timezone net collections. Laboratory users see collection work and, only when authorized, results awaiting verification. Pharmacy users see low/out-of-stock and expiry-window counts. Every user sees assigned unread shared notifications, while administrators additionally see failed email deliveries. Every card links to its authorized source worklist or reconciliation screen; existing organization and activity panels remain intact.
- **Verification:** focused role-dashboard acceptance passed 4 tests / 32 assertions across receptionist, doctor, pharmacist, and administrator roles, including doctor self-scope, permission-specific card absence, financial arithmetic, stock/expiry signals, unread alerts, and active-branch failed-delivery isolation. Combined appointment, billing, laboratory, pharmacy, notification, and dashboard coverage passed 25 / 209. Full PHP regression passed 266 / 2108 with three intentional opt-in concurrency skips. Both dashboard routes listed, Blade templates compiled, and explicit-file Pint passed after the required Git-only `--dirty` attempt reported the known repository limitation.
- **Limits:** cards are request-time operational snapshots for the active branch; cross-branch consolidated dashboards remain HMS-1603. Remote CI, staging, production, and real workload performance remain unverified.
- **Next:** HMS-705 — release engineering, performance checks, monitoring, backup/restore rehearsal, and deployment/rollback verification.

### 2026-09-27 — role dashboards and workflow alerts started

- **Task:** HMS-704 — IN PROGRESS.
- **Scope:** permission-aware active-branch dashboard cards for reception queues, doctor workload, billing/collections, laboratory collection and verification, pharmacy stock/expiry, unread notifications and delivery failures, with direct authorized drill-downs.
- **Initial checks:** the current dashboard contains organization counts only. Operational modules already expose the underlying scoped worklists and alert calculations, but there is no shared role-specific summary. `.ai/rules` is absent.
- **Next:** implement a reusable dashboard metrics service, update portal/API presentation, add role and tenant acceptance coverage, then run focused and full verification.

### 2026-09-27 — shared notification service completed

- **Task:** HMS-703 — DONE for its documented local scope; HMS-704 is next.
- **Actual changes:** added immutable tenant/branch/recipient-scoped operational notifications and per-channel delivery ledgers; a reusable deduplicating producer; recipient read state; a shared paginated notification center/API; and administrator-only terminal failure visibility/retry. Added queued email delivery through the configured Laravel mailer with three attempts, staged backoff, attempt/sent/failure timestamps, bounded error recording, and an HTML/Markdown email. Existing laboratory order, specimen, and result lifecycle producers now also publish into the shared service while retaining their dedicated worklist alerts.
- **Verification:** focused notification coverage passed 4 tests / 23 assertions, including deduplication, queued dispatch, email job success, failure recording/retry, read ownership, and branch isolation. Combined notification/laboratory coverage passed 9 / 121 before formatting; the final notification/worklist rerun passed 7 / 66. Full PHP regression passed 262 / 2076 with three intentional opt-in concurrency skips. All eight shared/laboratory notification routes listed, Blade templates compiled, explicit-file Pint fixed/passed after the required Git-only `--dirty` attempt reported the known repository limitation, and the additive notification/delivery migration applied to persistent SQLite.
- **Limits:** the local environment uses the configured log mailer, and email transport behavior is tested with Laravel's mail fake. A real SMTP/provider remains HMS-011; continuously supervised production queue workers, remote CI, staging, and production remain unverified release work.
- **Next:** HMS-704 — role dashboards, actionable workflow alerts, and operational drill-downs.

### 2026-09-27 — shared notification service started

- **Task:** HMS-703 — IN PROGRESS.
- **Scope:** tenant-scoped in-app notifications and read state, reusable event production, queued email delivery using the configured mailer, retry and terminal-failure recording, recipient/admin visibility, and role/tenant acceptance.
- **Initial checks:** laboratory workflows have a dedicated in-app notification table and producers, while the application queue, failed-job table, and local log mailer already exist. There is no shared notification center or per-delivery status ledger. `.ai/rules` is absent.
- **Next:** add shared notification/delivery schema and models, service/job/mailable, portal/API read and failure views, connect a representative laboratory producer, then run focused and full verification.

### 2026-09-27 — global search and exports completed

- **Task:** HMS-702 — DONE for its documented local scope; HMS-703 is next.
- **Actual changes:** added a shared global-search service, responsive portal, same-origin API, navigation, and CSV endpoint. Authorized users can search active-branch patients by UHID/name/mobile and invoices by number or patient identity. Results include compact record summaries and direct links, default to 20 per category, and are capped at 50 interactively. Categories are independently gated by `PATIENT.VIEW` and `INVOICE.MANAGE`; users without either permission are denied. CSV uses the identical query/authorization scope, caps exports at 500 rows, sends download/nosniff headers, and neutralizes spreadsheet-formula prefixes.
- **Verification:** focused search/export acceptance passed 3 tests / 38 assertions, covering patient/mobile/invoice lookup, HTML/API output, active-branch isolation, category-specific role access, no-access denial, CSV contents, limits, and validation. Combined patient registration, invoice, payment, and search coverage passed 50 / 503. Full PHP regression passed 258 / 2053 with three intentional opt-in concurrency skips. All four search/export routes listed, Blade templates compiled, and explicit-file Pint fixed/passed after the required Git-only `--dirty` attempt reported the known repository limitation.
- **Limits:** exports intentionally contain only bounded search summaries, not full clinical records or bulk hospital data. Cross-branch consolidated search belongs to HMS-1603; remote CI, staging, and production remain unverified.
- **Next:** HMS-703 — queued notification delivery, retry/failure visibility, and configured email notifications.

### 2026-09-27 — global search and exports started

- **Task:** HMS-702 — IN PROGRESS.
- **Scope:** active-branch patient lookup by UHID/name/mobile and invoice lookup by number/patient identity, bounded result sets, permission-specific category visibility, useful portal/API summaries, and CSV export protected by the same role and tenant boundaries.
- **Initial checks:** patient and invoice modules already provide separate scoped searches, but there is no shared search entry point or reusable permission-checked export. `.ai/rules` is absent.
- **Next:** implement the shared query service, portal/API and CSV endpoint, navigation, validation and tenant/permission tests, then run focused and full verification.

### 2026-09-27 — operational reporting completed

- **Task:** HMS-701 — DONE for its documented local scope; HMS-702 is next.
- **Actual changes:** added a unified administrator operational-report service, portal, and same-origin JSON API. Reports use the active authorized branch as the branch filter and accept inclusive `from`/`to` dates in that branch's timezone, defaulting to 30 days and rejecting ranges over 366 days. Metrics cover appointment status, consultation/finalization volume, billed revenue, gross/refunded/net collections, calculated receivables, department and doctor encounter activity, laboratory orders/results, pharmacy sales, and stock-movement quantities. Added role-aware navigation and responsive summary/activity presentation.
- **Verification:** focused reporting acceptance passed 2 tests / 28 assertions, covering financial arithmetic, clinical activity, laboratory and pharmacy totals, timezone/date handling, branch isolation, administrator authorization, HTML/API responses, and invalid/oversized ranges. Cross-module reporting, appointment, consultation, reconciliation, laboratory, and pharmacy checks passed 20 / 208. Full PHP regression passed 255 / 2015 with three intentional opt-in concurrency skips. Both report routes listed, Blade templates compiled, and explicit-file Pint fixed/passed after the required Git-only `--dirty` attempt reported the known repository limitation.
- **Limits:** reports are operational on-screen/API summaries for the active branch; cross-branch consolidated reporting belongs to HMS-1603, export files belong to HMS-702, and remote CI/staging/production remain unverified.
- **Next:** HMS-702 — authorized global search, bounded result summaries, and permission-checked exports.

### 2026-09-27 — operational reporting started

- **Task:** HMS-701 — IN PROGRESS.
- **Scope:** authorized operational reports for appointments, consultations, billed revenue versus collections, receivables, department/doctor activity, laboratory workload, and pharmacy sales/stock movement, with bounded date and branch filters plus hospital isolation.
- **Initial checks:** Sprints 2–6 supply the source ledgers and workflow records. No unified operational reporting endpoint or portal exists. `.ai/rules` is absent.
- **Next:** map source models and date semantics, implement the reporting query/service and portal/API, add permission/tenant/date-boundary acceptance coverage, then run focused and full verification.

### 2026-09-27 — pharmacy reconciliation and acceptance completed

- **Task:** HMS-606 — DONE for its documented local scope; Sprint 6 is complete locally and HMS-701 is next.
- **Actual changes:** added an administrator-only, branch-scoped stock reconciliation service, portal, and API. Every batch compares its stored on-hand quantity with the summed immutable movement ledger and the latest recorded movement balance, exposes reconciled/discrepancy counts, supports status/search filters, and summarizes movement totals. Added the latest-movement model relation, navigation and permission wiring, discrepancy acceptance coverage, and administrator/branch/hospital authorization coverage. Existing dispensing uses ordered `lockForUpdate()` batch locks, rejects insufficient or expired stock atomically, and preserves idempotent duplicate-sale replay behavior.
- **Verification:** focused acceptance coverage passed 3 tests / 20 assertions. Combined pharmacy catalog, receipt, dispensing, adjustment, workspace, and reconciliation coverage passed 27 / 197. Full PHP regression passed 253 / 1987 with the existing three intentional opt-in concurrency skips. All 37 pharmacy routes listed, Blade templates compiled, explicit-file Pint passed after the required Git-only `--dirty` attempt reported the known repository limitation, and the reconciliation permission seeder applied successfully to persistent SQLite.
- **Limits:** the local acceptance evidence verifies locking logic, rollback, replay, role boundaries, and tenant isolation; no new dedicated multi-process pharmacy MariaDB stress run was added. Remote CI, staging, production, and real-hospital acceptance remain unverified.
- **Next:** HMS-701 — operational reporting across appointments, consultations, finance, department/doctor activity, laboratory, and pharmacy.

### 2026-09-27 — pharmacy reconciliation and acceptance started

- **Task:** HMS-606 — IN PROGRESS.
- **Scope:** administrator reconciliation of every branch batch against immutable receipt, dispense, return, and write-off movements; discrepancy visibility; full receipt-to-sale-to-return acceptance; duplicate request and failed transaction checks; concurrent overselling proof on isolated MariaDB; and complete pharmacy role/branch/hospital boundaries.
- **Initial checks:** HMS-601 through HMS-605 provide all operational workflows and row-lock batch mutations. No dedicated reconciliation report or pharmacy concurrency acceptance exists. `.ai/rules` is absent.
- **Next:** implement reconciliation service/API/UI, add end-to-end and isolation tests plus opt-in multi-worker MariaDB oversell coverage, then run focused/full verification and close Sprint 6 only on evidence.

### 2026-09-27 — pharmacy portal and alerts completed

- **Task:** HMS-605 — DONE for its documented local scope; HMS-606 is next.
- **Actual changes:** added a consolidated branch pharmacy workspace with quick actions for receiving, dispensing, and adjustments; operational cards for medicine counts, low/out-of-stock medicines, expired/expiring batches, awaiting prescriptions, pending adjustments, and daily sales; medicine/code/generic/batch search; stock-status filters; branch-level total on-hand and active-batch counts; and reorder-level classification. Added stocked-batch expiry alerts separated into expired and expiring-soon states, persisted a branch-specific warning window defaulting to 90 days, and allowed only hospital administrators to configure 1–730 days while pharmacists retain read access. Added tenant-safe portal/API endpoints, navigation, permissions, audit, and branch isolation.
- **Verification:** focused workspace coverage passed 4 tests / 33 assertions. Combined pharmacy workspace/catalog/receipt/dispense/adjustment coverage passed 24 / 177. Full PHP regression passed 250 / 1967 with three intentional opt-in concurrency skips. All 35 pharmacy routes listed, Blade templates compiled, and explicit-file Pint passed after the required Git-only `--dirty` attempt reported the known repository limitation. The additive branch-setting migration and updated permissions applied successfully to persistent SQLite.
- **Resolved verification issues:** the initial focused run exposed seeded catalog medicines in absolute out-of-stock counts and fractional day display caused by time-of-day comparison. Assertions now account for legitimate seeded inventory, while expiry differences normalize both values to branch-local day boundaries; all checks pass.
- **Limits:** alerts are computed when the workspace is opened; queued or external notifications belong to Sprint 7. Browser automation, stock-ledger reconciliation, concurrent overselling acceptance, remote CI, staging, and production remain outside this completion claim.
- **Next:** HMS-606 — complete pharmacy reconciliation and acceptance, including concurrent stock protection, replay behavior, end-to-end workflow, and role/tenant isolation.

### 2026-09-27 — pharmacy portal and alerts started

- **Task:** HMS-605 — IN PROGRESS.
- **Scope:** consolidated branch pharmacy workspace; quick access and operational counts for receipts, dispensing, sales, and adjustments; medicine/batch stock search; low/out-of-stock classification against reorder levels; expired/expiring batch alerts; and an administrator-configurable branch expiry-warning window.
- **Initial checks:** HMS-601 through HMS-604 provide the underlying catalog, batches, receipts, sales, and adjustment workflows. No consolidated pharmacy landing page or persisted expiry-alert setting exists. `.ai/rules` is absent.
- **Next:** add the branch alert setting, workspace queries/filters and authorization, portal/API presentation, tests, migration, and regression verification.

### 2026-09-27 — pharmacy returns and stock adjustments completed

- **Task:** HMS-604 — DONE for its documented local scope; HMS-605 is next.
- **Actual changes:** added immutable branch-scoped stock adjustment requests for patient sale returns, supplier purchase returns, damage write-offs, and expiry write-offs. Requests preserve batch plus originating sale/purchase line references, positive quantity, reason, requester/time, UUID replay identity, status, administrator decision/reason/time, and the resulting movement reference. Pharmacists can submit and view requests but cannot approve; a different hospital administrator must approve or reject. Approval transactionally locks the request and batch, enforces remaining source quantities, current on-hand stock, and actual batch expiry, then restores sale-return stock or deducts purchase-return/damaged/expired stock and appends a permanent attributable movement. Rejection leaves stock untouched. Added portal/API queue, source-aware form/history, permissions, navigation, audit allowlisting, tenant/branch isolation, and immutable guards.
- **Verification:** focused HMS-604 coverage passed 5 tests / 38 assertions; combined adjustment, dispense, receipt, and financial-adjustment coverage passed 20 / 182. Full PHP regression passed 246 / 1934 with three intentional opt-in concurrency skips. All six adjustment routes listed, Blade templates compiled, and explicit-file Pint fixed/passed formatting after the required Git-only `--dirty` attempt reported the known repository limitation. The additive adjustment migration and updated permissions applied successfully to persistent SQLite.
- **Limits:** physical stock returns and write-offs are represented here; any patient cash refund or invoice credit remains a separately authorized entry in the existing immutable financial-adjustment ledger, linked operationally through the source sale/invoice. Low-stock and configurable expiry alerts, browser automation, concurrency acceptance, remote CI, staging, and production remain outside this completion claim.
- **Next:** HMS-605 — deliver a consolidated pharmacy workspace with stock search, low-stock status, configurable expiry windows, and actionable alerts.

### 2026-09-27 — pharmacy returns and stock adjustments started

- **Task:** HMS-604 — IN PROGRESS.
- **Scope:** immutable branch-scoped requests for sale returns, supplier purchase returns, damage write-offs, and expiry write-offs; source-line references and quantity limits; pharmacist submission; administrator approval/rejection; stock changes only after approval; attributable reversal movements and retained decision history.
- **Initial checks:** HMS-602/HMS-603 preserve receipt and dispense source lines plus batch balances and movements. Existing billing adjustments preserve invoice refunds/credits separately. `.ai/rules` is absent.
- **Next:** add adjustment schema/models/service, approval authorization, portal/API queues and source-aware forms, audit, and focused/full verification.

### 2026-09-27 — pharmacy dispensing and sales completed

- **Task:** HMS-603 — DONE for its documented local scope; HMS-604 is next.
- **Actual changes:** added immutable branch-scoped pharmacy sales and line snapshots linked to prescriptions, prescription items, medicine batches, invoice lines, and issued invoices. The pharmacist workspace lists awaiting prescriptions, exposes only active in-stock non-expired batches ordered by earliest expiry, and records positive integer dispensed quantities. Posting locks batches, validates medicine/batch ownership and availability, prevents a prescription line from being dispensed twice, deducts on-hand stock, appends an attributable negative `DISPENSE` movement, calculates sale/tax totals from batch and medicine snapshots, issues the billing invoice, audits the event, and supports UUID replay safety. Added pharmacist permissions, navigation, portal/API list/create/detail workflows, tenant/branch isolation, and invoice handoff for existing payment/receipt collection.
- **Verification:** focused dispensing coverage passed 5 tests / 36 assertions; combined dispensing, receipts, prescriptions, and invoices passed 20 / 177. Full PHP regression passed 241 / 1896 with three intentional opt-in concurrency skips. All seven pharmacy sale routes listed, Blade templates compiled, and explicit-file Pint fixed/passed formatting after the required Git-only `--dirty` attempt reported the known repository limitation. Both additive sale migrations and updated permissions applied successfully to persistent SQLite.
- **Resolved test setup issue:** the first focused run failed before assertions because its fixture relied on a doctor-profile seeder intentionally skipped under unit tests. The fixture now creates an explicit coherent doctor profile and encounter; all focused and full checks pass.
- **Limits:** payment collection and printable receipts are provided through the existing issued-invoice workflow; HMS-603 does not duplicate that ledger. Returns, reversals, damaged/expired adjustments, low-stock alerts, concurrent overselling acceptance, browser automation, remote CI, staging, and production remain outside this completion claim.
- **Next:** HMS-604 — implement sale/purchase returns and controlled damaged/expired adjustments with approvals, reversal references, stock restoration/deduction, and retained ledger history.

### 2026-09-27 — pharmacy dispensing and sales started

- **Task:** HMS-603 — IN PROGRESS.
- **Scope:** prescription-linked, branch-scoped dispensing; eligible non-expired batch selection; exact quantity/price/tax snapshots; atomic on-hand deductions and immutable issue movements; duplicate-request protection; and an issued pharmacy invoice/receipt integration through the existing billing ledger.
- **Initial checks:** HMS-602 provides attributable receipt movements and batch balances. HMS-405 provides immutable prescription items, and Sprint 3 provides issued invoice/payment infrastructure. `.ai/rules` is absent.
- **Next:** define the sale/dispense schema and lifecycle, integrate prescription and billing contracts transactionally, add pharmacist authorization plus portal/API workflows, then run focused and full verification.

### 2026-09-27 — pharmacy stock receipts completed

- **Task:** HMS-602 — DONE for its documented local scope; HMS-603 is next.
- **Actual changes:** added hospital/branch-scoped supplier purchases with atomic yearly numbering, supplier-invoice uniqueness, exact receipt totals, UUID replay protection, immutable item snapshots, batch manufacture/expiry and cost/sale pricing, accumulated received/on-hand quantities, and an immutable attributable stock movement ledger. Added pharmacist permissions, tenant-safe portal/API routes, receipt detail and batch-stock screens, navigation, audit, model relationships, validation, and guards against changing receipt, batch identity, expiry, or ledger history.
- **Verification:** focused HMS-602 plus pharmacy catalog/medicine coverage passed 12 tests / 81 assertions. Full PHP regression passed 236 / 1860 with three intentional opt-in concurrency skips. All six purchase routes listed, Blade templates compiled, and explicit changed-file Pint fixed/passed PHP formatting after the required Git-only `--dirty` attempt reported the repository limitation. The additive stock migration and updated permissions were applied successfully to persistent SQLite.
- **Limits:** this task records incoming supplier stock only. Dispensing, sale/invoice integration, returns, damage/expiry adjustments, alerts, concurrent overselling acceptance, browser automation, remote CI, staging, and production remain outside this completion claim.
- **Next:** HMS-603 — implement prescription-linked dispensing and sales, eligible non-expired batch selection, transactional stock deductions, quantity/replay controls, and invoice/receipt integration.

### 2026-09-27 — pharmacy stock receipts started

- **Task:** HMS-602 — IN PROGRESS.
- **Scope:** branch-scoped, idempotent supplier receipts with purchase numbering and totals; immutable receipt items; medicine batches with manufacture/expiry, received/on-hand quantities, cost/sale prices; and an immutable, attributable stock receipt ledger.
- **Initial checks:** HMS-601 provides hospital-scoped medicines, suppliers, units, tax, reorder settings, pharmacist permissions, and portal/API foundations. No purchase, batch, quantity, expiry, price, or stock movement persistence exists. `.ai/rules` is absent.
- **Next:** add the stock schema/models/factories, transactional receipt service, authorization, portal/API, immutable guards, and focused/full verification.

### 2026-09-27 — pharmacy catalog foundation completed

- **Task:** HMS-601 — DONE for its documented local scope; HMS-602 is next.
- **Actual changes:** added hospital-scoped manufacturer, medicine type, stock unit, and supplier masters with uniqueness and ownership constraints; supplier contact, tax-registration, address, and active-state data; and medicine links to manufacturer/type, purchase/sale units, decimal reorder level, configured tax percentage, and prescription-required setting while preserving the existing prescription catalog. Added a dedicated pharmacist role and pharmacy catalog permissions, tenant-safe portal/API management, navigation, factories, audit records, and foreign-reference validation. Existing doctors retain read-only medicine access, reception has no pharmacy access, and administrators/pharmacists manage catalog data.
- **Verification:** HMS-601 plus existing medicine/prescription coverage passed 10 tests / 71 assertions after Pint. Full PHP regression passed 230 / 1823 with three intentional opt-in concurrency skips. All 16 Playwright scenarios passed, including the new pharmacy catalog portal check. Blade templates compiled, all 12 pharmacy catalog routes listed, the schema and updated permissions applied to persistent SQLite in batch 7, and live `/up` returned HTTP 200. The required Pint `--dirty --format agent` attempt reported that `--dirty` requires Git; explicit changed-file Pint fixed/passed the PHP files.
- **Limits:** HMS-601 defines catalog and procurement identities only; no purchase receipt, batch quantities, expiry, pricing ledger, dispensing, sale, or stock alert is claimed. Evidence is local only; remote CI, staging, production, and real pharmacy configuration sign-off remain unverified.
- **Next:** HMS-602 — implement supplier purchases, receipts, batches, expiry/cost/sale prices, and an immutable stock movement ledger.

### 2026-09-27 — pharmacy catalog foundation started

- **Task:** HMS-601 — IN PROGRESS.
- **Scope:** hospital-scoped manufacturer, medicine type/unit, and supplier masters; extend medicines with normalized classifications, reorder level, configured tax, and purchase/sale units; add pharmacy authorization, portal/API management, audit, and focused verification.
- **Initial checks:** the existing prescription medicine master provides code, brand/name, generic name, free-text form and strength with administrator management and doctor read access. No supplier, manufacturer, pharmacy role, reorder level, or pharmacy tax catalog exists. `.ai/rules` is absent.
- **Next:** add schema/models/factories, pharmacy permissions and role, catalog controller/routes/UI, extend medicine management compatibly, then run focused and full verification.

### 2026-09-27 — reception appointment slot picker completed

- **Task:** HMS-209 — DONE for its documented local scope; HMS-601 is next again.
- **Actual changes:** added a tenant/branch-authorized portal/API endpoint that computes the next 1–60 days of bookable doctor availability from active weekly schedules, date-specific exceptions, leave/holidays, slot duration, capacity, existing non-cancelled appointments, and the branch timezone. Updated reception booking so choosing a doctor loads the next 30 days of available dates, choosing a date loads only open time slots, remaining places are displayed, full and past slots are omitted, unavailable states are announced accessibly, and old selections are restored after validation. Server booking now also rejects a non-future time on the current date while retaining transactional capacity validation.
- **Verification:** new focused coverage passed 3 feature tests; combined appointment/reception checks passed 14 / 102. Full PHP regression passed 226 / 1790 with three intentional opt-in concurrency skips. JavaScript syntax and asset build passed, Blade templates compiled, and all 13 appointment routes listed. The first browser attempt could not connect because the local server was stopped; after starting it, the initial browser run exposed only test-locator/state assumptions, which were corrected. The final complete Playwright run passed all 15 scenarios, including the new reception slot selection. Live `/up` returned HTTP 200. The required Pint `--dirty --format agent` attempt reported that `--dirty` requires Git; explicit changed-file Pint passed.
- **Limits:** availability is shown for the next 30 days in the portal, while the endpoint supports up to 60 days. Booking remains subject to a final transactional server check because another receptionist can take the last place after the list is displayed. Evidence is local only; remote CI, staging, and production remain unverified.
- **Next:** HMS-601 — begin the Sprint 6 pharmacy catalog foundation.

### 2026-09-26 — reception appointment slot picker started

- **Task:** HMS-209 — IN PROGRESS as a focused Sprint 2 usability enhancement; HMS-601 remains next afterward.
- **Scope:** provide a tenant/branch-authorized availability endpoint and update the reception booking form so selecting a doctor reveals configured future dates and only time slots with remaining capacity, respecting weekly schedules, exceptions, leave, holidays, and existing appointments.
- **Initial checks:** the booking service already validates schedules and capacity transactionally, but the form exposes unrestricted date/time inputs and the existing availability endpoint returns raw schedule rules rather than computed bookable slots. `.ai/rules` is absent.
- **Next:** implement shared slot calculation, the read endpoint, dynamic accessible form controls, and focused feature/browser-facing verification.

### 2026-09-26 — laboratory acceptance completed

- **Task:** HMS-506 — DONE for its documented local scope; Sprint 5 is complete locally.
- **Actual changes:** added dedicated end-to-end acceptance coverage for an automatically issued laboratory invoice paid in full, specimen collection/receipt/processing, technician result entry and calculated abnormal flag, independent doctor verification, finalized report access, reasoned correction, replacement values, second verification, and preservation of both report revisions and payment state. Added negative acceptance for unverified report printing, foreign-hospital patients and records, reception result access, technician verification, doctor result entry, cross-hospital access, and same-hospital access to another ordering doctor's patient results.
- **Verification:** HMS-506 passed 2 tests / 55 assertions after Pint; the complete Sprint 5 laboratory suite passed 30 / 350. Full PHP regression passed 223 / 1771 with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed. The required Pint `--dirty --format agent` attempt reported that `--dirty` requires Git; explicit-file Pint passed. Blade templates compiled, all 51 laboratory routes listed, persistent SQLite reports every migration applied through batch 6, and live `/up` returned HTTP 200.
- **Limits:** evidence is local only; remote CI, staging, production, and real hospital workflow sign-off remain unverified. The opt-in database concurrency checks remain intentionally outside the default suite.
- **Next:** HMS-601 — begin the Sprint 6 pharmacy catalog foundation.

### 2026-09-26 — laboratory acceptance started

- **Task:** HMS-506 — IN PROGRESS.
- **Scope:** prove the complete order-to-billing-to-specimen-to-result-to-verified-report journey, retained corrected revisions, suppression of unverified reports, role boundaries, patient scoping, and cross-branch/hospital isolation; close any workflow gaps discovered by acceptance checks.
- **Initial checks:** HMS-501 through HMS-505 are locally complete, `.ai/rules` is absent, and the previous full regression passed 221 tests / 1716 assertions with three intentional skips.
- **Next:** add focused end-to-end acceptance scenarios, run them, correct any defects, then execute the full PHP/browser/migration/health release checks and update this tracker.

### 2026-09-26 — laboratory worklists and notifications completed

- **Task:** HMS-505 — DONE for its documented local scope; HMS-506 acceptance remains next.
- **Actual changes:** added a branch-scoped laboratory workspace with searchable collection/recollection, specimen receipt/processing, draft verification, and latest-final-report queues. Queue visibility follows granular permissions: reception and laboratory technicians can manage specimen queues, ordering doctors see only their own verification/report work, laboratory technicians can discover finalized reports, and reception cannot access result content. Added persistent, recipient-scoped in-app notifications for new orders, rejected specimens, results ready for verification, and finalized reports; event keys prevent duplicates, read state is retained, and foreign recipients cannot mark notifications. Added navigation, responsive portal controls, API endpoints, schema/model/factory/service hooks, and role permissions.
- **Verification:** HMS-505 tests passed 3 / 43 after formatting; combined laboratory tests passed 22 / 225. Full PHP regression passed 221 / 1716 with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed. The required Pint `--dirty --format agent` attempt reported that `--dirty` requires Git because this folder is not a repository; Pint then fixed/passed all explicit changed PHP files. Blade templates compiled, all 51 laboratory routes listed, the notification migration and permissions applied to persistent SQLite in batch 6, and live `/up` returned HTTP 200.
- **Limits:** evidence is local only; remote CI, staging, production, and real hospital workflow sign-off remain unverified. Full order-to-report/billing acceptance and explicit unverified/corrected-result acceptance belong to HMS-506.
- **Next:** HMS-506 — complete laboratory acceptance across billing, report revisions, roles, patient boundaries, and hospital isolation.

### 2026-09-26 — laboratory worklists and notifications started

- **Task:** HMS-505 — IN PROGRESS.
- **Scope:** role-aware collection, processing, verification, and finalized-report queues; actionable in-app laboratory notifications; and authorized report discovery without exposing results to reception users.
- **Initial checks:** no general in-app notification store exists; only framework password-reset notifications are present. HMS-502 through HMS-504 provide the required order, specimen, and result statuses. `.ai/rules` is absent.
- **Next:** implement the notification store/service, workflow hooks, worklist controller and portal/API, read-state handling, authorization, and focused verification.

### 2026-09-26 — laboratory results and verification completed

- **Task:** HMS-504 — DONE for its documented local scope; Sprint 5 remains in progress.
- **Actual changes:** added typed result revisions tied to the exact ordered test version and processing specimen. Numeric results receive server-calculated LOW/NORMAL/HIGH flags from snapshotted ranges; text and boolean results require explicit NORMAL/ABNORMAL flags. Drafts are editable only by their entering technician, finalization requires a different authorized verifier, and finalized values/provenance are immutable. Corrections require a reason and create a copied draft revision that preserves every earlier finalized revision. Added laboratory technician entry, ordering-doctor verification, administrator capabilities with separation-of-duties enforcement, portal/API entry and review screens, revision history, audit metadata, order/specimen status integration, and printable finalized reports.
- **Verification:** HMS-504 added 6 tests / 72 assertions; the combined HMS-502 through HMS-504 laboratory workflow passed 19 / 182, covering typed validation, definition snapshots, calculated flags, draft ownership, independent verification, immutable final values, reasoned revisions, printable reports, role boundaries, and hospital isolation. Full PHP regression passed 218 / 1673 with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed. Pint passed on explicit changed PHP files after the required Git-only `--dirty` attempt reported no repository. Blade templates compiled, all 47 laboratory routes listed, migration and permissions applied to persistent SQLite, migration status reports the result migration in batch 5, and live `/up` returned HTTP 200.
- **Limits:** unified collection/processing/verification worklists, notifications, and broader authorized report availability remain HMS-505; complete order-to-report acceptance remains HMS-506. Evidence is local only; remote CI, staging, and production remain unverified.
- **Next:** HMS-505 — laboratory worklists, queues, notifications, and authorized report availability.

### 2026-09-26 — laboratory specimen lifecycle completed

- **Task:** HMS-503 — DONE for its documented local scope; Sprint 5 remains in progress.
- **Actual changes:** added hospital-wide sequential specimen identifiers and printable labels; multiple immutable attempts per ordered test; attributed collection, receipt, processing, and rejection timestamps; mandatory rejection reasons; replacement collection only after rejection; immutable actor/time/status event history; aggregate partial-collection, collected, received, processing, and recollection-required order states; and cancellation protection after specimen activity. Added specimen controls/history to the order portal, same-origin JSON transitions, audit metadata, factories, branch/doctor isolation, and a dedicated `LAB_TECHNICIAN` role with specimen permissions. Reception and administrators can manage specimens; ordering doctors can view their own specimen history and labels without changing it.
- **Verification:** combined HMS-503 and HMS-502 focused suites passed 13 tests / 110 assertions, covering unique labels, attribution, ordered transitions, invalid/duplicate transitions, immutable history, multi-item aggregation, rejection/recollection attempts, cancellation protection, technician access, doctor read-only access, and hospital isolation. Full PHP regression passed 212 / 1601 with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed. Pint passed on explicit changed PHP files after the required Git-only `--dirty` attempt reported no repository. Blade templates compiled, all 36 laboratory routes listed, the migration and permission seed applied to persistent SQLite, migration status reports the specimen migration in batch 4, and live `/up` returned HTTP 200.
- **Limits:** result entry, abnormal flags, verification/finalization, correction history, and report printing remain HMS-504; queues/notifications remain HMS-505. Evidence is local only; remote CI, staging, and production remain unverified.
- **Next:** HMS-504 — typed laboratory result entry, verification, correction history, and report printing.

### 2026-09-26 — laboratory ordering and billing completed

- **Task:** HMS-502 — DONE for its documented local scope; Sprint 5 remains in progress.
- **Actual changes:** added hospital/branch-scoped numbered laboratory orders linked to a patient, optional consistent encounter, and active ordering-doctor profile. Each item snapshots the active catalog version, code, name, currency, and price, and is linked to an immutable invoice line; order creation automatically issues the exact INR invoice. Added doctor self-scope, administrator/reception permissions, portal list/create/detail/cancel screens, same-origin JSON endpoints, audit metadata, model factories, and active-order protection against direct invoice voiding. Unpaid orders can be cancelled with a reason, which cancels their items and voids the invoice; collected net payment blocks cancellation until refunded.
- **Verification:** focused HMS-502 suite passed 6 tests / 44 assertions; combined HMS-502 and financial-adjustment checks passed 10 / 115; related catalog/invoice/payment checks passed 20 / 248 before the final direct-void guard; final full regression passed 205 / 1535 with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed. Pint passed on explicit changed PHP files after the required Git-only `--dirty` attempt reported no repository. Blade templates compiled, all 27 laboratory routes listed, the migration and permission seed applied to persistent SQLite, migration status is clean after removing an empty duplicate scaffold migration, and live `/up` returned HTTP 200.
- **Limits:** specimen lifecycle, results, verification, reports, notifications, and a dedicated laboratory staff role remain HMS-503 through HMS-506. Evidence is local only; remote CI, staging, and production remain unverified.
- **Next:** HMS-503 — specimen collection labels/identifiers, receipt and processing states, rejection/recollection, and status history.

### 2026-09-25 — laboratory catalog foundation completed

- **Task:** HMS-501 — DONE for its documented local scope; Sprint 5 remains in progress.
- **Actual changes:** added hospital-scoped laboratory category, sample-type, and unit masters; stable test identities; and numbered test versions containing sample requirements, collection instructions, INR price, and ordered parameters. Parameters support numeric, text, and boolean result types, optional hospital-owned units, numeric minimum/maximum ranges, and reference text. New tests start with active version 1; later definitions are created as drafts and activated transactionally, archiving the previous active version while preserving its content. Version and parameter content is immutable. Added factories, safe audit metadata, administrator management, doctor/reception active-catalog access, navigation, portal forms/details, and same-origin JSON APIs.
- **Verification:** focused laboratory catalog suite passed 6 tests / 70 assertions, covering creation, active/draft/archived promotion, immutable history, validation, inactive visibility, permissions, and hospital isolation. Full PHP regression passed 199 tests / 1491 assertions with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed in one run. Pint passed on explicit files after the required Git-only `--dirty` attempt reported no repository; Blade templates compiled; all 18 laboratory routes listed; the migration and permission seed applied to persistent SQLite; migration status reports the catalog migration ran; live `/up` returned HTTP 200.
- **Limits:** no laboratory orders, charge creation, specimens, results, verification, reports, or laboratory staff role exists yet; these remain HMS-502 through HMS-506. Reference ranges are currently definition-wide rather than age/sex-specific intervals. Evidence is local only.
- **Next:** HMS-502 — laboratory orders and billing integration.

### 2026-09-25 — operational pilot acceptance completed

- **Task:** HMS-407 — DONE for its documented local scope; Sprint 4 is complete locally.
- **Actual changes:** added permanent end-to-end acceptance coverage that carries one patient through registration, new appointment, check-in and doctor queue, encounter opening, vital signs, provisional diagnosis, prescription, finalized consultation, immutable diagnosis correction and consultation amendment, consultation invoice, full cash payment, printable receipt, and a booked follow-up visit. The same coverage verifies that the original finalized clinical records remain unchanged, reception Patient 360 exposes operational/billing events but not clinical events, doctors cannot create invoices or request billing history, and reception/administration cannot access or write encounter clinical data.
- **Verification:** focused HMS-407 suite passed 2 tests / 44 assertions. Full PHP regression passed 193 tests / 1421 assertions with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed in one run. Pint passed on the explicit acceptance-test file after the required `--dirty` invocation reported that Git is unavailable.
- **Resolved issue:** the initial focused run used `FOLLOW_UP`, while the established appointment API contract stores `FOLLOWUP`; the acceptance scenario was corrected to the existing contract and reran successfully.
- **Limits:** evidence is local on SQLite. Remote CI, staging, production, real-mail delivery, elapsed session-expiry evidence, fresh-checkout setup, and operational restore rehearsal remain separately pending. The acceptance suite validates the implemented outpatient pilot, not later laboratory, pharmacy, IPD, or integration modules.
- **Next:** HMS-501 — versioned laboratory test and parameter catalogs.

### 2026-09-25 — Patient 360 started

- **Task:** HMS-406 — IN PROGRESS.
- **Scope:** hospital-scoped patient identity summary; current-branch operational events; assigned-doctor clinical events; role-permitted financial events; unified server-side pagination with bounded page size; portal/API navigation and isolation tests.
- **Next:** implement the timeline query/controller/view/routes, add focused feature coverage, and run full SQLite regression and browser checks.

### 2026-09-25 — Patient 360 completed

- **Task:** HMS-406 — DONE for its documented local scope.
- **Actual changes:** added a Patient 360 portal and same-origin API with a hospital-scoped identity header, current-branch summary cards, and one database-union timeline ordered newest first. Appointment events are available to patient viewers; reception/administration can retrieve permitted invoice and payment events; doctors can retrieve active allergy history plus only encounters, diagnoses, and prescriptions assigned to their own profile in the active branch. Explicit clinical or billing filters return 403 when the role lacks that section. Page size is validated between 5 and 25, all section queries stay tenant/branch scoped, and event links return users to the authorized source record. Added patient-detail navigation and two routes without adding schema.
- **Verification:** focused Patient 360 suite passed 3 tests / 32 assertions, covering role-specific event inclusion/exclusion, explicit forbidden sections, assigned-doctor clinical scope, cross-branch invoice exclusion, cross-hospital 404, portal rendering, and bounded pagination. Full PHP regression passed 191 tests / 1377 assertions with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed in one run. Pint passed on explicit files after the required Git-only `--dirty` attempt reported no repository; Blade compilation, two-route inspection, SQLite operation, and live `/up` HTTP 200 passed.
- **Resolved verification issue:** the first focused run exposed an ambiguous compact Blade section that left an output buffer open and produced a risky test; the actions block was expanded, timestamp formatting moved into the controller, and the focused suite reran cleanly.
- **Limits:** the timeline currently covers implemented appointment, allergy, encounter, diagnosis, prescription, invoice, and payment records. Later laboratory, pharmacy, IPD, imaging, and other modules must register equivalent bounded events when delivered. Financial summary shows open invoice face value, not an accounting statement. Evidence is local only.
- **Next:** HMS-407 — operational pilot acceptance from registration through consultation, prescription, billing/payment, return visit, and permission-boundary validation.

### 2026-09-24 — medicine master and prescriptions started

- **Task:** HMS-405 — IN PROGRESS.
- **Scope:** hospital medicine master with active/inactive catalog entries; assigned-doctor encounter prescriptions with medicine/strength/dose/frequency/duration/route/timing/advice snapshots, patient allergy visibility, author/time attribution, immutable saved records, and print view.
- **Next:** create schema/models/factories/controllers/routes/UI/tests, seed permissions and sample medicines, migrate locally, then run focused and full regression verification.

### 2026-09-25 — medicine master and prescriptions completed

- **Task:** HMS-405 — DONE for its documented local scope.
- **Actual changes:** added a hospital-scoped medicine catalog with administrator create/activate/deactivate controls and doctor read access. Added one immutable prescription per encounter with up to 20 medicine lines, catalog-linked name/strength snapshots, dose, frequency, duration, route, timing, advice, notes, prescriber/time attribution, transaction-safe duplicate prevention, and closed-encounter protection. The consultation displays active allergies, prescription entry/history, and a printable saved prescription containing patient, allergy, prescriber, and medicine details. Added permissions, audit metadata, factories, fictional medicines, routes, UI, and feature coverage.
- **SQLite change:** at the user's direction, local `.env` and `.env.example` now use persistent `database/database.sqlite`; the file was created, all migrations and fictional demo data were applied, README setup guidance was updated, and the prior MariaDB files were left untouched. The local seed chain now includes the existing fictional clinical history, doctor profile/schedule, appointment, service catalog, and medicines outside unit-test runs so a fresh SQLite setup supports browser acceptance without polluting isolated feature-test assumptions.
- **Verification:** focused HMS-405 suite passed 14 tests / 102 assertions. Final full PHP regression passed 188 tests / 1345 assertions with three intentional opt-in concurrency skips. Blade compilation, nine route checks, Pint, SQLite migration/seed, prescription print assertions, tenant/role boundaries, duplicate and post-closure rejection, immutable snapshots, and live `/login` HTTP 200 passed. The browser regression passed 13/14 before exposing the missing demo appointment; after correcting the local seed chain, the appointment scenario passed its targeted rerun, giving all 14 scenarios passing across the final runs.
- **Resolved environment issue:** initial browser retries failed because MariaDB crash recovery left port 3306 unavailable. No recovery or destructive MariaDB action was taken; switching the configured local application to SQLite removed that dependency. A temporary SQLite acceptance database was used during diagnosis and is not the configured application database.
- **Limits:** prescription content is clinician-entered and does not provide interaction checking, formulary rules, e-prescribing, dispensing, or clinical decision support. Those pharmacy/integration concerns remain in later sprints. Evidence is local only.
- **Next:** HMS-406 — authorized Patient 360 summary and paginated timeline.

### 2026-09-24 — encounter diagnoses started

- **Task:** HMS-404 — IN PROGRESS.
- **Scope:** provisional/final encounter diagnoses, optional code system/code, diagnosis time and author attribution, assigned-doctor access, and immutable reasoned correction history.
- **Next:** create schema/models/factories/controller/UI and tests, migrate locally, then run focused and regression verification.

### 2026-09-24 — encounter diagnoses completed

- **Task:** HMS-404 — DONE for its documented local scope.
- **Actual changes:** added immutable hospital/branch/patient/encounter diagnosis records with provisional or final type, optional paired code system/code, branch-local diagnosis time converted to UTC, and author attribution. Added separate immutable reasoned corrections that preserve the original diagnosis while the consultation displays the latest effective version and correction history. Assigned doctors can record diagnoses only while their encounter is active and can append corrections after closure; non-clinical and unassigned access is denied. Added same-origin portal/API routes, consultation UI, audit metadata, model relationships, factories, migrations, and feature coverage.
- **Verification:** focused diagnosis/consultation/vitals/encounter suite passed 15 tests / 118 assertions. Full PHP regression passed 182 tests / 1307 assertions with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed. Both diagnosis migrations applied to MariaDB as batch 14; Pint passed on explicit changed files after the required Git-dependent `--dirty` attempt reported this folder is not a repository. Blade compilation, four-route inspection, and live `/up` HTTP 200 passed.
- **Resolved verification issue:** the first focused run had one assertion expecting a colon between the rendered code system and code; the UI intentionally renders a space. The expectation was corrected and the full focused suite reran successfully.
- **Limits:** coding is free text rather than a terminology-service lookup, and no clinical decision support is implied. Evidence is local only; remote CI, staging, production, and real-hospital workflow approval remain unverified.
- **Next:** HMS-405 — medicine master and prescriptions with allergy visibility and saved-record printing.

### 2026-09-24 — encounter vitals started

- **Task:** HMS-403 — IN PROGRESS.
- **Scope:** immutable encounter-linked vital observation sets with explicit stored units, measurement time, recorder, broad data-quality validation, assigned-doctor access, and patient/branch ownership suitable for later inpatient reuse.
- **Next:** create schema/model/factory/service/controller/UI and tests, migrate locally, then run focused and regression verification.

### 2026-09-24 — encounter vitals completed

- **Task:** HMS-403 — DONE for its documented local scope.
- **Actual changes:** added immutable hospital/branch/patient observations with an optional encounter link so the record shape can be reused when inpatient admission context is introduced. Each set can store temperature, pulse, respiratory rate, systolic/diastolic blood pressure, oxygen saturation, weight, height, notes, persisted canonical units, branch-local measurement time converted to UTC, and recording staff. The assigned doctor can record and review vitals in the active consultation; administrator/receptionist access and post-closure recording are denied.
- **Verification:** focused vitals/consultation/encounter suite passed 10 tests / 81 assertions, covering units, attribution, UI display, broad range validation, empty/future rejection, role scope, closed-encounter rejection, and immutability. Full PHP regression passed 177 tests / 1270 assertions with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed. MariaDB migration applied as batch 13; Pint, Blade compilation, factory formatting, and two-route inspection passed.
- **Limits:** numeric ranges are data-quality bounds, not clinical interpretation or treatment advice. Nursing/IPD capture awaits its role and admission model; corrections currently require a new observation rather than editing history. Evidence is local only.
- **Next:** HMS-404 — attributed provisional/final diagnoses with optional coding and correction history.

### 2026-09-24 — doctor consultation workflow started

- **Task:** HMS-402 — IN PROGRESS.
- **Scope:** assigned-doctor consultation workspace, complaint/history/examination/notes drafts, finalization with encounter and appointment closure, follow-up date, and reasoned attributed amendments that preserve the original finalized record.
- **Initial checks:** HMS-401 supplies one active encounter per consulting appointment and doctor-only access. The consultation will remain inaccessible to administrator/receptionist roles, and amendments will be stored separately rather than overwriting finalized content.
- **Next:** create consultation/amendment schema and models, service/controller/UI, integrate consultation-start redirect, migrate locally, and run focused plus regression verification.

### 2026-09-24 — doctor consultation workflow completed

- **Task:** HMS-402 — DONE for its documented local scope.
- **Actual changes:** added one consultation per encounter with complaint, history, examination, clinical notes, follow-up date, author, draft/finalized state, and finalizer/time. Added the assigned-doctor portal and same-origin APIs for viewing, draft saving, and finalizing. Finalization transactionally closes the encounter and completes its appointment. Finalized source content cannot be overwritten; each correction is a separate immutable amendment containing the complete corrected view, reason, actor, and time, with visible history. The consultation header surfaces active patient allergies and the appointment start action now opens the workspace.
- **Verification:** focused consultation/encounter/appointment suite passed 14 tests / 104 assertions, covering successful drafting/finalization/amendment, preserved original content, required-content rollback, role denial, encounter closure, and appointment completion. Full PHP regression passed 174 tests / 1245 assertions with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed. MariaDB consultation and amendment migrations applied as batch 12; permission reseed, Pint, PHP syntax, Blade compilation, and eight-route inspection passed.
- **Limits:** HMS-402 records narrative consultation content only. Structured vitals, diagnoses, prescriptions, prescription printing, and Patient 360 remain HMS-403 through HMS-406. Evidence is local, not staging or production.
- **Next:** HMS-403 — encounter-linked, attributed, validated vital measurements.

### 2026-09-24 — encounter foundation started

- **Task:** HMS-401 — IN PROGRESS.
- **Scope:** create a hospital/branch/patient encounter root usable by later OPD and IPD clinical records, link OPD encounters one-to-one with appointments, atomically create or retrieve the encounter when the assigned doctor starts consultation, and deny administrative/reception/other-doctor clinical access.
- **Initial checks:** the pilot requires one encounter per appointment, assigned-doctor ownership, and no clinical access from an administrative role alone. Existing appointment transitions already constrain `WAITING → CONSULTING` to the assigned doctor; HMS-401 will integrate encounter creation into that transition.
- **Next:** create the schema/model/factory/service and permissions, integrate the consultation-start endpoint, migrate/seed locally, and run focused plus regression verification.

### 2026-09-24 — encounter foundation completed

- **Task:** HMS-401 — DONE for its documented local scope.
- **Actual changes:** added an immutable hospital/branch/patient encounter root with OPD/IPD-ready type and lifecycle fields, a unique optional appointment relationship, assigned doctor/department ownership, provenance timestamps/actors, model relationships, factory, and local fictional backfill. Integrated encounter creation into the existing assigned-doctor `WAITING → CONSULTING` transition under one transaction and appointment lock; repeated starts return the existing encounter instead of duplicating it. Added doctor-only encounter permissions and scoped APIs while preserving the pilot rule that administrator/receptionist roles do not receive clinical access.
- **Verification:** focused encounter plus appointment suite passed 11 tests / 77 assertions, covering idempotency, failed-state rollback, assigned-doctor/role isolation, scoped retrieval, immutable ownership, and non-deletability. Full PHP regression passed 171 tests / 1218 assertions with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed. MariaDB migration applied as batch 11; permission seed, fictional encounter backfill, Pint, PHP syntax, and encounter route listing passed.
- **Limits:** HMS-401 provides the encounter and access foundation only. Consultation text, finalization/amendments, follow-up workflow, vitals, diagnoses, prescriptions, and Patient 360 remain HMS-402 through HMS-406. Evidence is local, not staging or production.
- **Next:** HMS-402 — assigned-doctor queue and consultation drafting/finalization/amendments.

### 2026-09-24 — reconciliation and Sprint 3 acceptance started

- **Task:** HMS-306 — IN PROGRESS.
- **Scope:** add daily branch reconciliation of payments and refunds, verify mode and ledger totals, exercise duplicate/concurrent payment and failed-transaction behavior, confirm permission/tenant boundaries, and run the Sprint 3 local exit journey.
- **Initial checks:** HMS-301 through HMS-305 are complete locally, MariaDB and the application are available, and no `.ai/rules` directory exists. The implementation will reuse the immutable payment/refund ledger and existing tenant context.
- **Next:** implement reconciliation UI/API and focused tests, add an isolated MariaDB payment-concurrency check, then run focused, full regression, and browser acceptance.

### 2026-09-24 — reconciliation and Sprint 3 local acceptance completed

- **Task:** HMS-306 — DONE for its documented local scope; Sprint 3 local exit condition met.
- **Actual changes:** added an administrator-only daily reconciliation screen and same-origin API scoped to the active hospital, branch, branch timezone, and selected business date. It reports exact gross collections, refunds, net collections, payment/refund counts, payment-mode totals, and linked immutable payment/refund ledgers. Added navigation, validation, tenant/role isolation, failed/replayed payment acceptance, and an opt-in isolated MariaDB test proving four simultaneous identical payment requests create one entry and return three safe replays.
- **Verification:** focused reconciliation/payment/financial-adjustment suite passed 10 tests / 161 assertions. The isolated MariaDB payment concurrency test passed 1 test / 19 assertions with four distinct connections, one payment row, and a paid invoice. Full default PHP regression passed 167 tests / 1189 assertions with three intentional opt-in concurrency skips. All 14 Playwright scenarios passed, including reconciliation authorization and the complete invoice/payment/receipt/refund/adjustment/void journeys. Pint passed on explicit changed PHP files after the required Git-only `--dirty` attempt; PHP syntax, Blade compilation, and JavaScript syntax passed.
- **Limits:** acceptance is local. Remote CI, staging, production, external gateway settlement, and real-hospital pricing/workflow approval remain unverified and separately tracked.
- **Next:** HMS-401 — OPD encounter foundation and appointment/care-team relationship.

### 2026-09-24 — billing screens and printouts started

- **Task:** HMS-305 — IN PROGRESS.
- **Scope:** complete operational invoice list/search/detail and outstanding-balance presentation, preserve the existing payment collection flow, and add authorized printable invoice and receipt documents.
- **Initial checks:** HMS-304 is complete locally, the application and MariaDB are available, and no `.ai/rules` directory exists. Existing invoice list/detail, payment forms, routes, and feature/browser tests will be extended rather than replaced.
- **Next:** implement the screens and print routes, add focused feature/browser coverage, format changed PHP, and verify against the local application.

### 2026-09-24 — billing screens and printouts completed

- **Task:** HMS-305 — DONE for its documented local scope.
- **Actual changes:** expanded the branch invoice workspace with number/UHID/patient/mobile search, status and date filters, an outstanding-only view and count, and adjusted-total/net-received/balance columns. Added authorized A4-friendly issued-invoice and per-payment receipt documents with hospital, branch, patient, charge, payment, refund, adjustment, and current-balance context. Draft printing is rejected, foreign branch/hospital documents remain hidden, filter state survives pagination, and invoice detail links directly to each printable document.
- **Verification:** focused invoice/payment/financial-adjustment suite passed 12 tests / 208 assertions. Full PHP regression passed 164 tests / 1159 assertions with two intentional opt-in concurrency skips. All 13 Playwright scenarios passed, including the extended receptionist invoice/payment/receipt/print journey and administrator refund/adjustment/void journey. Pint passed on the changed PHP files after the required Git-only `--dirty` attempt reported that this folder is not a Git repository; PHP syntax, Blade compilation, and browser JavaScript syntax passed.
- **Limits:** printing uses the authenticated browser's standard print/PDF facility; no emailed documents, fiscal signing, external payment gateway receipt, staging, or production deployment is claimed.
- **Next:** HMS-306 — daily collections reconciliation, duplicate/concurrent payment and failed-transaction checks, permission acceptance, and Sprint 3 local exit verification.

### 2026-09-16 — payment collection and balances

- **Task:** HMS-303 — DONE for its documented local scope.
- **Actual changes:** added immutable payment records for issued branch invoices, four manual payment modes, required noncash references, server-recorded actor/time, exact received/balance calculations, and issued/partial/paid status updates. The administrator/receptionist portal and same-origin API use UUID request keys; an identical repeat returns the original payment, while changed details are rejected. Collection and audit occur in a database transaction with invoice and hospital locks. Local MariaDB migration and permission seed completed.
- **Verification:** focused suite passed 3 tests / 60 assertions; full PHP suite passed 159 / 1064 with two intentionally skipped opt-in concurrency tests; targeted Playwright invoice/payment journey passed. Pint, Blade compilation, route listing, and browser JavaScript syntax passed.
- **Limits / next:** this records staff-entered payments and does not claim external gateway settlement. Refund/void/adjustment workflows are HMS-304, printing is HMS-305, and simultaneous-payment/reconciliation acceptance is HMS-306. No new full browser suite, remote CI, staging, or production claim.
- **Next:** HMS-304 — voids, refunds, and adjustments.

### 2026-09-16 — invoice draft and issue flow

- **Task:** HMS-302 — DONE for its documented local scope.
- **Actual changes:** added invoice/line/number-sequence schema; branch-scoped administrator/receptionist portal and API; server-calculated line snapshots from configured service quotes; editable drafts and transactionally numbered issue; patient and optional matching appointment linkage; guards against changing issued charges; safe invoice audit summaries. Migrated the local MariaDB database and reseeded permissions.
- **Verification:** focused PHP suite passed 4 tests / 53 assertions after the final guard change; full PHP suite passed 156 / 1004 with two intentional opt-in concurrency skips. All 12 browser scenarios have passing results: 11 in the complete run and its one timed-out older administrator scenario on an isolated rerun. Pint, Blade cache, route listing, and browser JavaScript syntax passed.
- **Limits / next:** payment state, receipts, voids/refunds, richer billing search/print screens, and reconciliation are HMS-303 through HMS-306. Configured prices and tax rules still require hospital approval before real operation. No remote CI, staging, or production claim.
- **Next:** HMS-303 — payments and balances.

### 2026-09-16 — service catalog and branch pricing

- **Task:** HMS-301 — DONE for its documented local scope.
- **Actual changes:** added hospital-scoped consultation and general service definitions with unique codes, INR base prices, explicit tax and discount rules, active status, and optional branch price/rule/availability overrides. Administrators manage and audit changes through portal and same-origin API; receptionists see available services and effective branch quotes. The quote service calculates discount before tax with integer-cent arithmetic and rejects unavailable services or invalid discounts. Added factories and repeatable fictional consultation seeding with zero tax and no discount.
- **Verification:** focused suite passed 4 tests / 42 assertions; full PHP suite passed 152 / 951 with two intentional opt-in skips; all 11 browser scenarios passed on the standalone rerun. Local MariaDB migration batch 7, permission and service seeders, 13-route listing, Pint, Blade compilation, and browser JavaScript syntax passed.
- **Resolved verification issue:** one browser scenario exceeded its timeout when full PHP regression and browser suites ran at the same time. The full browser suite was rerun alone and passed all 11 scenarios.
- **Limits / next:** a catalog quote is configured price data, not an invoice or tax-policy determination. Hospital-approved prices/tax rules are required before real operation; invoice snapshots and issue rules are HMS-302. No remote CI, staging, or production claim.
- **Next:** HMS-302 — invoice draft and issue flow.

### 2026-09-16 — Sprint 2 reception acceptance

- **Task:** HMS-208 — DONE for its documented local scope; Sprint 2 local exit condition met.
- **Actual changes:** added registration-to-doctor-queue acceptance coverage, hospital/branch and assigned-doctor isolation checks, actionable validation for changed-payload idempotency-key reuse, direct-URL doctor availability self-scope, and an isolated eight-worker MariaDB appointment concurrency test. The service now rejects a request key reused with different appointment details.
- **Verification:** acceptance suite passed 4 tests / 35 assertions; isolated MariaDB booking test passed 1 / 28 with exactly two accepted bookings and six capacity rejections; full PHP suite passed 148 / 909 with two intentional opt-in skips; full browser suite passed nine scenarios after restarting the stopped local server. Pint passed.
- **Limits / next:** all evidence is local. Remote CI, staging, onboarding decisions, and production rollout remain separately tracked in HMS-010 and later release gates. Billing and OPD are required for the operational pilot.
- **Next:** HMS-301 — service catalog and prices.

### 2026-09-15 — reception workspace and daily queue

- **Task:** HMS-207 — DONE for its documented local scope.
- **Actual changes:** the appointment screen now opens as the active branch's current-day queue, shows timezone and queue count, links to new-patient registration, supports scheduled and walk-in booking, and filters by date, doctor, department, and status. Reception can confirm, check in, move a patient to waiting, cancel with a reason, and mark an elapsed appointment no-show. The assigned doctor can start a waiting consultation and complete it. Every accepted transition is transaction-locked and audited; invalid, terminal, future no-show, cross-role, and non-assigned-doctor actions are rejected.
- **Verification:** appointment/reception suite passed 7 tests / 48 assertions; full PHP suite passed 144 / 873 with one intentional opt-in skip; all 9 browser scenarios passed. Pint, Blade compilation, browser JavaScript syntax, ten-route listing, and local permission reseeding passed.
- **Limits / next:** HMS-208 retains the complete reception journey acceptance, expanded cross-hospital/branch checks, and simultaneous MariaDB booking/token exercise. Encounter creation begins in Sprint 4; local completion is not deployment evidence.
- **Next:** HMS-208 — Sprint 2 acceptance and concurrency verification.

### 2026-09-15 — appointment lifecycle and allocation

- **Task:** HMS-206 — DONE for its documented local scope.
- **Actual changes:** added hospital/branch appointments linked to patient and doctor profile; `NEW`, `FOLLOWUP`, `WALK_IN`, and `ONLINE` types; branch-local dates/times; schedule, exception, closure, slot-boundary, and capacity validation; transaction-serialized doctor/date token allocation with database uniqueness; duplicate request-key handling; audited rescheduling and reason-required cancellation; administrator/receptionist management; doctor own-queue read access; portal booking/filter/reschedule/cancel controls; same-origin APIs; model relationships/factory and repeatable fictional seed data.
- **Verification:** focused suite passed 5 tests / 28 assertions; full suite passed 142 / 853 with one intentional opt-in skip; all 9 browser scenarios passed. MariaDB migration batch 6, permission and appointment seeders, eight-route listing, Pint, Blade compilation, and browser JavaScript syntax passed.
- **Resolved issue:** the first new appointment browser run passed page rendering but used an unreliable exact-label locator for the generated select. Stable field IDs were used and the targeted test plus complete nine-scenario rerun passed.
- **Limits / next:** HMS-207 owns check-in, daily reception worklists, and later status changes. HMS-208 retains the explicit simultaneous MariaDB booking/token acceptance exercise; current local completion is not staging or production evidence.
- **Next:** HMS-207 — reception workspace and daily queue.

### 2026-09-15 — doctor availability

- **Task:** HMS-205 — DONE for its documented local scope.
- **Actual changes:** added branch timezone storage with an `Asia/Kolkata` default; recurring weekly doctor hours with slot duration and capacity; inclusive holiday/leave closures; one replacement exception per date; hospital isolation; administrator and owning-doctor management; receptionist/doctor viewing permissions; audited create, replace, and delete operations; portal and same-origin API routes; useful factories; and repeatable fictional weekday availability seeding.
- **Verification:** focused suite passed 5 tests / 24 assertions; full PHP suite passed 136 / 821 with one intentional opt-in skip; all 8 browser scenarios passed. After exposing timezone in the branch form, 6 branch-focused tests / 42 assertions and the targeted administrator browser scenario also passed. Local MariaDB migration batch 5 and seeders, 19-route listing, Pint, Blade cache, and browser JavaScript syntax passed.
- **Resolved issues:** date-cast matching initially caused a duplicate exception insert in SQLite and was changed to an explicit date lookup/update. MariaDB rejected an automatically generated index name over its 64-character limit; the unrecorded partial HMS-205 objects were rebuilt using an explicit short index name, and migration status then passed.
- **Limits / next:** availability defines the calendar inputs and timezone rules. Bookable-slot enforcement, appointment capacity consumption, collision handling, tokens, and lifecycle states belong to HMS-206; no staging or production claim.
- **Next:** HMS-206 — appointment lifecycle and transaction-safe slot/token allocation.

### 2026-09-15 — doctor availability self-scope correction

- **Issue and correction:** the shared doctor-directory query showed every hospital doctor to doctor-role users. The query now recognizes the active `DOCTOR` membership and restricts results to `user_id = signed-in user` and `branch_id = active branch`; administrator and receptionist directory behavior remains unchanged.
- **Verification:** added API regression coverage with another doctor in the same hospital and branch. The response contains exactly the signed-in doctor's profile and excludes the other profile. Full PHP suite passed 137 tests / 825 assertions with one intentional opt-in skip; the targeted doctor browser journey passed; Pint passed.

### 2026-09-15 — doctor profiles

- **Task:** HMS-204 — DONE for its documented local scope.
- **Actual changes:** added doctor-profile schema/model/factory and repeatable fictional seeder; administrator CRUD screens and JSON endpoints; directory visibility for all pilot roles; branch, active doctor-membership, and active department consistency checks; hospital-unique registration numbers; qualifications, specialization, decimal consultation fee, status, and audited changes. Added Doctors navigation and README API guidance.
- **Verification:** focused suite passed 5 tests / 22 assertions; full PHP suite passed 131 / 797 with one intentional opt-in skip; all 8 browser scenarios passed. Local MariaDB migration/permissions/profile seeding, nine-route listing, Pint, Blade cache, and browser JavaScript syntax passed.
- **Resolved issue:** the first SQLite test exposed an invalid composite department foreign key because the referenced legacy table has no matching composite unique key. The migration now uses a normal department foreign key while the controller transaction validates hospital/branch/active consistency; focused and full suites passed afterward.
- **Limits / next at completion:** profiles are branch-specific, so a multi-branch doctor has one profile per assigned branch while the medical registration number remains hospital-unique. Availability was then delivered in HMS-205; no staging/production claim.
- **Next at completion:** HMS-205 — schedules, capacity, holidays, leave, and exceptions (subsequently completed).

### 2026-09-15 — patient clinical history and documents started

- **Task:** HMS-203 — DONE for its documented local scope.
- **Initial decision:** allergies, medical history, and patient documents are confidential clinical data. The initial role boundary grants access to doctors; receptionist demographic access and an administrator role by itself do not grant clinical access, matching the pilot scope. Records remain hospital-patient scoped while recording the active branch and staff actor.
- **Initial checks:** reviewed HMS-203 status, pilot clinical-access rules, the patient identity workflow, current private administrative file storage, audit behavior, and existing permission conventions.
- **Actual changes:** added hospital/patient-scoped allergy, medical-history, and private-document schema/models/factories plus repeatable fictional clinical seed data; explicit doctor-only clinical permissions; attributed create/update endpoints and UI; preserved `entered_in_error` records; audited changes; private PDF/JPEG/PNG upload/download up to 10 MB with hidden paths and hospital permission checks.
- **Verification:** focused HMS-203 suite passed 7 tests / 52 assertions; combined patient suites passed 46 / 380; full PHP suite passed 126 / 775 with one intentional opt-in skip; all 8 browser scenarios passed. Local migration, permission and fictional clinical seeding, Pint, Blade cache, route inspection, and JavaScript syntax passed.
- **Limits / next:** this scope does not include encounters or Patient 360 access relationships. Clinical access currently follows the doctor role until doctor profiles/care relationships are implemented. HMS-204 doctor profiles is next; remote CI/staging and restore rehearsal remain pending.

### 2026-09-15 — patient registration, search, and demographic audit

- **Task:** HMS-202 — DONE for its documented local scope.
- **Actual changes:** added hospital-scoped patient portal screens and same-origin JSON endpoints for search, registration, detail, and demographic editing; administrator/receptionist write access and doctor read-only access; unknown-DOB and contact validation; normalized phone search; possible duplicate suggestions across patient and emergency contacts with explicit separate-record confirmation; immutable UHID/registration provenance; transactional patient audit with visible before/after details; and patient navigation. Updated permissions and README API/feature guidance.
- **Verification:** focused patient suite passed 39 tests / 328 assertions. Full PHP suite passed 119 tests / 723 assertions with the opt-in MariaDB concurrency test skipped as designed. Full Playwright suite passed 8 scenarios, including four patient scenarios. Blade compilation, frontend build, JavaScript syntax, patient route listing, selected PHP Pint formatting, local permission seeding, and login HTTP smoke passed.
- **Resolved verification issues:** the first browser attempt ran while the development server was stopped; after restarting it, three scenarios passed and the no-JavaScript submit locator remained unstable. A forced click was used for that deliberately JavaScript-disabled scenario; its targeted rerun and the complete eight-scenario rerun passed. A search for literal `0`, readable duplicate DOB output, stale duplicate confirmation, emergency-contact matching, and cancelled model updates were reviewed and corrected before final verification.
- **Limits / pending:** allergies, clinical history, and patient documents remain HMS-203. Appointments, encounters, billing, and Patient 360 remain later tasks. Remote CI/staging and Sprint 1 external closeout are still pending; local completion is not deployment evidence.
- **Next:** HMS-203 — attributed allergies/medical history and authorized private patient documents.

### 2026-09-14 — patient registration workflow started

- **Task:** HMS-202 — IN PROGRESS; selected as the recorded next coding task after HMS-201.
- **Scope:** hospital-scoped registration, demographic detail/edit/search, shared-contact duplicate suggestions, role permissions, and transactional demographic audit; extend the existing Bootstrap portal and session API.
- **Initial checks:** read the tracker, pilot scope, existing patient service, access controls, and sibling controllers; confirmed installed Laravel 13.31.0, Pint 1.32.1, PHPUnit 12.5.35, and Bootstrap 5.3.8. `.ai/rules` is absent; this directory still has no Git repository.
- **Pending / next:** implement and verify the patient workflow, apply permissions locally, and record reproducible user checks. DOC-001's separate guide is unfinished and is not being claimed complete by this coding task.

### 2026-09-13 — local foundation implementation

- **Tasks:** HMS-001 through HMS-010, to the scopes/statuses recorded above.
- **Actual changes:** created Laravel/PHP/Composer setup, dedicated local database, foundation schema/seeders, session authentication/reset flow, branch context, three staff roles, organization/staff administration, Bootstrap screens, administrative audit/private-file APIs, tests, CI definition, and operating/deployment guides.
- **Fixes:** corrected a staff template parsing problem and mobile menu backdrop ordering during browser verification. Test locators were corrected to match actual labels/status messages and the profile dropdown.
- **Verification:** 33 PHP tests / 246 assertions, four browser scenarios across runs, successful local migrations/build/cache checks and HTTP responses. See the verification record for limits.
- **Pending:** Git/fresh checkout, remote CI, real SMTP, staging deployment, elapsed session-idle verification, operational restore rehearsal, and all later product modules.
- **Next:** complete available foundation closeout; implement patient identity/UHID and registration.

### 2026-09-13 — persistent progress tracking

- **Task:** TRACK-001 — DONE.
- **Actual changes:** created this full sprint/task roadmap, recorded implementation evidence and remaining gaps, and added persistent update instructions to AGENTS.md and CLAUDE.md plus a README link.
- **Verification:** read-only audit of implementation/test files and existing documentation completed; `git status --short` confirmed the absence of a repository. Checked 105 unique task IDs, 18 sprint sections, zero broken local links, and instruction placement outside generated guidance. Application tests were not rerun for these documentation changes.
- **Pending:** no remaining work for this documentation task; application backlog remains as listed.
- **Next:** HMS-201 is the next coding task; Sprint 1 closeout remains separately tracked.

### 2026-09-13 — project run instructions

- **Actual work:** checked the existing launch helper and local server, and provided the XAMPP MySQL prerequisite, PowerShell serve command, browser URL, and existing demo sign-in details.
- **Verification:** `GET http://127.0.0.1:8000/login` returned HTTP 200. The application was already responding; no reinstall, database reset, or additional server process was needed.
- **Pending / next:** application task statuses are unchanged. Resume available foundation closeout and HMS-201 when development continues.

### 2026-09-13 — patient identity foundation

- **Task:** HMS-201 — DONE for backend-only scope; Sprint 2 remains in progress.
- **Actual changes:** added `patients` and `patient_number_sequences` migrations, hospital/registration-branch/staff ownership foreign keys, unique UHIDs and query indexes; Patient model/relations, explicit hospital scope and immutable identity guards; atomic creation service with a hospital lock, transaction retries, baseline demographics validation and server-generated IDs; branch/patient factories and repeatable local/testing demo patient seeding. README and pilot scope now explain the implemented boundaries and optional concurrency test.
- **Verification:** 47 patient feature tests / 149 assertions passed. Final combined suite: 80 passed / 395 assertions, with the opt-in concurrency test skipped. Separately, eight independent synchronized PHP workers passed the MariaDB concurrency test (1 test / 42 assertions) in a test-owned database that was removed after completion. New migration and fictional seeder applied to the existing local XAMPP database; `/login` and `/up` returned 200. Changed PHP files formatted with Pint.
- **Resolved verification issues:** initial immutability assertions compared unrefreshed factory attributes with database defaults; the test baseline was corrected. The production-seeder guard test was adjusted to exercise the seeder directly because Artisan intercepts production execution with its own confirmation. All tests passed after correction. Pint's Git-only `--dirty` option was replaced with explicit changed-file paths because this folder still has no Git repository.
- **Limits / pending:** no patient UI/API, HTTP role checks, duplicate suggestions, registration/edit audit, patient documents, or clinical features are delivered by HMS-201; those remain assigned to HMS-202/HMS-203 and later sprints. The explicit query scope and service require authorized callers; they do not automatically authorize raw model queries. UI/browser checks were not rerun. SMTP, session-expiry evidence, remote CI/staging, and other Sprint 1 closeout remain pending.
- **Next:** HMS-202 — registration/edit/search using authenticated hospital/branch/staff context, patient permissions, duplicate review, and auditable demographic changes.

### 2026-09-24 — project status audit

- **Current position:** Sprint 2 remains locally complete. Sprint 3 remains in progress: HMS-301 through HMS-303 are done locally, HMS-304 application code and isolated verification are complete, and HMS-305/HMS-306 have not started. Sprint 1 external closeout remains pending.
- **Repository audit:** no application files are newer than the 2026-09-23 HMS-304 implementation, and the folder is still not a Git repository. Direct dependencies remain Laravel 13.31.0, PHPUnit 12.5.35, Pint 1.32.1, and Laravel Boost 2.8.1.
- **Environment check:** the local HTTP server was not running (`http://127.0.0.1:8000/up` was unreachable). The XAMPP PHP 8.2.12 executable is below the dependency requirement; project Artisan commands must use the bundled PHP 8.4.25 runtime through `hms.ps1` or `.runtime/php/php.exe`.
- **Blocker reconfirmed:** `migrate:status --no-interaction -vvv` with the bundled runtime failed because MariaDB rejected the configured `hms_app` login. Migration state could not be read, so the HMS-304 migration, permission seed, and browser smoke check remain unverified against the configured database.
- **Verification:** this was a read-only status audit; application tests were not rerun because no code changed. The latest passing application evidence remains the 2026-09-23 results recorded above.
- **Next:** restore the existing `hms_app` database access without discarding local data, apply the pending HMS-304 migration and permissions, start the local server, smoke-check the administrator financial-adjustment journey, then mark HMS-304 DONE and begin HMS-305.

### 2026-09-24 — HMS-304 local completion started

- **Task:** HMS-304 — IN PROGRESS; completing the remaining configured MariaDB migration, permission seed, and smoke verification.
- **Initial checks:** MariaDB is running and the existing `hms_foundation` database is present. The configured `hms_app` account is absent from `mysql.user`, explaining the recorded authentication failure; the `.env` database name, username, and password are populated. Existing database contents have not been removed or replaced.
- **Next:** recreate the narrowly scoped application account from the existing configuration, apply the additive migration and permissions, and run focused application and HTTP/browser checks.

### 2026-09-24 — HMS-304 local completion

- **Task:** HMS-304 — DONE for its documented local scope.
- **Actual changes:** recreated the missing `hms_app` MariaDB account from the existing `.env` configuration with privileges limited to `hms_foundation`; no database or existing data was replaced. Applied the financial-adjustments migration as batch 10 and reran `PermissionsSeeder`. Added permanent Playwright coverage for an administrator payment refund, credit adjustment, and unpaid invoice void through the portal.
- **Verification:** MariaDB migration status reports `2026_09_23_154915_create_financial_adjustments_table` as batch 10. The permissions seeder passed. The focused `FinancialAdjustmentTest` plus `PaymentTest` suite passed 7 tests / 131 assertions. JavaScript syntax passed. The local server `/up` endpoint returned HTTP 200, and the targeted Playwright financial-action journey passed 1 test. The full PHP suite was not rerun because no PHP application code changed during this completion step; its latest result remains 163 tests / 1128 assertions with two intentional opt-in skips from 2026-09-23.
- **Limits:** evidence is local only; remote CI, staging, and production remain unverified. The application account was restored only for the configured local database.
- **Next:** HMS-305 — invoice list/search/detail, payment collection presentation, invoice/receipt printing, and outstanding-balance screens.

### 2026-09-23 — voids, refunds, and adjustments implementation

- **Task:** HMS-304 — PARTIAL; application implementation and isolated verification complete, configured local MariaDB application pending.
- **Actual changes:** added immutable `financial_adjustments` records for invoice voids, payment-linked refunds, credits, and debits; preserved original invoice charges and payments; required reasons and UUID replay protection; serialized eligibility checks; bounded refunds and credit adjustments; recalculated gross/refunded/net paid, adjusted totals, balances, and invoice status. Added administrator-only permission, same-origin portal/API actions, invoice ledger display and forms, audit allowlisting, model relationships, factory, and focused feature coverage.
- **Verification:** focused HMS-304 plus payment suite passed 7 tests / 131 assertions; full PHP suite passed 163 / 1128 with two intentional opt-in concurrency skips. Pint passed on explicit changed PHP files after its required `--dirty` invocation reported that the folder is not a Git repository. All 20 invoice routes listed successfully and Blade templates compiled.
- **Blocker:** `php artisan migrate` and the permission reseed could not connect to the configured local MariaDB database because `hms_app` authentication was rejected. The failure occurred before the additive migration ran; no local MariaDB deployment claim is made. Browser verification against that configured database was therefore not run.
- **Next:** restore the existing local database account/configuration without discarding data, run the HMS-304 migration and `PermissionsSeeder`, smoke-check an administrator void/refund/adjustment journey, then mark HMS-304 DONE and start HMS-305.

<!-- For future sessions, append an entry using the structure below and update the current task tables too.
### YYYY-MM-DD — concise work description
- Tasks: stable IDs.
- Actual changes: delivered behavior and relevant files.
- Verification: commands/results/environment, or clearly state not run and why.
- Pending/blockers: remaining work and what is needed to proceed.
- Next: concrete next action and task ID.
-->
