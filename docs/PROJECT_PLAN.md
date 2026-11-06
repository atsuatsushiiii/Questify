# QUESTIFY — Architecture & Implementation Plan

> Status: implemented. Phases 0–8 have shipped, and the verification ladder now
> includes 63 PHPUnit unit tests, seven HTTP regression suites, and a headless
> Chrome browser QA pass with axe-core WCAG scans (see the README). This document
> remains the architecture, schema, and decision reference; the roadmap below
> records what shipped.

---

## 0. Environment audit (project start)

| Tool | Status |
|---|---|
| PHP | 8.2.12 — `C:\xampp\php\php.exe` (not on PATH) |
| Database | MariaDB 10.4.32 — `C:\xampp\mysql\bin\mysql.exe` |
| Node.js | v24.12.0 |
| npm | 11.19.0 |
| Git | installed |
| Composer | **not installed** |
| Project folder | `C:\Users\kevin\Desktop\Questify` (empty at project start) |

MariaDB 10.4 is the practical target, not MySQL 8. That means: no `JSON_TABLE`,
no `INSERT ... RETURNING`, limited JSON path indexing. The schema below avoids
all MySQL-8-only syntax so it runs identically on MySQL 8 and MariaDB 10.4.

---

## 1. Folder structure

```
Questify/
├── public/                      # ONLY web-exposed directory (document root)
│   ├── index.php                # front controller (all requests)
│   ├── .htaccess                # rewrite to index.php, security headers
│   ├── assets/
│   │   ├── css/app.css          # compiled Tailwind (generated)
│   │   ├── js/app.js            # compiled JS bundle (generated)
│   │   └── img/                 # static brand images, og-image
│   └── uploads/avatars/         # user avatars (script execution denied)
│
├── app/
│   ├── Core/
│   │   ├── App.php              # bootstrap / container wiring
│   │   ├── Router.php           # route registration + dispatch
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Database.php         # PDO factory (prepared statements only)
│   │   ├── View.php             # layout + partial rendering, escaping helpers
│   │   ├── Session.php          # DB-backed session handler
│   │   ├── Auth.php             # identity, login/logout, RBAC checks
│   │   ├── Csrf.php
│   │   ├── Validator.php
│   │   ├── RateLimiter.php
│   │   ├── Middleware.php       # pipeline
│   │   └── Logger.php
│   ├── Controllers/
│   │   ├── Web/
│   │   │   ├── LandingController.php
│   │   │   ├── AuthController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── QuestController.php
│   │   │   ├── AchievementController.php
│   │   │   ├── LeaderboardController.php
│   │   │   ├── ProfileController.php
│   │   │   └── Admin/…          # AdminDashboard, AdminUser, AdminAchievement, AdminCategory, AdminAudit
│   │   └── Api/
│   │       ├── QuestApiController.php
│   │       ├── StatsApiController.php
│   │       ├── LeaderboardApiController.php
│   │       └── ProfileApiController.php
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   ├── GuestMiddleware.php
│   │   ├── AdminMiddleware.php
│   │   ├── CsrfMiddleware.php
│   │   ├── RateLimitMiddleware.php
│   │   └── SecurityHeadersMiddleware.php
│   ├── Models/                  # one class per table, explicit SQL, no ORM magic
│   │   ├── User.php
│   │   ├── Quest.php
│   │   ├── QuestCompletion.php
│   │   ├── XpTransaction.php
│   │   ├── Achievement.php
│   │   ├── UserAchievement.php
│   │   ├── DailyActivity.php
│   │   ├── Category.php
│   │   ├── SessionRecord.php
│   │   └── AuditLog.php
│   ├── Services/                # all business rules live here, not controllers
│   │   ├── AuthService.php
│   │   ├── RegistrationService.php
│   │   ├── QuestService.php
│   │   ├── QuestCompletionService.php   # the XP transaction boundary
│   │   ├── LevelService.php
│   │   ├── StreakService.php
│   │   ├── AchievementService.php
│   │   ├── LeaderboardService.php
│   │   ├── StatsService.php
│   │   ├── AdminService.php
│   │   └── MailService.php
│   ├── Validation/
│   │   ├── AuthRules.php
│   │   ├── QuestRules.php
│   │   └── ProfileRules.php
│   ├── Support/
│   │   ├── helpers.php          # e(), url(), old(), flash(), asset()
│   │   └── Constants.php        # enums mirrored from DB
│   └── Views/
│       ├── layouts/app.php, auth.php, admin.php, public.php
│       ├── partials/            # sidebar, topbar, flash, modal, pagination, empty-state
│       ├── components/          # quest-card, stat-card, xp-bar, badge-card, rank-row
│       ├── pages/               # dashboard, quests, achievements, leaderboard, profile, settings
│       ├── auth/                # login, register, forgot, reset
│       ├── admin/               # dashboard, users, user-detail, achievements, categories, audit
│       └── errors/              # 403, 404, 419, 429, 500
│
├── config/
│   ├── app.php                  # name, env, url, debug
│   ├── database.php
│   ├── session.php
│   ├── security.php             # cookie flags, CSP, rate limits
│   ├── game.php                 # XP table, level curve, rank titles, streak rules
│   └── mail.php
│
├── routes/
│   ├── web.php
│   └── api.php
│
├── database/
│   ├── migrations/              # 001_create_users.php … ordered
│   ├── seeders/                 # categories, achievements, demo users/quests
│   └── schema.sql               # generated reference snapshot
│
├── resources/
│   ├── css/app.css              # @tailwind base/components/utilities + theme vars
│   └── js/
│       ├── app.js               # entry: boots modules, icons
│       └── modules/
│           ├── api.js           # fetch wrapper (CSRF, JSON envelope, errors)
│           ├── toast.js
│           ├── modal.js
│           ├── quest-board.js   # CRUD + complete, optimistic UI
│           ├── filters.js
│           ├── charts.js        # Chart.js instances, dark theme defaults
│           ├── animations.js    # GSAP page/reveal/counter/XP-bar
│           └── profile.js
│
├── storage/
│   ├── logs/app.log
│   ├── cache/
│   └── uploads/                 # if avatars moved out of webroot
│
├── tests/
│   ├── Unit/                    # LevelService, StreakService, Validator, Csrf
│   └── Feature/                 # auth flow, quest completion, authz (IDOR)
│
├── scripts/
│   ├── migrate.php              # CLI migration runner
│   ├── seed.php
│   └── create-admin.php
│
├── docs/
│   ├── PROJECT_PLAN.md          # this file
│   └── SECURITY.md
│
├── .env.example
├── .env                         # git-ignored
├── .gitignore
├── composer.json
├── package.json
├── tailwind.config.js
├── postcss.config.js
├── phpunit.xml
└── README.md
```

