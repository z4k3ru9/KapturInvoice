# cPanel installation without SSH or Terminal

This procedure is for a cPanel account that provides File Manager, MySQL,
domains, email, cron jobs, and PHP settings, but no SSH or Terminal. Build the
application on a local machine or CI, then upload the prepared release. Do not
run `migrate:fresh`, use the development `DatabaseSeeder`, or place the full
Laravel source tree in a public document root.

Each company is a separate deployment at launch. If both domains share one
cPanel account, keep their databases, storage paths, mail settings, and user
accounts logically separate as required by the release decisions.

## 1. Prepare a release away from cPanel

On a machine with PHP 8.4+, Composer, Node.js, and npm:

```sh
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Prepare a release archive containing the application source, `vendor/`,
`public/build/`, and the normal Laravel directories. Exclude `.env`, local
databases, test data, `node_modules/`, `.git/`, legacy SQL dumps, secrets, and
runtime logs. Keep a copy of the exact commit and archive checksum.

## 2. Create the cPanel resources

In cPanel:

1. Create a MySQL database and database user in **MySQL Databases**. Grant the
   user all privileges on that database. cPanel usually prefixes both names,
   such as `accountuser_kapturinvoice`.
2. Create the company domain or addon domain. Prefer a document root that
   points directly to `kapturinvoice/public`.
3. If the host requires a `public_html/<domain>` document root, upload only
   the contents of Laravel's `public/` folder there. Edit its `index.php` so
   the Composer and bootstrap paths point to the private application folder:

   ```php
   require __DIR__.'/../../kapturinvoice/vendor/autoload.php';
   $app = require_once __DIR__.'/../../kapturinvoice/bootstrap/app.php';
   ```

   Adjust the relative depth to the actual folders shown by File Manager.
4. Select PHP 8.4 or newer in **MultiPHP Manager** or **Select PHP Version**.
   Enable `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`,
   and `gd` or `imagick` if the host allows extensions.
5. Create the production mailboxes in **Email Accounts**, or obtain the SMTP
   credentials for the approved transactional mail provider.

## 3. Upload and configure the application

Use **File Manager** to create a private folder such as
`/home/accountuser/kapturinvoice`. Upload the release zip, extract it there,
and confirm that `vendor/autoload.php`, `bootstrap/app.php`, and
`public/index.php` exist. Do not upload `.env` from development.

Create `.env` by copying `.env.example` in File Manager and edit it with the
following production values:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_KEY=base64:GENERATE_LOCALLY_AND_KEEP_SECRET

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=accountuser_kapturinvoice
DB_USERNAME=accountuser_kapturinvoice
DB_PASSWORD=use-the-cpanel-database-password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=mail.your-domain.example
MAIL_PORT=465
MAIL_USERNAME=app@your-domain.example
MAIL_PASSWORD=use-the-mailbox-password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=app@your-domain.example
MAIL_FROM_NAME="Your Company"
```

Generate `APP_KEY` locally with `php artisan key:generate --show`, or use the
key from the prepared release. Never paste it into a ticket or commit it.
Losing it makes encrypted settings and sessions unreadable. Keep the two
company deployments' `.env` files and databases separate.

## 4. Bootstrap the database over HTTPS

The application includes a temporary, token-gated HTTP bootstrap because this
hosting profile has no shell:

1. In `.env`, set a long random `DEPLOY_MIGRATE_TOKEN`, plus a real owner:

   ```dotenv
   DEPLOY_MIGRATE_TOKEN=generate-a-long-random-token
   DEPLOY_COMPANY_SLUG=company-a  # use company-b on the other deployment
   DEPLOY_ADMIN_NAME=Company Owner
   DEPLOY_ADMIN_EMAIL=owner@your-domain.example
   DEPLOY_ADMIN_PASSWORD=use-a-unique-password
   ```

2. Save `.env`, wait for the host to reload PHP, then visit:

   `https://your-domain.example/deploy/bootstrap?token=YOUR_TOKEN`

   The route runs migrations and the idempotent Currency, Country, and
   Company reference seeders for only the DEPLOY_COMPANY_SLUG tenant. It creates or updates the configured Owner and
   never runs the development `DatabaseSeeder` login.
