# Client feedback corrections — 21 November 2026

Branch: `fix/ggc-client-feedback-nov21`.

This revision builds on `fb618aac88661911dcc36ef2470ca0a96b52a4e2`, including both preliminary closure fixes. The original testing branch remains at `ef2d53c59b084608b56aae88bd62494758eb782e`; its Windows cache-path correction is inherited. The verified application baseline `08e12b83109f0bcf68866254f2acd8bb7c0f0b9f` and all intervening revisions are retained.

## What changed

### Closed evaluation periods

Faculty and Office cards previously lacked the closed-period disabling already used by Staff cards. Their server rejection could also redirect without a validation error, allowing Inertia to invoke a success callback. The preliminary fixes correctly disable the cards and return validation errors for closure and duplicate submissions; both were reviewed and retained.

The completed correction refreshes `isSubmissionOpen` during partial pagination and submission requests, passes it to the existing modal, and disables a known-closed form's rating controls, comments and Submit button. Both page and modal submission handlers reject known closure without showing success. Completed badges remain Completed. Stale pages are still protected by server validation and the existing transaction-level period recheck and locks.

The explicit `is_submission_open` flag remains authoritative. An expired date does not close an open period, and extending a closed period does not reopen it. No evaluation rows or historical answers are rewritten by these changes.

An already-loaded page learns of closure on a refresh/request; no new live polling or push channel was introduced. A stale form can remain visible until then, but its submission is rejected by the server.

### PDF information alignment

The shared PDF generator now centers the applied semester/year, filter text and individual Faculty/Office/Staff identification beneath the existing header. This covers all six summary/individual modes. The logo, heading, green tables, overall ratings, response labels and table cells retain their existing alignment and design. Long metadata wraps and paginates inside the page instead of overflowing.

The generator still uses the existing authorized report payload and presentation helpers. Calculations, filters, report permissions, feedback rules and Staff disclosure restrictions are unchanged.

### Notification expectations and safe improvement

The bell is an aggregate release inbox, not a real-time student-submission feed. Existing generation deliberately waits for official closure and at least five distinct student respondents per notification group. Faculty notifications group activity across the period; Office notifications group activity for one office. Five evaluations from fewer than five distinct students do not qualify.

Super Admin receives eligible Faculty and Office groups; Department Admin receives Faculty groups; Office Admin receives only the assigned Office's groups. Students cannot access this inbox. Staff submissions do not generate these legacy Faculty/Office notifications.

The dialog now explicitly explains delayed release and why open periods or small groups are absent. The existing queue job runs after closure commits, with retry handling. Scheduled reconciliation repairs missed dispatches without duplicating notices or resetting read state. The client-local launcher already starts the website, database queue worker and scheduler together; it required no change. Production workers/scheduler were not accessed.

**The client's expectation of alerts during an open period is not implemented.** That changes the approved disclosure policy and needs explicit client approval:

- **Current approved option:** retain closure-only aggregate notices and the five-distinct-student threshold. This is the behavior shipped by this revision.
- **Possible future option, requiring approval and separate implementation:** a fixed-schedule delayed Faculty/Office activity digest during open periods. Set a minimum delay, require at least five new distinct respondents in each releasable batch, cap frequency, and prevent overlapping batches or repeated updates that expose small increments. Keep student identities, comments, exact counts and submission times out of notices. A five-response threshold alone does not remove timing/inference risk in small cohorts. Define recipient scope and the treatment of reopened periods before implementation. Staff disclosure rules must remain separate.
- **Immediate individual alerts:** not implemented; per-submission timing could identify respondents and conflicts with the stated anonymity requirements.

## Files in this correction

Application changes in this commit:

- `resources/js/Pages/Student/Evaluation.jsx`
- `resources/js/Pages/Student/Partials/EvaluationFormModal.jsx`
- `resources/js/Pages/Admin/Reports/Partials/downloadReport.js`
- `resources/js/Components/EvaluationNotifications.jsx`

Regression tests:

- `tests/Feature/ClosedEvaluationTest.php` (new)
- `tests/frontend/staffEvaluation.test.mjs`
- `tests/frontend/reports.test.mjs`
- `tests/frontend/notifications.test.mjs`

Documentation: `docs/CLIENT_FEEDBACK_NOV21.md` (this report).

The complete dedicated-branch difference from the testing branch also includes the already-pushed preliminary edits to `app/Http/Controllers/Student/EvaluationController.php` and `resources/js/Pages/Student/Evaluation.jsx`.

## Verification results

- **PHP: 80 tests, 1,084 assertions passed.** Suites: ClosedEvaluation, EvaluationWorkflow, EvaluationNotification, RoleScoping, StaffReportPrivacy, ReportImprovements, HistoricalReleaseGuard and ClientLocalCachePaths.
- **Additional Staff regression: 9 tests, 133 assertions passed.** Covers optional feedback boundaries, transactions, duplicate protection, historical snapshots, questionnaire validation and Staff privacy. No existing tests were removed or skipped.
- **Frontend: 18 tests passed, zero failures/skips.** Suites: staffEvaluation, reports and notifications. Includes closed/open/completed cards, stale-form rejection without false success, accessible controls, all six PDF alignment modes, wrapped/paginated metadata, notification guidance, permissions revalidation and read/unread behavior.
- **Production frontend build passed.** `npm run build` completed successfully.
- **Real synthetic HTTP checks passed** on loopback port 8873: all five demo roles authenticated; all three stale submissions failed with HTTP 422 after authorized closure; open Faculty/Office/Staff submissions succeeded; all three duplicates failed; required progress remained independent of Staff progress; private runtime paths stayed inaccessible.
- **Real database queue delivery passed:** eligible closure notices reached the authorized admins, forged Office access was denied, read/unread toggles worked, and reopening suppressed notifications and Staff results again.
- **Historical report preservation:** all six historical summary/individual JSON payloads remained structurally identical after synthetic submissions and close/reopen operations.
- **Actual PDF review:** six application PDFs generated from authorized HTTP report payloads, plus long-identity and withheld-Staff cases. Poppler-rendered pages were visually inspected. Period/filter/identity text is centered, tables and ratings remain left aligned, long metadata wraps across pages, and withheld Staff output has no scores, small-group counts or feedback. PDF text-position checks confirmed centered periods and page bounds for all eight files.
- **Portable launcher:** setup succeeded in a new independent disposable source copy, including additive migrations and synthetic fixtures. The real worker and scheduler entry points ran. Dependencies/build assets were reused, so this is not a new dependency-install certification. Windows drive/Unix/UNC cache-path resolution passed framework-level regression tests; native Windows execution was unavailable.
- `git diff --check` passed.

Local evidence is ignored under `node_modules/.cache/ggc-nov21-check/` (PHP/frontend/build/HTTP logs, synthetic report payloads and PDFs). No credentials or runtime databases are committed.

## Remaining verification and release limits

These focused checks used new SQLite test databases. The mid-request closure regression explicitly simulates closure between the initial check and the locked recheck for all three evaluation types. It does **not** certify parallel MySQL/MariaDB lock behavior under load; an isolated disposable MySQL concurrency run remains a separate verification item. Requests already committed before the closure transaction are valid; requests encountering official closure are rejected.

Rendered PDFs and component accessibility/interaction tests were checked. A complete browser-driven desktop/mobile visual acceptance pass was not performed. Native Windows launcher execution and production queue/cron health were not certified here.

No migrations, scoring changes, new notification-release behavior, production access or deployment are included. Existing client databases, the original checkout, other worktrees, `main` and the original feature branch were not changed. The dedicated revision branch is for review; this report does not declare production readiness.
