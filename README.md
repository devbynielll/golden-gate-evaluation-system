# Golden Gate Evaluation System

Anonymous faculty and office monitoring/evaluation for Golden Gate Colleges. Laravel 12, PHP, React/Inertia, Tailwind/Vite and MySQL, based on verified Checkpoint 11.

This is a clean source repository with fresh local history. It has not been published or deployed. No real environment, data, default administrator, demo database, dependency directories or historical Git objects are included.

Read [Railway preparation](docs/RAILWAY.md) before configuring web/worker services. Migrations are manual and require separate approval and backup; startup never migrates or seeds. Supply private service variables using the blank .env.example checklist. Do not copy demonstration credentials or use production log mail delivery.

For local development, use a NEW disposable database and local environment. Install from composer.lock and package-lock.json, using PHP >=8.2 (8.3 locally tested), Composer 2, and compatible Node 22. Run npm ci and npm run build; never point tests/setup at an existing client database. Tests remain available with SQLite in-memory configuration. Two known baseline registration tests expect intentionally disabled public registration.

[Source/attribution and deployment-only differences](docs/SOURCE_PROVENANCE.md). [Historical original README](docs/ORIGINAL_README.md) is retained for attribution/context; its old seed/setup commands do not apply to this clean release repository.

Before live deployment: verify Railway runtime/MySQL, persistent uploads and cross-service cleanup, workers, SMTP, backups, security/load limits and client acceptance. The BCIHS Library deployment and original localhost previews are unrelated and must remain untouched.
