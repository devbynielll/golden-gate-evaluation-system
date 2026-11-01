# Verified application source synchronization

Date: 2 November 2026. Review baseline: main at ece7cd6a9042904ff95dd9fa5544e9fae325a192, retaining the professional README byte-for-byte. That README's Checkpoint 11 notice describes the historical main baseline; this document describes the synchronization branch until it is reviewed and merged. No production deployment is performed by this PR.

## Reconciled source

39 application/test paths are copied byte-for-byte from the verified local application revisions following Checkpoint 11. Thirty release-guard hashes match the preserved release manifests for unified imports/email repair, b619 client changes, h82f Office Dashboard and k61d positional Class imports. Other source changes are their regression coverage. This is a saved-release/source comparison; it is not a fresh connection to or complete byte-level certification of the live server.

Included behavior: mixed Regular/Irregular roster imports with no inferred irregular subjects; import progress/error reconciliation; linked student/login email repair and reset-message wording; assigned-office pending feed and reminder safeguards; read-only Office review period and safe missing-date rendering; multi-subject positional Class Review/Save with literal-comma handling; PDF logo flattening. Earlier dashboard/card refinements, Office Criteria, permissions, historical-evaluation protections and other Checkpoint 11 functionality remain in the base.

Seven additional paths update source documentation or add the CLI authoritative-autoload release check. The professional and original READMEs, attribution, blank environment template, security ignore rules, production HTTPS behavior, runtime configuration, dependencies and both lockfiles are preserved. Default-account seeders, obsolete local samples and historical Git objects omitted from the clean repository are not reintroduced.

## Authoritative Composer autoloading

The r4m8 production correction repaired stale generated maps. Those server-generated maps do not belong in Git. The App namespace already maps to app/; PositionalClassValues.php and SendOfficeEvaluationReminder.php are ordinary PSR-4 source and are included automatically when Composer builds the map from this complete source tree.

For a clean release artifact, install the locked production dependencies and verify in a fresh PHP process:

```sh
composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist
php scripts/verify-authoritative-autoload.php
npm ci
npm run build
```

Run these only in a clean build/staging environment with appropriate private configuration. Never copy stale vendor maps from an older release, disable authoritative mode to hide missing classes, or run an unreviewed dependency update on production. Composer's normal post-autoload hook clears compiled provider manifests before package discovery; do not retain development provider caches in a production-only artifact. The CLI verifier does not bootstrap Laravel, read .env, send mail or connect to a database.

## Verification performed for this branch

- PHP 8.3.30 / PHPUnit 11.5.56: 64 focused tests, 563 assertions, all passed.
- Full suite: 278 tests, 2,492 assertions; 276 passed, two unchanged original registration failures. Public registration remains disabled: GET registration expects 200 but receives 404; signup expects authentication but does not authenticate. Neither tests nor authorization were weakened.
- Eleven Node frontend tests passed, including eight actual dashboard/modal rendering scenarios within the rendering test; Inertia context is adapted, actual dashboards/modals/feed/layout are rendered. Import polling and PDF-logo behavior are covered.
- Vite production build passed on Node 22.23.2. All 194 source PHP files passed syntax checks.
- Locked Composer development and production-only installations generated authoritative maps; fresh-process required-class resolution, production platform checks and normal package-discovery hooks passed. Existing verified dependency packages were reused offline; no dependency or lockfile update was made.
- Complete prospective source-tree filename/secret-pattern scan plus manual review found no credentials, real records or private runtime artifacts. Existing published mail views under resources/views/vendor and empty storage .gitignore files are application source, not generated dependencies/logs. Placeholder/test/factory addresses are synthetic; dependency-author attribution stays intact. Pattern scanning is not an absolute guarantee of secret absence.
- Fetched clean GitHub history was checked for private runtime artifacts. Original historical objects were never imported. Original local repository branch, HEAD, refs, staging state and working-source hashes were checked unchanged.

