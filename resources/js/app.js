import { initAnimations } from './modules/animations';
import { initAuth } from './modules/auth';
import { initIcons } from './modules/icons';
import { initNav } from './modules/nav';

function boot() {
  initIcons();
  initNav();
  initAuth();
  initAnimations();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}
