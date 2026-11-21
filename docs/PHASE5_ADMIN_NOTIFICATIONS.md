# Phase 5 — Administrator evaluation notifications

Implemented only in the isolated `feature/ggc-post-defense-revisions` worktree, retaining base commit `08e12b83109f0bcf68866254f2acd8bb7c0f0b9f` and the uncommitted Phase 1A–4 changes.

## Behavior

- Reusable bell and unread badge in the existing admin header, opening a Headless UI modal with Faculty/Office identification, Review Period label, authorized report navigation, read/unread controls and pagination.
- Loading, empty, network/access error and busy states; native keyboard-operable buttons, named bell/close/read controls, dialog title, focus handling inherited from the existing Modal, status/alert announcements, responsive wrapping and bounded scrolling.
- Super Admin receives Faculty and separate Office activity groups. Department Admin receives global Faculty activity, preserving the existing global academic administration model. Office Admin receives only their currently assigned Office's groups.
- No Staff notifications, no changes to required evaluation progress, dashboards, report calculations, eligibility, imports or reminders.

## Privacy and release policy

Notifications are delayed aggregate activity notices, never immediate per-submission alerts. Each eligible recipient receives at most one notice per Review Period and activity scope:

- Faculty: all authorized Faculty activity within that period; at least five **distinct students**, regardless of how many subjects they evaluated.
- Office: one specific Office within that period; at least five **distinct students** for that Office.

Only submitted legacy evaluations with stored answers qualify. Failed submissions, transaction rollbacks, incomplete headers and Staff evaluations do not count. Notifications are not statements that every required evaluation is complete and contain no ratings or numeric response totals.

Release requires `is_submission_open = false`; expired dates do not release notices. Five students is a notification-only small-group safeguard, consistent with the approved privacy approach. It does not modify legacy Faculty/Office report calculations or their existing disclosure/access behavior. Faculty notices do not identify a particular low-response faculty member. Staff reports retain their separate five-student, officially-closed gate and feedback exclusion; notifications never link to them.

Every inbox query, unread count and read/unread write rechecks the current recipient role, office ownership, live closure and distinct-student threshold. Protected/unreleased groups are entirely absent, including from counters. Reopening hides existing notices and blocks changes to their read states. Reclosure returns the same notices with preserved read state. Office reassignment hides old-office notices immediately on the next authorized request; reconciliation can deliver the new office's eligible historical groups.

Notification payloads expose only an opaque notification ID, activity kind, read boolean, generic title/message, authorized subject, school period label and report URL. They never expose student identifiers/names, answers, feedback, evaluation IDs or individual/notification/read timestamps. Laravel's internal notification timestamps remain stored only for persistence.

The visible bell/panel revalidates on focus and every 15 seconds while the document is visible; previously delivered values are cleared during revalidation and on failure. Every report-navigation action first rechecks notification access through the scoped read endpoint. Existing report endpoints retain their own authorization. Already delivered information cannot be recalled; this is not a push-based instantaneous revocation mechanism.

**Client policy note:** the safe aggregate approach intentionally supersedes any expectation of immediate individual-submission notifications. Immediate alerts would permit timing correlation and require a separately approved privacy policy; no immediate mode was added.

## Durable delivery and recovery

- Successful existing evaluation records are the durable activity source. No new per-submission observer, visible event, message or identity-bearing notification ledger is created.
- Explicit period closure commits first, then dispatches `GenerateEvaluationNotifications` using Laravel's existing queue infrastructure with `afterCommit`. Rolled-back closures do not enqueue work.
- The job rechecks live closure, computes eligible scopes in grouped queries, and selects verified recipients in chunks of 100. Staff records are never queried for notification generation.
- Stable recipient/period/scope UUIDs are database primary keys. `insertOrIgnore` makes retries and repeated closures idempotent without resetting read state.
- An hourly, overlapping-run-protected scheduler callback queues reconciliation for closed periods with submitted legacy evaluations. It repairs missed dispatches, permits newly authorized recipients and safely retries generation using the same keys. There are no expensive per-student scans during evaluation submission.
- Three job attempts with a 30-second backoff reuse existing queue failure handling. Open/deleted periods are harmless no-ops. Release/read queries use transaction/shared-period locks; role/ownership is read fresh under a user-row shared lock.
- Future execution requires the normal Laravel queue worker and scheduler to be running in the authorized environment. No workers, cron, credentials or deployment settings were changed outside the disposable runtime. A queue outage does not undo an already committed closure; reconciliation repairs delivery when service returns.
- No email/SMS channels or external notification services were added. Existing reminder mail remains separate.

## Additive schema

