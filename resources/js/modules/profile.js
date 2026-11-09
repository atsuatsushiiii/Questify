// Code © 2026 Kevin Cyril Maraño, MIT license.

/**
 * Profile editor enhancement: character counter and a local avatar preview.
 *
 * The server remains the authority on image type, dimensions, and size. The
 * preview never uploads anything; it only reflects the chosen file.
 */

const MAX_BYTES = 2 * 1024 * 1024;

function formatBytes(bytes) {
  if (bytes >= 1024 * 1024) {
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  }

  return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

export function initProfile() {
  const nameInput = document.querySelector('[data-display-name]');
  const nameCount = document.querySelector('[data-display-name-count]');

  if (nameInput && nameCount) {
    const syncCount = () => {
      nameCount.textContent = String(nameInput.value.length);
    };

    nameInput.addEventListener('input', syncCount);
    syncCount();
  }

  const reportDetails = document.querySelector('[data-report-details]');
  const reportCount = document.querySelector('[data-report-count]');

  if (reportDetails && reportCount) {
    const syncReportCount = () => {
      reportCount.textContent = String(reportDetails.value.length);
    };

    reportDetails.addEventListener('input', syncReportCount);
    syncReportCount();
  }

  const input = document.querySelector('[data-avatar-input]');
  const nameLabel = document.querySelector('[data-avatar-name]');
  const preview = document.querySelector('[data-avatar-preview]');
  const fallback = document.querySelector('[data-avatar-fallback]');

  if (!input) {
    return;
  }

  input.addEventListener('change', () => {
    const file = input.files?.[0];

    if (!file) {
      if (nameLabel) {
        nameLabel.textContent = 'Choose an image';
      }

      return;
    }

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      if (nameLabel) {
        nameLabel.textContent = 'Please choose a JPEG, PNG, or WebP image';
      }

      input.value = '';
      return;
    }

    if (file.size > MAX_BYTES) {
      if (nameLabel) {
        nameLabel.textContent = `That file is ${formatBytes(file.size)} — the limit is 2 MB`;
      }

      input.value = '';
      return;
    }

    if (nameLabel) {
      nameLabel.textContent = `${file.name} · ${formatBytes(file.size)}`;
    }

    // FileReader keeps the preview compatible with the app's CSP
    // (`img-src 'self' data:`) without relaxing it for blob: URLs.
    if (preview && typeof FileReader === 'function') {
      const reader = new FileReader();

      reader.addEventListener('load', () => {
        if (typeof reader.result === 'string') {
          preview.src = reader.result;
          preview.classList.remove('hidden');
          fallback?.classList.add('hidden');
        }
      });

      reader.readAsDataURL(file);
    }
  });
}
