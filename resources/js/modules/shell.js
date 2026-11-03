/**
 * Authenticated shell behaviour: collapsible desktop sidebar, off-canvas mobile
 * navigation, profile menu, and the Phase 3 quest-preview dialog.
 *
 * The HTML works without JavaScript for navigation and sign-out; this module
 * layers on the interactive states and remembers the sidebar preference in a
 * cookie so the server can render it without a flash.
 */

const SIDEBAR_COOKIE = 'questify_sidebar';
const DESKTOP_QUERY = '(min-width: 1024px)';

function writeCookie(name, value) {
  document.cookie = `${name}=${value}; Path=/; Max-Age=31536000; SameSite=Lax`;
}

function initSidebar(shell) {
  const panel = document.querySelector('[data-sidebar-panel]');
  const overlay = document.querySelector('[data-sidebar-overlay]');
  const openButton = document.querySelector('[data-sidebar-open]');
  const closeButton = document.querySelector('[data-sidebar-close]');
  const collapseButton = document.querySelector('[data-sidebar-collapse]');
  const content = document.querySelector('.app-content');
  const bottomNav = document.querySelector('.app-bottom-nav');

  if (!panel) {
    return;
  }

  const desktop = window.matchMedia(DESKTOP_QUERY);

  // While the mobile drawer is open, keep keyboard and screen-reader focus out
  // of the page behind the overlay.
  const setBackgroundInert = (inert) => {
    if (content) {
      content.inert = inert;
    }

    if (bottomNav) {
      bottomNav.inert = inert;
    }
  };

  const setCollapsed = (collapsed) => {
    shell.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';
    collapseButton?.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    writeCookie(SIDEBAR_COOKIE, collapsed ? 'collapsed' : 'expanded');
  };

  setCollapsed(shell.dataset.sidebar === 'collapsed');

  collapseButton?.addEventListener('click', () => {
    setCollapsed(shell.dataset.sidebar !== 'collapsed');
  });

  const isMobileOpen = () => panel.classList.contains('is-open');

  const setMobileOpen = (open, restoreFocus = true) => {
    panel.classList.toggle('is-open', open);
    overlay?.classList.toggle('is-open', open);
    document.body.classList.toggle('app-scroll-lock', open);
    openButton?.setAttribute('aria-expanded', String(open));

    if (!desktop.matches) {
      panel.setAttribute('aria-hidden', String(!open));
      setBackgroundInert(open);
    }

    if (open) {
      closeButton?.focus();
    } else if (restoreFocus) {
      openButton?.focus();
    }
  };

  openButton?.addEventListener('click', () => setMobileOpen(true));
  closeButton?.addEventListener('click', () => setMobileOpen(false));
  overlay?.addEventListener('click', () => setMobileOpen(false));

  panel.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      if (!desktop.matches && isMobileOpen()) {
        setMobileOpen(false, false);
      }
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !desktop.matches && isMobileOpen()) {
      setMobileOpen(false);
    }
  });

  const syncViewport = () => {
    if (desktop.matches) {
      panel.removeAttribute('aria-hidden');
      panel.classList.remove('is-open');
      overlay?.classList.remove('is-open');
      document.body.classList.remove('app-scroll-lock');
      openButton?.setAttribute('aria-expanded', 'false');
      setBackgroundInert(false);
      return;
    }

    panel.setAttribute('aria-hidden', String(!isMobileOpen()));
    setBackgroundInert(isMobileOpen());
  };

  desktop.addEventListener('change', syncViewport);
  syncViewport();
}

function initUserMenu() {
  const root = document.querySelector('[data-user-menu]');
  const toggle = root?.querySelector('[data-user-menu-toggle]');
  const panel = root?.querySelector('[data-user-menu-panel]');
  const chevron = root?.querySelector('[data-user-menu-chevron]');

  if (!root || !toggle || !panel) {
    return;
  }

  const setOpen = (open) => {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    chevron?.classList.toggle('rotate-180', open);
  };

  toggle.addEventListener('click', (event) => {
    event.stopPropagation();
    setOpen(panel.hidden);
  });

  document.addEventListener('click', (event) => {
    if (!root.contains(event.target)) {
      setOpen(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden) {
      setOpen(false);
      toggle.focus();
    }
  });
}

export function initShell() {
  const shell = document.querySelector('[data-app-shell]');

  if (!shell) {
    return;
  }

  initSidebar(shell);
  initUserMenu();
}
