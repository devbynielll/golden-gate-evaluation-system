# Final release gate closure — 19 November 2026 (Asia/Manila)

**Production decision: NO-GO pending the remaining gates below.** Technical source/history safeguards are verified locally; missing target-engine and owner acceptance evidence must not be represented as a production PASS. Nothing has been deployed.

This report supersedes the historical-risk status in `POST_DEFENSE_FINAL_VERIFICATION.md`. Its earlier consolidated results remain valid evidence for the pre-hardening revision: 367/369 PHP tests (two deliberately disabled-registration expectations), 31/31 frontend tests, build and additive migration preservation checks. No full audit or full-suite rerun was performed here.

## Short GO/NO-GO checklist

- **VERIFIED — Source and manifest:** branch `feature/ggc-post-defense-revisions`, HEAD exactly `08e12b83109f0bcf68866254f2acd8bb7c0f0b9f`; all six stages retained. Complete changed-file manifest refreshed in `POST_DEFENSE_CHANGED_FILES.csv`; three additive migrations remain ordered 3→4→5. No dependency, credential or deployment configuration change.
- **VERIFIED — Historical loss guards:** evaluated students and faculty cannot be deleted through the supported admin actions; faculty photos/attribution survive rejection. Super Admin Faculty Criteria now obey the same answered-history and open-period guards as Department Admin. No schema or score changes. Five new tests (42 assertions) plus 32 affected existing tests (302 assertions) pass; syntax and authoritative autoload check pass.
- **BLOCKED — Disposable MySQL/MariaDB verification:** one availability check found Docker's daemon unavailable and no standalone MySQL/MariaDB executable. No existing database was used and no repeated startup attempts were made. SQLite migration/constraint/transaction checks remain useful but do not certify target-engine concurrency.
- **REQUIRES OWNER/CLIENT ACCEPTANCE — Desktop/mobile visuals:** browser access remains unavailable. Follow the exact local preview and single checklist below. No final integrated visual/keyboard/screen-reader PASS is claimed.
- **REQUIRES OWNER/CLIENT ACCEPTANCE — Privacy/history policy:** accept the residual limitations and minimal future safeguards listed below; approval of legacy retention/correction/feedback policy remains distinct from the newly fixed destructive paths.
- **REQUIRES OWNER/CLIENT ACCEPTANCE — Hostinger operations:** an owner must run the read-only diagnostics below, confirm compatible target PHP/MySQL, current migrations, reliable `default`/`imports` workers and minute-based scheduler capability. These commands have not been run against production.
- **REQUIRES OWNER/CLIENT ACCEPTANCE — Backups and release authorization:** private complete backup plus successful disposable restore rehearsal, signed acceptance, reviewed artifact/manifest and explicit deployment authorization are required. A writable directory or installed dump tool alone does not close this gate.

## Narrow fixes performed

Confirmed inherited history-loss paths were addressed now, rather than treating a waiver of data loss as ordinary client sign-off:

1. `StudentController::destroy`: reject deletion if any Faculty/Office evaluation header or Staff history exists, under the existing student lock. Previously only Staff history prevented deletion; the legacy FK can cascade answers on student deletion. No login/roster/answer is removed on rejection. Unevaluated roster deletion is preserved.
2. `FacultyController::destroy`: transaction locks periods and the faculty row before checking linked evaluations across all periods. Reject before deleting the photo or faculty record, avoiding null-on-delete loss of historical attribution. Unevaluated deletion is preserved.
3. `CriteriaController`: remove Super Admin's Faculty history/open-period bypass; reuse the existing Department Admin protections and role-specific UI lock metadata. Answered question text and section names/numbers cannot be rewritten/deleted, and questionnaire writes are blocked while the active period is open. Unused criteria remain editable when closed; new forward-looking questions may be added. This intentionally tightens an inherited Super Admin behavior to preserve history.

Only these supported application paths are hardened; no DB triggers, archive feature, record conversion or retrospective wording reconstruction was introduced. Existing program/department restrictive FKs prevent bypass through those parent deletions; existing class-section evaluation guards also remain in place. A focused parent-deletion test confirms program/department removal cannot erase the evaluated fixture.

Exact application/test files touched in this gate-closing pass:

- `app/Http/Controllers/Admin/StudentController.php`
- `app/Http/Controllers/Admin/FacultyController.php`
- `app/Http/Controllers/Admin/CriteriaController.php`
- `tests/Feature/DepartmentCriteriaAccessTest.php` — strengthened existing Super Admin expectations, not skipped/weakened.
- `tests/Feature/HistoricalReleaseGuardTest.php` — five new preservation/allowed-deletion tests.

Also updated: final report/matrix/manifest and this gate document. No frontend source changed, so no additional production build was necessary. Logs are `node_modules/.cache/ggc-final-check/release-guards-existing.log` and `release-history-guards.log`. Full regression was not repeated.

## Portable client preview and manual acceptance

