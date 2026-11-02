import {
  createIcons,
  ArrowRight,
  CalendarCheck,
  Check,
  ChevronRight,
  CircleCheck,
  Crown,
  Flame,
  LineChart,
  Medal,
  Menu,
  ShieldCheck,
  Sparkles,
  Star,
  Swords,
  Target,
  TrendingUp,
  Trophy,
  UserPlus,
  X,
  Zap,
} from 'lucide';

/**
 * Lucide is bundled from npm and only the icons we actually use are included.
 * Add new icons here when a view starts using them.
 */
const iconSet = {
  ArrowRight,
  CalendarCheck,
  Check,
  ChevronRight,
  CircleCheck,
  Crown,
  Flame,
  LineChart,
  Medal,
  Menu,
  ShieldCheck,
  Sparkles,
  Star,
  Swords,
  Target,
  TrendingUp,
  Trophy,
  UserPlus,
  X,
  Zap,
};

export function initIcons() {
  createIcons({
    icons: iconSet,
    attrs: {
      'stroke-width': '1.75',
    },
  });
}
