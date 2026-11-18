> **Post-defense client testing:** On `feature/ggc-post-defense-revisions`, follow [Client Local Testing](docs/CLIENT_LOCAL_TESTING.md) for the complete disposable localhost setup and generated synthetic accounts. This is not a production deployment.

# Golden Gate Colleges | Monitoring & Evaluation System

A web-based platform for managing **faculty and office evaluations** at **Golden Gate Colleges (GGC)**. It supports structured evaluation periods, role-based administration, student participation, and reporting while maintaining the application's established anonymous-evaluation workflow.

**Live site:** [ggcevaluation.site](https://ggcevaluation.site) · **Framework:** Laravel 12 · **Frontend:** React + Inertia.js · **Database:** MySQL/MariaDB

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

**Source synchronization notice:** As of **2 November 2026**, the `main` branch in this repository still contains the **initial clean Checkpoint 11 source snapshot**. The live Hostinger environment includes subsequent application updates that **have not yet been verified as committed to this branch**. This README documents the project and current operational context; it does **not** certify GitHub-to-production source parity.

Recent production work includes expanded student/class import handling, email/account updates, office evaluation reminders, a read-only review-period display for Office Administrators, report presentation improvements, and a production Composer autoload correction. Release evidence and production source must be reconciled before publishing a new source baseline.

**Do not deploy this branch directly over the live website** until source differences, database compatibility, assets, queues, and rollback requirements have been independently reviewed.

## Local development

Use a **separate, disposable development database**. Never connect local setup scripts or automated tests to the client’s production database.

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
