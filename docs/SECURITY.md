# Security notes

Questify is a server-rendered PHP application with a MySQL database. This
document records the controls that are implemented, the checks that prove them,
and the steps required to run it safely in production.

**Code © 2026 Kevin Cyril Maraño, MIT license.**

## Reporting a vulnerability

This is a learning/demo project, not a hosted service. If you find a problem,
open a private issue or contact the maintainer directly instead of publishing a
working exploit. Include the route, the request, and the observed behaviour.

## Implemented controls

### Authentication

- Passwords are hashed with PHP's `PASSWORD_DEFAULT` (bcrypt on PHP 8.2) and
  verified with `password_verify`. Plaintext is never stored or logged.
- Minimum length is 10 characters (maximum 72, the bcrypt input limit) and a
  password must contain at least one letter and one number.
- Login attempts are throttled per account (5) and per IP (20) inside a
  15-minute decay window. The 6th bad attempt is rejected before the hash check.
- Password reset and email-change tokens are 32 random bytes, stored as SHA-256
  hashes, single-use, and expire after one hour. Requests are limited to three
  per hour.
- A successful password change or reset revokes every session and remember-me
  token for the account.
- Suspended accounts are rejected at sign-in and any existing session is
  revoked, so the target is signed out on its next request. Timed suspensions
  store `users.suspended_until`; an expired suspension is lifted lazily on the
  next sign-in, authenticated request, or public-profile lookup, so the account
  returns to `active` without an administrator action. Permanent suspensions
  keep `suspended_until` null.
- Google sign-in uses OpenID Connect with the authorization-code grant, PKCE
  (`S256`), a single-use session-bound `state`, and a `nonce`. The ID token is
  received directly from Google's token endpoint over TLS; issuer, audience,
  nonce, expiry, and a verified email are still validated before the account is
  trusted.
- A verified Google email links to an existing account only when it exactly
  matches the Questify email. A second Google subject cannot take over an email
  that is already linked.
- Google-created accounts have no usable password until the owner sets one via
  the reset flow. Disconnecting Google is refused while `password_set_at` is
  null, so the account cannot be locked out.

### Sessions

- Sessions live in the database (`sessions` table), not in files, and store the
  owning `user_id` so “this device” and “revoke others” are accurate.
- Cookies are `HttpOnly` and `SameSite=Lax`; `Secure` is controlled by
  `SESSION_SECURE` and must be `true` in production.
- Session ID regeneration happens on sign-in and privilege changes. Idle
  sessions expire after 30 minutes and absolute sessions after 12 hours.
- Remember-me tokens are hashed at rest and rotate on use.

### Authorization

- `AuthMiddleware` requires a signed-in account for every non-public route.
- `AdminMiddleware` runs after authentication and returns `403` for any
  non-administrator, before CSRF validation or controller code. Guests get the
  normal sign-in redirect (or `401` JSON).
- Every quest, notification, profile and account query is scoped by
  `user_id = ?`; another user's row returns `404`, not a hidden control. The
  admin quest directory is the deliberate exception: it is reachable only
  through `AdminMiddleware`, and its archive, restore, and delete mutations are
  CSRF-protected and audited.
- Reports are scoped to signed-in accounts and resolve only reachable targets: an
  active `show_profile = 1` profile or an existing quest. Self-reports and
  duplicates are rejected before insert (`uq_reports_reporter_target` is the
  backstop), submissions are capped at 10 per account per rolling hour
  (`auth.report_per_hour`), the reporter's identity is visible only to
  administrators, and resolve/dismiss is an audited, CSRF-protected admin
  mutation that only applies while the report is open. `GET /reports` is scoped
  to the signed-in reporter, and report-closure notifications never include the
  administrator's private resolution note.
- Warning and suspension from a report are separate audited admin mutations that
  only apply to an open **profile** report and never change the report's own
  status. The reported account receives an in-app notification that names the
  reason and, for a suspension, its expiry, but never the reporter's identity or
  the private resolution note. Suspending from a report uses the same
  session/token revocation and `suspended_until` rules as the user detail page.
- Admin mutations enforce self-protection and last-administrator rules
  server-side; the disabled buttons are not the security boundary.
- `tests/Unit/RouteAuthorizationTest.php` fails if an `/admin` route is added
  without `AuthMiddleware` + `AdminMiddleware`, or if CSRF is checked before the
  role gate.

### Public profiles

- `GET /u/{username}` is public by design, but resolves only when
  `users.status = 'active'` **and** `users.show_profile = 1`.
- Hidden profiles, suspended accounts, and unknown usernames all return the same
  `404` response, so the route cannot be used to enumerate accounts.
- The public view renders only display name, username, avatar, level/rank,
  lifetime XP, completed-quest count, current/longest streak, unlocked
  achievements, and the member-since month. Email, timezone, quest titles,
  sessions, login metadata, and settings are never rendered; timezone is selected
  only to compute the current streak correctly.
- `show_profile` is separate from `show_on_leaderboard`; leaderboard links to a
  profile render only when `show_profile = 1`.

### CSRF

- All state-changing forms embed a synchronizer token (`csrf_field()`), and
  `VerifyCsrfToken` compares it with `hash_equals`.
- Requests that fail CSRF return `419`; JSON clients receive a JSON error.
- Admin routes check the role before the CSRF token, so a normal user learns
  nothing about token state.

### Input, output and uploads

- All SQL goes through PDO prepared statements. No user value is interpolated
  into SQL.
