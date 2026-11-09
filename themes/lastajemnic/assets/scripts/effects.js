/*
 * Las Tajemnic — small progressive enhancements (no dependencies):
 *  1. floating spores / fireflies in hero banners
 *  2. sections fade in as they scroll into view
 * Does nothing for visitors who prefer reduced motion.
 */
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function addMotes(hero) {
    if (hero.querySelector('.lt-motes')) return;
    const layer = document.createElement('div');
    layer.className = 'lt-motes';
    layer.setAttribute('aria-hidden', 'true');
    // Mostly bone-white spores, a few moss-green fireflies.
    const colours = ['var(--color-text)', 'var(--color-text)', 'var(--color-accent)'];
    const count = window.innerWidth < 600 ? 10 : 18;
    for (let i = 0; i < count; i++) {
        const mote = document.createElement('span');
        mote.className = 'lt-mote';
        const r = (min, max) => (min + Math.random() * (max - min)).toFixed(1);
        mote.style.cssText = [
            `--x:${r(35, 100)}%`, `--y:${r(30, 95)}%`, `--s:${r(1, 2.6)}px`,
            `--d:${r(7, 14)}s`, `--delay:${r(-14, 0)}s`, `--dx:${r(-40, 40)}px`, `--dy:${r(-140, -40)}px`,
            `--c:${colours[i % colours.length]}`,
        ].join(';');
        layer.appendChild(mote);
    }
    hero.appendChild(layer);
}

function revealOnScroll() {
    if (!('IntersectionObserver' in window)) return;
    const targets = document.querySelectorAll('.block:not(.block--hero) > .container, .larp-section, .match-card');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px' });
    targets.forEach((el) => {
        // Only animate what is still below the fold, so nothing visible blinks on load.
        if (el.getBoundingClientRect().top > window.innerHeight) {
            el.classList.add('lt-reveal');
            observer.observe(el);
        }
    });
}

function init() {
    if (reduceMotion) return;
    document.querySelectorAll('.hero').forEach(addMotes);
    revealOnScroll();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
// Turbo navigations
document.addEventListener('turbo:load', init);
