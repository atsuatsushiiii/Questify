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

### Changed

- The static showcase and both favicons now use the Expedition Ledger system.
- Admin charts and the emergency error fallback use the warm brass palette.
- The default category colour is now brass (`#C9993F`).

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
