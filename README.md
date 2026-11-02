# Questify

A gamified productivity platform where everyday tasks become **quests**. Complete
quests to earn XP, level up, unlock achievements, and keep daily streaks alive.

> **Status:** Phase 2 — application shell and dashboard complete. Authentication
> is live; the quest engine arrives in Phase 3, achievements in Phase 4, and
> leaderboard, profile, and settings in Phase 5.
> See [`docs/PROJECT_PLAN.md`](docs/PROJECT_PLAN.md) for the full architecture,
> schema, and roadmap.

---

## Stack

| Layer | Technology |
|---|---|
| Markup & styling | HTML5, Tailwind CSS 3.4 |
| Behaviour | Vanilla JavaScript (ES modules, bundled with esbuild) |
| Motion | GSAP + ScrollTrigger |
| Charts | Chart.js (installed, wired in a later phase) |
| Icons | Lucide |
| Backend | PHP 8.2 (no framework), PDO prepared statements |
| Database | MySQL / MariaDB 10.4+ (InnoDB, utf8mb4) |

## Requirements

- PHP **8.2+** with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`
- MySQL **8** or MariaDB **10.4+**
- Composer 2
- Node.js 20+ and npm
- Apache (XAMPP is fine) or PHP's built-in server

---

## Local setup

1. **Start MySQL** (XAMPP Control Panel → Start MySQL).

2. **Create the database and a dedicated user** — do not use `root`:

   ```sql
   CREATE DATABASE questify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'questify'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
   GRANT ALL PRIVILEGES ON questify.* TO 'questify'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. **Install dependencies**

   ```powershell
   php composer.phar install
   npm install
   ```

   If Composer is installed globally, use `composer install` instead.

4. **Configure the environment**

   ```powershell
   Copy-Item .env.example .env
   ```

   Set `DB_USERNAME`, `DB_PASSWORD`, and `APP_URL` to match your setup.

5. **Run migrations and build assets**

   ```powershell
   php scripts/migrate.php
   npm run build
   ```

6. **Serve the app**

   ```powershell
   php -S localhost:8000 -t public scripts/router.php
   ```

   Or point an Apache vhost document root at `public/`. Never expose the project
   root — `.env` and application code must stay outside the web root.

7. **Verify**

   - `http://localhost:8000/` → landing page
   - `http://localhost:8000/api/health` → `{"success":true,...}`

> On this machine PHP lives at `C:\xampp\php\php.exe`. Add `C:\xampp\php` to
> `PATH` to use the shorter `php` command.

---

## Project layout

```
public/          Web root — front controller, compiled assets, uploads
app/Core/        Router, request/response, config, PDO, sessions, errors
app/Controllers/ Thin HTTP layer (Web + Api)
app/Services/    Business rules and transactions (added per phase)
app/Models/      Explicit SQL per table (added per phase)
app/Support/     Shared helpers and display-only progression calculations
app/Views/       Layouts, partials, components, pages, errors
config/          App, database, session, security, game economy
routes/          web.php + api.php
database/        Migrations and seeders
resources/       Tailwind entry + JS modules (source)
scripts/         Migration runner, dev server router, CLI tools
storage/         Logs, cache, private uploads
docs/            Architecture plan and security notes
```

## Commands

| Command | Purpose |
|---|---|
| `php scripts/migrate.php` | Apply pending migrations |
| `php scripts/migrate.php status` | Show applied/pending migrations |
| `php scripts/migrate.php rollback` | Roll back the last batch |
| `npm run build` | Compile + minify CSS and JS |
| `npm run dev:css` | Tailwind watch mode |
| `npm run dev:js` | esbuild watch mode |
| `php tests/auth_flow.php` | End-to-end auth regression suite (55 checks) |
| `php tests/app_shell.php` | Authenticated shell and dashboard suite (58 checks) |
| `composer test` | Run both regression suites |
| `php -S localhost:8000 -t public scripts/router.php` | Dev server |

## Authentication

| Route | Purpose |
|---|---|
| `GET/POST /register` | Create an account, then sign in automatically |
| `GET/POST /login` | Sign in with email or username; optional 30-day remember-me |
| `POST /logout` | End the session and revoke the remember-me token |
| `GET/POST /forgot-password` | Request a reset link (generic response, no account enumeration) |
| `GET/POST /reset-password` | Set a new password with a single-use, one-hour token |
| `GET /dashboard` | Authenticated account home; guests are redirected to `/login` |

