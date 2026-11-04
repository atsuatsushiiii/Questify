// Code © 2026 Kevin Cyril Maraño, MIT license.

import { gsap } from 'gsap';

const prefersReducedMotion = () =>
  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Short entrance transition for full-page navigations.
 *
 * Questify is server-rendered, so every navigation reloads the document. This
 * fades the primary content in instead of animating between pages. The content
 * is already in its final state in the DOM; GSAP only moves toward it, so
 * reduced-motion users and browsers without JavaScript see it immediately.
 */
export function initPageTransitions() {
  if (prefersReducedMotion()) {
    return;
  }

  const targets = document.querySelectorAll('[data-page-transition]');

  if (targets.length === 0) {
    return;
  }

  gsap.from(targets, {
    autoAlpha: 0,
    y: 10,
    duration: 0.35,
    ease: 'power2.out',
    clearProps: 'opacity,visibility,transform',
  });
}
