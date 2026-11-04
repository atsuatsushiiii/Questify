// Code © 2026 Kevin Cyril Maraño, MIT license.

/**
 * Quest journal interactions. All behaviour is progressive: every form and
 * reward value already exists in the server-rendered HTML.
 */
export function initQuests() {
  const form = document.querySelector('[data-quest-form]');

  if (form) {
    const preview = document.querySelector('[data-xp-preview]');
    const previewLabel = document.querySelector('[data-xp-preview-difficulty]');
    const options = Array.from(form.querySelectorAll('[data-xp-option]'));

    const paintPreview = () => {
      const selected = options.find((option) => option.checked);

      if (!selected) {
        return;
      }

      if (preview) {
        preview.textContent = selected.dataset.xp || '0';
      }

      if (previewLabel) {
        previewLabel.textContent = `${selected.dataset.difficultyLabel || 'Quest'} quest`;
      }
    };

    options.forEach((option) => option.addEventListener('change', paintPreview));
    paintPreview();

    const description = form.querySelector('[data-quest-description]');
    const counter = document.querySelector('[data-description-count]');

    if (description && counter) {
      const paintCount = () => {
        counter.textContent = String(description.value.length);
      };

      description.addEventListener('input', paintCount);
      paintCount();
    }
  }

  document.querySelectorAll('[data-quest-filter]').forEach((select) => {
    select.addEventListener('change', () => {
      const filterForm = select.closest('form');

      if (filterForm) {
        filterForm.requestSubmit();
      }
    });
  });

  document.querySelectorAll('form[data-quest-action]').forEach((actionForm) => {
    actionForm.addEventListener('submit', (event) => {
      const message = actionForm.dataset.confirm;

      if (message && !window.confirm(message)) {
        event.preventDefault();

        return;
      }

      const submit = actionForm.querySelector('button[type="submit"]');

      if (!submit) {
        return;
      }

      // Guard against double clicks while the request is in flight. The server
      // still enforces single-use XP; this is only interface feedback.
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
      submit.textContent = submit.dataset.loadingLabel || 'Working…';
    });
  });
}