PHP tests ran only in a newly created, network-blocked disposable verification tree with SQLite :memory:, array mail/cache/sessions and a synthetic key. No existing MySQL, client/demo database, mail service or production endpoint was accessed. No standalone migration, deployment or data-import command was run against an existing database; automated tests create their ephemeral schemas internally. Current MySQL/concurrency/browser/PDF/live SMTP acceptance was not repeated by this synchronization task.

## Release review and remaining limitations

The additive 2026_11_01_000003 migration introduces regular_count and irregular_count on import_batches. Preserve its filename/history. A new environment needs the approved migrations; an existing environment must check actual migration status first. Do not reset, seed or blindly rerun production setup. Deployment requires separate authorization and verified private backups; this PR is source synchronization only.

Keep workers for both imports and default queues. Queued is not delivered; live SMTP, worker/cron behavior and PHP-FPM opcode-cache refresh remain server acceptance checks. Preserve production keys, credentials, accounts and historical records outside the repository.

Known limitations remain: two registration-test failures; historical full-history SQLite rollback limitation; mobile-navigation and some report/PDF presentation concerns; singleton/assignment/evaluation concurrency and production load/security/compatibility acceptance require the appropriate existing evidence or further authorized testing. This PR neither certifies client acceptance nor resolves unrelated issues.

## Exact changed/new paths

- `app/Http/Controllers/Admin/ClassImportController.php`
- `app/Http/Controllers/Admin/Concerns/HandlesAcademicImports.php`
- `app/Http/Controllers/Admin/EvaluationReminderController.php`
- `app/Http/Controllers/Admin/FacultyImportController.php`
- `app/Http/Controllers/Admin/ImportBatchController.php`
- `app/Http/Controllers/Admin/StudentController.php`
- `app/Http/Controllers/Admin/StudentImportController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Jobs/ImportClassRowsJob.php`
- `app/Jobs/ImportFacultyRowsJob.php`
- `app/Jobs/ImportStudentRowsJob.php`
- `app/Jobs/SendEvaluationReminderRowsJob.php`
- `app/Jobs/SendOfficeEvaluationReminder.php`
- `app/Models/ImportBatch.php`
- `app/Services/AdminDashboardService.php`
- `app/Services/StudentImportAnalyzer.php`
- `app/Support/PositionalClassValues.php`
- `database/migrations/2026_11_01_000003_add_student_type_counts_to_import_batches.php`
- `docs/ACADEMIC_IMPORTS.md`
- `docs/SOURCE_PROVENANCE.md`
- `docs/SOURCE_SYNC.md`
- `docs/STUDENT_IMPORT.md`
- `docs/UNIFIED_STUDENT_IMPORT.md`
- `docs/student-import.md`
- `resources/js/Components/BatchProgressModal.jsx`
- `resources/js/Components/ImportModal.jsx`
- `resources/js/Components/importPolling.js`
- `resources/js/Pages/Admin/Departments/Partials/ManageProgramView.jsx`
- `resources/js/Pages/Admin/OfficeDashboard.jsx`
- `resources/js/Pages/Admin/Partials/EvaluationFeedCard.jsx`
- `resources/js/Pages/Admin/Partials/ManagePeriodsModal.jsx`
- `resources/js/Pages/Admin/Partials/ReviewPeriodCard.jsx`
- `resources/js/Pages/Admin/Reports/Partials/downloadReport.js`
- `resources/js/Pages/Admin/Reports/Partials/reportLogo.js`
- `resources/js/Pages/Auth/Partials/ForgotPasswordModal.jsx`
- `routes/web.php`
- `scripts/verify-authoritative-autoload.php`
- `tests/Feature/ClassImportTest.php`
- `tests/Feature/OfficeReminderTest.php`
- `tests/Feature/RoleScopingTest.php`
- `tests/Feature/StudentEmailIdentityTest.php`
- `tests/Feature/UnifiedStudentImportTest.php`
- `tests/Unit/PositionalClassValuesTest.php`
- `tests/frontend/dashboardRendering.test.mjs`
- `tests/frontend/importPolling.test.mjs`
- `tests/frontend/reportLogo.test.mjs`
