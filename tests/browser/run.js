// Code © 2026 Kevin Cyril Maraño, MIT license.

'use strict';

/**
 * Questify browser QA.
 *
 * Drives the real application in Chrome to cover registration, sign-in,
 * the full quest lifecycle, profile editing, mobile navigation, account
 * deletion, and WCAG 2.0/2.1 A+AA checks on every critical page.
 *
 * Requirements:
 *   - the app is already serving at APP_URL (default http://localhost:8000)
 *   - Chrome or Chromium is installed (set CHROME_PATH when it is not found)
 *
 * Usage:
 *   npm run test:browser
 *   QA_HEADED=1 npm run test:browser
 *   QA_BASE_URL=http://127.0.0.1:8000 npm run test:browser
 *
 * The suite creates one throwaway account and deletes it before exiting. Axe
 * results, page errors, and failure screenshots are written to
 * storage/logs/browser-qa/ so CI can upload them when a run fails.
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const puppeteer = require('puppeteer-core');

const ROOT = path.resolve(__dirname, '..', '..');
const REPORT_DIR = path.join(ROOT, 'storage', 'logs', 'browser-qa');
const BASE_URL = (process.env.QA_BASE_URL || process.env.APP_URL || 'http://localhost:8000').replace(/\/+$/, '');
const HEADLESS = process.env.QA_HEADED !== '1';
const AXE_PATH = require.resolve('axe-core/axe.min.js');
const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];
const DELETE_PHRASE = 'DELETE MY ACCOUNT';

const runId = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
const account = {
  displayName: `QA Runner ${runId}`,
  renamedDisplay: `QA Renamed ${runId}`,
  username: `qa_${runId}`.slice(0, 30),
  email: `qa.${runId}@example.test`,
  password: 'Questify123!',
};

const state = {
  browser: null,
  page: null,
  checks: 0,
  accountCreated: false,
  accountDeleted: false,
  failedStep: null,
  dialogs: [],
  pageErrors: [],
  consoleErrors: [],
  audits: [],
};

const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

function which(command) {
  const finder = process.platform === 'win32' ? 'where' : 'which';

  try {
    const output = execFileSync(finder, [command], {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    });

    return output.split(/\r?\n/).map((line) => line.trim()).find(Boolean) || null;
  } catch {
    return null;
  }
}

function resolveChrome() {
  const candidates = [];

  if (process.env.CHROME_PATH) {
    candidates.push(process.env.CHROME_PATH);
  }

  if (process.env.PUPPETEER_EXECUTABLE_PATH) {
    candidates.push(process.env.PUPPETEER_EXECUTABLE_PATH);
  }

  if (process.platform === 'win32') {
    candidates.push(
      'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
      'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
      process.env.LOCALAPPDATA ? path.join(process.env.LOCALAPPDATA, 'Google', 'Chrome', 'Application', 'chrome.exe') : '',
      'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
      'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    );
  } else if (process.platform === 'darwin') {
    candidates.push(
      '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
      '/Applications/Chromium.app/Contents/MacOS/Chromium',
      '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
    );
  }

  candidates.push('google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser', 'chrome', 'msedge');

  for (const candidate of candidates) {
    if (!candidate) {
      continue;
    }

    if (fs.existsSync(candidate)) {
      return fs.realpathSync(candidate);
    }

    const resolved = which(candidate);

    if (resolved) {
      return resolved;
    }
  }

  return null;
}

function assert(condition, message, detail = '') {
  if (!condition) {
    throw new Error(detail ? `${message}\n${detail}` : message);
  }

  state.checks += 1;
  console.log(`  ok: ${message}`);
}

function currentPath() {
  const url = new URL(state.page.url());

  return url.pathname + url.search;
}

function textIncludes(haystack, needle) {
  return haystack.toLowerCase().includes(needle.toLowerCase());
}

function allViolations() {
  const violations = [];

  for (const audit of state.audits) {
    for (const violation of audit.violations) {
      violations.push({ page: audit.page, url: audit.url, ...violation });
    }
  }

  return violations;
}

async function settle() {
  await state.page.waitForNetworkIdle({ idleTime: 250, timeout: 10000 }).catch(() => {});
  await sleep(300);
}

async function goto(pathname, expectedStatus = 200) {
  const response = await state.page.goto(`${BASE_URL}${pathname}`, {
    waitUntil: 'domcontentloaded',
    timeout: 45000,
  });

  await settle();

  if (response) {
    assert(
      response.status() === expectedStatus,
      `${pathname} responded ${expectedStatus}`,
      `received HTTP ${response.status()}`,
    );
  }

  return response;
}

async function bodyText() {
  return state.page.evaluate(() => document.body.innerText);
}

async function waitForText(text, timeout = 10000) {
  await state.page.waitForFunction(
    (value) => document.body.innerText.includes(value),
    { timeout },
    text,
  );
}

async function assertPath(expected, message = `Navigated to ${expected}`) {
  await state.page.waitForFunction(
    (value) => window.location.pathname + window.location.search === value,
    { timeout: 10000 },
    expected,
  );
  assert(currentPath() === expected, message, `current path: ${currentPath()}`);
}

async function fill(selector, value) {
  await state.page.waitForSelector(selector, { visible: true });
  await state.page.click(selector);
  await state.page.$eval(selector, (element) => {
    if ('value' in element) {
      element.value = '';
      element.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
  await state.page.type(selector, value);
}

async function submit(selector) {
  await state.page.waitForSelector(selector, { visible: true });
  await Promise.all([
    state.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 45000 }),
    state.page.click(selector),
  ]);
  await settle();
}

async function waitForNavigationFromClick(selector) {
  await state.page.waitForSelector(selector, { visible: true });
  await Promise.all([
    state.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 45000 }),
    state.page.click(selector),
  ]);
  await settle();
}

async function audit(name) {
  const hasAxe = await state.page.evaluate(() => typeof window.axe !== 'undefined');

  if (!hasAxe) {
    await state.page.addScriptTag({ path: AXE_PATH });
  }

  const result = await state.page.evaluate(async (tags) => {
    const output = await window.axe.run(document, {
      runOnly: { type: 'tag', values: tags },
    });

    return {
      passes: output.passes.length,
      incomplete: output.incomplete.map((item) => ({ id: item.id, nodes: item.nodes.length })),
      violations: output.violations.map((violation) => ({
        id: violation.id,
        impact: violation.impact,
        help: violation.help,
        helpUrl: violation.helpUrl,
        nodes: violation.nodes.map((node) => ({
          target: node.target.join(' '),
          html: node.html.slice(0, 300),
          failureSummary: node.failureSummary,
        })),
      })),
    };
  }, AXE_TAGS);

  state.audits.push({ page: name, url: state.page.url(), ...result });

  if (result.violations.length === 0) {
    console.log(`  ok: axe ${name} (0 violations)`);
    return;
  }

  const nodes = result.violations.reduce((total, violation) => total + violation.nodes.length, 0);
  console.log(`  FAIL: axe ${name} (${result.violations.length} rules, ${nodes} nodes)`);
}

async function scanPage(name, pathname, expectedStatus = 200) {
  await goto(pathname, expectedStatus);
  await audit(name);
}

async function login(identifier, password) {
  await goto('/login');
  await fill('#identifier', identifier);
  await fill('#password', password);
  await submit('form[data-auth-form] button[type="submit"]');
}

async function captureFailure(name, error) {
  if (!state.page) {
    return;
  }

  try {
    fs.mkdirSync(REPORT_DIR, { recursive: true });

    const slug = name.replace(/[^a-z0-9]+/gi, '-').toLowerCase();
    const screenshotPath = path.join(REPORT_DIR, `failure-${slug}.png`);

    await state.page.screenshot({ path: screenshotPath, fullPage: true });
    fs.writeFileSync(
      path.join(REPORT_DIR, `failure-${slug}.json`),
      JSON.stringify({
        step: name,
        error: String(error && error.stack ? error.stack : error),
        url: state.page.url(),
        title: await state.page.title().catch(() => ''),
        body: (await bodyText().catch(() => '')).slice(0, 4000),
      }, null, 2),
      'utf8',
    );

    console.error(`  screenshot: ${screenshotPath}`);
  } catch (captureError) {
    console.error(`  could not capture the failure: ${captureError.message}`);
  }
}

async function step(name, callback) {
  console.log(`\n[step] ${name}`);
  const started = Date.now();

  try {
    await callback();
    console.log(`  done in ${Date.now() - started} ms`);
  } catch (error) {
    state.failedStep = name;
    await captureFailure(name, error);
    throw error;
  }
}

async function cleanupAccount() {
  if (!state.page || !state.accountCreated || state.accountDeleted) {
    return;
  }

  console.log('\n[cleanup] removing the temporary QA account');

  try {
    if (new URL(state.page.url()).pathname !== '/settings') {
      await goto('/settings');
    }

    if (new URL(state.page.url()).pathname !== '/settings') {
      await login(account.email, account.password);
      await goto('/settings');
    }

    await fill('#delete_current', account.password);
    await fill('#delete_confirmation', DELETE_PHRASE);
    await state.page.waitForFunction(() => {
      const button = document.querySelector('[data-delete-submit]');

      return button instanceof HTMLButtonElement && !button.disabled;
    }, { timeout: 5000 });
    await submit('[data-delete-submit]');

    state.accountDeleted = new URL(state.page.url()).pathname === '/login';

    if (state.accountDeleted) {
      console.log('  ok: temporary account deleted');
    } else {
      console.warn('  warning: the deletion form did not return to /login');
    }
  } catch (error) {
    console.warn(`  warning: could not delete the temporary account (${error.message})`);
  }
}

function writeReport({ startedAt, status, failure }) {
  fs.mkdirSync(REPORT_DIR, { recursive: true });

  const violations = allViolations();
  const reportPath = path.join(REPORT_DIR, 'report.json');
  const report = {
    baseUrl: BASE_URL,
    chromePath: state.chromePath,
    startedAt: startedAt.toISOString(),
    finishedAt: new Date().toISOString(),
    status,
    checks: state.checks,
    failure: failure ? String(failure.stack || failure.message) : null,
    account: {
      username: account.username,
      email: account.email,
      created: state.accountCreated,
      deleted: state.accountDeleted,
    },
    accessibility: {
      tags: AXE_TAGS,
      pagesScanned: state.audits.length,
      violationCount: violations.length,
      violations,
      audits: state.audits,
    },
    dialogs: state.dialogs,
    pageErrors: state.pageErrors,
    consoleErrors: state.consoleErrors,
  };

  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2), 'utf8');

  return { reportPath, violations };
}

async function run() {
  await step('Guest pages render and pass axe', async () => {
    await goto('/');
    const landing = await bodyText();
    assert(
      landing.includes('Code © 2026 Kevin Cyril Maraño, MIT license.'),
      'Landing page keeps the exact footer credit',
    );
    await audit('landing');

    await scanPage('login', '/login');
    await scanPage('register', '/register');
    await scanPage('forgot-password', '/forgot-password');
    await scanPage('reset-password', '/reset-password?token=qa-invalid-token');
    await scanPage('not-found', `/qa-missing-${runId}`, 404);
  });

  await step('Registration signs the new account in', async () => {
    await goto('/register');
    await fill('#display_name', account.displayName);
    await fill('#username', account.username);
    await fill('#email', account.email);
    await fill('#password', account.password);
    await fill('#password_confirmation', account.password);
    await state.page.click('input[name="terms"]');
    await submit('form[data-auth-form] button[type="submit"]');
    state.accountCreated = true;

    await assertPath('/dashboard', 'Registration lands on the dashboard');
    await waitForText(account.displayName);
    assert(true, 'Dashboard greets the new player by display name');

    await audit('dashboard');
  });

  await step('Quest creation and the active quest view', async () => {
    await scanPage('quests-empty', '/quests');
    const emptyLog = await bodyText();
    assert(textIncludes(emptyLog, 'No active quests'), 'A fresh account shows the active-quest empty state');

    await scanPage('quest-create', '/quests/create');

    await fill('#title', `QA Quest ${runId}`);
    await fill('#description', `A throwaway quest created by the browser QA runner (${runId}).`);
    const category = await state.page.$eval('#category_id', (select) => {
      const option = Array.from(select.options).find((item) => item.value !== '');

      return option ? option.value : '';
    });

    if (category) {
      await state.page.select('#category_id', category);
    }

    await state.page.$eval('input[name="difficulty"][value="medium"]', (input) => input.click());
    await submit('form[data-quest-form] button[type="submit"]');

    const match = new URL(state.page.url()).pathname.match(/^\/quests\/(\d+)$/);
    assert(match !== null, 'Creating a quest opens its detail page', state.page.url());
    state.questId = match ? Number(match[1]) : 0;
    state.questPath = `/quests/${state.questId}`;

    await waitForText(`QA Quest ${runId}`);
    assert(true, 'Quest detail shows the created title');
    await audit('quest-detail');
  });

  await step('Archive and restore an active quest', async () => {
    const dialogCount = state.dialogs.length;
    await submit('form[action$="/archive"] button[type="submit"]');
    await assertPath('/quests?status=archived', 'Archiving opens the archived tab');
    assert(
      state.dialogs.length === dialogCount + 1 && state.dialogs.at(-1).includes('Archive this quest?'),
      'Archiving asks for confirmation first',
    );
    await waitForText(`QA Quest ${runId}`);
    const archived = await bodyText();
    assert(
      textIncludes(archived, 'Archived') && textIncludes(archived, 'Restore'),
      'Archived tab offers a restore action',
    );
    await audit('quests-archived');

    await goto(state.questPath);
    await submit('form[action$="/restore"] button[type="submit"]');
    await assertPath('/quests', 'Restoring an active quest returns to the active log');
    await waitForText(`QA Quest ${runId}`);
    assert(true, 'Restored quest is active and can be completed');
  });

  await step('Complete, archive, and restore a completed quest', async () => {
    await submit('form[action$="/complete"] button[type="submit"]');
    await assertPath(state.questPath, 'Completing stays on the quest detail page');
    const completed = await bodyText();
    assert(textIncludes(completed, 'Reward claimed'), 'Completed quest shows the claimed reward');

    await goto('/quests?status=completed');
    await waitForText(`QA Quest ${runId}`);
    assert(true, 'Completed tab lists the finished quest');
    await audit('quests-completed');

    await goto(state.questPath);
    const dialogCount = state.dialogs.length;
    await submit('form[action$="/archive"] button[type="submit"]');
    await assertPath('/quests?status=archived', 'Archiving a completed quest opens the archived tab');
    assert(
      state.dialogs.length === dialogCount + 1
        && state.dialogs.at(-1).includes('Archive this completed quest?'),
      'Archiving a completed quest warns that XP is kept',
    );

    await goto(state.questPath);
    await submit('form[action$="/restore"] button[type="submit"]');
    await assertPath('/quests', 'Restoring a completed quest returns to the journal');
    await goto('/quests?status=completed');
    await waitForText(`QA Quest ${runId}`);
    const restored = await bodyText();
    assert(textIncludes(restored, 'Completed'), 'Restored completed quest stays in the completed log');
  });

  await step('Profile editing and public profile', async () => {
    await scanPage('profile', '/profile');

    await fill('#display_name', account.renamedDisplay);
    await submit('form[action$="/profile"] button[type="submit"]');
    await assertPath('/profile', 'Saving the profile stays on the profile page');
    await waitForText(account.renamedDisplay);
    assert(true, 'Profile page shows the updated display name');

    await goto(`/u/${account.username}`);
    await waitForText(account.renamedDisplay);
    assert(true, 'Public profile shows the updated display name');
  });

  await step('Notifications, achievements, leaderboard, and settings pass axe', async () => {
    await scanPage('notifications', '/notifications');
    const notifications = await bodyText();
    assert(textIncludes(notifications, 'Profile updated'), 'Profile changes create a notification');

    const unreadBefore = await state.page.$$eval('.notification-card.is-unread', (items) => items.length);
    const markRead = await state.page.$('form[data-notification-read] button[type="submit"]');

    if (markRead && unreadBefore > 0) {
      await markRead.click();
      await state.page.waitForFunction(
        (expected) => document.querySelectorAll('.notification-card.is-unread').length === expected,
        { timeout: 10000 },
        unreadBefore - 1,
      );
      const unreadLabels = await state.page.$$eval('[data-unread-label]', (labels) => labels.length);
      assert(
        unreadLabels === unreadBefore - 1,
        'Marking a notification read removes its visually hidden unread label',
      );

      const label = await state.page.$eval(
        'header [data-unread-count]',
        (badge) => badge.closest('a')?.getAttribute('aria-label') ?? '',
      );
      assert(label.startsWith('Notifications'), 'Marking a notification read keeps the topbar label');
      assert(!label.includes('('), 'Notification labels keep unread counts out of parentheses');
    }

    await scanPage('achievements', '/achievements');
    await scanPage('leaderboard', '/leaderboard');
    await scanPage('settings', '/settings');
  });

  await step('Mobile drawer and bottom navigation work', async () => {
    await state.page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    await goto('/dashboard');
    await state.page.waitForFunction(
      () => document.querySelector('[data-sidebar-panel]')?.getAttribute('aria-hidden') === 'true',
      { timeout: 10000 },
    );

    const mobileState = await state.page.evaluate(() => {
      const bottomNav = document.querySelector('.app-bottom-nav');
      const bottomRect = bottomNav?.getBoundingClientRect();

      return {
        bottomVisible: Boolean(
          bottomNav
          && getComputedStyle(bottomNav).display !== 'none'
          && bottomRect
          && bottomRect.width > 0
          && bottomRect.height > 0,
        ),
        bottomLinks: bottomNav ? bottomNav.querySelectorAll('a').length : 0,
      };
    });

    assert(mobileState.bottomVisible, 'Mobile bottom navigation is visible');
    assert(mobileState.bottomLinks === 5, 'Mobile bottom navigation exposes five destinations');
    await audit('dashboard-mobile');

    await state.page.click('[data-sidebar-open]');
    await state.page.waitForFunction(
      () => {
        const panel = document.querySelector('[data-sidebar-panel]');
        const toggle = document.querySelector('[data-sidebar-open]');

        return panel?.classList.contains('is-open')
          && panel.getAttribute('aria-hidden') === 'false'
          && toggle?.getAttribute('aria-expanded') === 'true';
      },
      { timeout: 10000 },
    );
    await sleep(350);

    const drawerState = await state.page.evaluate(() => ({
      scrollLocked: document.body.classList.contains('app-scroll-lock'),
      focusOnClose: document.activeElement === document.querySelector('[data-sidebar-close]'),
      backgroundInert: Boolean(document.querySelector('.app-content')?.inert),
    }));

    assert(drawerState.scrollLocked, 'Opening the drawer locks background scrolling');
    assert(drawerState.focusOnClose, 'Opening the drawer moves focus to its close button');
    assert(drawerState.backgroundInert, 'Opening the drawer makes the background inert');
    await audit('dashboard-mobile-drawer');

    await state.page.keyboard.press('Escape');
    await state.page.waitForFunction(
      () => {
        const panel = document.querySelector('[data-sidebar-panel]');
        const toggle = document.querySelector('[data-sidebar-open]');

        return panel?.getAttribute('aria-hidden') === 'true'
          && toggle?.getAttribute('aria-expanded') === 'false';
      },
      { timeout: 10000 },
    );
    assert(true, 'Escape closes the mobile drawer');

    await state.page.click('[data-sidebar-open]');
    await state.page.waitForFunction(
      () => document.querySelector('[data-sidebar-panel]')?.classList.contains('is-open'),
      { timeout: 10000 },
    );
    await waitForNavigationFromClick('#app-sidebar a[href$="/quests"]');
    await assertPath('/quests', 'A drawer link navigates to the quest log');
    await waitForText('My quests');
    assert(true, 'Drawer navigation lands on the quest log');

    await waitForNavigationFromClick('.app-bottom-nav a[href$="/achievements"]');
    await assertPath('/achievements', 'A bottom-nav link navigates to achievements');
    await waitForText('Achievements');
    assert(true, 'Bottom navigation lands on achievements');

    await state.page.setViewport({ width: 1440, height: 1000, deviceScaleFactor: 1 });
  });

  await step('Logout and guest public profile', async () => {
    await goto('/dashboard');
    await submit('form[action$="/logout"] button[type="submit"]');
    await assertPath('/login', 'Signing out returns to the login page');
    const signedOut = await bodyText();
    assert(signedOut.includes('Welcome back, adventurer.'), 'Login form is rendered after sign-out');

    await goto(`/u/${account.username}`);
    await waitForText(account.renamedDisplay);
    assert(true, 'Public profile renders for guests');
    await audit('public-profile');
  });

  await step('Sign back in and delete the account', async () => {
    await login(account.email, account.password);
    await assertPath('/dashboard', 'The throwaway account can sign back in');

    await goto('/settings');
    await fill('#delete_current', account.password);
    await fill('#delete_confirmation', DELETE_PHRASE);
    await state.page.waitForFunction(() => {
      const button = document.querySelector('[data-delete-submit]');

      return button instanceof HTMLButtonElement && !button.disabled;
    }, { timeout: 5000 });
    await submit('[data-delete-submit]');

    await assertPath('/login', 'Deleting the account returns to the login page');
    const deleted = await bodyText();
    assert(
      deleted.includes('Your account and all of its data have been deleted.'),
      'Deletion confirms that account data was removed',
    );
    state.accountDeleted = true;

    await goto(`/u/${account.username}`, 404);
    assert(true, 'Deleted account no longer has a public profile');

    await goto('/login');
    await fill('#identifier', account.email);
    await fill('#password', account.password);
    await submit('form[data-auth-form] button[type="submit"]');
    await assertPath('/login', 'Deleted credentials cannot sign in');
    const rejected = await bodyText();
    assert(rejected.includes('Those credentials do not match our records.'), 'Deleted account is rejected');
  });
}

(async () => {
  const startedAt = new Date();
  let status = 'passed';
  let failure = null;

  const chromePath = resolveChrome();

  if (!chromePath) {
    console.error(
      'Unable to locate Chrome or Chromium. Install Chrome or set CHROME_PATH to the browser executable.',
    );
    process.exitCode = 1;
    return;
  }

  state.chromePath = chromePath;

  console.log('Questify browser QA');
  console.log(`  base URL: ${BASE_URL}`);
  console.log(`  chrome:   ${chromePath}`);
  console.log(`  headless: ${HEADLESS ? 'yes' : 'no'}`);
  console.log(`  account:  ${account.username}`);

  try {
    const health = await fetch(`${BASE_URL}/`, { redirect: 'manual' }).catch(() => null);

    if (!health || health.status >= 500) {
      throw new Error(
        `Questify is not reachable at ${BASE_URL}. Start the app with "php -S localhost:8000 -t public scripts/router.php".`,
      );
    }

    console.log('  server:   reachable');

    state.browser = await puppeteer.launch({
      executablePath: chromePath,
      headless: HEADLESS,
      args: ['--no-sandbox', '--disable-dev-shm-usage', '--hide-scrollbars'],
      defaultViewport: { width: 1440, height: 1000 },
    });

    state.page = await state.browser.newPage();
    state.page.setDefaultTimeout(15000);
    state.page.setDefaultNavigationTimeout(45000);
    await state.page.setBypassCSP(true);
    await state.page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);

    state.page.on('dialog', async (dialog) => {
      state.dialogs.push(dialog.message());
      await dialog.accept();
    });
    state.page.on('pageerror', (error) => state.pageErrors.push(error.message));
    state.page.on('console', (message) => {
      if (message.type() === 'error') {
        const location = message.location();
        state.consoleErrors.push({
          text: message.text(),
          url: location.url,
          line: location.lineNumber,
        });
      }
    });

    await run();

    if (state.pageErrors.length > 0) {
      throw new Error(`Uncaught page errors:\n${state.pageErrors.join('\n')}`);
    }

    const violations = allViolations();

    if (violations.length > 0) {
      const lines = state.audits.flatMap((auditResult) => auditResult.violations.map(
        (violation) => `- ${auditResult.page}: ${violation.id} (${violation.nodes.length} node${violation.nodes.length === 1 ? '' : 's'}) - ${violation.help}`,
      ));

      throw new Error(`Accessibility violations found:\n${lines.join('\n')}`);
    }
  } catch (error) {
    status = 'failed';
    failure = error;

    if (!state.failedStep) {
      await captureFailure('run', error);
    }

    await cleanupAccount();
  } finally {
    const { reportPath } = writeReport({ startedAt, status, failure });

    if (state.browser) {
      await state.browser.close().catch(() => {});
    }

    if (failure) {
      console.error(`\nFAILED: ${failure.message}`);
      console.error(`Report: ${reportPath}`);
      process.exitCode = 1;
    } else {
      console.log(`\nPASS: ${state.checks} checks, ${state.audits.length} accessibility scans, 0 violations`);
      console.log(`Report: ${reportPath}`);
    }
  }
})();
