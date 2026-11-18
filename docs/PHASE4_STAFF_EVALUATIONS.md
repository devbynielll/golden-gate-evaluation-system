# Phase 4 — Individual Office Staff Evaluations and Reports

Implemented locally on `feature/ggc-post-defense-revisions`, based on `08e12b83109f0bcf68866254f2acd8bb7c0f0b9f`. All Phase 1A–3 changes remain uncommitted and preserved. No Phase 5 notifications were implemented.

## Workflow and preservation

- The existing Student Evaluation page now includes optional individual staff cards for active staff in global or student-department offices. Shared and applicable office-specific Staff Criteria are combined; foreign-office criteria are excluded.
- Staff completion is separate from required Faculty/Office completion, dashboard statistics, reminders and legacy reports. Existing Faculty/Office submission methods and numerical calculations are unchanged.
- The server rechecks the active period's explicit submission flag, student/department eligibility, current staff assignment and archival state. Expired dates alone neither close submissions nor release reports.
- Submissions require exactly the applicable question IDs and integer ratings from 1–4. Empty questionnaires, forged IDs, invalid ratings and duplicates are rejected. The reused form presents server errors without false success and labels rating controls for assistive technology.
- A transaction locks periods, the student and assignment, inserts separate records, calls the Phase 3 criteria-freeze helper, and stores exact staff name/position, office, period and question/category snapshots. Failure rolls back records and freeze stamps together. Model guards prevent rewriting/deleting submitted records.
- Staff edits or archival retain submitted snapshots. Staff-linked period identity edits, period deletion and student deletion are blocked to protect references. Dates can still be extended without changing submission status.

## Schema and migration

Additive migration: `database/migrations/2026_11_19_000002_create_staff_evaluation_tables.php`.

- `staff_evaluations`: separate header, student, immutable office assignment and period references, evaluated staff/office/period snapshots, submission time and optional stored comments.
- `staff_evaluation_answers`: separate 1–4 answers, question/category references and exact wording, section, scope and order snapshots.
- Unique student + period + office-staff assignment prevents duplicate submissions, including competing inserts. Unique evaluation + question prevents duplicate answers.
- Restrictive foreign keys protect staff, office, student, period and answered criteria references. A composite assignment/office foreign key prevents moving evaluated assignments between offices.
- Rollback checks both new tables before dropping anything and refuses when either contains records. Empty rollback removes only Phase 4 tables and its additional assignment index, retaining all Phase 3 records. Roll back Phase 4 before Phase 3 because of dependent references.
- No existing evaluation tables or records are migrated or rewritten. Migration execution during this work was limited to disposable SQLite databases.

## Approved report privacy

- Each report covers one staff assignment in one Review Period. Results never pool periods or split respondents by historical position/name.
- At least **five distinct student evaluations** and `is_submission_open = false` are required. Both Super Admin and Office Admin use the same service and rules.
- Reports also withhold the whole result if any answered section has fewer than five respondents. This conservatively prevents revealing small section cohorts after questionnaires grow between openings.
- Below threshold, ratings, counts and sections are absent/null and the message is **“Insufficient responses to protect student anonymity.”** While open, the message explains that official closure is required.
- Zero-response roster/period groups use the same suppression shape as low-response groups. Roster group totals are not respondent totals.
- Student identities, individual records, submission times, question-level answers and free-text feedback are never included in staff report payloads or PDFs. Comments remain safely stored for a future disclosure policy.
- Released reports use snapshot labels and actual stored answer-weighted averages, with existing rating interpretations. Search selects whole aggregates after grouping; it cannot isolate respondents who evaluated a previous position.
- Office Admin scope is checked against the actor's current office assignment, including that office's archived staff history. Forged foreign office filters are denied and foreign report targets are unavailable. Department Admins and students cannot access these reports.
- Summary and individual View/PDF actions use the same authorized payload. Full exports include every filtered report group, independently of page size, and use applied server filters rather than unsaved controls.
- No report cache is used; responses carry `private, no-store`. Every request rechecks official closure. The visible staff page/viewer refreshes on focus and every five seconds, and PDF downloads fetch a fresh payload rather than exporting a stale preview.
- Existing Faculty and overall Office disclosure and feedback behavior is preserved. No staff ratings/counts were added to legacy dashboard summaries.

## Exact Phase 4 source manifest

New files:

- `app/Http/Controllers/Student/StaffEvaluationController.php`
- `app/Http/Controllers/Admin/StaffReportController.php`
- `app/Models/StaffEvaluation.php`
- `app/Models/StaffEvaluationAnswer.php`
- `app/Services/StaffEvaluationService.php`
- `app/Services/StaffReportService.php`
- `database/migrations/2026_11_19_000002_create_staff_evaluation_tables.php`
- `resources/js/Pages/Admin/Reports/Staff.jsx`
- `tests/Feature/StaffEvaluationTest.php`
- `tests/Feature/StaffReportPrivacyTest.php`
- `tests/frontend/staffEvaluation.test.mjs`
- `docs/PHASE4_STAFF_EVALUATIONS.md`

Updated files, including incremental edits to earlier-phase uncommitted files:

