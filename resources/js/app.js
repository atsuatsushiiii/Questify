import { initAnimations } from './modules/animations';
import { initIcons } from './modules/icons';
import { initNav } from './modules/nav';

function boot() {
  initIcons();
  initNav();
  initAnimations();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}
