// Code © 2026 Kevin Cyril Maraño, MIT license.

/**
 * Notification inbox enhancement.
 *
 * Mark-read forms still submit normally without JavaScript. When fetch is
 * available the same POST is sent in the background and the row is switched to
 * its read state without a navigation.
 *
 * Selecting a card opens the native modal reader. Without JavaScript the full
 * message is already visible in the card, so nothing is hidden behind the
 * enhancement.
 */

function unreadBadges() {
  return Array.from(document.querySelectorAll('[data-unread-count]'));
}

function refreshBadges(remaining) {
  unreadBadges().forEach((badge) => {
    badge.textContent = remaining > 99 ? '99+' : String(remaining);
    badge.hidden = remaining === 0;

    const wrapper = badge.closest('a, button');

    if (wrapper && wrapper.hasAttribute('aria-label')) {
      wrapper.setAttribute(
        'aria-label',
        remaining > 0 ? `Notifications ${remaining} unread` : 'Notifications',
      );
    }
  });
}

function currentUnread() {
  const badge = unreadBadges()[0];

  return badge ? Number.parseInt(badge.textContent ?? '0', 10) || 0 : 0;
}

function initReader(list) {
  const reader = document.querySelector('[data-notification-reader]');

  if (!reader || typeof reader.showModal !== 'function') {
    return;
  }

  const title = reader.querySelector('[data-notification-reader-title]');
  const message = reader.querySelector('[data-notification-reader-message]');
  const time = reader.querySelector('[data-notification-reader-time]');
  const link = reader.querySelector('[data-notification-reader-link]');
  const close = reader.querySelector('[data-notification-reader-close]');
  let opener = null;

  list.classList.add('is-enhanced');

  const open = (card, trigger) => {
    const cardTitle = card.querySelector('[data-notification-title]');
    const cardMessage = card.querySelector('[data-notification-message]');
    const cardTime = card.querySelector('[data-notification-time]');
    const cardLink = card.querySelector('[data-notification-link]');

    if (title) {
      title.textContent = cardTitle?.textContent?.trim() ?? '';
    }

    if (message) {
      message.textContent = cardMessage?.textContent?.trim() ?? '';
    }

    if (time) {
      time.textContent = cardTime?.textContent?.trim() ?? '';

      const datetime = cardTime?.getAttribute('datetime');

      if (datetime) {
        time.setAttribute('datetime', datetime);
      } else {
        time.removeAttribute('datetime');
      }
    }

    if (link) {
      const href = cardLink instanceof HTMLAnchorElement ? cardLink.getAttribute('href') : '';

      if (href) {
        link.setAttribute('href', href);
        link.hidden = false;
      } else {
        link.removeAttribute('href');
        link.hidden = true;
      }
    }

    opener = trigger instanceof HTMLElement ? trigger : null;

    if (!reader.open) {
      reader.showModal();
    }

    close?.focus();
  };

  list.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
      return;
    }

    const card = target.closest('[data-notification]');

    if (!card) {
      return;
    }

    const trigger = target.closest('[data-notification-open]');

    if (!trigger && target.closest('a, button, form, input, select, textarea, label')) {
      return;
    }

    open(card, trigger ?? card.querySelector('[data-notification-open]'));
  });

  close?.addEventListener('click', () => reader.close());

  reader.addEventListener('click', (event) => {
    if (event.target === reader) {
      reader.close();
    }
  });

  reader.addEventListener('close', () => {
    opener?.focus();
    opener = null;
  });
}

export function initNotifications() {
  const list = document.querySelector('[data-notification-list]');

  if (!list) {
    return;
  }

  initReader(list);

  if (typeof window.fetch !== 'function') {
    return;
  }

  const markRead = (item) => {
    item.classList.remove('is-unread');
    item.querySelector('.notification-dot')?.remove();
    item.querySelector('[data-unread-label]')?.remove();
    item.querySelector('[data-notification-read]')?.remove();
  };

  list.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-notification-read]');

    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    event.preventDefault();
    const item = form.closest('[data-notification]');
    const button = form.querySelector('button');
    button?.setAttribute('disabled', 'disabled');

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const pathname = new URL(response.url, window.location.origin).pathname;

      if (!response.ok || !pathname.endsWith('/notifications')) {
        throw new Error('Mark read failed');
      }

      if (item) {
        markRead(item);
      }

      refreshBadges(Math.max(0, currentUnread() - 1));
    } catch {
      button?.removeAttribute('disabled');
      form.submit();
    }
  });

  const markAll = document.querySelector('[data-notifications-read-all]');

  markAll?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = markAll.querySelector('button');
    button?.setAttribute('disabled', 'disabled');

    try {
      const response = await fetch(markAll.action, {
        method: 'POST',
        body: new FormData(markAll),
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const pathname = new URL(response.url, window.location.origin).pathname;

      if (!response.ok || !pathname.endsWith('/notifications')) {
        throw new Error('Mark all read failed');
      }

      document.querySelectorAll('[data-notification].is-unread').forEach(markRead);
      refreshBadges(0);
      markAll.remove();
    } catch {
      button?.removeAttribute('disabled');
      markAll.submit();
    }
  });
}