- Server-side validation is the source of truth; HTML constraints are only a
  convenience. Unknown payload fields are dropped.
- All view output is escaped through `e()`; JSON is produced by `Response::json`.
- Avatars are validated by detected MIME type and image dimensions (max 2 MB,
  64 px minimum, 4096 px maximum), renamed server-side, and written only to
  `public/uploads/avatars`. That directory ships an `.htaccess` that denies
  script execution, and the filename pattern is re-checked before rendering.
- XP rewards are derived on the server from the difficulty; a submitted
  `xp_reward` is ignored.
- Recurring quests award at most once per period. The period key is computed
  server-side from the account timezone, and the unique
  `(quest_id, period_key)` index rejects any same-period replay at the database
  layer even if the quest-row check is bypassed.

### Transport and headers

`config/security.php` sends these on every response:

- `Content-Security-Policy` with `script-src 'self'`, `frame-ancestors 'none'`,
  `object-src 'none'`, `base-uri 'self'`, and
  `form-action 'self' https://accounts.google.com`. The Google host is required
  because `form-action` also governs the redirect from the sign-in POST to
  Google's authorization endpoint.
- `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` disabling camera, microphone and geolocation
- `Cross-Origin-Opener-Policy: same-origin`

The stylesheet policy allows `'unsafe-inline'` because server-rendered
components use a small number of inline style attributes (category colours,
progress widths). Scripts are never inline and Chart.js is bundled locally.

### Email

- Password-reset and email-change links are the only outbound messages.
- Local development uses a `log` transport and appends to
  `storage/logs/mail.log`; production uses PHPMailer over SMTP with STARTTLS
  (`tls`, port 587) or implicit TLS (`ssl`, port 465).
- SMTP credentials come from `.env` and are never logged. A missing
  `MAIL_HOST`, unknown transport, or unknown encryption mode throws before any
  connection is attempted, so password resets cannot be silently dropped.
- `tests/Unit/MailServiceTest.php` asserts both message formats and the
  misconfiguration guards.

### Audit and logging

- Privileged mutations append to `audit_logs` with actor, action, target,
  summary, IP address and JSON metadata. The model exposes no update or delete
  method, so entries are append-only.
- The application logger writes to `storage/logs` and is gated by
  `APP_DEBUG`; production errors never include stack traces in the response.
- Secrets (`.env`, database passwords, tokens) are never rendered or committed.

## Production checklist

1. **Environment**
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=https://your-host` (HTTPS only)
   - `SESSION_SECURE=true`
   - Real database credentials in `.env` (copy `.env.production.example`).
   - `MAIL_TRANSPORT=smtp` plus `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
     `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, and a `MAIL_FROM_ADDRESS` on a verified
     sender domain.
2. **Database**
   - Use a dedicated MySQL user with only `SELECT, INSERT, UPDATE, DELETE`
     (plus DDL during migrations) on the `questify` database.
   - Run `php scripts/migrate.php migrate` and confirm `status` is clean.
3. **Web server**
   - Point the document root at `public/`, never the repository root.
   - Serve only over HTTPS; add HSTS at the proxy once HTTPS is confirmed.
   - Keep the `.htaccess` deny rules (or the nginx equivalents) for dotfiles and
     `storage/`.
4. **Filesystem**
   - `storage/` must be writable by PHP and readable by nobody else.
   - `vendor/`, `node_modules/` and `public/assets` build output are not
     sensitive, but `storage/logs` and `storage/uploads` are.
5. **Assets**
   - Run `npm ci && npm run build` during deployment; `public/assets` is not in
     git.
6. **Post-deploy smoke test**
   - `GET /api/health` returns DB `ok`.
   - A normal account receives `403` on `/admin` and `/api/admin/stats`.
   - Sign-in, quest completion, and a real password-reset email still work.

## Backups and recovery

The database and uploaded avatars are the only stateful pieces.

```powershell
# Database (run on the server or against a reachable host)
mysqldump --single-transaction --routines --triggers `
  -u questify_app -p questify > questify-$(Get-Date -Format yyyyMMdd-HHmm).sql

# Uploaded avatars
Compress-Archive -Path public/uploads/avatars/* -DestinationPath uploads-$(Get-Date -Format yyyyMMdd-HHmm).zip
```

Restore into an empty database, then copy the avatars back:

```powershell
mysql -u questify_app -p questify < questify-20260101-1200.sql
Expand-Archive -Path uploads-20260101-1200.zip -DestinationPath public/uploads/avatars -Force
```

Store backups off the application host, encrypt them, and test a restore
periodically. Sessions can be discarded safely; users simply sign in again.

## Verification

```powershell
php vendor/bin/phpunit --no-coverage   # level, streak, recurrence, validation, mail, route authz
php tests/auth_flow.php                # authentication and throttling
php tests/app_shell.php                # shell, navigation, copyright
php tests/quest_flow.php               # ownership, single-use and per-period XP
php tests/phase4_flow.php              # achievements, streaks, leaderboards
php tests/phase5_flow.php              # account settings and deletion
php tests/phase6_flow.php              # admin authorization, moderation, reports
php tests/public_profile_flow.php      # public profiles, privacy, 404s
```

The Phase 6 suite is the executable form of the completion criterion: a normal
user gets `403` on every `/admin` route and API. The public profile suite proves
that hidden and suspended accounts resolve as `404` and that emails/timezones
never leak.

`.github/workflows/ci.yml` runs the unit suite and all eight HTTP suites against
a MariaDB service on every push and pull request, so the verification above is
not manual-only.
