# Railway deployment preparation — not deployed

This application uses Laravel 12, PHP, React/Inertia/Vite and MySQL. Do not substitute PostgreSQL. No credentials, application key, users, databases or vendor/node_modules are included. Create the GitHub repository as PRIVATE only after publication authorization; no remote is configured here.

## Services and build

Prepare separate web and worker services from the same reviewed revision, plus a private MySQL service. Retain dependency lockfiles; use Composer 2 and a supported PHP satisfying composer.lock. PHP 8.3 was locally tested. The existing Vite dependency requires Node ^20.19 or >=22.12; Node 22 was locally used. Composer and frontend component checks are local evidence, not a successful Railway image build.

Select Railpack. It supports PHP/Laravel and serves Laravel's public directory. This repository deliberately overrides its default migration-running startup using root start-container.sh. Set RAILPACK_SKIP_MIGRATIONS=true as additional defense. No startup/build/pre-deploy script in this repository migrates or seeds a database. Do not configure a pre-deploy migration until separately authorized and backed up. [Railpack PHP documentation](https://railpack.com/languages/php/), [upstream Laravel startup](https://github.com/railwayapp/railpack/blob/main/core/providers/php/start-container.laravel.sh).

Use the Railpack detected locked dependency installation, with the custom build command `npm run build` after dependencies are installed. Confirm logs show production Composer dependencies and Vite manifest creation; never composer update. No local PHP development server belongs in production. Set the following service commands:

| Setting | Web | Worker |
|---|---|---|
| Start | `sh start-container.sh` | `sh railway/run-worker.sh` |
| Public networking | Only the web service, after authorization | Disabled |
| Health check | `/up` | No HTTP healthcheck |
| Restart | On failure / reviewed policy | Always, including successful max-time exit |
| Scaling initially | One replica, with persistent uploads | One worker until measured |

The Caddy configuration exposes only /app/public, uses Railway's PORT, disables Caddy-managed TLS because Railway terminates TLS, and denies private dot paths. Laravel-generated production URLs are forced to HTTPS; local/testing behavior is unchanged. Confirm edge HTTPS, APP_URL, cookies, host/proxy behavior and redirects on the actual target before acceptance. Do not broadly trust forwarded host/IP headers without reviewing the actual proxy path.

Railway's legacy config-as-code page currently marks railway.json/toml deprecated for new configuration. This preparation therefore provides explicit service commands rather than adding a soon-obsolete project schema. Recheck platform guidance when publication/deployment is authorized. [Railway configuration reference](https://docs.railway.com/config-as-code/reference).

## Private environment and shared state

.env.example is a blank production checklist, not usable credentials. Enter real values in private service variables, not Git. Give web/worker the SAME APP_KEY, MySQL database/user, database queue/cache/session configuration and cache prefix. Do not regenerate an existing production key without a migration plan. Configure APP_ENV=production, APP_DEBUG=false, HTTPS APP_URL, secure cookies and real SMTP/provider credentials. LOG_CHANNEL=stderr avoids local password mail logs; do not use MAIL_MAILER=log in production. The runtime guard rejects log/array transports. Never set real secrets in VITE_* variables, which may enter public bundles.

Map DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD/DB_DATABASE to the authorized MySQL service's MYSQLHOST/MYSQLPORT/MYSQLUSER/MYSQLPASSWORD/MYSQLDATABASE through private variable references; service names depend on the actual project. Prefer a restricted application account, keep DB networking private and do not publish a public DB proxy merely for convenience. Check actual server version, charset, generated indexes/FKs and least-privilege runtime versus migration grants. [Railway MySQL documentation](https://docs.railway.com/databases/mysql).

Set QUEUE_CONNECTION=database, CACHE_STORE=database, SESSION_DRIVER=database and DB_QUEUE_RETRY_AFTER=120. The worker processes default AND imports, with a 60-second job timeout and one attempt before reviewed manual retry, avoiding blind repeated account/email side effects. Test real import durations and adjust timeout/retry_after together. Configure adequate graceful termination, verify worker exits/restarts and failed_jobs alerts, and drain workers during migrations. A running web server alone will not process queued imports or credentials. Test actual SMTP and spam/delivery limits; generated account credentials require restricted database/job/log access.

## Upload persistence — unresolved release gate

Web-local uploads/photos must survive container replacement. Mount a persistent web-service volume at /app/storage/app and check permissions plus public/storage symlink. Never mount over /app or public/build. Keep one web replica for the current local-disk architecture; confirm actual plan restrictions. [Railway volume documentation](https://docs.railway.com/volumes/reference).

The current import jobs serialize parsed rows, so worker execution does not require rereading the original uploaded workbook. However, batch completion callbacks attempt deletion of the web-local source upload. Separate service filesystems do not supply a shared upload disk: worker cleanup cannot be assumed to remove the web copy. Failed/abandoned uploads also need private retention controls. A shared-storage/cleanup plan must be separately verified before live release. Do not switch FILESYSTEM_DISK to S3 blindly: existing import parsing uses Storage::path and expects a local file. No storage redesign or new dependency was introduced in this preparation.

## Database and go-live gates

Provision no default/demo accounts automatically; unsafe starter seeders were omitted. Initial Super Admin creation requires a separately authorized secure owner procedure. Do not run the historical README's seeding commands. The normal migration workflow is preserved, including all original and new migrations. Before a real upgrade obtain a verified consistent database/files backup, independently restore it, validate office/admin mappings and rehearse on a private clone. Duplicate Department Admins must be resolved explicitly; Office Criteria rollback is guarded. Never migrate:fresh, seed client data or blindly roll back migrations.

No Railway connection or image deployment has been performed. Docker/Railpack runtime availability, actual MySQL version, persistent upload cleanup, SMTP, worker reliability, target load/security testing, client data backup and client acceptance remain prerequisites. MySQL/SQLite application verification from Checkpoint 11 is reused; it is not certification of a new cloud service. Preserve post-cutover submissions before any database restore. Obtain separate explicit deployment authorization.