- `app/Http/Controllers/Student/EvaluationController.php` — staff board props only; legacy submission methods unchanged.
- `app/Http/Controllers/Admin/ReviewPeriodController.php` — protect staff-linked identity and deletion.
- `app/Http/Controllers/Admin/StudentController.php` — protect roster references with staff history.
- `app/Http/Controllers/DashboardController.php` — combine linked-history flag used by period editor without a separate staff response metric.
- `app/Models/OfficeStaff.php` — staff evaluation relationship.
- `app/Models/ReviewPeriod.php` — staff evaluation relationship.
- `app/Models/StaffCriteriaQuestion.php` — freeze-helper comment only; helper preserved.
- `app/Services/ReportService.php` — expose existing rating-interpretation helper for reuse; formula unchanged.
- `routes/web.php` — student submission and authorized staff report routes.
- `resources/js/Layouts/AdminLayout.jsx` — Staff entry under existing Reports menu.
- `resources/js/Pages/Student/Evaluation.jsx` — optional cards, independent progress, staff submission route.
- `resources/js/Pages/Student/Partials/EvaluationCard.jsx` — optional unavailable state.
- `resources/js/Pages/Student/Partials/EvaluationFormModal.jsx` — shared server-error handling and accessible labels/title.
- `resources/js/Pages/Admin/Partials/ManagePeriodsModal.jsx` — staff-linked history also locks identity fields.
- `resources/js/Pages/Admin/Reports/Partials/OfficePerformanceCard.jsx` — reuse existing card for staff metadata, office filter and suppression.
- `resources/js/Pages/Admin/Reports/Partials/ReportViewer.jsx` — staff identity, suppression messages and feedback exclusion.
- `resources/js/Pages/Admin/Reports/Partials/reportRows.js` — shared Staff View/PDF columns and withheld labels.
- `resources/js/Pages/Admin/Reports/Partials/useReport.js` — authorized staff filters, live rechecks and fresh PDF requests.
- `resources/js/Pages/Admin/Reports/Partials/downloadReport.js` — staff metadata/sections, suppressed values and feedback exclusion.
- `tests/Feature/Phase1ARegressionTest.php` — minimal disposable staff-history fixture for the additional period guard.
- `tests/Feature/OfficeStaffMigrationTest.php` — roll back dependent Phase 4 tables before testing Phase 3 rollback.
- `tests/frontend/reports.test.mjs` — Staff View/PDF disclosure consistency and fresh-download checks.

Ignored local build/evidence files were also generated under `public/build`, `vendor` and `node_modules/.cache/ggc-phase4-check`; they are not committed release artifacts.

## Verification

- Focused PHP regression run: **83 tests, 990 assertions passed**, covering staff workflow/privacy plus existing Evaluation Workflow, Reports, Review Period Editing, Office Staff, Staff Criteria and migration tests.
- Supplemental staff edge cases, including missing criteria, forged snapshots and immutable records: final staff-only run **20 tests, 377 assertions passed**. This includes inherited workflow cases; it replaces the 18 staff tests in the combined run rather than representing 20 wholly additional cases. The other **65 regression tests** passed unchanged.
- Frontend: **20 tests passed** across Phase 1A/1B, Staff Management/Criteria, staff submission, report previews/PDFs and logo handling.
- `npm run build` passed with 1,352 modules transformed.
- Authoritative Composer map regenerated locally with scripts disabled. All six Phase 4 classes plus inherited import/reminder release-check classes resolve to this worktree. Existing `scripts/verify-authoritative-autoload.php` passed.
- `git diff --check` passed.
- Synthetic browser: submitted the fifth staff response while required Office progress stayed 0/1; verified Office Admin navigation, View and actual PDF download; verified closed release and automatic suppression in an already-open viewer after reopening; checked the narrow 390×844 viewport.
- Generated PDFs were rendered with Poppler and visually inspected, including real browser output with the existing GGC logo. Ratings, section labels, metadata, suppression and feedback exclusion matched the viewer.
- Synthetic browser server stopped and its fresh fixture database/session/runtime directory removed. Evidence retained only inside this worktree.

Evidence under `node_modules/.cache/ggc-phase4-check`:

- `student-staff.png` — separate optional staff completion.
- `staff-viewer.png` — released individual report.
- `staff-reopened-mobile.png` — reopened-period suppression on a narrow screen.
- `staff-browser.pdf` / `staff-browser.png` — actual browser PDF and rendered inspection image.
- `staff-withheld.pdf`, `staff-released.pdf`, `staff-summary.pdf` — focused PDF fixtures.

## Remaining limits and review notes

- No verified disposable MySQL server was available. Row-lock ordering, transaction rollback, uniqueness and restrictive references were checked on disposable SQLite; actual MySQL concurrent-worker behavior remains unverified.
- Previously delivered/downloaded results cannot be recalled. Visible pages recheck within five seconds/on focus, while new API/PDF requests gate immediately against current state. Repeated legitimate releases before/after new submissions can permit aggregate differencing; the approved five-response/closed-period policy is implemented without inventing an additional release policy.
- Reporting currently loads authorized staff/period aggregates in memory; production-scale load certification was outside this focused revision.
- No existing database was migrated. Local source requires the additive Phase 3 and Phase 4 migrations in a separately authorized future environment before these features can run there.
- Production/Hostinger, existing databases, original checkout, other worktrees, GitHub PR #1 and existing branch commits were not modified. No commit, push, merge or deployment occurred.
