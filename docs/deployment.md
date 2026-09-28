# Deployment preparation

These instructions prepare a staging deployment. No external environment has been provisioned or deployed during the local foundation work.

## Application environment

1. Install PHP 8.4+ with PDO MySQL, mbstring, OpenSSL, fileinfo, intl, curl, XML, and zip extensions. Use a supported MySQL/MariaDB server and Node.js 20+ for asset building.
2. Check out the application, run `composer install --no-dev --optimize-autoloader`, then `npm ci` and `npm run build` in the build environment.
3. Supply a private `.env`: `APP_ENV=staging` (or production), `APP_DEBUG=false`, HTTPS `APP_URL`, correct database credentials, database sessions, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, and a working SMTP mailer. `MAIL_MAILER=log` is only a local development default.
4. On first installation, generate `APP_KEY` using `php artisan key:generate`. Preserve this key securely; never generate a new key during an ordinary release.
5. Use a dedicated database and application account. Grant runtime data access only; give the deployment process separate migration privileges where possible.
6. Run `php artisan migrate --force`, then `php artisan db:seed --class=PermissionsSeeder --force`.
7. Run `php artisan hms:provision` interactively. It creates a hospital, first branch, and administrator; the password is entered through a hidden prompt. Do not load demo users into a real deployment.
8. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
9. Set the web server document root to the application's `public/` directory. Give its service account write access to `storage/` and `bootstrap/cache/` only as needed. Do not publish `.env`, `.runtime`, source files, or `storage/app/private`.
10. Check `/up` for process liveness and `/ready` for database, cache, queue-table, and writable-storage readiness. Run `php artisan hms:release-check --strict`; do not release while any check fails.
11. Sign in as the newly provisioned administrator, create a department and staff member, and verify branch selection and password reset delivery.

The bundled PHP development server is for local development. Production needs a managed web server/PHP runtime. The database queue/cache drivers are configured; introduce supervised queue workers and the scheduler when asynchronous notification jobs are added.

## Release and rollback

- Run the feature tests and asset build before releasing. Run an HTTP smoke check after deploying cached routes/configuration.
- Back up the database and private document storage before schema changes. Keep the matching application key in secure configuration storage.
- Apply additive, compatible migrations where possible. Keep the previous application release available. Do not automatically run destructive migration rollbacks on a database containing operational records.
- Monitor application errors, failed requests, database capacity, and backup completion. Do not put passwords, tokens, or entire clinical payloads in logs.
- Before a hospital pilot, restore the database and private files into an isolated environment and verify sign-in, tenant boundaries, record counts, and an authorized file download. A scheduled backup is not proof that restoration works.

For the current SQLite local deployment, create and rehearse a protected backup with:

```text
php artisan hms:backup-sqlite
php artisan hms:verify-sqlite-backup <filename-reported-by-backup-command>
```

Backups are created without overwrite under `storage/app/backups`, restricted to simple `.sqlite` filenames, and should be copied to encrypted off-host storage by the operator. Verification copies the backup to an isolated temporary restore, runs SQLite integrity checks, verifies required application tables, reports representative record counts, and removes the temporary restore. MySQL/MariaDB deployments must use their managed consistent-snapshot tooling and rehearse restoration into a separate database before release.

Rollback means switching application traffic to the retained previous release while preserving the current database and application key. Do not run automatic down-migrations against operational data. If a migration itself must be reversed, restore the verified pre-release database/private-file snapshot into an isolated environment first, validate it, then follow an approved incident-specific recovery plan.

## Foundation acceptance

Validate with two fictional organizations in an isolated environment: hospital A must not access hospital B by list, direct ID, crafted membership input, or download. Disabled users and memberships must lose access on the next request. Unauthorized roles must be rejected on the server even when they craft requests manually.

The foundation does not yet implement clinical encounters, patient care relationships, financial ledgers, integrations, or a platform administrator UI. Those modules have separate acceptance gates in the pilot specification.