**Why `public/` as document root:** `.env`, app code, and migrations must never
be web-reachable. With XAMPP, use a vhost pointing at `public/`; never point the
browser at the project root.

---

## 2. Database schema

Conventions: InnoDB, `utf8mb4_unicode_ci`, `BIGINT UNSIGNED` PKs, `DATETIME`
stored in UTC, `DATE` columns represent the user's local calendar day, FKs
indexed, no `SELECT *` in application code.

### 2.1 `users`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | |
| username | VARCHAR(30) UNIQUE | `[a-z0-9_]`, immutable-ish |
| email | VARCHAR(190) UNIQUE | lowercase-normalized |
| password_hash | VARCHAR(255) | bcrypt/argon2id |
| display_name | VARCHAR(50) | |
| avatar_path | VARCHAR(255) NULL | |
| bio | VARCHAR(280) NULL | |
| role | ENUM('user','admin') | default `user` |
| status | ENUM('active','suspended') | `suspended` blocks login |
| xp_total | INT UNSIGNED | denormalized cache of ledger |
| level | SMALLINT UNSIGNED | denormalized cache from curve |
| current_streak | SMALLINT UNSIGNED | |
| longest_streak | SMALLINT UNSIGNED | |
| last_streak_date | DATE NULL | user-local day of last completion |
| timezone | VARCHAR(64) | default `UTC` |
| show_on_leaderboard | TINYINT(1) | privacy |
| show_profile | TINYINT(1) | public profile opt-in, default `1` |
| email_verified_at | DATETIME NULL | |
| last_login_at | DATETIME NULL | |
| created_at / updated_at | DATETIME | |

Indexes: `(role)`, `(status)`, `(xp_total DESC)` for all-time leaderboard.