The earlier developer-cache preview is superseded and is not a client dependency. Use `docs/CLIENT_LOCAL_TESTING.md` for exact clean-clone installation/start/stop/reset instructions, generated local-only accounts, withheld/released Staff scenarios and one concise desktop/mobile checklist across every role. No production configuration or private developer files are required.

The portable command is `php scripts/client-local.php start` after following that guide's dependency and disposable setup steps. Browser/target-engine acceptance remains separate from the automated checks; no unperformed visual inspection is claimed.

## Remaining privacy/history risks and smallest safeguards

**Must be enforced before unrestricted release — now fixed and locally verified:** no supported deletion of evaluated student/faculty records; no supported rewrite/deletion of answered Faculty criteria; no open-period questionnaire changes by Super Admin. These changes preserve data without introducing a new retention system. Target-engine races still need the blocked MySQL check.

**Requires owner/client policy, not an unapproved schema rewrite:**

- **Historical wording already changed or records already deleted:** new guards cannot reconstruct missing earlier data. Smallest safeguard: owner compares private trusted backups/release evidence and approves any separate recovery; never fabricate scores or silently restore over newer evaluations.
- **Historical Faculty/Office identity corrections:** legacy reports use current names/program/office metadata. Ratings do not change, but historical labels can move when metadata is corrected. Smallest immediate safeguard: review such corrections under a documented owner procedure; approve identity versioning/snapshots or additional field locks separately if original-at-submission identity is required. Staff reports already store snapshots.
- **Privileged/raw writes:** application guards do not replace a DB-level retention policy; direct SQL or ad hoc maintenance can bypass them and legacy student FKs remain cascading. Smallest safeguard: restrict production DB write access and prohibit manual destructive edits; review every maintenance/import path. Decide separately whether a future restrictive-FK migration or archive policy is required. No authorized UI bypass was left open in the checked paths.
- **Legacy Faculty/Office feedback and small groups:** legacy aggregate reports still disclose their existing free text and response counts, and dashboard/reminder rosters retain names/completion metadata. Free text can identify a student. Smallest safeguard: client approves a disclosure/redaction/release policy for these legacy outputs; do not silently impose Staff thresholds or remove existing feedback in this release-gate task. Staff feedback remains fully excluded.
- **Repeated releases/differencing and delivered copies:** five respondents alone cannot prevent comparing repeated aggregates, and downloaded results cannot be recalled. Smallest safeguard: approved release/freeze procedure and restricted authorized downloads; consider immutable release snapshots only in a separately approved revision. Current live gates/polling remain enforced.
- **Notification policy:** one Faculty-period or Office-period notice at five distinct students after official closure, never a per-student event. Accept that delay, retroactive eligible historical notices and the stricter Staff per-section suppression. No immediate alert mode or Staff feedback disclosure is enabled.
- **Archive/retention/correction requests:** guards intentionally reject destructive actions; they do not create an archive UI or prescribe how long private records must be retained. Owner/client approval is needed for those workflows and retention requirements.

## Minimal READ-ONLY Hostinger diagnostics — owner runs; NOT executed here

Substitute verified account-specific paths/URL. Keep command output private; do not paste `.env`, tokens, queue payloads, exceptions, SQL credentials or backup contents into chat. These checks do not install packages, start workers, execute scheduled jobs, migrate data or create backups. Normal diagnostic bootstrap/access logging may still occur.

### Runtime and migration/scheduler definitions

```sh
GGC_APP_ROOT='/absolute/private/path/to/approved/application'
GGC_PHP='/absolute/path/to/matching/php-cli'
GGC_COMPOSER='/absolute/path/to/composer.phar'
cd -- "$GGC_APP_ROOT"
"$GGC_PHP" -v
"$GGC_PHP" -m
"$GGC_PHP" "$GGC_COMPOSER" --version
"$GGC_PHP" "$GGC_COMPOSER" --no-plugins --no-scripts check-platform-reqs --no-dev
"$GGC_PHP" scripts/verify-authoritative-autoload.php
"$GGC_PHP" artisan migrate:status --no-ansi
"$GGC_PHP" artisan schedule:list --no-ansi
```

Use the actual Composer invocation if it is installed as a binary instead of a phar. The autoload verifier must exist in the inspected source. Before deployment, production may not yet contain the new schedule/migrations: compare the **current** baseline status with the candidate release in staging; absence of candidate definitions from the unreleased old source is expected. Do not run `migrate`, `schedule:run`, `queue:work`, `queue:retry`, config/cache writes, dependency installation or seeders as “diagnostics.”

### Worker presence and cron capability, without printing raw arguments

```sh
ps -u "$USER" -o pid=,etime=,args= | awk '/[a]rtisan queue:(work|listen)/ {print $1, $2, "Laravel queue worker present"}'
crontab -l 2>/dev/null | awk '/^[[:space:]]*#/ {next} /artisan[[:space:]]+schedule:run/ {print $1, $2, $3, $4, $5, "Laravel scheduler cron entry"}'
```

No output is not proof of absence: workers/cron may be managed through hPanel or another approved process manager. Owner should view hPanel **read-only**, confirm CLI path/user and minute-level invocation, and privately verify worker queue arguments (`default,imports` or existing configured overrides). A process/cron entry is not delivery evidence; observe approved staging queue transitions and later sanctioned production monitoring before sign-off. Do not edit cron or start/restart workers during these checks.

