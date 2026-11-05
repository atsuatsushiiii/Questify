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
  revoked, so the target is signed out on its next request.

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
  `user_id = ?`; another user's row returns `404`, not a hidden control.
- Admin mutations enforce self-protection and last-administrator rules
  server-side; the disabled buttons are not the security boundary.
- `tests/Unit/RouteAuthorizationTest.php` fails if an `/admin` route is added
  without `AuthMiddleware` + `AdminMiddleware`, or if CSRF is checked before the
  role gate.

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

### Transport and headers

`config/security.php` sends these on every response:

- `Content-Security-Policy` with `script-src 'self'`, `frame-ancestors 'none'`,
  `object-src 'none'`, `base-uri 'self'`, and `form-action 'self'`
- `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` disabling camera, microphone and geolocation
- `Cross-Origin-Opener-Policy: same-origin`

The stylesheet policy allows `'unsafe-inline'` because server-rendered
components use a small number of inline style attributes (category colours,
progress widths). Scripts are never inline and Chart.js is bundled locally.

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
   - Sign-in, quest completion, and password reset still work.

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
php vendor/bin/phpunit --no-coverage   # level, streak, validation, route authz
php tests/auth_flow.php                # authentication and throttling
php tests/app_shell.php                # shell, navigation, copyright
php tests/quest_flow.php               # ownership, single-use XP
php tests/phase4_flow.php              # achievements, streaks, leaderboards
php tests/phase5_flow.php              # account settings and deletion
php tests/phase6_flow.php              # admin authorization and moderation
```

The Phase 6 suite is the executable form of the completion criterion: a normal
user gets `403` on every `/admin` route and API.
