# Golden Gate Colleges Monitoring & Evaluation System

A web-based system for managing faculty, office and individual office staff evaluations at **Golden Gate Colleges (GGC)**. It brings student participation, academic administration, evaluation periods and reporting into one role-based application, with safeguards for anonymous responses and historical records.

> **Client testing starts here:** follow [How to Run the Latest System on Localhost](#how-to-run-the-latest-system-on-localhost). The `feature/ggc-post-defense-revisions` branch contains the complete revised system for private testing with synthetic data. It is **not approved for production deployment**.

## Understand the three environments

- **GitHub repository:** the private source code and documentation. Downloading or updating a branch does not deploy the application.
- **Localhost:** your own test copy, running only on your computer at **http://127.0.0.1:8000/login**. It uses a separate disposable SQLite database and generated demonstration accounts. Sharing this address does not give another computer access.
- **Live Hostinger website:** [ggcevaluation.site](https://ggcevaluation.site), operated separately with its own database, accounts and private configuration. Local testing must never connect to or change that environment.

## Key features

The testing branch preserves the existing application and verified client revisions, including student imports, email recovery, Office dashboard/reminder workflows and positional comma-separated Class imports.

It also includes all six post-defense stages:

- **1A — Login and period creation:** accessible Show/Hide Password control and a rolling Academic Year dropdown that retains historical values.
- **1B — Review Period editing:** safe corrections and date extensions, validation and confirmation, with historical period identity protected. Extending a date does not automatically reopen submissions.
- **2 — Faculty and Office reports:** summary and individual previews, PDF downloads, section ratings from stored answers, and complete exports using applied filters.
- **3 — Office staff management:** create, edit and archive staff, with separate shared and office-specific Staff Criteria.
- **4 — Individual staff evaluations:** optional student submissions, separate progress/results, historical snapshots and authorized Staff reports. Results require at least five distinct students and official period closure; reopening hides them again. Staff feedback is excluded from reports.
- **5 — Administrator notifications:** a notification bell, read/unread management and authorized report links. Notices describe eligible aggregate Faculty/Office activity after official closure, never individual submission events or Staff activity.

**Additional client revision — optional comments:** Faculty, Office and individual Staff evaluation forms share an optional Comments / Feedback field with a 300-character limit and live counter. Blank feedback does not prevent submission; existing reporting and privacy restrictions are unchanged.

Additional history safeguards prevent supported deletion of evaluated student/faculty records and changes to answered criteria. Access remains role-based: Department Admin retains global academic administration, while Office Admin is restricted to the assigned office. Staff evaluations do not change overall Office scores or required completion.

## Technology stack

- **Backend:** PHP / Laravel 12.
- **Frontend:** React, Inertia.js, Tailwind CSS and Vite.
- **Database:** MySQL/MariaDB for the production architecture; dedicated SQLite for portable client testing.
- **Background processing:** Laravel database queues and scheduled jobs.
- **Imports:** PhpSpreadsheet and the existing validated academic import workflows.
- **Reports:** shared report viewers and jsPDF/PDF generation; existing document libraries are retained.
- **Email:** configured application mail delivery in an authorized environment; client testing keeps mail local and sends no real email or SMS.

## How to Run the Latest System on Localhost

**Use this workflow for client testing.** It reuses the verified `scripts/client-local.php` launcher. Do not create a project `.env`, copy hosting configuration, start a separate MySQL database, run seeders or use manual migration/reset commands.

For detailed scenarios, troubleshooting and the desktop/mobile checklist, see [Client Local Testing](docs/CLIENT_LOCAL_TESTING.md).

### 1. Check software requirements

Ask your IT representative to install:

- **Git**, with access to the private repository.
- **PHP 8.3**, including PDO SQLite, mbstring, XML, GD, ZIP and the extensions required by the locked Composer dependencies. PHP must allow `proc_open`.
- **Composer 2**.
- **Node.js 22.12 or newer compatible with the lockfile**, including npm.
- A modern web browser.

**macOS:** use Terminal. **Windows:** use PowerShell, Windows Terminal or Command Prompt. The commands below are the same on both systems; the tools must be available on PATH. Reopen the terminal after installing them.

Check installation with:

```sh
git --version
php -v
composer --version
node --version
npm --version
```

The clean-checkout setup was verified on macOS with PHP 8.3 and Node 22.23.2. Native Windows/Linux execution and client visual acceptance remain to be completed. Docker, MySQL, a hosting account and production credentials are not required for this demonstration.

### 2. Download the correct branch

Open a terminal in the folder where you want to keep the project:

```sh
git clone --branch feature/ggc-post-defense-revisions --single-branch https://github.com/devbynielll/golden-gate-evaluation-system.git
cd golden-gate-evaluation-system
git branch --show-current
```

The last command must print `feature/ggc-post-defense-revisions`. Sign in to GitHub if prompted. If access is denied, ask the repository owner to confirm your private-repository access; never share a GitHub password or token in chat.

Do not use `main` for the latest revised client test. If the destination folder already exists, open your existing test folder rather than cloning over it. Do not overwrite local edits or reset its Git history.

### 3. Install and prepare — first time only

Run each command **one at a time**, inside the project folder. Wait for it to finish successfully before continuing:

```sh
composer install --no-dev --classmap-authoritative --no-scripts --no-interaction --prefer-dist
composer --no-plugins --no-scripts check-platform-reqs --no-dev
npm ci
npm run build
php scripts/verify-authoritative-autoload.php
php scripts/client-local.php setup
```

Setup creates a **new, owned disposable database** with synthetic records and generates local-only credentials and an application key. The private runtime is stored in the ignored `storage/client-local/` folder; it is not part of GitHub. Setup refuses to overwrite an existing runtime.

No developer preview cache, Mac-specific path, production `.env` or college database is needed. Do not substitute `artisan migrate:fresh`, default seeders or a manual database connection for this setup.

### 4. Start and open the website

```sh
php scripts/client-local.php start
```

Keep this terminal open, then visit:

**http://127.0.0.1:8000/login**

The launcher starts the local website, normal queue worker and scheduler together. Do not start Vite, another web server or a separate worker. Terminal request messages are normal.

### 5. Obtain your synthetic login credentials

Open a **second terminal in the same project folder**:

```sh
php scripts/client-local.php accounts
```

Use the complete generated **USER ID** and password shown in that output. Each installation has different account IDs and a randomly generated password; no shared password is published in this README.

The available accounts end in:

- `-ADMIN` — Super Admin.
- `-DEPARTMENT` — Department Admin.
- `-OFFICE` — Registrar Office Admin.
- `-LIBRARY` — Library Office Admin, for cross-office access checks.
- `-STUDENT6` — Student with new evaluations available.

These accounts exist only in your synthetic local database. Do not try them on the live website or include their passwords in screenshots. Recovery and account emails do not reach an inbox in this local setup because mail remains local; live delivery requires separate acceptance.

### 6. Test each role

Use synthetic records only. Sign out before switching accounts.

- **Super Admin:** test Academic Year choices, Review Period editing/extensions and explicit closure/reopening; manage staff and Staff Criteria; preview/download Faculty, Office and Staff reports; check aggregate notifications.
- **Department Admin:** test authorized Faculty reports, section ratings and Faculty notifications. This role retains global academic administration; it does not gain Office or Staff reporting access.
- **Registrar Office Admin:** manage assigned-office staff and criteria, preview/download Office and Staff reports, and check Registrar notifications. Use the Library account to confirm Registrar data is inaccessible across offices.
- **Student:** submit Faculty and overall Office evaluations, then optional Staff evaluations. Staff submissions must not complete an overall Office card or change required completion. Duplicate and closed-period submissions must be rejected.

The initial dataset provides both Staff report scenarios:

1. **Previous academic year, 2nd Semester:** officially closed with five distinct respondents. The demonstrated Registrar Staff report is released, with section ratings and an overall rating of **3.00**.
2. **Current academic year, 1st Semester:** open with four Staff respondents. Close it as Super Admin to confirm the insufficient-response message. Reopen, submit Registrar Staff as `-STUDENT6`, then close again to release the five-respondent aggregate. Reopening must hide it again.

Staff reports expose no free-text feedback, individual answers, submission times or student identities. Notifications require eligible aggregate activity and official closure; they do not announce individual submissions. See the [complete scenarios and desktop/mobile checklist](docs/CLIENT_LOCAL_TESTING.md#7-reproduce-withheld-and-released-staff-reports) before adding criteria that may change the demonstration's response thresholds.

### 7. Stop, restart or reset safely

**Stop:** press **Ctrl-C** in the terminal running the launcher. This stops the website, worker and scheduler. On Windows, an unexpectedly closed terminal may leave a PHP process running; ask IT to check port 8000 before restarting.

**Restart without losing synthetic changes:**

```sh
php scripts/client-local.php start
```

**Reset the disposable demonstration:** stop it first, then run:

```sh
php scripts/client-local.php reset --confirm-disposable
php scripts/client-local.php accounts
php scripts/client-local.php start
```

Reset deletes only the launcher's marked local runtime, recreates the demonstration and generates **new credentials**. It refuses a running application or an unverified/linked runtime. It cannot select a production database. Do not use this command as a general database reset or delete lock files to bypass a refusal.

### 8. Resolve common problems

- **A command is not found:** ask IT to configure PATH, reopen the terminal and repeat the version checks.
- **Composer reports a missing PHP extension:** enable it for the same CLI PHP shown by `php -v`, then rerun installation.
- **Node/npm version error or missing build manifest:** use a compatible Node version, then run `npm ci` and `npm run build`.
- **Port 8000 is in use:** stop your other known local server. Do not stop an unfamiliar service without IT assistance.
- **Local runtime already exists:** use `accounts` and `start`; use the explicitly confirmed reset only if you want to discard your synthetic changes.
- **The local system is running:** stop it before starting a second copy or resetting. Do not remove its lock file.
- **Login is rejected:** obtain the current complete USER ID/password using `accounts`. A reset changes the credentials.
- **Reports remain withheld:** check the selected period, official closure, distinct-student and section thresholds. Never change privacy rules to force a result.
- **Notifications are delayed:** keep the launcher terminal open, inspect its errors and refresh the bell. The local worker processes eligible closure activity.

For more help, see [full troubleshooting and issue reporting](docs/CLIENT_LOCAL_TESTING.md#9-troubleshooting). Report your operating system, browser, role, steps, expected result and actual result. Hide passwords, generated IDs and private information in screenshots or logs.

### Ask ChatGPT for step-by-step help

Copy this prompt and fill in your operating system:

```text
Help me run the Golden Gate Colleges Monitoring & Evaluation System locally.
I am a beginner. My operating system is: [Windows or macOS, including version].
Repository: devbynielll/golden-gate-evaluation-system (private).
Branch: feature/ggc-post-defense-revisions.

Follow README.md and docs/CLIENT_LOCAL_TESTING.md exactly. First check my
operating system and installed Git, PHP, Composer and Node/npm versions.
Give me one clear step at a time and help explain any errors I provide.

Use only the existing scripts/client-local.php setup/start/accounts workflow.
Do not invent a launcher, ask me to create a project .env, connect to Hostinger,
use real college records, request production credentials, enable public access,
merge branches or deploy. Before suggesting a demo reset, explain that it
removes synthetic changes and generates new credentials; use only the provided
reset --confirm-disposable command after the local application is stopped.

If you cannot access the private documentation, ask me to paste the README or
client guide. Never ask me to paste .env files, passwords, tokens, databases,
private runtime files or hosting configuration. Remind me to redact screenshots.
```

## Branch and production status

The approved synchronized source is commit `08e12b83109f0bcf68866254f2acd8bb7c0f0b9f`. The testing branch continues from that source with all post-defense phases and historical-data safeguards; it is not an older-checkpoint rebuild.

- [PR #1 — synchronized application source](https://github.com/devbynielll/golden-gate-evaluation-system/pull/1) proposes the synchronization branch into `main`.
- [Draft PR #2 — post-defense revisions and client testing](https://github.com/devbynielll/golden-gate-evaluation-system/pull/2) is stacked on PR #1's synchronization branch.
- `main` has not received these unmerged revisions. Client testing must use `feature/ggc-post-defense-revisions`.

The live Hostinger deployment is maintained separately through reviewed, backup-first procedures. GitHub branch status does not certify live source/database parity. **Production remains NO-GO** pending client visual/privacy acceptance, target-version MySQL/MariaDB concurrency checks, hosting/worker/scheduler compatibility, private backup restoration and separate release authorization. Local SQLite success does not close those gates.

## Developer-only notes

This section is **not an alternative client setup**. Developers investigating application configuration should review the blank `.env.example`, locked dependencies and [source synchronization notes](docs/SOURCE_SYNC.md) in a separately authorized disposable environment.

No manual `.env`, key-generation, migration or SMTP configuration is required for the client workflow above. Do not mix a developer-managed environment with the launcher's owned SQLite runtime. Any developer database changes, live email test or deployment require separate scope, private backups and review.

The historical baseline includes two registration tests that expect public signup, which is deliberately disabled. Do not enable public registration merely to pass those tests. Reuse the documented verification evidence and run checks appropriate to the change.

## Security, history and release practices

- Keep the repository private. Never commit real credentials, `.env`, application keys, database files/dumps, private documents, student records, backups, logs or generated vendor/build artifacts.
- Keep `storage/client-local/` private and ignored. Its generated accounts and synthetic data are for local testing only.
- Preserve authentication, role/office boundaries, anonymity, stored ratings and historical records. Do not bypass validation or privacy gates for a demonstration.
- Keep production SMTP and administrative credentials private. Use synthetic files and local mail during client tests.
- Review sensitive files, source differences, dependency maps, migration history, assets, workers, backups and rollback readiness before any separately authorized release.
- Keep unrelated applications and hosting configurations separate. Do not deploy this branch directly over the live website.

## Further documentation

- [Complete client testing guide](docs/CLIENT_LOCAL_TESTING.md)
- [Portable setup verification](docs/CLIENT_LOCAL_VERIFICATION.md)
- [Release gates, privacy risks and backup/rollback plan](docs/POST_DEFENSE_RELEASE_GATES.md)
- [Requirement coverage](docs/POST_DEFENSE_REQUIREMENTS.csv)
- [Post-defense implementation manifest](docs/POST_DEFENSE_CHANGED_FILES.csv)
- [Source provenance and attribution](docs/SOURCE_PROVENANCE.md)
- [Verified source synchronization](docs/SOURCE_SYNC.md)
- [Original README — historical reference](docs/ORIGINAL_README.md)
- [Railway preparation — alternative hosting reference, not the live deployment](docs/RAILWAY.md)

Historical setup and alternative hosting notes are references, not instructions to configure client testing or update production.

---

**Project:** Golden Gate Colleges Monitoring & Evaluation System  
**Software development and technical documentation:** Daniel Cresino  
**Repository:** [devbynielll/golden-gate-evaluation-system](https://github.com/devbynielll/golden-gate-evaluation-system)