### 2.2 `sessions`
DB-backed PHP sessions via `SessionHandlerInterface`, so admins can revoke and
the app can list active devices.

| Column | Type |
|---|---|
| id | CHAR(64) PK (hashed session id) |
| user_id | BIGINT UNSIGNED NULL FK users ON DELETE CASCADE |
| ip_address | VARCHAR(45) |
| user_agent | VARCHAR(255) |
| payload | MEDIUMTEXT |
| last_activity | INT UNSIGNED |
| expires_at | DATETIME |

### 2.3 `remember_tokens`
`id`, `user_id` FK CASCADE, `selector` CHAR(24) UNIQUE, `validator_hash`
CHAR(64), `expires_at`, `created_at`. Rotated on every use; never stores the raw
validator.

### 2.4 `password_resets`
`id`, `user_id` FK CASCADE, `token_hash` CHAR(64) UNIQUE, `expires_at`,
`used_at` NULL, `created_at`. Single-use, 60-minute expiry. Raw token only in
the email link.

### 2.5 `login_attempts`
`id`, `email` VARCHAR(190), `ip_address` VARCHAR(45), `successful` TINYINT,
`attempted_at`. Indexes `(email, attempted_at)`, `(ip_address, attempted_at)`.
Drives throttling; pruned after 30 days.

### 2.6 `categories`
`id`, `name` VARCHAR(40) UNIQUE, `slug` VARCHAR(50) UNIQUE, `color` CHAR(7),
`icon` VARCHAR(40) (Lucide name), `sort_order`, `is_active` TINYINT.

Seeded: Work, Study, Health, Fitness, Personal, Chores, Creative, Social.

### 2.7 `quests` (the quest definition / template)
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| user_id | FK users CASCADE | owner — every query is scoped by this |
| category_id | FK categories SET NULL NULL | |
| title | VARCHAR(120) | |
| description | TEXT NULL | |
| difficulty | ENUM('easy','medium','hard','epic') | maps to XP server-side |
| xp_reward | SMALLINT UNSIGNED | snapshot of difficulty value at creation |
| priority | ENUM('low','medium','high') | default `medium` |
| recurrence | ENUM('none','daily','weekly','monthly') | shipped as `quests.recurrence` |
| due_date | DATE NULL | |
| status | ENUM('active','completed','archived') | recurring quests stay `active` |
| completed_at | DATETIME NULL | last completion; recurring quests keep it set |
| last_completed_period | VARCHAR(16) NULL | timezone-derived period key for recurring quests |
| created_at / updated_at | DATETIME | |

Indexes: `(user_id, status, due_date)`, `(user_id, recurrence)`,
`(category_id)`.

### 2.8 `quest_completions` (immutable history)
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| quest_id | FK quests SET NULL NULL | survives quest deletion |
| user_id | FK users CASCADE | |
| quest_title | VARCHAR(120) | snapshot |
| difficulty | ENUM(...) | snapshot |
| period_key | VARCHAR(16) | `once`, `YYYY-MM-DD`, `YYYY-Www`, or `YYYY-MM` |
| xp_awarded | SMALLINT UNSIGNED | |
| completed_on | DATE | user-local day; powers streak/heatmap |
| created_at | DATETIME | |

**UNIQUE `(quest_id, period_key)`** — the hard guarantee against double-claiming
XP for the same daily/weekly/monthly quest or a one-off quest. In the shipped
implementation this ledger row lives in `xp_transactions.period_key`; there is
no separate `quest_completions` table.
Indexes: `(user_id, completed_on)`, `(completed_on)`.

### 2.9 `xp_transactions` (append-only ledger — source of truth)
`id`, `user_id` FK CASCADE, `amount` INT (signed, for admin corrections),
`source` ENUM('quest','achievement','streak_bonus','admin_adjustment'),
`source_id` BIGINT NULL (polymorphic reference), `balance_after` INT,
`note` VARCHAR(255) NULL, `created_at`.

Indexes: `(user_id, created_at)`, `(source, source_id)`.
`users.xp_total` is a cache; it is only ever updated inside the same DB
transaction that inserts the ledger row.

