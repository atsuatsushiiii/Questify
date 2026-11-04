// Code © 2026 Kevin Cyril Maraño, MIT license.

import {
  CategoryScale,
  Chart,
  Filler,
  Legend,
  LineController,
  LineElement,
  LinearScale,
  PointElement,
  Tooltip,
} from 'chart.js';

Chart.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  LineController,
  Filler,
  Tooltip,
  Legend,
);

const prefersReducedMotion = () =>
  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const numberFormatter = new Intl.NumberFormat('en-US');
const dateFormatter = new Intl.DateTimeFormat('en-GB', {
  day: 'numeric',
  month: 'short',
  timeZone: 'UTC',
});

/**
 * Administrator dashboard growth chart.
 *
 * The KPI cards are server-rendered; this chart is an enhancement. If the
 * request fails or the payload is malformed the canvas is hidden and a short
 * status message explains why, without affecting the rest of the page.
 */
function initGrowthChart() {
  const canvas = document.querySelector('[data-admin-growth-chart]');

  if (!(canvas instanceof HTMLCanvasElement)) {
    return;
  }

  const status = document.querySelector('[data-admin-chart-status]');
  const skeleton = document.querySelector('[data-admin-chart-skeleton]');
  const endpoint = canvas.dataset.endpoint || '/api/admin/stats';

  const setStatus = (message) => {
    if (!status) {
      return;
    }

    status.textContent = message;
    status.classList.toggle('hidden', message === '');
  };

  const setLoading = (loading) => {
    if (!skeleton) {
      return;
    }

    skeleton.classList.toggle('hidden', !loading);
  };

  setLoading(true);

  const parseSeries = (series) => {
    if (!Array.isArray(series)) {
      return [];
    }

    return series.map((row) => ({
      label: dateFormatter.format(new Date(`${String(row.date)}T00:00:00Z`)),
      total: Number(row.total) || 0,
    }));
  };

  fetch(endpoint, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`Request failed with ${response.status}`);
      }

      return response.json();
    })
    .then((payload) => {
      const data = payload && payload.data ? payload.data : null;

      if (!data) {
        throw new Error('Missing chart payload');
      }

      const signups = parseSeries(data.signups);
      const completions = parseSeries(data.completions);
      const labels = signups.map((row) => row.label);

      new Chart(canvas, {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: 'Signups',
              data: signups.map((row) => row.total),
              borderColor: '#FACC15',
              backgroundColor: 'rgba(250, 204, 21, 0.12)',
              pointBackgroundColor: '#FACC15',
              fill: true,
              tension: 0.35,
              borderWidth: 2,
              pointRadius: 0,
              pointHoverRadius: 4,
            },
            {
              label: 'Completions',
              data: completions.map((row) => row.total),
              borderColor: '#DC2626',
              backgroundColor: 'rgba(220, 38, 38, 0.14)',
              pointBackgroundColor: '#DC2626',
              fill: true,
              tension: 0.35,
              borderWidth: 2,
              pointRadius: 0,
              pointHoverRadius: 4,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'index',
            intersect: false,
          },
          animation: prefersReducedMotion() ? false : { duration: 700 },
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: 'rgba(8, 7, 13, 0.96)',
              borderColor: 'rgba(255, 255, 255, 0.10)',
              borderWidth: 1,
              titleColor: '#F8FAFC',
              bodyColor: '#CBD5E1',
              padding: 10,
              displayColors: true,
              callbacks: {
                label: (context) =>
                  `${context.dataset.label}: ${numberFormatter.format(context.parsed.y)}`,
              },
            },
          },
          scales: {
            x: {
              grid: { color: 'rgba(255, 255, 255, 0.04)' },
              border: { display: false },
              ticks: {
                color: '#64748B',
                font: { size: 10 },
                maxRotation: 0,
                autoSkipPadding: 18,
              },
            },
            y: {
              beginAtZero: true,
              grid: { color: 'rgba(255, 255, 255, 0.06)' },
              border: { display: false },
              ticks: {
                color: '#64748B',
                font: { size: 10 },
                precision: 0,
                callback: (value) => numberFormatter.format(Number(value)),
              },
            },
          },
        },
      });

      setStatus('');
      setLoading(false);
    })
    .catch(() => {
      canvas.classList.add('hidden');
      setLoading(false);
      setStatus(
        'Live chart unavailable right now. The KPI cards above are server-rendered and remain accurate.',
      );
    });
}

function slugify(value) {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

/**
 * Category form colour picker and live preview. The text field carries the
 * submitted value so a typed hex colour is still validated server-side.
 */
function initCategoryForm() {
  const form = document.querySelector('[data-category-form]');

  if (!(form instanceof HTMLFormElement)) {
    return;
  }

  const picker = form.querySelector('[data-color-picker]');
  const text = form.querySelector('[data-color-text]');
  const nameField = form.querySelector('input[name="name"]');
  const slugField = form.querySelector('input[name="slug"]');
  const dot = document.querySelector('[data-category-preview-dot]');
  const nameOutput = document.querySelector('[data-category-preview-name]');
  const slugOutput = document.querySelector('[data-category-preview-slug]');
  const validHex = /^#[0-9a-f]{6}$/i;

  const currentColor = () =>
    text instanceof HTMLInputElement && validHex.test(text.value.trim())
      ? text.value.trim().toUpperCase()
      : '#FACC15';

  const paintColor = () => {
    const color = currentColor();
    const invalid =
      text instanceof HTMLInputElement && text.value.trim() !== '' && !validHex.test(text.value.trim());

    if (text instanceof HTMLInputElement) {
      text.setAttribute('aria-invalid', invalid ? 'true' : 'false');
    }

    if (picker instanceof HTMLInputElement) {
      picker.value = color;
    }

    if (dot instanceof HTMLElement) {
      dot.style.backgroundColor = color;
    }
  };

  const paintPreview = () => {
    const name = nameField instanceof HTMLInputElement ? nameField.value.trim() : '';
    const typedSlug = slugField instanceof HTMLInputElement ? slugField.value.trim() : '';
    const slug = typedSlug !== '' ? typedSlug : slugify(name);

    if (nameOutput) {
      nameOutput.textContent = name !== '' ? name : 'Category name';
    }

    if (slugOutput) {
      slugOutput.textContent = `/${slug !== '' ? slug : 'slug'}`;
    }
  };

  if (picker instanceof HTMLInputElement) {
    picker.addEventListener('input', () => {
      if (text instanceof HTMLInputElement) {
        text.value = picker.value.toUpperCase();
      }

      paintColor();
    });
  }

  if (text instanceof HTMLInputElement) {
    text.addEventListener('input', paintColor);
  }

  if (nameField instanceof HTMLInputElement) {
    nameField.addEventListener('input', paintPreview);
  }

  if (slugField instanceof HTMLInputElement) {
    slugField.addEventListener('input', paintPreview);
  }

  paintColor();
  paintPreview();
}

function initConfirmPrompts() {
  document.querySelectorAll('[data-admin-surface] form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const message = form.dataset.confirm;

      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
}

export function initAdmin() {
  initGrowthChart();
  initCategoryForm();
  initConfirmPrompts();
}
