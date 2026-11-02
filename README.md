# Questify

A gamified productivity platform where everyday tasks become **quests**. Complete
quests to earn XP, level up, unlock achievements, and keep daily streaks alive.

> **Status:** Phase 0 — foundation complete. Authentication is the next phase.
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
| `php -S localhost:8000 -t public scripts/router.php` | Dev server |

## Security baseline (already active)

- PDO prepared statements with emulation disabled
- Database-backed sessions; IDs hashed at rest, `HttpOnly`, `SameSite=Lax`,
  strict mode, idle + absolute expiry
- CSP, `X-Frame-Options: DENY`, `nosniff`, referrer and permissions policies
- Central error handler that logs details but never leaks production traces
- Upload directory denies script execution
- `.env`, `vendor/`, `node_modules/`, build output and logs are git-ignored

Authentication, CSRF, rate limiting and authorization middleware arrive in
Phase 1–2.
