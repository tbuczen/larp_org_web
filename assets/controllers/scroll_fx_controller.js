import { Controller } from '@hotwired/stimulus';

/*
 * Scroll-linked motion for any element: while it's on screen, sets CSS variables
 * on it that stylesheets turn into movement.
 *
 *   --p     0 → 1   the element enters at the bottom (0) and leaves at the top (1)
 *   --pc   -1 → 1   the same, centred: 0 when the element is mid-screen
 *   --exit  0 → 1   how far the element has scrolled off the top (for heroes)
 *
 * Adds "fx-ready" on connect and "is-inview" once the element is first seen, so
 * CSS can hide things only when JavaScript will reveal them.
 *
 * Usage: <section data-controller="scroll-fx"> and e.g.
 *   .thing { transform: translateY(calc(var(--pc, 0) * 40px)); }
 *
 * Does nothing for visitors who prefer reduced motion.
 */
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const active = new Set();
let queued = false;

const clamp = (v) => Math.min(1, Math.max(0, v));

function frame() {
    queued = false;
    const vh = window.innerHeight;
    active.forEach((c) => c.measure(vh));
}

function schedule() {
    if (!queued) {
        queued = true;
        window.requestAnimationFrame(frame);
    }
}

function track(controller, on) {
    const before = active.size;
    on ? active.add(controller) : active.delete(controller);
    if (before === 0 && active.size > 0) {
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule, { passive: true });
    } else if (before > 0 && active.size === 0) {
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
    }
}

export default class extends Controller {
    connect() {
        if (reduceMotion.matches || !('IntersectionObserver' in window)) return;

        this.element.classList.add('fx-ready');
        this.measure(window.innerHeight);

        // Motion runs only while the element is (nearly) on screen.
        this.motion = new IntersectionObserver(([entry]) => {
            track(this, entry.isIntersecting);
            if (entry.isIntersecting) this.measure(window.innerHeight);
        }, { rootMargin: '15% 0px' });
        this.motion.observe(this.element);

        // Reveals start once a bit of the element is really visible.
        this.reveal = new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) {
                this.element.classList.add('is-inview');
                this.reveal.disconnect();
            }
        }, { rootMargin: '0px 0px -12% 0px' });
        this.reveal.observe(this.element);
    }

    disconnect() {
        this.motion?.disconnect();
        this.reveal?.disconnect();
        track(this, false);
    }

    measure(vh) {
        const rect = this.element.getBoundingClientRect();
        const p = clamp((vh - rect.top) / (vh + rect.height));
        const style = this.element.style;
        style.setProperty('--p', p.toFixed(4));
        style.setProperty('--pc', (p * 2 - 1).toFixed(4));
        style.setProperty('--exit', clamp(-rect.top / Math.max(rect.height, 1)).toFixed(4));
    }
}
