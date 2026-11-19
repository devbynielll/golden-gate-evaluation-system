# GGC client localhost testing

This is the complete post-defense testing branch. It includes the existing system and Phases 1A, 1B, 2, 3, 4 and 5. It is **for synthetic local testing only**. Production release remains NO-GO until the separate acceptance and infrastructure checks are complete.

The website runs only on the computer that starts it. A link beginning with `127.0.0.1` or `localhost` will not let colleagues on other computers access your copy. Do not change the network binding or publish this demonstration without separate authorization.

## 1. Ask your IT representative to install these tools

- Git, with access to the private GitHub repository.
- PHP 8.3 with Composer 2. PHP must include PDO SQLite, mbstring, XML, GD, ZIP and the other extensions reported by Composer. Allow the PHP `proc_open` function for local startup.
- Node.js 22.12 or newer compatible with the lockfile, with npm.
- A modern browser.

Windows, macOS and Linux use the same commands below when `git`, `php`, `composer` and `npm` are on the terminal's PATH. The setup has been verified on macOS/PHP 8.3; Windows/Linux execution still needs client acceptance. Docker, MySQL, a paid hosting account and production passwords are **not needed** for this local demonstration. SQLite testing does not replace the separate production MySQL/MariaDB release checks.

Ask IT for help if these commands cannot run: `git --version`, `php -v`, `composer --version`, `node --version` and `npm --version`.

## 2. Download the correct private branch

Open Terminal, PowerShell or Command Prompt in a folder where you keep local projects. Run:

```sh
git clone --branch feature/ggc-post-defense-revisions --single-branch https://github.com/devbynielll/golden-gate-evaluation-system.git
git -C golden-gate-evaluation-system branch --show-current
cd golden-gate-evaluation-system
```

The branch command must show `feature/ggc-post-defense-revisions`. Sign in to GitHub if prompted. Do not use `main`: the post-defense branch is intentionally awaiting review and depends on the existing unmerged synchronization PR #1. Cloning this branch includes its required history automatically.

You can also download the ZIP while this branch is selected on GitHub, extract it and open a terminal in the extracted application folder. Installing dependencies below is still required.

## 3. Install and prepare — first time only

Run these commands, one at a time, inside the application folder:

```sh
composer install --no-dev --classmap-authoritative --no-scripts --no-interaction --prefer-dist
composer --no-plugins --no-scripts check-platform-reqs --no-dev
npm ci
npm run build
php scripts/verify-authoritative-autoload.php
php scripts/client-local.php setup
```

Wait until each command finishes successfully before continuing. Do **not** copy or create a project `.env`, use a production database, run seeders, or use `artisan migrate:fresh`. The local launcher supplies its own isolated configuration. `--no-scripts` avoids booting any project environment during dependency installation; the local launcher discovers packages in its own runtime.

Setup creates a new disposable SQLite database with demonstration records. It generates fresh local account IDs, a local password and an application key. They are stored only in the ignored `storage/client-local/` folder, never in Git. Setup refuses an existing local database instead of overwriting it.

## 4. Start the system

```sh
php scripts/client-local.php start
```

Leave this terminal open. It starts the localhost website, the normal Laravel queue worker and the local scheduler together. Open:

**http://127.0.0.1:8000/login**

The terminal may print request messages while you use the website. Those are normal. There is no need to start Vite, another server, MySQL, or a separate worker.

## 5. Find your synthetic login accounts

In a second terminal in the same application folder, run:

```sh
php scripts/client-local.php accounts
```

Use the **USER ID** shown in that output, not the display name or email. Each installation has different IDs and a randomly generated password. The main accounts end in:

- `-ADMIN`: Super Admin, with access to all authorized administration/reporting screens.
- `-DEPARTMENT`: Department Admin, retaining global academic administration and Faculty reporting.
- `-OFFICE`: Registrar Office Admin, restricted to the Registrar.
- `-LIBRARY`: Library Office Admin, useful for checking that Registrar records are inaccessible.
- `-STUDENT6`: Student with new Faculty, Office and optional Staff evaluations available.

All these accounts use the local-only password printed by `accounts`. Do not put that output in a public issue or screenshot. These accounts/passwords are newly generated inside your disposable database and are not shared with, created on, or intended for production. Never enter them into a live website.

No real students or production records are included. Email stays in the local array mail driver; no Hostinger connection, live SMTP or SMS service is used. Password-recovery messages and import account emails therefore will not arrive in anyone's inbox. Their real delivery is a separate acceptance check, not a local-preview promise.

## 6. One concise feature checklist

Check desktop and a narrow/mobile browser window. Try Tab, Shift-Tab, Enter, Space and Escape as well as the mouse. Record unexpected results rather than bypassing permissions.

