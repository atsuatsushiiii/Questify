# Questify

A gamified productivity platform where everyday tasks become **quests**. Complete
quests to earn XP, level up, unlock achievements, and keep daily streaks alive.

> **Status:** Phase 4 — achievements, daily streaks, owner profiles, in-app
> notifications, and leaderboards are live alongside the Phase 1–3 auth, shell,
> and quest engine. Account settings remain scheduled for Phase 5.
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
app/Support/     Shared helpers, level curve, rank, and formatting utilities
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
| `php tests/app_shell.php` | Authenticated shell and Phase 4 pages suite (65 checks) |
| `php tests/quest_flow.php` | Quest engine, ownership, XP, and locking suite (117 checks) |
| `php tests/phase4_flow.php` | Achievements, streaks, profiles, notifications, leaderboards (78 checks) |
| `composer test` | Run all four regression suites (315 checks) |
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

All suites drive the real HTTP surface, so start MySQL, migrate, build assets,
and serve the app first:

```powershell
php scripts/migrate.php
npm run build
php -S localhost:8000 -t public scripts/router.php

composer test                 # all four suites
php tests/auth_flow.php       # auth only (55 checks)
php tests/app_shell.php       # shell and Phase 4 pages (65 checks)
php tests/quest_flow.php      # quests only (117 checks)
php tests/phase4_flow.php     # achievements/streaks/profile/notifications/leaderboard (78 checks)
```

The auth suite creates a throwaway account, exercises registration, CSRF
rejection, login throttling, remember-me rotation and tampering, password reset,
and session expiry. The shell suite checks route protection, the application
layout, navigation, sidebar persistence, Phase 4 destinations, and honest empty
states. The quest suite covers creation, validation, editing, ownership, rewards
for every difficulty, duplicate and lock-serialized completion, archive,
restore, deletion rules, level/rank changes, dashboard statistics, and CSRF on
every mutation. The Phase 4 suite covers idempotent achievement evaluation,
timezone-aware streaks and duplicate/archive behaviour, profile validation and
avatar storage, notification ownership and deduplication, and leaderboard
periods, visibility, tie-breaking, and pagination. All four delete their own
data.

## Application shell, quest log, and Phase 4 (Phases 2–4)

Every authenticated page renders inside `app/Views/layouts/app.php`:

- Collapsible desktop sidebar with server-rendered state (the `questify_sidebar`
  cookie), an off-canvas drawer and bottom tab bar on mobile, and a top bar with
  a notification bell, unread badge, and the account menu.
- Seven destinations: Dashboard, My Quests, Achievements, Leaderboard, My
  Profile, Notifications, and Settings. Settings is the only remaining preview
  and is clearly labelled; the other six are live.
- The dashboard reads real quest counts from the database and builds its recent
  activity feed from the XP ledger. It also shows live achievement progress,
  the real seven-day streak strip, and an all-time leaderboard panel.
- “New quest” links to the real creation form. Every quest action is a normal
  form with a CSRF token, so the journal works without JavaScript; the bundled
  script only adds the live reward preview, character counter, confirmation
  prompts, double-submit feedback, and the notification/profile enhancements.

| Route | Status |
|---|---|
| `/dashboard` | Live — level/XP, rank, streak, quest counts, XP activity, trophy and leaderboard panels |
| `/quests` | Live — active, completed, and archived quest tabs |
| `/quests/create` | Live — create with server-derived XP |
| `/quests/{id}` | Live — owner-only detail and actions |
| `/quests/{id}/edit` | Live — edit active quests |
| `/achievements` | Live — catalogue, progress, and category filters |
| `/leaderboard` | Live — all-time, weekly, and monthly boards with pagination |
| `/profile` | Live — owner-only editor, avatar, streak history, visibility |
| `/notifications` | Live — owner-scoped inbox with read/unread filters |
| `/settings` | Placeholder — Phase 5 |

All routes require authentication. There is no public profile route; profiles
are owner-only in Phase 4.

## Adventure systems (Phase 4)

### Achievements

- Ten definitions are seeded in `database/seeders/achievements.php` and evaluated
  from typed criteria (`criteria_type` + `threshold`), never from client input.
- `AchievementService::evaluate()` runs inside the quest-completion transaction.
  Inserts are idempotent, so re-evaluating never duplicates an unlock.
- Unlocks create one deduplicated notification per achievement
  (`event_key = achievement:<key>`).

### Daily streaks

- Streak days use the account's timezone, not UTC. One qualifying quest per
  local day records one `streak_activity` row; duplicate completions and
  archive/restore cycles never add another.
- `current_streak` decays to zero after a missed local day, while
  `longest_streak` is preserved. The profile page renders a 12-week heatmap.

### Profiles

- Owner-only edits validate display name, timezone, and visibility server-side;
  failures re-render with `422` and field errors.
- Avatars accept JPEG, PNG, and WebP up to 2 MB and 64×64–4096×4096. Files are
  validated by real content, renamed with random server-generated names, and
  stored in `public/uploads/avatars`, which denies script execution. The upload
  path is verified with `realpath()` before writing.
- Leaderboard visibility is opt-in and shown directly on the profile card.

### Notifications

- In-app events (achievement, level-up, streak milestone, profile, leaderboard
  rank) are stored per user with `UNIQUE(user_id, event_key)` deduplication.
- The inbox is ownership-scoped; another user's notification cannot be read or
  marked read (`404`). JavaScript enhances mark-read/mark-all without a reload,
  and the forms still submit normally without it.

### Leaderboards

- Rankings are computed from the XP ledger, never from a cached counter.
- All-time, weekly, and monthly periods are supported. Weekly and monthly
  boundaries use UTC (Monday 00:00 and the first of the month at 00:00).
- Hidden, suspended, and deleted accounts are excluded. Ties break
  deterministically by lifetime XP and then account age, and the board paginates.
- Viewing the board records the viewer's all-time rank and creates a deduplicated
  rank-change notification; the first view records a baseline instead.

## Quest engine (Phase 3)

Rewards are centralised in [`config/game.php`](config/game.php) and assigned by
the server; a submitted `xp_reward` field is ignored.

| Difficulty | XP |
|---|---:|
| Easy | 10 |
| Medium | 25 |
| Hard | 50 |
| Epic | 100 |

- **Ownership:** every read and write includes `user_id = ?` in the SQL
  predicate. Another user's quest returns `404`, not a hidden control.
- **Single-use XP:** completion runs in one transaction, locks the quest row and
  the account row with `SELECT … FOR UPDATE`, transitions `active → completed`,
  and inserts an append-only `xp_transactions` ledger row. A unique index on
  `quest_id` is the final guard against duplicate awards.
- **Progression:** total XP is the only input to level and rank. The curve is
  `100 × (level − 1)²` (max level 100), and ranks are derived from the level.
- **Archive instead of destructive history loss:** completed quests can be
  archived and restored, but never deleted. Quests that have never paid XP can
  be deleted permanently.
- **Reward changes:** editing an active quest recalculates its reward from the
  new difficulty. Completed quests keep the XP they already paid.

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
- Quest ownership checks on every query, server-derived XP, a transactional
  single-use award, and an append-only XP ledger (Phase 3)
- Idempotent server-evaluated achievements, timezone-aware streaks, owner-only
  profile edits, content-validated avatar uploads, ownership-scoped
  deduplicated notifications, and visibility-aware leaderboards (Phase 4)

Role-based admin policies and account settings remain scheduled for later
phases.
