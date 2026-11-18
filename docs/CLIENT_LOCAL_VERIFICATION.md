# Portable client-testing verification

Verified 19 November 2026 (Asia/Manila), on the isolated post-defense feature branch. This document records private localhost publication readiness, not production release readiness. The production decision remains **NO-GO** pending the previously documented owner acceptance and infrastructure gates.

## Scope

The complete pending Phases 1A–5, final historical-preservation guards and portable client setup are included together. Source remains descended from approved commit `08e12b83109f0bcf68866254f2acd8bb7c0f0b9f`. All existing application source/history and attribution are preserved. README receives only a client-guide callout; its original text is retained.

New portable setup files:

- `scripts/client-local.php`: setup, accounts, start and explicitly confirmed disposable reset.
- `scripts/client-local/environment.php`: private local bootstrap/configuration and ownership checks.
- `scripts/client-local/fixtures.php`: reproducible synthetic roles, questionnaires, academic records, separate evaluations and disclosure scenarios.
- `scripts/client-local/router.php`: loopback development router and guarded demo upload serving.
- `docs/CLIENT_LOCAL_TESTING.md`: beginner-facing clean-clone and role/scenario guide.
- `tests/client_local_smoke.py`: optional Python-standard-library developer HTTP smoke test; Python is not required to run the client application.

`.gitignore` excludes the complete `storage/client-local/` runtime, including generated credentials/key, database, sessions, uploads, cache and logs. No default production seeder, credential change or migration was added. The new source uses the same Laravel authentication, permissions, validation, scoring, privacy gates and queue processing as the application.

## Clean-checkout evidence

A new isolated clone of the approved Git baseline was created inside the permitted worktree and overlaid with the exact pending candidate source. It initially contained no vendor, node_modules, `.env`, generated assets or developer preview runtime. Only prospective Git source was copied; runtime/cache files were not copied.

The documented installation was performed there:

- Locked production Composer installation with `--no-dev --classmap-authoritative --no-scripts`: PASS; installed a separate vendor directory, without copied developer provider caches or Faker dependencies.
- `composer check-platform-reqs --no-dev`: PASS on PHP 8.3.30.
- Clean `npm ci` and production build: PASS on Node 22.23.2; 1,353 modules transformed.
- Authoritative autoload release verifier: PASS from that fresh vendor map.
- Portable `setup`: PASS; six demonstration students, all test admin roles, original Faculty/Office and separate Staff answers/criteria, one current open four-Staff-response period and one historical closed five-Staff-response period.
- Actual generated-account login: PASS for Super Admin, Department Admin, Registrar Office Admin, Library Office Admin and Student. No authentication/CSRF bypass was used.
- Actual Faculty and Office submissions: PASS, with normal required-progress changes.
- Actual optional Staff submission and duplicate validation: PASS; Staff submission leaves required completion unchanged.
- Staff report fixtures: PASS; historical report has five respondents, two sections and stored-answer overall 3.00. Current report is suppressed while open. The fifth current response submitted through the real form endpoint releases only after official closure; reopening suppresses again.
- Department/Office/Student permissions and forged Office/notification IDs: PASS.
- Automatic local database worker: PASS; closure produces aggregate notifications with correct recipients; read/unread and reopening behavior pass. The launcher also starts the normal scheduler loop; the recent existing scheduler test remains supporting evidence.
- Private `.env`, runtime credentials, scripts and vendor paths over HTTP: 404 as expected.
- Focused frontend report/PDF, notifications and Staff workflow checks from the clean checkout: **12 tests passed**. These are automated component/hook/PDF checks, not a claim of browser visual acceptance.
- Portable PHP syntax: all four launcher/bootstrap/fixture/router files pass.

After initial success, Windows path comparison was normalized to forward-slash canonical paths and the affected clean-checkout HTTP flow was repeated successfully. Native Windows/Linux execution remains client acceptance; there are no Mac-specific runtime paths or dependencies in the portable files.

## Isolation and reset checks

- Setup succeeded with deliberately conflicting process DB/MySQL URL, SMTP and config-cache variables; the launcher forced its owned SQLite database and local mail configuration.
- The clean checkout was then given a fake production-looking root `.env` and a root config cache that throws if used. Real login/submission/report/queue checks still passed, proving those root inputs were ignored. Only synthetic test files were involved; no real credentials or existing database were accessed.
- Reset without explicit `--confirm-disposable`: refused.
- Setup over an existing demo runtime: refused.
- Reset while the local app held its run lock: refused.
- Reset with a wrong ownership marker: refused without deleting the database.
- Reset with a database symlink to an outside sentinel: refused; the sentinel was preserved.
- Valid stopped-demo reset: passed; recreated only the marked runtime and generated different installation IDs/passwords.
- Ctrl-C shutdown: checked; launcher stops its web/worker/scheduler children and releases the run lock. No test server remains running after verification.

Credentials are independently generated per installation and hashed through Laravel. They are stored only in that installation's new private runtime; no published shared password exists, no production account is created and production is never used for comparison/testing. Client accounts cannot be selected via an existing MySQL/Hostinger configuration because the launcher does not accept a database URL/name/path and verifies its canonical owned SQLite path.

## Focused safety audit and publication boundary

The complete changed/new source set is reviewed against `08e12b8…`; the manifest is `docs/POST_DEFENSE_CHANGED_FILES.csv`. The three additive migrations remain ordered staff/criteria → staff evaluation snapshots → notifications. Recent full/focused regression evidence is reused; no expensive full-suite rerun was necessary for this CLI/documentation-only addition.

Prospective Git files are checked for real environment files, database/dump/backup/key/log artifacts, generated dependencies/assets and high-confidence credential patterns. Synthetic example domains and randomly generated local credentials are distinct from production data. Earlier Mac/developer-cache preview instructions were replaced by references to the portable guide; no private hosting paths are required.

Generated testing keys, runtime databases, downloaded dependencies, build outputs, logs and Python bytecode must remain ignored and are not part of the commit. Secret scans support this review but are not a universal guarantee. No production service or real SMTP/SMS was contacted.

The explicitly requested target is private repository `devbynielll/golden-gate-evaluation-system`. Existing PR #1 remains open from `codex/sync-verified-client-revisions` at the approved base SHA to `main`. The new feature PR is stacked on that synchronization branch, so it reviews post-defense changes without duplicating/replacing PR #1. No main/PR #1 merge or rewrite is performed. The worktree's unrelated saved `origin` URL is not changed; publication uses the explicit requested repository URL and a normal non-force push of only the dedicated feature branch.

## Remaining acceptance

- Client desktop/mobile and live keyboard/screen-reader checks from the guide.
- Native client operating-system acceptance, especially Windows PHP toolchain/process cleanup.
- Disposable target-version MySQL/MariaDB migration and parallel-worker certification before production (not needed to run this SQLite demo).
- Owner/client privacy, retention/correction and legacy-feedback policy sign-off.
- Hostinger PHP/migration/worker/scheduler/private backup/restore and live email acceptance, followed by separate production authorization.

Local publication does not close those production gates. Production, existing databases, the original checkout, unrelated worktrees and GitHub main remain unchanged.
