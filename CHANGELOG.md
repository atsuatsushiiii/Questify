# Changelog

All notable changes to Questify are recorded in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Google sign-in via OpenID Connect: a CSRF-protected start endpoint, a
  PKCE + state + nonce callback, verified-email account linking, automatic
  account creation, and connect/disconnect controls in Settings.
- Admin quest moderation: a searchable, filterable directory of every quest,
  a detail view with the owner, XP ledger, and moderation history, and audited
  archive, restore, and delete actions.
- Reports and flag queue: signed-in users can report a public profile with a
  reason and optional details; admins get a searchable queue with status,
  target, and reason filters, a detail view, and audited resolve/dismiss
  decisions. Duplicate and self-reports are rejected, and closed reports are
  immutable.
- Report loop: a private **My Reports** page (`GET /reports`) lists the
  signed-in reporter's own submissions and statuses, resolving or dismissing a
  report sends the reporter an idempotent in-app notification that omits the
  administrator's private note, and submissions are capped at 10 per account per
  rolling hour. "My reports" is linked from the report form and the account menu.
- Report account actions: an open profile report now offers a moderator warning
  notification and a timed suspension (1, 2, 3, 7, 14, 30 days, or permanent)
  with an optional message. Timed suspensions store `users.suspended_until` and
  lift automatically after expiry; both actions are audited against the report
  and never identify the reporter.
- Read-only moderation: admins can limit an account to read-only access with
  `users.restricted_at` instead of suspending it. The account keeps its sessions,
  sign-in, public profile, and settings, but `ReadOnlyMiddleware` blocks quest
  create/edit/complete/archive/restore/delete and report submission with an
  explanatory banner and flash. The limit can be applied from the user detail or
  an open profile report and is lifted with a matching unrestricted action; both
  transitions notify the account and write `user.restricted` /
  `user.unrestricted` audit entries. A **Read-only** filter and badge are
  available in the user directory.
- The admin user detail accepts a suspension length, and the report detail shows
  the reported account's current suspension state.
- Timed read-only limits: `users.restricted_until` stores an optional expiry for
  read-only access, offered as 1, 2, 3, 7, 14, or 30 days or permanent. Expired
  limits are lifted on the next authenticated request, and the read-only banner
  and admin detail show the expiry.
- Read-only appeals: a limited account can open `/appeal` and request a review
  with a 20–2000 character message. One appeal can be open at a time and a
  24-hour cooldown follows each decision. `/admin/appeals` provides a searchable
  queue and detail view with audited accept and deny actions; accepting restores
  full access, denying keeps the limit, and both notify the account with the
  optional decision note. Closed appeals are immutable.
- Read-only lift notices: when a timed limit expires on an authenticated
  request, the account receives a one-time in-app notification that full access
  has returned. Appeal decisions and manual unrestricted actions label the
  moderator's optional note explicitly.
- Notification reader: selecting a notification opens an accessible native
  modal with the full title, message, timestamp, and related link. The inbox
  still renders and submits without JavaScript.

### Changed

- The static showcase and both favicons now use the Expedition Ledger system.
- Admin charts and the emergency error fallback use the warm brass palette.
- The default category colour is now brass (`#C9993F`).

### Fixed

- Admin sidebar rows now render every icon; the bundled Lucide set was missing
  `flag` (Reports), `gavel`, `tag`, `send`, `repeat`, `external-link`,
  `archive-restore`, `circle-slash`, and `triangle-alert`.
- The admin sidebar no longer leaves **Overview** highlighted while **Users** or
  another child section is active; `nav_active()` supports an exact-match flag.
- Disabled admin pagination controls no longer rely on reduced opacity, which
  failed WCAG AA colour contrast on the users and audit pages.
- Notification messages are no longer truncated at 255 characters, so moderation
  warnings and read-only notices keep the moderator's full optional message.
- Timed read-only limits now send a notification when they expire instead of
  lifting silently.

## [1.0.0] - 2026-11-07

The first complete release: every phase of the project plan has shipped, with
automated coverage across unit, HTTP, and browser layers.

### Added

- Account lifecycle: registration, sign-in, remember-me, sign-out, password
  reset, email verification, sessions, and account deletion.
- Command center with level progress, XP totals, streaks, quest counts, recent
  activity, trophy previews, and leaderboard standings.
- Quest journal with create, edit, complete, archive, restore, delete, search,
  filters, pagination, and daily/weekly/monthly recurrence.
- Atomic completion pipeline: XP ledger, level curve, rank titles, daily
  activity, streaks, achievement evaluation, and notifications inside one
  transaction.
- Achievement catalogue with ten seeded, server-evaluated trophies and live
  criteria progress.
- Public opt-in profiles at `/u/{username}` and all-time, weekly, and monthly
  leaderboards with deterministic tie-breaks.
- Account settings for profile details, privacy, notification preferences,
  avatar upload, email changes, password changes, active sessions, data export,
  and irreversible deletion.
- Administrator tools: KPI dashboard, user moderation, category and achievement
  CRUD, append-only audit log, and role-gated JSON stats.
- Static GitHub Pages showcase in `site/` with real application screenshots.
- Demo seeder (`php scripts/seed_demo.php`) and production setup guidance,
  backup/restore steps, and a written security/threat review.
- Browser QA suite (`npm run test:browser`) using Puppeteer and axe-core covering
  authentication, the quest lifecycle, profiles, notifications, mobile
  navigation, and account deletion across 71 functional checks and 20 WCAG
  2.0/2.1 A+AA scans.

### Changed

- Accessibility pass across public and authenticated pages: accessible muted
  text tokens, higher-contrast primary buttons, corrected control labels,
  decorative landing watermarks, and a visually hidden unread marker that stays
  in sync after in-place notification updates.
- Documentation now reflects the shipped application, the eight completed
  phases, and the full verification ladder.

### Fixed

- Leaderboard tie-break checks and the last-admin invariant now pass regardless
  of existing demo or seed data.
- Notification mark-read updates keep accessible names accurate and remove the
  hidden “Unread:” label.

### Security

- PDO prepared statements throughout, CSRF tokens on mutations, rate-limited
  authentication, DB-backed sessions with fixation defence, CSP and security
  headers, ownership checks on every user resource, and an append-only audit
  trail for administrative actions.

### Verification

- 63 PHPUnit tests (259 assertions).
- Seven HTTP regression suites (585 checks).
- 71 browser checks and 20 axe scans (zero violations).
- CI runs the unit, HTTP, and browser suites on every push and pull request.
