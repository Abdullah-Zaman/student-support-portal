# Student Support & Grievance Portal

A web portal for the Software Engineering Department at Superior University, Lahore, that digitizes the paper-based student application/grievance process. Students submit and track applications online; an Admin Officer reviews, prioritizes, communicates, and updates status; a Super Admin manages departments, accounts, categories and system settings.

Built with Laravel 13, MySQL 8.4, Blade + Livewire + Alpine.js + Tailwind CSS (Vite).

## Requirements

- PHP 8.4
- MySQL 8.4
- Node.js (for the Vite asset build)
- [Laravel Herd](https://herd.laravel.com/) (recommended for local development) or any environment that can serve a Laravel app and run `php artisan serve`

## Local setup

1. **Clone and install dependencies**

   ```bash
   composer install
   npm install
   ```

2. **Environment file**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Open `.env` and fill in:
   - `APP_URL` — e.g. `http://student-support-portal.test` if served by Herd.
   - `DB_*` — your local MySQL connection (see [Database](#database) below).
   - `MAIL_*` — a Mailtrap inbox for local development (see [Mail](#mail) below).
   - `STUDENT_EMAIL_DOMAIN` / `STUDENT_REGISTRATION_NO_PATTERN` — only if your institution's format differs from the defaults already filled in.

3. **Database**

   Create a MySQL 8.4 database and user matching your `.env` (locally this is typically `student_support_portal` / `portal_user`), then run:

   ```bash
   php artisan migrate
   ```

4. **Seed the required accounts**

   ```bash
   php artisan db:seed
   ```

   This creates the Software Engineering department, the three roles, the default application categories, default settings, one Super Admin and one Admin Officer (credentials from `SEED_SUPER_ADMIN_*` / `SEED_ADMIN_OFFICER_*` in `.env`, defaulting to `superadmin@superior.edu.pk` / `admin.se@superior.edu.pk`, both with password `Password123!`).

   `AdminUserSeeder` refuses to run with these default passwords when `APP_ENV=production` — set real `SEED_*` values before seeding a production database.

5. **Seed demo data (optional, for UI testing)**

   ```bash
   php artisan db:seed --class=DemoSeeder
   ```

   Creates 10 realistic demo students (Pakistani names, English content) with 23 applications across every status, a few message threads (free-text messages, info/document requests, and student responses), and three announcements (one live, one scheduled, one expired). This is **not** run by the default `db:seed` — run it explicitly, and only once per database.

   If you need to change the demo dataset's content later (e.g. after a supervisor review) without creating duplicate rows, edit `database/seeders/DemoSeeder::demoStudents()` and run:

   ```bash
   php artisan demo:refresh
   ```

   This rewrites the existing demo students/applications in place. It never touches `su92-bssem-f22-171@superior.edu.pk` or the seeded Super Admin / Admin Officer accounts, so it's safe to run against a database that also has real-looking manual test data under that protected account.

6. **Build frontend assets**

   ```bash
   npm run build
   ```

   Or `npm run dev` for a watching dev server (see [Running the app](#running-the-app) below for the all-in-one option).

## Running the app

With Laravel Herd, the site is already served — just visit the `APP_URL` from your `.env`.

Without Herd, or to also run the queue worker and Vite dev server together:

```bash
composer run dev
```

This runs three processes concurrently:

- `php artisan serve` — the HTTP server
- `php artisan queue:listen --tries=1 --timeout=0` — the queue worker, since application submissions, status changes, messages, and announcements all send **queued** database notifications and emails
- `npm run dev` — the Vite dev server for hot-reloading assets

If you run these processes separately instead (e.g. in production), make sure a queue worker is always running — nothing will be emailed or appear as a database notification without one.

### Scheduler

This application does not currently register any scheduled (`Schedule::`) tasks — `php artisan schedule:list` reports none. If a scheduled task is added later, point a single cron entry at Laravel's scheduler rather than cron-ing individual commands:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Mail

### Development (Mailtrap)

1. Create a free inbox at [mailtrap.io](https://mailtrap.io).
2. Open its SMTP settings and copy the host, port, username and password into `.env`'s `MAIL_*` variables (see `.env.example`).
3. Every email the app sends (verification, password reset, application notifications, etc.) lands in that inbox — nothing is ever sent to a real address.

### Production (Gmail / Google Workspace)

The spec calls for Gmail SMTP via a Google Workspace App Password, sending as `zeeshan.ahmed@superior.edu.pk` with the from-name "SE Department - Student Support":

1. In the Google Workspace admin for that account, enable 2-Step Verification.
2. Generate an **App Password** (Google Account → Security → App passwords) — never use the account's real login password here.
3. Set in the production `.env`:

   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_SCHEME=tls
   MAIL_USERNAME=zeeshan.ahmed@superior.edu.pk
   MAIL_PASSWORD=the-16-character-app-password
   MAIL_FROM_ADDRESS="zeeshan.ahmed@superior.edu.pk"
   MAIL_FROM_NAME="SE Department - Student Support"
   ```

All mail settings come from `.env` — credentials are never hardcoded in the codebase.

### MAIL_ALWAYS_TO — non-production safety net

Any environment that is **not** `production` (local, staging, a demo server) but is pointed at a real SMTP provider risks emailing real students or staff by mistake. Setting `MAIL_ALWAYS_TO` in `.env` redirects **every** outgoing email to that one address instead, regardless of its real recipient:

```env
MAIL_ALWAYS_TO=your-catch-all-inbox@example.com
```

This has no effect when `APP_ENV=production` — leave it blank there. See `App\Providers\AppServiceProvider::configureMailSafety()`.

## File uploads

Application attachments accept PDF, JPG, JPEG, PNG, DOC and DOCX files up to 5 MB each (max 3 per upload). The production server's `php.ini` must allow at least that:

```ini
upload_max_filesize = 10M
post_max_size = 32M
```

This matches the local Herd `php.ini` the application was built against. Files are stored on a private disk under a randomly generated filename (the original filename is kept only in the database) and served only through an authorized controller — never from a publicly accessible URL.

## Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_KEY`
- [ ] Real `SEED_SUPER_ADMIN_*` / `SEED_ADMIN_OFFICER_*` credentials before running `php artisan db:seed` (the default password is refused in production)
- [ ] `MAIL_ALWAYS_TO` left **blank**
- [ ] Gmail/Google Workspace SMTP configured with a real App Password (see [Mail](#mail))
- [ ] `upload_max_filesize` / `post_max_size` raised per [File uploads](#file-uploads)
- [ ] A persistent queue worker running (e.g. `php artisan queue:work` under Supervisor) — `queue:listen` is for local development only
- [ ] `php artisan migrate --force` run against the production database
- [ ] HTTPS enforced at the web server / load balancer
- [ ] Database backups configured

## Testing

```bash
composer ci:check
```

Runs Pint (code style), PHPStan/Larastan (static analysis), and the full Pest test suite, in that order. Run just the tests with:

```bash
php artisan test --compact
```
