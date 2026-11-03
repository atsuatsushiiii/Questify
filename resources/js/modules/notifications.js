/**
 * Notification inbox enhancement.
 *
 * Mark-read forms still submit normally without JavaScript. When fetch is
 * available the same POST is sent in the background and the row is switched to
 * its read state without a navigation.
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
        remaining > 0 ? `Notifications (${remaining} unread)` : 'Notifications',
      );
    }
  });
}

function currentUnread() {
  const badge = unreadBadges()[0];

  return badge ? Number.parseInt(badge.textContent ?? '0', 10) || 0 : 0;
}

export function initNotifications() {
  const list = document.querySelector('[data-notification-list]');

  if (!list || typeof window.fetch !== 'function') {
    return;
  }

  const markRead = (item) => {
    item.classList.remove('is-unread');
    item.querySelector('.notification-dot')?.remove();
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
