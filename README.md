# Questify

[![CI](https://github.com/atsuatsushiiii/Questify/actions/workflows/ci.yml/badge.svg)](https://github.com/atsuatsushiiii/Questify/actions/workflows/ci.yml)

A gamified productivity platform where everyday tasks become **quests**. Complete
quests to earn XP, level up, unlock achievements, and keep daily streaks alive.

> **Status:** Phase 8 — production readiness complete: 63 PHPUnit unit tests,
> seven HTTP regression suites, and a headless Chrome browser QA pass with
> axe-core WCAG scans, alongside a repeatable demo seeder, a written
> security/threat review with a production checklist, and documented
> backup/restore steps. These sit alongside recurring daily/weekly/monthly
> quests, public opt-in profiles, the Phase 7 polish pass, and the Phase 1–6
> auth, shell, quest engine, achievements, streaks, profiles, notifications,
> leaderboards, account settings, and administrator tooling.
> See [`docs/PROJECT_PLAN.md`](docs/PROJECT_PLAN.md) for the full architecture,
> schema, and roadmap.

**Code © 2026 Kevin Cyril Maraño, MIT license.** See [LICENSE](LICENSE) and
[CHANGELOG.md](CHANGELOG.md) for release notes.

---

## Live showcase

**https://atsuatsushiiii.github.io/Questify/**

The project page is a static showcase of the real application: feature tour,
screenshots, stack, security notes, and setup commands. GitHub Pages hosts that
page only — it cannot run PHP or MySQL, so the application itself still runs
locally or on any PHP-capable host (see [Local setup](#local-setup)).

- Source: [`site/`](site/)
- Deployment: [`.github/workflows/pages.yml`](.github/workflows/pages.yml)
- One-time setup: in **Settings → Pages → Build and deployment**, set **Source**
  to **GitHub Actions**. The workflow then publishes `site/` on every push to
  `master` that touches it, and can also be run manually from the Actions tab.

---

## Stack

| Layer | Technology |
|---|---|
| Markup & styling | HTML5, Tailwind CSS 3.4 |
| Behaviour | Vanilla JavaScript (ES modules, bundled with esbuild) |
| Motion | GSAP + ScrollTrigger |
| Charts | Chart.js (admin growth chart) |
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
| `php vendor/bin/phpunit` | Unit suite — progression, validation, streaks, recurrence, mail, route authz (63 tests) |
| `php scripts/seed_demo.php` | Create/reuse a local demo account with realistic quests |
| `php tests/auth_flow.php` | End-to-end auth regression suite (55 checks) |
| `php tests/app_shell.php` | Authenticated shell and Phase 4-6 pages suite (69 checks) |
| `php tests/quest_flow.php` | Quest engine, ownership, XP, recurring cadences, and locking suite (137 checks) |
| `php tests/phase4_flow.php` | Achievements, streaks, profiles, notifications, leaderboards (78 checks) |
| `php tests/phase5_flow.php` | Settings, email verification, sessions, export, deletion (96 checks) |
| `php tests/phase6_flow.php` | Admin authorization, KPIs, moderation, catalogue CRUD, audit (111 checks) |
| `php tests/public_profile_flow.php` | Public profile rendering, privacy, 404s, leaderboard links (39 checks) |
| `npm run test:browser` | Chrome browser QA for auth, quests, profiles, mobile navigation, and WCAG scans |
| `composer test` | Run the unit suite and all seven HTTP suites (648 checks) |
| `php -S localhost:8000 -t public scripts/router.php` | Dev server |

## Authentication

| Route | Purpose |
|---|---|
| `GET/POST /register` | Create an account, then sign in automatically |
| `GET/POST /login` | Sign in with email or username; optional 30-day remember-me |
| `POST /logout` | End the session and revoke the remember-me token |
| `GET/POST /forgot-password` | Request a reset link (generic response, no account enumeration) |
| `GET/POST /reset-password` | Set a new password with a single-use, one-hour token |
| `POST /auth/google` | Start Google sign-in (CSRF-protected) |
| `GET /auth/google/callback` | Google redirect target; validates state, nonce, and ID token |
| `POST /settings/google` | Connect Google to the signed-in account |
| `POST /settings/google/unlink` | Disconnect Google (requires a usable password) |
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
- Google sign-in uses OpenID Connect with the authorization-code grant, PKCE
  (`S256`), a single-use session-bound `state`, and a `nonce`. The ID token is
  validated for issuer, audience, nonce, expiry, and a verified email before the
  account is trusted. Existing accounts are linked only when the verified Google
  email matches the Questify email.

Tune throttling, token lifetimes, and password length limits in
[`config/auth.php`](config/auth.php). Google credentials live in
[`config/oauth.php`](config/oauth.php) and are read from the environment.

### Google sign-in setup

1. Open [Google Cloud Console → Credentials](https://console.cloud.google.com/apis/credentials)
   and create an **OAuth client ID** of type **Web application**.
2. Add an authorized redirect URI:
   `https://your-domain.example/auth/google/callback` (for local work,
   `http://localhost:8000/auth/google/callback`).
3. Copy the client ID and secret into `.env`:

   ```ini
   GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=your-client-secret
   # Optional; defaults to APP_URL/auth/google/callback
   # GOOGLE_REDIRECT_URI=https://your-domain.example/auth/google/callback
   ```

4. Clear the config cache if one is used, then reload `/login`. The
   **Continue with Google** button appears only when both values are present.

Accounts created through Google start without a usable password. Their owners
can set one through the normal **Forgot password** flow; until then, Settings
will not allow Google to be disconnected.

### Local password-reset email

No SMTP transport is needed for local work. In the `local` environment, reset
links are appended to `storage/logs/mail.log` instead of being sent. The same
file can be watched while testing:

```powershell
Get-Content storage/logs/mail.log -Wait
```

Set `MAIL_TRANSPORT=smtp` plus the `MAIL_*` values to send real mail; production
defaults to SMTP and fails loudly if `MAIL_HOST` is missing rather than silently
dropping the message.

### Running the test suites

The unit suite is database-free and runs without a server:

```powershell
php vendor/bin/phpunit --no-coverage   # 63 tests, 259 assertions
```

The remaining suites drive the real HTTP surface, so start MySQL, migrate, build
assets, and serve the app first:

```powershell
php scripts/migrate.php
npm run build
php -S localhost:8000 -t public scripts/router.php

composer test                 # unit suite + all seven HTTP suites
php tests/auth_flow.php       # auth only (55 checks)
php tests/app_shell.php       # shell and Phase 4-6 pages (69 checks)
php tests/quest_flow.php      # quests only (137 checks)
php tests/phase4_flow.php     # achievements/streaks/profile/notifications/leaderboard (78 checks)
php tests/phase5_flow.php     # settings/email/sessions/export/deletion (96 checks)
php tests/phase6_flow.php     # admin authorization/moderation/catalogue/audit (111 checks)
php tests/public_profile_flow.php  # public profiles/privacy/leaderboard links (39 checks)
```

The unit suite pins the level curve and rank ladder, validator edge cases,
timezone-aware streak boundaries, recurring period keys, and the route table’s
authorization ordering:
every `/admin` page and API must carry `AuthMiddleware` then `AdminMiddleware`,
and admin mutations must check the role before CSRF.

The auth suite creates a throwaway account, exercises registration, CSRF
rejection, login throttling, remember-me rotation and tampering, password reset,
and session expiry. The shell suite checks route protection, the application
layout, navigation, sidebar persistence, Phase 4–5 destinations, and honest empty
states. The quest suite covers creation, validation, editing, ownership, rewards
for every difficulty, duplicate and lock-serialized completion, recurring
daily/weekly/monthly cadences, per-period rollover and duplicate rejection,
archive, restore, deletion rules, level/rank changes, dashboard statistics, and
CSRF on every mutation. The Phase 4 suite covers idempotent achievement evaluation,
timezone-aware streaks and duplicate/archive behaviour, profile validation and
avatar storage, notification ownership and deduplication, and leaderboard
periods, visibility, tie-breaking, and pagination. The Phase 5 suite covers
password rotation and credential revocation, verified email changes with hashed
single-use tokens, preference gating, per-device session revocation, export
redaction, and account deletion cascade. The Phase 6 suite proves that guests are
redirected and normal users receive `403` on every `/admin` route and API, then
covers KPI data, user search/filter/pagination, suspend/activate and
promote/demote rules, category CRUD with quest assignment and filtering,
achievement CRUD with unlock-safe deletion, audit filters, and CSRF rejection on
admin mutations. The public profile suite covers guest rendering, completed
stats and achievements, dedicated privacy opt-out, suspended-account 404s,
leaderboard links, and hidden-profile 404s. All seven delete their own data.

### Browser QA

`npm run test:browser` runs a headless Chrome pass against the served app. It
uses Puppeteer for real interactions and axe-core for WCAG 2.0/2.1 A+AA scans.
The suite creates one throwaway account, drives it through the complete journey,
deletes it, and then verifies that its credentials and public profile are gone.

Prerequisites are the same as the HTTP suites, plus Chrome or Chromium:

```powershell
php scripts/migrate.php
npm ci
npm run build
php -S localhost:8000 -t public scripts/router.php
npm run test:browser
```

The runner looks for Chrome in the usual platform locations and on `PATH`. Set
`CHROME_PATH` when it cannot find your browser. Set `QA_BASE_URL` (or `APP_URL`)
to target a different origin, and set `QA_HEADED=1` to watch the run.

Coverage includes:

- guest landing, login, registration, password-reset, 404, and public profile
  pages
- registration, sign-in, sign-out, and account deletion
- quest creation, completion, archive, restore, and the active, completed, and
  archived journal tabs
- profile editing and the owner/public profile views
- the mobile bottom navigation plus the off-canvas drawer's focus, scroll lock,
  inert background, Escape handling, and link navigation
- axe scans of every page reached above at desktop width, plus the dashboard and
  open drawer at mobile width
- notification mark-read behaviour, including the accessible label and hidden
  unread marker

Results are written to `storage/logs/browser-qa/report.json`; a failed step also
leaves a full-page screenshot and a JSON snapshot beside it. CI runs the suite
after the HTTP suites and uploads `storage/logs/` when anything fails. The
intentional 404 and failed-login responses appear as console errors in the
report and are expected.

## Application shell, quest log, and Phase 4–6 (Phases 2–6)

Every authenticated page renders inside `app/Views/layouts/app.php`:

- Collapsible desktop sidebar with server-rendered state (the `questify_sidebar`
  cookie), an off-canvas drawer and bottom tab bar on mobile, and a top bar with
  a notification bell, unread badge, and the account menu.
- Seven live destinations for everyone: Dashboard, My Quests, Achievements,
  Leaderboard, My Profile, Notifications, and Settings. Administrators also see a
  conditional Administration section (Dashboard, Users, Categories,
  Achievements, Audit).
- The dashboard reads real quest counts from the database and builds its recent
  activity feed from the XP ledger. It also shows live achievement progress,
  the real seven-day streak strip, and an all-time leaderboard panel.
- “New quest” links to the real creation form. Every quest action is a normal
  form with a CSRF token, so the journal works without JavaScript; the bundled
  script only adds the live reward preview, character counter, confirmation
  prompts, double-submit feedback, and the notification/profile/settings
  enhancements.

| Route | Status |
|---|---|
| `/dashboard` | Live — level/XP, rank, streak, quest counts, XP activity, trophy and leaderboard panels |
| `/quests` | Live — active, completed, and archived quest tabs, with difficulty and category filters |
| `/quests/create` | Live — create with server-derived XP |
| `/quests/{id}` | Live — owner-only detail and actions |
| `/quests/{id}/edit` | Live — edit active quests |
| `/achievements` | Live — catalogue, progress, and category filters |
| `/leaderboard` | Live — all-time, weekly, and monthly boards with pagination |
| `/profile` | Live — owner-only editor, avatar, streak history, visibility |
| `/notifications` | Live — owner-scoped inbox with read/unread filters |
| `/settings` | Live — password, verified email, preferences, sessions, export, deletion |

Authenticated routes require authentication. Public profiles live at
`GET /u/{username}` and require no session; every settings action is scoped to
the authenticated account.

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

- Owner-only edits validate display name, timezone, profile visibility, and
  leaderboard visibility server-side; failures re-render with `422` and field
  errors.
- Avatars accept JPEG, PNG, and WebP up to 2 MB and 64×64–4096×4096. Files are
  validated by real content, renamed with random server-generated names, and
  stored in `public/uploads/avatars`, which denies script execution. The upload
  path is verified with `realpath()` before writing.
- Public profiles live at `GET /u/{username}` and render for guests. They show
  display name, username, avatar, level/rank, lifetime XP, completed quests,
  current/longest streak, unlocked achievements, and member-since month. Email,
  timezone, quest titles, and account settings are never exposed.
- `show_profile` is a dedicated opt-in toggle, separate from
  `show_on_leaderboard`. Hidden profiles and suspended accounts return `404`,
  exactly like an unknown username, so the route cannot be used for enumeration.
- Leaderboard names, usernames, and avatars link to `/u/{username}` only when
  that account has a public profile. Leaderboard visibility remains independent.

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

## Account settings (Phase 5)

Every action below is owner-only; no form accepts a target user id.

| Route | Purpose |
|---|---|
| `GET /settings` | Account summary, security, email, notifications, sessions, export, danger zone |
| `POST /settings/password` | Change password after verifying the current one |
| `POST /settings/email` | Request a change; sends a confirmation link to the new address |
| `GET/POST /settings/email/confirm` | Review and confirm a single-use email-change token |
| `POST /settings/notifications` | Save the four adventure notification toggles |
| `POST /settings/sessions/{id}/revoke` | Sign out one other owned session |
| `POST /settings/sessions/revoke-others` | Sign out every session except the current one |
| `POST /settings/export` | Download a JSON account export as an attachment |
| `POST /settings/delete` | Permanently delete the account |

- **Password changes** require the current password, reject reuse, rotate the
  current session, revoke every other session and all remember-me tokens, and
  create a security notification. The current device stays signed in.
- **Email changes** require the current password and send a one-hour,
  single-use token to the new address. Only a SHA-256 hash is stored; older
  pending links are superseded, requests are limited to three per hour, and the
  new address is marked verified only after confirmation.
- **Notification preferences** gate achievement, level-up, streak, and
  leaderboard notifications. Profile and security messages are always delivered.
- **Active sessions** are read from the database-backed session store. The
  hashed session id is used only for revocation and is never exposed in the
  export. The current device is labelled and cannot revoke itself; cross-account
  revocation returns `404`.
- **Exports** include the account, quests, XP ledger, achievements, streak days,
  notifications, and session metadata. Password hashes, reset/email tokens,
  session payloads, and remember-me secrets are never included.
- **Deletion** requires the current password plus the exact phrase
  `DELETE MY ACCOUNT`. It is immediate, cascades through owned rows, removes the
  avatar file, signs the account out, and cannot be undone.

Session rows now store their owning `user_id` when the session is written, which
makes the device list and “revoke others” accurate for newly created sessions.

## Administration (Phase 6)

Admin routes are protected by both authentication and a role gate. Guests are
redirected to sign in; any authenticated non-admin gets `403` on every page and
on `/api/admin/stats`, before CSRF or controller code runs. The sidebar and
account menu only render the Administration section for admins.

| Route | Purpose |
|---|---|
| `GET /admin` | KPI cards, 14-day signup/completion chart, recent audit entries |
| `GET /api/admin/stats` | JSON KPI + 7–30-day series for the dashboard chart |
| `GET /admin/users` | Search, role/status filters, pagination |
| `GET /admin/users/{id}` | Stats, recent quests, sessions, and moderation history |
| `POST /admin/users/{id}/suspend` | Suspend an account and revoke its sessions/tokens |
| `POST /admin/users/{id}/activate` | Restore sign-in access |
| `POST /admin/users/{id}/promote` | Grant administrator access |
| `POST /admin/users/{id}/demote` | Remove administrator access |
| `GET/POST /admin/categories` | List and create categories |
| `GET/POST /admin/categories/{id}` | Edit a category (`_method=PUT`) |
| `POST /admin/categories/{id}/delete` | Delete a category without deleting quests |
| `GET/POST /admin/achievements` | List and create achievement definitions |
| `GET/POST /admin/achievements/{id}` | Edit a definition (`_method=PUT`) |
| `POST /admin/achievements/{id}/delete` | Delete an unlocked-free definition |
| `GET /admin/audit` | Append-only privileged action log with filters |

- **Dashboard:** every KPI is a live database aggregate. The Chart.js growth chart
  fetches `/api/admin/stats`; the KPI cards remain accurate without JavaScript.
- **Moderation:** an admin cannot suspend or demote themselves, and the last
  administrator cannot be demoted. Suspension revokes database sessions and
  remember-me tokens immediately. Promotions and demotions take effect on the
  target's next request.
- **Categories:** CRUD validates name, slug, six-digit colour, Lucide icon, sort
  order, and active state. Quests select an active category on create/edit and can
  filter by category slug. Deactivating hides a category from new quests while
  existing quests keep it; deleting a category keeps every quest and clears its
  `category_id` via `ON DELETE SET NULL`.
- **Achievements:** definitions validate against the evaluator's whitelist
  (`quests_completed`, `xp_total`, `level`, `epic_quests`, `longest_streak`), so a
  badge cannot be created that the engine ignores. Definitions with unlocks
  cannot be deleted; an admin deactivates them to preserve trophy history.
- **Audit log:** moderation and catalogue mutations append an entry with actor,
  action, target, summary, IP, and JSON metadata. The model exposes no update or
  delete method, and the page supports action, target, and actor filters.

## Polish and motion (Phase 7)

- Full-page navigations fade the primary content in with GSAP. The DOM already
  holds the final state, so reduced-motion users and browsers without JavaScript
  see every page immediately.
- The admin growth chart renders a shimmering bar placeholder while
  `/api/admin/stats` resolves. A failed request clears the placeholder and shows
  the existing explanatory status line instead.
- Focus rings are visible across the public and authenticated shells, and the
  global reduced-motion rule disables transitions, animation loops, and the
  skeleton shimmer.
- Every layout credits **Code © 2026 Kevin Cyril Maraño, MIT license.**, and the
  same notice heads each first-party source file.

## Production readiness (Phase 8)

### Demo data

`scripts/seed_demo.php` creates a realistic local fixture without touching
existing accounts:

```powershell
php scripts/seed_demo.php
```

- Account: `demo@questify.local` / `Questify123` (display name “Demo Adventurer”,
  `Europe/London`).
- Eight quests across five categories, four of them completed, including one
  daily recurring quest, which yields 185 XP and level 2.
- Safe to re-run: the account is reused and quests are only created when the
  demo account has none. Completion still runs through `QuestService`, so the
  XP ledger, streaks, and achievements are consistent with real usage.
- Intended for local demos and screenshots only. Do not run it against
  production data.

### Tests

- `phpunit.xml` + `tests/Unit` provide 63 DB-free tests / 259 assertions over
  `Progression`, `Validator`, `StreakService`, `Recurrence`, `MailService`, and
  the route authorization table.
- `composer test:unit` runs PHPUnit; `composer test` now runs the unit suite
  before the seven HTTP suites (648 checks total).
- `npm run test:browser` adds a headless Chrome pass with 71 functional checks
  and 20 axe-core WCAG 2.0/2.1 A+AA scans. It lives outside `composer test`
  because it needs the app served by a web server.
- `tests/bootstrap.php` boots configuration and autoloading without starting a
  session or opening a database connection.
- `.github/workflows/ci.yml` runs Composer/npm installs, the asset build, PHP
  lint, migrations, the unit suite, all seven HTTP suites against MariaDB 10.11,
  and the browser QA pass on every push and pull request.

### Email

- Local development uses the `log` transport: reset and email-change links are
  appended to `storage/logs/mail.log`, so the flows are testable without SMTP.
- Production uses PHPMailer over SMTP. Configure `MAIL_HOST`, `MAIL_PORT`,
  `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_ENCRYPTION` (`tls` for STARTTLS on
  587, `ssl` for implicit TLS on 465); see
  [`.env.production.example`](.env.production.example).
- A missing host or unknown transport throws before any message is sent, so a
  misconfigured deployment fails loudly instead of dropping password resets.
- `tests/Unit/MailServiceTest.php` covers both local message formats and the
  misconfiguration guards.

### Security and operations

[`docs/SECURITY.md`](docs/SECURITY.md) records the implemented controls, the
reasoning behind them, and the operational checklist:

- Copy [`.env.production.example`](.env.production.example) to `.env` on the
  host. It sets `APP_ENV=production`, `APP_DEBUG=false`, an HTTPS `APP_URL`, and
  `SESSION_SECURE=true`.
- Point the web server document root at `public/`, serve HTTPS only, and keep
  the dotfile/`storage/` deny rules.
- Create a least-privilege MySQL user, run migrations, and confirm
  `/api/health` reports the database as `ok`.
- Back up the database with `mysqldump --single-transaction` and archive
  `public/uploads/avatars`; restore into an empty database and copy the avatars
  back. Test a restore periodically and keep backups off the host.
- Run `npm ci && npm run build` on deploy — `public/assets` is not committed.

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
  the account row with `SELECT … FOR UPDATE`, transitions the quest, and inserts
  an append-only `xp_transactions` ledger row. A unique index on
  `(quest_id, period_key)` is the final guard against duplicate awards.
- **Recurring quests:** each quest has a `recurrence` of `none`, `daily`,
  `weekly`, or `monthly`. Recurring quests stay `active` and record
  `last_completed_period`; the period key is derived from the account timezone
  (`Y-m-d`, `o-\WW`, or `Y-m`), so a daily quest resets at the user’s local
  midnight. Changing the cadence clears the stored period, and restoring an
  archived recurring quest returns it to `active`.
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
- Current-password checks, session rotation and revocation, hashed single-use
  email-change tokens, notification preference gating, redacted JSON export,
  and password-plus-phrase account deletion (Phase 5)
- Role-gated `/admin` pages and JSON APIs with admin-before-CSRF ordering,
  self-moderation and last-administrator guards, immediate session/token
  revocation on suspension, whitelist-validated achievement definitions,
  unlock-safe deletion, and an append-only audit trail (Phase 6)
- A DB-free unit suite covering progression, validation, streaks, and route
  authorization ordering, plus a production checklist and backup/restore
  procedure in [`docs/SECURITY.md`](docs/SECURITY.md) (Phase 8)

Public profiles are opt-in only; hidden, suspended, and unknown usernames are
indistinguishable.

## License

MIT. **Code © 2026 Kevin Cyril Maraño, MIT license.** See [LICENSE](LICENSE).
