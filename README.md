> **Post-defense client testing:** On `feature/ggc-post-defense-revisions`, follow [Client Local Testing](docs/CLIENT_LOCAL_TESTING.md) for the complete disposable localhost setup and generated synthetic accounts. This is not a production deployment.

# Golden Gate Colleges | Monitoring & Evaluation System

A web-based platform for managing **faculty and office evaluations** at **Golden Gate Colleges (GGC)**. It supports structured evaluation periods, role-based administration, student participation, and reporting while maintaining the application's established anonymous-evaluation workflow.

**Live site:** [ggcevaluation.site](https://ggcevaluation.site) · **Framework:** Laravel 12 · **Frontend:** React + Inertia.js · **Database:** MySQL/MariaDB

## Client testing — Run the latest revised system locally

**For the Golden Gate Colleges client and their IT representative.** This branch contains the complete post-defense revision set for **private, synthetic localhost acceptance testing**. It is not a live production deployment.

### Before you begin

- Obtain access to this **private** GitHub repository.
- Install **Git**, **PHP 8.3** (including PDO SQLite, mbstring, XML, GD and ZIP), **Composer 2**, and compatible **Node.js 22.12+ / npm**. Make sure the commands work in Terminal or PowerShell.
- PHP must permit `proc_open` for the local launcher. The verified setup was tested on macOS; Windows and Linux still require client-side verification.
- You do **not** need the college's real database, passwords, hosting account, Docker or MySQL for this demonstration.

### 1. Download the latest client-testing branch

Open Terminal, PowerShell or Command Prompt and run:

```sh
git clone --branch feature/ggc-post-defense-revisions --single-branch https://github.com/devbynielll/golden-gate-evaluation-system.git
cd golden-gate-evaluation-system
```

Do not clone `main` for this demonstration; it does not yet contain the post-defense revisions.

### 2. Install and prepare the demonstration (first time only)

Run each command in the cloned project folder, in order:

```sh
composer install --no-dev --classmap-authoritative --no-scripts --no-interaction --prefer-dist
composer --no-plugins --no-scripts check-platform-reqs --no-dev
npm ci
npm run build
php scripts/verify-authoritative-autoload.php
php scripts/client-local.php setup
```

The launcher creates an **isolated, disposable SQLite database**, synthetic records, a local-only application key and generated login details. **Do not create or copy a real `.env`; do not run `artisan migrate:fresh`, seeders or setup commands against any existing college database.**

### 3. Start and open the website

```sh
php scripts/client-local.php start
```

Leave that terminal open and visit **http://127.0.0.1:8000/login** in a browser.

Open a **second terminal** in the same project folder and run:

```sh
php scripts/client-local.php accounts
```

The command prints the generated **USER IDs and password** for the synthetic Super Admin, Department Admin, Registrar/Library Office Admins and Student. These are not production accounts. Keep the credentials private.

### 4. Test the revisions and stop safely

Use the synthetic accounts to check login visibility, Review Period editing, office staff and criteria, student evaluations, Faculty/Office/Staff reports, privacy restrictions and notifications. The detailed guide includes **withheld-versus-released Staff Report scenarios** and the full client checklist.

When finished, press **Ctrl+C** in the terminal running the site. You can restart later with `php scripts/client-local.php start`.

**Complete guide:** [Client Localhost Testing — setup, scenarios, troubleshooting and safe reset](docs/CLIENT_LOCAL_TESTING.md).

### Using ChatGPT as a setup assistant

You can paste the following request into ChatGPT and share this README or the `docs/CLIENT_LOCAL_TESTING.md` file:

> Help me run the Golden Gate Colleges Evaluation System from the private `feature/ggc-post-defense-revisions` branch on my own computer. I am a beginner. Follow the repository's `docs/CLIENT_LOCAL_TESTING.md` instructions exactly, give me one safe step at a time, and explain any errors I paste. First check my operating system and installed tools. Use only the disposable localhost setup. Never ask for production passwords, client records or hosting access; do not run database reset/migrations outside the provided local launcher.

**Reminder:** `localhost` works only on the computer running the program. Sharing this repository link or the `127.0.0.1` URL does **not** publish a website for other people. Do not connect the demonstration to Hostinger or upload real student data.

---

## Overview

The system brings academic evaluation activities into a single, organized workflow:

- **Role-based access** for Super Administrators, Department Administrators, Office Administrators, and Students.
- **Faculty and office evaluations** with managed questionnaires, criteria, and evaluation periods.
- **Academic administration** for departments, programs, subjects, faculty, classes, and student assignments.
- **Excel import workflows** for student and class records, with review and validation before saving.
- **Monitoring and reports** for evaluation participation, progress, and results.
- **Email notifications** supporting account recovery and evaluation reminders, subject to configured delivery settings.

Features and permissions depend on the signed-in role and the deployed release. Evaluation confidentiality must be preserved across reporting and administration.

## Technology

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.2+ / Laravel 12 |
| Frontend | React, Inertia.js, Tailwind CSS, Vite |
| Database | MySQL / MariaDB |
| Background work | Laravel database queues and scheduled workers |
| Email | Configurable SMTP |
| Imports and reports | PhpSpreadsheet, PhpWord, and document-generation libraries |

## Deployment and repository status

**Production hosting:** The GGC application is deployed separately on **Hostinger Premium Web Hosting**, using its own domain, database, and private configuration. Production releases are deployed through reviewed, backup-first procedures.

**Branch status:** `main` retains the earlier clean source snapshot. The `feature/ggc-post-defense-revisions` branch includes the latest synchronized application changes and all six post-defense implementation phases for **private client testing**. This branch has not been merged into `main` or approved for production. GitHub-to-production parity and release readiness require separate verification.

The deployed Hostinger system remains separate from this client-testing branch. Production release evidence, database migrations, workers, backups, privacy acceptance and MySQL/MariaDB compatibility must be reconciled before any live update.

**Do not deploy this branch directly over the live website** until source differences, database compatibility, assets, queues, and rollback requirements have been independently reviewed.

## Advanced local development (not the client testing setup)

For client acceptance use the **Client testing** instructions above; do not follow this advanced workflow. Use a **separate, disposable development database**. Never connect local setup scripts or automated tests to the client’s production database.

Requirements:

- PHP 8.2 or newer (PHP 8.3 used during local verification) and Composer 2
- Node.js 22 or another version compatible with the committed dependencies
- A supported local MySQL/MariaDB database, or the isolated test database configuration

Typical application setup, after reviewing the environment template:

```bash
composer install
npm ci
cp .env.example .env
# Configure a LOCAL database, APP_KEY, email, and queue settings.
php artisan key:generate
npm run build
```

Run migrations **only** against an authorized disposable database after reviewing them. Never run seeders, destructive resets, or migration commands against existing client data without explicit approval and a verified backup.

Tests may use isolated SQLite in-memory configuration where supported. The original baseline contains two registration tests that expect public registration, which is intentionally disabled; review test expectations rather than enabling public signup to make them pass.

## Security and release practices

- Keep `.env`, application keys, passwords, tokens, private documents, production databases, logs, backups, and generated vendor dependencies **out of Git**.
- Keep production SMTP credentials and administrative accounts private. Do not commit demonstration or client login credentials.
- Review authentication, permissions, anonymous-response boundaries, and email behavior before release.
- Run tests and frontend builds, inspect the full diff, and check for sensitive files before committing or pushing.
- Use a verified production baseline, private backups, strict source-integrity checks, and a documented rollback plan for live updates.
- Keep the unrelated BCIHS Library application and its hosting configuration completely separate.

## Documentation

- [Source provenance and attribution](docs/SOURCE_PROVENANCE.md)
- [Original README (historical reference)](docs/ORIGINAL_README.md)
- [Railway deployment preparation (alternative hosting reference; not the current live deployment)](docs/RAILWAY.md)

Historical setup instructions are retained for reference and should not be treated as current production deployment commands.

---

**Project:** Golden Gate Colleges Monitoring & Evaluation System  
**Software development and technical documentation:** Daniel Cresino  
**Repository:** [devbynielll/golden-gate-evaluation-system](https://github.com/devbynielll/golden-gate-evaluation-system)
