// Code © 2026 Kevin Cyril Maraño, MIT license.

/**
 * Settings enhancements.
 *
 * The delete phrase gate is a convenience only: the server independently
 * verifies the current password and the exact confirmation phrase. The
 * confirmation prompts are also progressive enhancements, so every form
 * remains usable without JavaScript.
 */

const DELETE_PHRASE = 'DELETE MY ACCOUNT';

function initConfirmPrompts() {
  document.querySelectorAll('[data-settings-page] form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const message = form.dataset.confirm;

      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
}

function initDeleteGate() {
  const form = document.querySelector('[data-delete-form]');

  if (!form) {
    return;
  }

  const phrase = form.querySelector('[data-delete-phrase]');
  const submit = form.querySelector('[data-delete-submit]');
  const status = form.querySelector('[data-delete-status]');

  if (!(phrase instanceof HTMLInputElement) || !(submit instanceof HTMLButtonElement)) {
    return;
  }

  const sync = () => {
    const matches = phrase.value.trim() === DELETE_PHRASE;

    // The server remains authoritative. This only stops an accidental click
    // before the exact phrase has been typed.
    submit.disabled = !matches;
    phrase.setAttribute('aria-invalid', phrase.value.trim() === '' || matches ? 'false' : 'true');

    if (status) {
      if (matches) {
        status.textContent = 'Phrase matches. Deletion is immediate and irreversible.';
        status.className = 'mt-1.5 text-[11px] text-aqua-300';
      } else if (phrase.value.trim() === '') {
        status.textContent = 'This phrase is exact and case-sensitive.';
        status.className = 'mt-1.5 text-[11px] text-ember-100/60';
      } else {
        status.textContent = 'Not quite — type DELETE MY ACCOUNT exactly.';
        status.className = 'mt-1.5 text-[11px] text-ember-300';
      }
    }
  };

  phrase.addEventListener('input', sync);

  form.addEventListener('submit', (event) => {
    if (phrase.value.trim() !== DELETE_PHRASE) {
      event.preventDefault();
      phrase.focus({ preventScroll: true });
    }
  });

  sync();
}

export function initSettings() {
  initConfirmPrompts();
  initDeleteGate();
}