### 2.10 `achievements`
`id`, `code` VARCHAR(50) UNIQUE, `name`, `description` VARCHAR(255), `icon`,
`tier` ENUM('bronze','silver','gold','platinum'), `category`
ENUM('quests','streaks','xp','level','special'), `criteria_type`
ENUM('quests_completed','xp_total','streak_days','level_reached'), `threshold`
INT UNSIGNED, `xp_bonus` SMALLINT UNSIGNED, `is_active`, `sort_order`.

Typed criteria (not JSON) keeps evaluation deterministic and indexable.

### 2.11 `user_achievements`
`id`, `user_id` FK CASCADE, `achievement_id` FK CASCADE, `unlocked_at`.
**UNIQUE `(user_id, achievement_id)`** — idempotent unlocking.

### 2.12 `daily_activity`
`user_id` FK CASCADE + `activity_date` DATE → composite PK,
`quests_completed` SMALLINT, `xp_earned` INT. One row per user per day; upserted
on completion. Powers the streak calendar, heatmap and 7/30-day charts without
scanning the ledger.

### 2.13 `audit_logs`
`id`, `actor_user_id` FK SET NULL NULL, `action` VARCHAR(60),
`target_type` VARCHAR(30), `target_id` BIGINT NULL, `meta` JSON NULL,
`ip_address` VARCHAR(45), `created_at`.

### 2.14 `notifications` (Phase 7, optional)
`id`, `user_id`, `type`, `title`, `message`, `read_at` NULL, `created_at`.

### 2.15 `leaderboard_snapshots` (optional, only if live ranking is slow)
`period_type` ENUM('weekly','monthly','all_time'), `period_key`, `user_id`,
`xp`, `rank`. Populated by a CLI job. Not needed at classroom scale.

### Relationships

```
users 1─N quests 1─N quest_completions
users 1─N xp_transactions          (source_id → quests/achievements)
users 1─N daily_activity
users 1─N sessions / remember_tokens / password_resets
users M─N achievements  (via user_achievements)
categories 1─N quests
users 1─N audit_logs (as actor)
```

Cascade rules: deleting a user removes their quests, completions, ledger,
activity and unlocks. Deleting a quest **keeps** completion history
(`quest_id = NULL`, snapshot fields preserved). Deletions are restricted to the
owner via service-layer authorization, not just FK behavior.

### Level curve (single source of truth: `config/game.php`)

```
total XP required to reach level L = 100 × (L − 1)²
level from XP                     = floor(sqrt(xp / 100)) + 1
```

L2 = 100 XP, L3 = 400, L4 = 900, L5 = 1600, L10 = 8100. Easy to invert, no
lookup table, and the next-level progress bar is exact.

XP per difficulty: easy 10, medium 25, hard 50, epic 100. Streak milestones are
awarded as achievement bonuses (3/7/14/30 days), not per-day XP, to keep the
economy from inflating.

---

## 3. System architecture

### Request lifecycle

```
Browser
  → Apache vhost → public/index.php
  → App::boot(): .env, config, error handler, security headers
  → Session start (DB handler, strict mode)
  → Router match (web.php | api.php)
  → Middleware pipeline: security headers → auth/guest/admin → CSRF (mutations) → rate limit
  → Controller (thin: validate input, call service, choose response)
  → Service (business rules, DB transaction)
  → Model (PDO prepared statements)
  → MariaDB
  → View (HTML, escaped)  or  JSON envelope
  → Browser
```

### Rendering strategy (recommended: server-rendered + JSON islands)

PHP renders every page (fast first paint, real URLs, simple auth). JavaScript is
used for *interaction*, not routing:

- Quest create/edit/delete/complete via `fetch` to `/api/...` with CSRF header,
  then update the DOM and re-sync XP/streak widgets from the response.
- Charts and leaderboard polling read JSON endpoints.
- Full page loads still work if JS fails (forms are real POSTs with PRG).

I do **not** recommend a PHP JSON backend + SPA frontend here: it doubles the
validation surface, complicates auth, and fights the PHP hosting model without
adding value for nine modules.

### Layering rules

- Controllers never touch PDO; services never echo HTML.
- Every mutation is wrapped in a DB transaction where money-equivalents (XP) are
  involved.
- Authorization is checked in the service (`quest.user_id === auth.id`) in
  addition to route middleware — this closes IDOR holes.
- All SQL uses prepared statements; the only dynamic SQL allowed is a whitelisted
  `ORDER BY` / sort column map.

