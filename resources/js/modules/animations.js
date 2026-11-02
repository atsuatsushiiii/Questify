import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const prefersReducedMotion = () =>
  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Marketing-page motion. Every element exists in its final state in the DOM;
 * GSAP only animates toward it, so nothing is hidden if JS fails.
 */
export function initAnimations() {
  if (prefersReducedMotion()) {
    return;
  }

  const hero = document.querySelector('[data-hero]');

  if (hero) {
    const timeline = gsap.timeline({
      defaults: { ease: 'power3.out', duration: 0.8 },
    });

    timeline
      .from('[data-hero-eyebrow]', { y: 16, autoAlpha: 0, duration: 0.6 })
      .from(
        '[data-hero-title] .hero-line',
        { y: 30, autoAlpha: 0, stagger: 0.09 },
        '-=0.4',
      )
      .from('[data-hero-copy]', { y: 18, autoAlpha: 0 }, '-=0.55')
      .from(
        '[data-hero-actions] > *',
        { y: 14, autoAlpha: 0, stagger: 0.08, duration: 0.6 },
        '-=0.5',
      )
      .from('[data-hero-proof]', { y: 10, autoAlpha: 0, duration: 0.5 }, '-=0.45')
      .from(
        '[data-hero-panel]',
        { y: 36, autoAlpha: 0, duration: 1, ease: 'power2.out' },
        '-=0.9',
      )
      .from(
        '[data-hero-float]',
        { y: 16, autoAlpha: 0, duration: 0.7 },
        '-=0.45',
      );
  }

  document.querySelectorAll('[data-meter]').forEach((element) => {
    const target = Number(element.dataset.value || 0);
    const type = element.dataset.meter;

    if (type === 'width') {
      gsap.fromTo(
        element,
        { width: '0%' },
        {
          width: `${target}%`,
          duration: 1.5,
          ease: 'power3.out',
          delay: 0.6,
        },
      );
    }

    if (type === 'ring') {
      const circumference = Number(element.getAttribute('stroke-dasharray') || 176);

      gsap.fromTo(
        element,
        { strokeDashoffset: circumference },
        {
          strokeDashoffset: circumference * (1 - target / 100),
          duration: 1.7,
          ease: 'power3.out',
          delay: 0.6,
        },
      );
    }
  });

  // The server renders the final value; GSAP counts up to it, so the number is
  // still correct when motion is reduced or JavaScript never runs.
  document.querySelectorAll('[data-counter]').forEach((element) => {
    const target = Number(element.dataset.counter);

    if (!Number.isFinite(target) || target <= 0) {
      return;
    }

    const formatter =
      element.dataset.counterFormat === 'comma' ? new Intl.NumberFormat('en-US') : null;
    const state = { value: 0 };

    gsap.to(state, {
      value: target,
      duration: 1.1,
      ease: 'power2.out',
      delay: 0.35,
      onUpdate: () => {
        const value = Math.round(state.value);
        element.textContent = formatter ? formatter.format(value) : String(value);
      },
      onComplete: () => {
        element.textContent = formatter ? formatter.format(target) : String(target);
      },
    });
  });

  document.querySelectorAll('[data-reveal]').forEach((element, index) => {
    gsap.from(element, {
      y: 28,
      autoAlpha: 0,
      duration: 0.75,
      ease: 'power3.out',
      delay: (index % 3) * 0.06,
      scrollTrigger: {
        trigger: element,
        start: 'top 88%',
        once: true,
      },
    });
  });

  const orbs = document.querySelectorAll('[data-orb]');

  if (orbs.length > 0) {
    gsap.to(orbs, {
      yPercent: 10,
      ease: 'none',
      scrollTrigger: {
        trigger: hero,
        start: 'top top',
        end: 'bottom top',
        scrub: 1,
      },
    });
  }
}
