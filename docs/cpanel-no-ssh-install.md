# cPanel installation without SSH

Build locally or in CI, then upload a prepared release through File Manager. The application requires PHP 8.3 or newer; CI also supports PHP 8.4 and 8.5. Use `composer install --no-dev --optimize-autoloader`, `npm ci`, and `npm run build`. Exclude `.env`, local databases, `node_modules`, `.git`, SQL dumps, secrets, and logs. Keep each company's database, storage, mail settings, and owner accounts separate.

Create the MySQL database/user, point the domain document root to `public` (or upload only `public/` and fix `index.php` paths), enable `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, and `gd`/`imagick`, then create a production `.env` with `APP_DEBUG=false`, MySQL, database session/cache/queue, and SMTP settings. Generate `APP_KEY` locally; never upload development `.env` or expose credentials.

For no-shell bootstrap, set a long temporary `DEPLOY_MIGRATE_TOKEN`, `DEPLOY_COMPANY_SLUG`, and real owner fields, then visit `/deploy/bootstrap?token=...`. It runs migrations and idempotent reference seeders for one tenant and never runs the development seeder. Confirm login, then remove the token and temporary password; the route returns 404 when unset.

Restore legacy databases with phpMyAdmin or host support, configure read-only `legacy_v5_company_a` / `legacy_v5`, and run one allow-listed import route at a time: `/deploy/import/company-a?token=...` or `/deploy/import/company-b?token=...`. Reconcile before cutover and remove the token. Never expose arbitrary artisan commands, connection names, dumps, or credentials in the web root.

Configure DNS/AutoSSL, test homepage, login, dashboard, PDFs, portal, company boundaries, mail, private attachments, and backups. Add cPanel cron for `schedule:run` and a bounded `queue:work --stop-when-empty --max-time=50` using the host's PHP CLI path. For updates, back up database and `storage/app`, upload beside the release, switch document root after checking `vendor` and `public/build`, and retain the previous release for rollback. Never run `migrate:fresh` in production.