Security properties:

- Passwords are hashed with `password_hash()` (bcrypt) and transparently
  rehashed on sign-in when the algorithm cost changes. Plain-text passwords are
  never stored or logged.
- Every state-changing form carries a session CSRF token; POST requests without
  one are rejected with `419`. The token is rotated after sign-in.
- Login throttling allows 5 failures per identifier + IP and 20 failures per IP
  in a 15-minute window. Successful sign-in clears the identifier's failures.
- Remember-me uses a selector + validator cookie. Only `sha256(validator)` is
  stored; the validator rotates on every use and a tampered selector is revoked.
- Reset tokens are 32 random bytes, stored as SHA-256 hashes, single-use, and
  expire after one hour. A successful reset revokes all sessions and
  remember-me tokens for the account.
- Signed-in users are redirected away from guest-only auth pages, and guest
  requests to protected pages remember the intended URL.
- Validation is server-side (mirrored by HTML constraints); failures re-render
  the form with `422` and field-level messages.

Tune throttling, token lifetimes, and password length limits in
[`config/auth.php`](config/auth.php).

### Local password-reset email

No SMTP transport is configured yet. In the `local` environment, reset links are
appended to `storage/logs/mail.log` instead of being sent. The same file can be
watched while testing:

```powershell
Get-Content storage/logs/mail.log -Wait
```

Outside `local`, `MailService` refuses to silently drop mail; wire a real
transport before deploying.

### Running the test suites

Both suites drive the real HTTP surface, so start MySQL, migrate, build assets,
and serve the app first:

```powershell
php scripts/migrate.php
npm run build
php -S localhost:8000 -t public scripts/router.php

composer test                 # both suites
php tests/auth_flow.php       # auth only (55 checks)
php tests/app_shell.php       # shell only (58 checks)
```

The auth suite creates a throwaway account, exercises registration, CSRF
rejection, login throttling, remember-me rotation and tampering, password reset,
and session expiry. The shell suite checks route protection, the application
layout, navigation, sidebar persistence, placeholder pages, and honest empty
states. Both delete their own data.

## Application shell (Phase 2)

Every authenticated page renders inside `app/Views/layouts/app.php`:

- Collapsible desktop sidebar with server-rendered state (the `questify_sidebar`
  cookie), an off-canvas drawer and bottom tab bar on mobile, and a top bar with
  the account menu.
- Six destinations: Dashboard, My Quests, Achievements, Leaderboard, My Profile,
  and Settings. Pages scheduled for later phases are clearly labelled previews,
  not working features.
- The dashboard reads only values stored on the signed-in account — level, XP,
  streaks, timezone, and timestamps. Quest, achievement, and leaderboard panels
  render labelled empty states because that data does not exist yet; no
  statistics, badges, or rankings are fabricated.
- The “New quest” control opens a non-functional `<dialog>` preview and falls
  back to `/quests` when JavaScript is unavailable.

| Route | Status |
|---|---|
| `/dashboard` | Live — account, level/XP, streak, honest empty states |
| `/quests` | Placeholder — Phase 3 |
| `/achievements` | Placeholder — Phase 4 |
| `/leaderboard` | Placeholder — Phase 5 |
| `/profile` | Placeholder — Phase 5 |
| `/settings` | Placeholder — Phase 5 |

All six routes require authentication. XP awarding, streak transitions,
achievement unlocks, and ranking calculations are deliberately not implemented
here; they belong to Phases 3 and 4.

## Security baseline (active)

- PDO prepared statements with emulation disabled
- Database-backed sessions; IDs hashed at rest, `HttpOnly`, `SameSite=Lax`,
  strict mode, idle + absolute expiry
- CSP, `X-Frame-Options: DENY`, `nosniff`, referrer and permissions policies
- Central error handler that logs details but never leaks production traces
- Upload directory denies script execution
- `.env`, `vendor/`, `node_modules/`, build output and logs are git-ignored
- CSRF synchroniser tokens, login throttling, and route authentication
  middleware (Phase 1)

Authorization policies (roles, ownership) arrive with the quest engine in
Phase 3, and account settings in Phase 5.