`2026_11_19_000003_create_evaluation_notifications_table.php` creates Laravel-compatible `notifications` storage (UUID primary key, type, polymorphic recipient, data, read state and internal timestamps), plus indexed server-owned period/kind/office scope. Period and Office references restrict deletion. Populated rollback refuses to delete notification history; empty rollback is supported. No existing evaluation/staff tables, scores or records are rewritten.

The migration was exercised only against fresh disposable SQLite databases. Applying it to any existing environment remains a separate authorized operation.

## Exact Phase 5 file manifest

New:

- `app/Http/Controllers/Admin/EvaluationNotificationController.php`
- `app/Jobs/GenerateEvaluationNotifications.php`
- `app/Services/EvaluationNotificationService.php`
- `database/migrations/2026_11_19_000003_create_evaluation_notifications_table.php`
- `resources/js/Components/EvaluationNotifications.jsx`
- `tests/Feature/EvaluationNotificationTest.php`
- `tests/frontend/notifications.test.mjs`
- `docs/PHASE5_ADMIN_NOTIFICATIONS.md`

Incremental updates:

- `app/Http/Controllers/Admin/ReviewPeriodController.php` — fresh locked explicit toggle, notification dispatch only after successful closure commit.
- `resources/js/Layouts/AdminLayout.jsx` — shared notification bell in the existing header.
- `routes/web.php` — verified admin-only inbox and scoped read/unread endpoints.
- `routes/console.php` — hourly recovery/reconciliation on the existing queue/scheduler infrastructure.
- `tests/Feature/Phase1ARegressionTest.php` — fake only the new job for migration-free legacy fixtures that intentionally omit queue/notification tables. Real queue/commit behavior is covered independently by the new tests.

Ignored local Composer autoload and production build outputs were regenerated in this worktree. No package or credential changes were needed.

## Verification evidence

- Focused combined PHP run: **47 tests, 692 assertions passed** across notifications, existing Evaluation Workflow/reminder behavior, Review Period Editing and Staff Report Privacy (including inherited staff submission/history tests).
- After the final API user-lock hardening, the nine notification tests passed again (**273 assertions**). The additional scheduled-recovery case passed (**1 test, 16 assertions**), giving **10 focused notification tests / 289 assertions** across those final targeted runs. No unnecessary full-suite run was performed.
- Coverage includes all three admin roles, assigned-office/forged-recipient access, changed roles/ownership, read/unread state, pagination/validation, threshold/distinct students, submission rollback, actual closure dispatch, queued after-commit behavior, reopened jobs, duplicate/retried jobs, scheduled repair, safe rollback and Staff-policy preservation.
- Frontend: **23 tests passed**, including three new notification tests for accessible controls, loading/empty/error states, scoped read/report navigation, reopened/revoked groups and late-response cancellation. The three notification tests passed again after final request-cancellation hardening.
- A real database queue was exercised in a fresh synthetic file database: after closure there was **1 queued job and 0 notifications**; `queue:work --once` completed `GenerateEvaluationNotifications` and produced exactly **4 authorized notifications** (Super Faculty + Office, Department Faculty, assigned Office Admin Office; foreign Office Admin got none).
- PHP syntax checks passed for all nine Phase 5 PHP files. Existing authoritative autoload verification and source resolution for all three new production PHP classes passed.
- Local production build passed with **1,353 modules transformed**. `git diff --check` passed.
- Real browser verification was attempted but blocked by **“Local browser use is disabled in Work settings.”** No screenshots or desktop/mobile visual confirmation are claimed for Phase 5. Browser → UI verification remains a review limitation; API/queue/data flow and frontend behavior are covered by the checks above. The verification skill was used to trace those boundaries.
- Temporary server stopped; synthetic database, sessions and runtime directory removed. No real email/SMS was sent.

## Remaining limits and preservation

- Desktop/mobile visual and live keyboard checks remain outstanding because browser access is disabled. Source uses the existing accessible modal and responsive GGC design; this has not replaced visual inspection.
- Real MySQL concurrent-worker certification and production-scale load testing were not performed. SQLite uniqueness/transactions, real database queue execution and idempotent repeated jobs passed.
- Notification reconciliation can notify eligible closed historical periods and newly authorized recipients. There is no historical-notification cutoff or immediate notification mode.
- The queue worker and scheduler must be operated in a separately authorized future environment; none were installed or configured in production.
- Phases 1A–4, legacy Faculty/Office report behavior and Staff anonymity protections remain preserved. No existing database, production/Hostinger site, original checkout, other worktree or GitHub PR was modified. No commit, push, merge or deployment occurred.