1. **Login:** Show/Hide Password retains the entered text and does not submit the form.
2. **Super Admin:** check the Academic Year dropdown, Review Period edit/extension confirmation, invalid-date errors and locked year/semester for evaluated periods. Extending an End Date must not reopen submissions. Use the explicit open/close action instead.
3. **Super Admin and Registrar Office Admin:** create/edit/archive synthetic staff; inspect separate shared/private Staff Criteria. Close the active period before editing criteria. Used criteria cannot be rewritten/deleted, including Faculty criteria as Super Admin. Evaluated student/faculty records cannot be deleted. Use new unused records for permitted edit/delete tests.
4. **Student:** submit Faculty and overall Office forms normally, then optional Staff forms. Each form has an **optional Comments / Feedback** field at the bottom (0–300 characters, with a visible counter); ratings are still required. Check blank, exactly 300 characters, and over-limit server rejection. Staff completion must not complete an overall Office card or change required progress. Duplicate or closed-period submissions must be rejected.
5. **Reports:** Super/Department can View and download authorized Faculty summaries and individual PDFs. Super/Registrar can do the same for Office and individual Staff reports. Check section labels/ratings, applied filters, complete exports and readable tables/dialogs. Library Admin must not gain Registrar access through changed IDs/URLs.
6. **Notifications:** check the bell, unread/read controls, period labels and report links. Super sees distinct Faculty/Office activity; Department sees Faculty only; Registrar sees its own Office only; Library sees no Registrar group. No student details, individual answers, times, feedback or small response counts should appear.
7. **Privacy and historical records:** follow both report scenarios below; reopen a period while viewing results and verify suppression again. Existing Faculty/Office reporting behavior remains separate from Staff results.
8. **Layout:** check header/bell/navigation, dialogs, keyboard focus, error messages, scrolling and PDF readability at approximately 1280×800 and 390×844. Import only synthetic sample files if testing imports; never upload real college records.

## 7. Reproduce withheld and released Staff reports

The prepared records deliberately include two Review Periods:

- **Previous academic year, 2nd Semester:** closed, with five distinct respondents for the demonstrated Registrar staff member. Its individual report should be released immediately, including section ratings. The answer-weighted overall rating is **3.00** from stored ratings 4, 3 and 2.
- **Current academic year, 1st Semester:** active/open, with four Staff respondents. Staff results must be withheld while it is open, regardless of date.

For the current period:

1. As Super Admin, explicitly close it. Its Staff report should say **“Insufficient responses to protect student anonymity.”** No ratings, feedback or small count should appear.
2. Reopen it. Sign in as `-STUDENT6` and submit the **Demonstration Registrar Staff** form. This creates the fifth respondent through normal validation.
3. Sign in as Super Admin and close it again. Registrar Staff results should now be released, with section ratings and five responses. Other staff without enough responses stay withheld.
4. Reopen it while a Staff viewer is open. Staff results hide again within five seconds/on focus; a new PDF request must also be withheld. Reopening is independent of extending dates.

The local worker normally processes closure notifications within a few seconds. Notifications require five distinct students in their Faculty-period or Office-period group and official closure; they never announce individual submissions or Staff activity. Repeated closures/retries must not duplicate notices or reset read states. Notification panels refresh within 15 seconds/on focus.

Start these scenarios from the initial dataset before adding new criteria. New sections with too few respondents can keep Staff results withheld by design. Reset the disposable demonstration when you need to reproduce the initial scenario exactly.

## 8. Stop, restart and safely reset

**Stop:** press Ctrl-C in the startup terminal. It stops the website, local worker and scheduler. Close the browser tab if desired. On Windows, if the terminal closes unexpectedly, verify no remaining PHP process owns port 8000 before restarting.

**Restart:** run `php scripts/client-local.php start` again. Your synthetic changes remain.

**Reset:** stop the local system first, then run:

```sh
php scripts/client-local.php reset --confirm-disposable
php scripts/client-local.php accounts
php scripts/client-local.php start
```

Reset deliberately deletes only the launcher's marked local runtime, recreates the demonstration database and generates new local credentials. It refuses an unmarked/linked runtime or a running application. It cannot select a Hostinger/MySQL database or accept a database name/URL. Do not manually point it at another folder. Never reset a real database to make tests pass.

## 9. Troubleshooting

- **Command not found:** ask IT to add the installed tool to PATH, then reopen the terminal.
- **Composer reports a missing extension:** enable that extension for the same PHP CLI shown by `php -v`, then rerun installation. SQLite support must be enabled.
- **Node/npm version error:** use a compatible Node version, then rerun `npm ci` and `npm run build`.
- **Port 8000 is in use:** stop your other local server; the launcher refuses to share or silently change the port. Do not stop an unfamiliar service without IT assistance.
- **Local runtime already exists:** use `accounts` and `start`. Setup is intentionally not a reset command.
- **The local system is running:** stop it before resetting or starting a second copy. Do not delete the lock file while processes are running.
- **Missing build manifest or unstyled page:** run `npm ci` and `npm run build`, then restart. The launcher ignores stale Vite hot files and developer preview caches.
- **Login rejected:** use the complete generated USER ID and current password from `accounts`; reset generates new ones. Clear old localhost cookies or use a fresh browser session if necessary.
- **Reports remain withheld:** check the exact selected period, explicit closure, five distinct students and section thresholds. Never edit privacy conditions to force release.
- **Notifications delayed:** keep the startup terminal open, check its errors, then refresh the bell. No separate production worker/cron is used locally.
- **Moved an already configured folder:** ownership checks intentionally refuse the old runtime. Keep the existing folder, or make a fresh clone and run setup there. Ask IT before removing anything.
- **Browser unavailable to the development agent:** client representatives can still run this checklist themselves. No unperformed visual verification is claimed.

## 10. Report an issue

Send the project owner: branch/commit (`git rev-parse HEAD`), operating system, browser, role used, exact steps, expected result and actual result. Attach a screenshot with passwords, generated IDs/private logs and personal information hidden. Use only the synthetic data. Do not publish credentials, `.env`, database files or hosting information.

The branch is for private review. Do not merge to `main`, upload it to Hostinger, configure public access, or treat local SQLite success as production authorization. Production privacy/visual/database/backup/worker acceptance remains a separate release process.