3. Confirm the response reports success, open `/login`, and sign in with the
   configured owner.
4. Remove `DEPLOY_MIGRATE_TOKEN`, `DEPLOY_ADMIN_PASSWORD`, and any temporary
   deployment-only values from `.env` after bootstrap. The route returns 404
   when the token is unset. If cPanel offers **PHP Options** or a cache reset,
   use it after editing `.env`; otherwise the next request reloads the config.

Do not leave the bootstrap token enabled. It is rate-limited, but it is still a
privileged deployment endpoint.

## 5. Import existing InvoiceNinja data, if applicable

Imports require the legacy database to be reachable from the application. A
no-SSH cPanel account cannot restore a dump from the shell, so use the host's
phpMyAdmin or database-import tool first, or restore the dump through the
host's documented database support channel. Configure the read-only legacy
connections in `.env` and `config/database.php` before importing.

Current sources are InvoiceNinja v5:

- Company A: `legacy_v5_company_a`, non-tax new transactions.
- Company B: `legacy_v5`, Indonesian tax-enabled transactions.

With the deployment token temporarily enabled, visit the allow-listed routes:

```text
https://your-domain.example/deploy/import/company-a?token=YOUR_TOKEN
https://your-domain.example/deploy/import/company-b?token=YOUR_TOKEN
```

Run one company at a time. The importer records a batch, supports resume, and
must be reconciled before any production cutover. Remove the token again after
the import. Do not expose arbitrary artisan commands or connection names in a
URL, and never upload legacy dumps or credentials into the web root.

## 6. Configure domains and SSL

Point each company's DNS to the cPanel account. Add both domains in cPanel and
wait until **AutoSSL** reports a valid certificate for each. The public
homepage and portal resolve the company from the request domain; the internal
admin uses `/tall/{company-slug}/...` and must still enforce company access.

Test both domains in a private browser window. Confirm the homepage, `/login`,
the company dashboard, a generated PDF, and a portal invitation before sending
real mail.

## 7. Add cron jobs in cPanel

In **Cron Jobs**, add the following with the application path and PHP binary
used by the selected PHP version. If the host provides no shell, ask support
for the PHP CLI path; it is often a versioned path under `/opt/cpanel/ea-php*/`.

```cron
* * * * * cd /home/accountuser/kapturinvoice && /path/to/php artisan schedule:run >> /home/accountuser/kapturinvoice/storage/logs/scheduler.log 2>&1
* * * * * cd /home/accountuser/kapturinvoice && /path/to/php artisan queue:work --stop-when-empty --max-time=50 >> /home/accountuser/kapturinvoice/storage/logs/queue.log 2>&1
```

The scheduler drives reminders, quotation expiry, overdue marking, and other
registered commands. The queue cron drains retryable work and exits before the
next minute. Keep the log paths private and rotate them through cPanel or the
host's log controls.

## 8. Verify and operate without a shell

- Confirm `APP_DEBUG=false` by requesting an invalid route; it must not show a
  stack trace or environment values.
- Log in as the configured Owner and verify both company boundaries.
- Send a test invoice email and inspect the queue/mail log.
- Upload a private attachment, download it through an authorized page, and
  confirm another company cannot access it.
- Generate Bahasa and English PDFs and inspect A4 layout.
- Check **Cron Jobs** history and the private scheduler/queue logs after five
  minutes.
- Use cPanel **Backup Wizard** or **JetBackup** to confirm both MySQL and
  `storage/app` are included. Uploaded files are not recoverable from the
  database alone.

## Updating or rolling back

For an update, prepare a new archive locally, back up the database and
`storage/app`, upload it beside the current release, and switch the document
root only after checking `vendor/` and `public/build/`. Use the bootstrap route
only for pending migrations; never use `migrate:fresh` in production. Keep the
previous archive until the new login, PDFs, mail, cron logs, and portal checks
pass. If the update fails, point the document root back to the previous
archive and restore the database/files only when the migration's rollback plan
requires it.

For a production incident, disable the affected domain or use cPanel's
maintenance controls, preserve logs and backups, and contact the deployment
owner. Do not delete financial records, uploaded evidence, or the previous
release while investigating.