### XP completion transaction (the critical path)

```
BEGIN
  SELECT quest FOR UPDATE (scoped by user_id)
  verify state + period_key not already claimed
  INSERT quest_completions        -- UNIQUE constraint is the idempotency guard
  INSERT xp_transactions          -- server-computed amount
  UPDATE users SET xp_total = xp_total + ?, level = ?
  UPSERT daily_activity
  UPDATE streak fields (if first completion today)
  evaluate achievements → INSERT user_achievements (+ bonus ledger rows)
  UPDATE quests.status (one-off only)
COMMIT
```

Failure anywhere rolls the whole thing back; no partial XP, no double XP.

### Frontend architecture

- `app.js` imports modules; esbuild bundles and minifies into `public/assets/js`.
- Tailwind compiled by its CLI into `public/assets/css`, with design tokens in
  `tailwind.config.js` (colors, glow shadows, radii, fonts).
- GSAP: page-enter timeline, staggered card reveals, XP bar tween, count-up
  stats, confetti on level-up/achievement. Guarded by
  `prefers-reduced-motion`.
- Chart.js: XP over 7/30 days, quest completions by category, admin growth.
  Shared dark defaults + gradient fills.
- Lucide: local package, `createIcons()` after DOM updates.

### Security model

| Area | Control |
|---|---|
| Passwords | `password_hash` (bcrypt cost 12; argon2id when available) |
| Sessions | DB-backed, `HttpOnly`, `SameSite=Lax`, `Secure` in prod, strict mode, ID regeneration on login, 30-min idle / 12-h absolute timeout |
| Remember me | selector + hashed validator, rotation, revocation on password change |
| CSRF | per-session token, `hash_equals`, header for fetch, rotate on login |
| SQL | PDO prepared statements, emulation off, exceptions on |
| XSS | `e()` on every output, CSP (no inline script except nonce) |
| Authz | middleware + per-record ownership checks, admin role gate |
| Brute force | login 5 / 15 min per email+IP; register/reset 3 / hour per IP |
| Uploads | MIME sniffed with `finfo`, 2 MB cap, random names, no script execution |
| Enumeration | generic "invalid credentials"; reset always returns the same response |
| Admin | every destructive action written to `audit_logs` |
| Headers | CSP, `X-Frame-Options: DENY`, `Referrer-Policy`, `nosniff`, HSTS in prod |

---

## 4. Pages & navigation

### Public
| Route | Page |
|---|---|
| `/` | Landing: hero, feature grid, live platform stats, CTA |
| `/login` | Login |
| `/register` | Register |
| `/forgot-password` | Request reset link |
| `/reset-password?token=` | Set new password |
| `/u/{username}` | Public profile (respects privacy flag) |
| — | 403 / 404 / 429 / 500 |

### Authenticated app
| Route | Page |
|---|---|
| `/dashboard` | Level ring, XP progress, streak flame, today's quests, quick-add, 7-day XP chart, recent badges |
| `/quests` | Quest board: filters (status, category, difficulty, search), list/board toggle, pagination |
| `/quests/create` | Create quest (also available as modal) |
| `/quests/{id}/edit` | Edit quest |
| `/achievements` | Badge grid, locked/unlocked, progress toward next |
| `/leaderboard` | Tabs: weekly / monthly / all-time, podium + table, "your rank" sticky row |
| `/profile` | Own profile: stats, heatmap, recent completions, badges |
| `/settings` | Display name, avatar, bio, timezone, leaderboard visibility, password change, active sessions |
| `POST /logout` | Logout (CSRF-protected) |

### Admin (`role = admin`)
| Route | Page |
|---|---|
| `/admin` | KPIs, signups chart, quest/XP activity, recent audit entries |
| `/admin/users` | Search, filter, paginate; suspend/activate, promote/demote |
| `/admin/users/{id}` | User detail: stats, quests, ledger, sessions |
| `/admin/achievements` | Achievement CRUD |
| `/admin/categories` | Category CRUD |
| `/admin/audit` | Audit log with filters |

### Chrome
- Desktop: fixed left sidebar (logo, primary nav, admin section, user card) +
  top bar (page title, global quest search, XP pill, streak pill, avatar menu).
- Mobile: top bar + hamburger sheet; bottom tab bar for Dashboard / Quests /
  Achievements / Leaderboard / Profile.
