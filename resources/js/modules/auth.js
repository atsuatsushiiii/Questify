// Code © 2026 Kevin Cyril Maraño, MIT license.

/**
 * Authentication form behaviour: password visibility, strength feedback,
 * timezone detection and submit progress. Everything degrades gracefully when
 * JavaScript is unavailable.
 */

const STRENGTH_STEPS = [
  { label: 'Too short', tone: 'text-rose-300', bar: 'bg-rose-400' },
  { label: 'Weak', tone: 'text-rose-300', bar: 'bg-rose-400' },
  { label: 'Fair', tone: 'text-amber-300', bar: 'bg-amber-400' },
  { label: 'Strong', tone: 'text-aqua-300', bar: 'bg-aqua-400' },
  { label: 'Very strong', tone: 'text-emerald-300', bar: 'bg-emerald-400' },
];

const INACTIVE_BAR = 'bg-white/[0.08]';

function scorePassword(value) {
  if (value.length < 10) {
    return 0;
  }

  let score = 1;

  if (value.length >= 14) score += 1;
  if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 1;
  if (/\d/.test(value)) score += 1;
  if (/[^A-Za-z0-9]/.test(value)) score += 1;

  return Math.min(4, score);
}

function initPasswordToggles() {
  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle || '');

    if (!input) {
      return;
    }

    button.hidden = false;

    const open = button.querySelector('[data-eye-open]');
    const closed = button.querySelector('[data-eye-off]');

    button.addEventListener('click', () => {
      const reveal = input.type === 'password';

      input.type = reveal ? 'text' : 'password';
      button.setAttribute('aria-pressed', String(reveal));
      button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
      open?.classList.toggle('hidden', reveal);
      closed?.classList.toggle('hidden', !reveal);
      input.focus({ preventScroll: true });
    });
  });
}

function initStrengthMeters() {
  document.querySelectorAll('[data-strength-input]').forEach((input) => {
    const meter = document.querySelector('[data-strength]');

    if (!meter) {
      return;
    }

    const segments = [...meter.querySelectorAll('[data-strength-segment]')];
    const label = meter.querySelector('[data-strength-label]');

    const update = () => {
      const value = input.value;
      meter.classList.toggle('hidden', value.length === 0);

      if (value.length === 0) {
        return;
      }

      const score = scorePassword(value);
      const step = STRENGTH_STEPS[score];

      segments.forEach((segment, index) => {
        segment.classList.remove(...STRENGTH_STEPS.map((item) => item.bar), INACTIVE_BAR);
        segment.classList.add(index < Math.max(score, 1) ? step.bar : INACTIVE_BAR);
      });

      if (label) {
        label.textContent = step.label;
        label.className = `mt-1.5 text-[11px] ${step.tone}`;
      }
    };

    input.addEventListener('input', update);
    update();
  });
}

function initTimezoneDetection() {
  document.querySelectorAll('[data-timezone]').forEach((select) => {
    if (!(select instanceof HTMLSelectElement) || select.dataset.touched === '1') {
      return;
    }

    const detected = Intl.DateTimeFormat().resolvedOptions().timeZone;

    if (!detected) {
      return;
    }

    const match = [...select.options].find((option) => option.value === detected);

    if (match && select.value === 'UTC' && detected !== 'UTC') {
      select.value = detected;
    }

    select.addEventListener('change', () => {
      select.dataset.touched = '1';
    });
  });
}

function initSubmitState() {
  document.querySelectorAll('[data-auth-form]').forEach((form) => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('[data-submit]');

      if (!(button instanceof HTMLButtonElement) || button.disabled) {
        return;
      }

      const label = button.querySelector('[data-submit-label]');
      const spinner = button.querySelector('[data-submit-spinner]');

      button.disabled = true;
      button.classList.add('opacity-80');
      label?.classList.add('sr-only');
      spinner?.classList.remove('hidden');
    });
  });
}

export function initAuth() {
  initPasswordToggles();
  initStrengthMeters();
  initTimezoneDetection();
  initSubmitState();
}
