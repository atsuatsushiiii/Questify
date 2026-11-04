// Code © 2026 Kevin Cyril Maraño, MIT license.

/**
 * Public site navigation: scrolled state + accessible mobile menu.
 */
export function initNav() {
  const header = document.querySelector('[data-nav]');
  const toggle = document.querySelector('[data-nav-toggle]');
  const menu = document.querySelector('[data-mobile-menu]');
  const openIcon = document.querySelector('[data-icon-open]');
  const closeIcon = document.querySelector('[data-icon-close]');

  if (header) {
    const updateScrolled = () => {
      header.classList.toggle('is-scrolled', window.scrollY > 12);
    };

    updateScrolled();
    window.addEventListener('scroll', updateScrolled, { passive: true });
  }

  if (!toggle || !menu) {
    return;
  }

  const isOpen = () => menu.classList.contains('is-open');

  const setOpen = (open) => {
    menu.classList.toggle('is-open', open);
    menu.setAttribute('aria-hidden', String(!open));
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    openIcon?.classList.toggle('hidden', open);
    closeIcon?.classList.toggle('hidden', !open);
  };

  toggle.addEventListener('click', () => setOpen(!isOpen()));

  menu.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setOpen(false));
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && isOpen()) {
      setOpen(false);
      toggle.focus();
    }
  });

  const desktop = window.matchMedia('(min-width: 768px)');
  desktop.addEventListener('change', (event) => {
    if (event.matches && isOpen()) {
      setOpen(false);
    }
  });
}
