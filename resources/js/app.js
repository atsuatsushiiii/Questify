import { initAnimations } from './modules/animations';
import { initAuth } from './modules/auth';
import { initIcons } from './modules/icons';
import { initNav } from './modules/nav';
import { initNotifications } from './modules/notifications';
import { initProfile } from './modules/profile';
import { initQuests } from './modules/quests';
import { initSettings } from './modules/settings';
import { initShell } from './modules/shell';

function boot() {
  initIcons();
  initNav();
  initAuth();
  initShell();
  initQuests();
  initNotifications();
  initProfile();
  initSettings();
  initAnimations();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}