- Active route highlighting, breadcrumbs on detail pages.
- Shared states for every list: loading skeleton, empty state with CTA, error
  state with retry.

---

## 5. Development roadmap

Each phase ends with a working, demoable slice — no dead buttons. All eight
phases below have shipped.

### Phase 0 — Foundation (½–1 day)
Composer/npm setup, PSR-4 autoload, `.env`, config loader, PDO factory, router,
error handler, security headers, DB session handler, Tailwind theme tokens,
layout shell, 404 page, git init, migration runner.
**Done when:** `php -S` serves a styled shell and `/api/health` returns DB OK.

### Phase 1 — Authentication (2 days)
Migrations for users/sessions/tokens/attempts, register, login, logout, remember
me, password reset (mail logged to file in dev), rate limiting, CSRF, validation,
suspended-account handling.
**Done when:** full account lifecycle works, sessions persist in DB, 6th bad
login is throttled, reset token is single-use.

### Phase 2 — App shell & dashboard (1–2 days)
Sidebar/topbar, profile menu, flash/toast system, dashboard queries
(level, XP, streak, today's quests, weekly chart data), empty states.
**Done when:** a seeded user sees real numbers from the DB.

### Phase 3 — Quest CRUD + completion (3 days)
Quest list/filters/search/pagination, create/edit/delete, archive, recurring
period logic, completion transaction, XP ledger, daily activity upsert, optimistic
UI + GSAP feedback.
**Done when:** completing a daily quest twice in one day is impossible at the DB
level, and XP/level update atomically.

### Phase 4 — Progression systems (2–3 days)
Level curve + rank titles, streak calculation with timezone-correct day
boundaries, achievement definitions/seed, evaluation engine, unlock toasts, XP
bonuses.
**Done when:** crossing a threshold unlocks exactly one badge, once, even under
concurrent requests.

### Phase 5 — Leaderboard & profile (2 days)
Weekly/monthly/all-time ranking from the ledger, privacy opt-out, own-rank row,
public profile at `/u/{username}` with a dedicated `show_profile` opt-in,
avatar upload, settings, session management, Chart.js visuals.
**Done when:** rankings match ledger sums and suspended/hidden users are excluded.

### Phase 6 — Admin (2–3 days)
Dashboard KPIs, user management, category CRUD, achievement CRUD, audit logging,
admin authorization tests.
**Done when:** a normal user gets 403 on every `/admin` route and API.

### Phase 7 — Polish (2 days)
GSAP page transitions, staggered reveals, reduced-motion support, responsive QA
at 360/768/1024/1440, focus states, keyboard nav, skeleton loaders, microcopy.

### Phase 8 — Hardening & QA (2 days)
PHPUnit for level/streak/validation/authz, security checklist in
`docs/SECURITY.md`, seed demo data, README, backup/restore notes, production
config (debug off, HTTPS cookies, CSP).

**Total: roughly 15–18 focused working days.**

**Verification:** 63 PHPUnit unit tests (259 assertions), seven HTTP regression
suites (585 checks), and 71 browser checks with 20 axe-core WCAG 2.0/2.1 A+AA
scans. CI runs all of them on every push and pull request.

---

## 6. Dependencies & installation

### Required
| Dependency | Version | Purpose |
|---|---|---|
| PHP | 8.2+ (8.2.12 present) | runtime; `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json` |
| MariaDB | 10.4+ (10.4.32 present) | database |
| Composer | 2.x | PHP autoload + packages |
| Node.js | 20+ (24.12.0 present) | asset build |
| Apache | XAMPP | local host (or PHP built-in server) |

### Composer (production)
- `vlucas/phpdotenv` — `.env` loading
- `phpmailer/phpmailer` — password-reset email (dev mode writes to log)

### Composer (dev)
- `phpunit/phpunit` — unit/feature tests
- `squizlabs/php_codesniffer` (optional) — style consistency

### npm (dev)
- `tailwindcss@3.4`, `postcss`, `autoprefixer` — styling pipeline
- `esbuild` — JS bundling/minification
- `gsap`, `chart.js`, `lucide` — runtime libraries, bundled locally (no CDN)

I recommend Tailwind **3.4.x** over v4: the config-file theme model is stable,
well documented, and matches this build setup. (v4 would work but changes the
config surface for no benefit here.)

### Installation (Windows / XAMPP)

1. **Put PHP on PATH** (one-time, new terminal):
   ```powershell
   [Environment]::SetEnvironmentVariable(
     "Path", $env:Path + ";C:\xampp\php", "User")
   ```
2. **Start Apache + MySQL** from the XAMPP Control Panel.
3. **Create the database and a dedicated user** (do not use `root`):
   ```sql
   CREATE DATABASE questify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'questify'@'localhost' IDENTIFIED BY '<strong-password>';
   GRANT ALL PRIVILEGES ON questify.* TO 'questify'@'localhost';
   ```
4. **Install Composer** (Composer-Setup.exe, or `composer.phar` in the project).
5. **Project setup**:
   ```powershell
   git init
   composer install
   npm install
   Copy-Item .env.example .env     # then fill DB credentials
   php scripts/migrate.php
   php scripts/seed.php
   npm run build
   ```
6. **Run** — option A (simplest, no vhost):
   ```powershell
   php -S localhost:8000 -t public scripts/router.php
   ```
   option B (Apache vhost), in `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
   ```apache
   <VirtualHost *:80>
     ServerName questify.local
     DocumentRoot "C:/Users/kevin/Desktop/Questify/public"
     <Directory "C:/Users/kevin/Desktop/Questify/public">
       AllowOverride All
       Require all granted
     </Directory>
   </VirtualHost>
   ```
   plus `127.0.0.1 questify.local` in `C:\Windows\System32\drivers\etc\hosts`.
7. **Verify**: `http://questify.local` → landing page; `/api/health` → DB OK.

### npm scripts
```json
"dev:css":  "tailwindcss -i resources/css/app.css -o public/assets/css/app.css --watch",
"dev:js":   "esbuild resources/js/app.js --bundle --sourcemap --outfile=public/assets/js/app.js --watch",
"build:css":"tailwindcss -i resources/css/app.css -o public/assets/css/app.css --minify",
"build:js": "esbuild resources/js/app.js --bundle --minify --outfile=public/assets/js/app.js",
"build":    "npm run build:css && npm run build:js",
"test:browser": "node tests/browser/run.js"
```

---

## 7. Trade-offs & risks

1. **No framework.** We hand-roll routing, sessions, CSRF and validation. This is
   appropriate for the assignment and keeps the stack exactly as specified, but
   it means security primitives must be written once, carefully, and covered by
   tests. That is Phase 0/8 work, not an afterthought.
2. **MariaDB 10.4, not MySQL 8.** Avoid `JSON_TABLE`, `INSERT ... RETURNING`,
   functional indexes and MySQL-8 collation defaults. The schema above is
   deliberately portable.
3. **Denormalized XP/level.** Fast reads, but two sources of truth. Mitigation:
   only the completion service writes them, always inside the ledger
   transaction; a `scripts/reconcile-xp.php` check can verify cache vs ledger.
4. **Recurring quests.** A daily quest is one row + many completion rows keyed by
   `period_key`; this is the only model that survives history, streaks and
   timezone boundaries correctly. It costs one extra join on the quest board.
5. **Email in local dev.** No SMTP credentials assumed; reset links are written
   to `storage/logs/mail.log` in dev and switched to PHPMailer in production.
6. **Avatar storage.** Files under `public/uploads/avatars` are web-served;
   `.htaccess` must deny PHP execution there. Alternative (stricter) is serving
   through a controller from `storage/`.

---

## 8. Decisions (resolved)

Every decision below was adopted as proposed and is reflected in the shipped app.

| # | Decision | Resolved default |
|---|---|---|
| 1 | Are quests private per user, or admin-assigned/global? | Private, user-owned; admin sees aggregate stats only |
| 2 | Leaderboard periods | Weekly, monthly, all-time (from XP ledger) |
| 3 | Public profiles | Shipped at `/u/{username}` with a dedicated `show_profile` opt-out; hidden/suspended accounts return `404` |
| 4 | Password reset email | Log file in dev, PHPMailer config in prod |
| 5 | Admin roles | Single `admin` role (no moderator tier) |
| 6 | Level titles | Novice → Adventurer → Champion → Legend → Mythic |
| 7 | Tailwind version | 3.4.x pinned |