### Aggregate SQL health — existing authorized read-only connection only

Use the owner's already approved private MySQL client connection (no password on the command line and no new credential changes). Run only these reads in the selected correct database:

```sql
START TRANSACTION READ ONLY;
SELECT VERSION() AS db_version, @@sql_mode AS sql_mode;
SELECT migration, batch FROM migrations ORDER BY batch, migration;
SELECT table_name, engine
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('jobs','failed_jobs','office_staff','staff_criteria_categories',
                    'staff_criteria_questions','staff_evaluations','staff_evaluation_answers','notifications');
SELECT queue, COUNT(*) AS queued_jobs,
       SUM(reserved_at IS NOT NULL) AS reserved_jobs,
       MIN(available_at) AS earliest_available_epoch
FROM jobs GROUP BY queue;
SELECT COUNT(*) AS failed_job_count FROM failed_jobs;
ROLLBACK;
```

No evaluation rows, recipients, job payloads or exception text are selected. Missing queue tables/SQL privileges are a diagnostic result, not permission to create/change them. A nonempty queue may be healthy or stalled: compare aggregates with owner monitoring over time, preserving confidentiality. Production engine/version output guides the **new disposable** compatibility environment; it does not authorize tests on production.

### Application and private-backup capability

```sh
GGC_HEALTH_URL='https://OWNER-VERIFIED-DOMAIN/up'
curl --silent --show-error --output /dev/null --write-out 'Health HTTP %{http_code}\n' "$GGC_HEALTH_URL"
command -v mysqldump || command -v mariadb-dump
GGC_BACKUP_DIR='/owner-approved/private/backup-parent-outside-public'
test -d "$GGC_BACKUP_DIR" && test -w "$GGC_BACKUP_DIR" && printf 'Private backup parent exists and is writable\n'
ls -ld -- "$GGC_BACKUP_DIR"
df -h -- "$GGC_BACKUP_DIR"
```

A 200 health endpoint proves only that endpoint's boot path, not database/SMTP/queue correctness. Tool availability, free space and directory permissions do not prove a backup or restore works. Owner must verify private backup storage is outside the web root, access-controlled/encrypted as appropriate, contains database + migration history + uploads + source/vendor/assets + private configuration and worker/cron definitions, and has a separately approved successful disposable restoration. Actual dump creation/restore is a later authorized operation, not part of these read-only commands.

## Release preparation and safe rollback

- Complete manifest: `POST_DEFENSE_CHANGED_FILES.csv` includes every modified/new nonignored source/test/doc file against `08e12b8…`, with hashes. Include all untracked application files in any future reviewed artifact; never package the preview runtime, node cache, private logs/credentials or synthetic databases.
- Migration order: `2026_11_19_000001` staff/criteria → `000002` separate staff evaluations/assignment FK → `000003` notifications, after the preserved baseline migrations. No fourth migration or destructive reset is introduced by the history guards. Confirm actual target migration history before any later approved application.
- Target prerequisites: locked PHP/Composer dependencies and extensions; fresh authoritative vendor map and built frontend assets; disposable target-version MySQL acceptance; correct public root/HTTPS/private debug/storage permissions; preserved production APP_KEY/configuration and uploads; reliable workers and persistent scheduler-lock cache.
- Defined schedule: `evaluation-notification-reconciliation`, hourly at minute 0 via Laravel's every-minute scheduler tick; default/database job `GenerateEvaluationNotifications`, three attempts/30-second backoff, stable recipient/period/scope primary key. Preserve both `default` and `imports` processing. Read endpoints independently recheck closure/scope even if jobs are delayed. Reopening hides notices without deleting/resetting history.
- Before an approved release: verify private full backups and a disposable restore; freeze submissions/drain workers during an approved maintenance window, retain previous artifact and exact configuration, stage candidate source/assets/vendor together, apply only reviewed pending additive migrations, refresh hosting caches by the established procedure, then perform authorized smoke/queue/history checks. No such action was taken here.
- Code rollback: keep maintenance and pause new workers; use the verified previous compatible artifact only after staging proves compatibility with retained additive tables. Refresh application/opcode caches by the approved procedure. Keep new data, never delete responses to make old code work.
- Schema rollback: empty reversal is 5→4→3; populated migrations intentionally refuse. Do **not** run blanket rollback once staff/notification history exists. Prefer a reviewed forward fix or compatible code-only rollback. A full DB restore can lose newer evaluations: freeze writes, preserve a post-failure copy, reconcile new responses and obtain explicit recovery/data-loss approval before any restoration.

## Final boundary

Only isolated source/tests/docs were changed. Existing databases, production/Hostinger, original checkout, other worktrees and GitHub PR #1 remain untouched. No commit, push, merge, production migration, cron change or deployment occurred. No server was started in this gate-closing pass. Stop here for owner/client review; these remaining gates cannot be closed by claiming unavailable checks passed.
