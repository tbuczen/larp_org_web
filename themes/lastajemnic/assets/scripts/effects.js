/*
 * Las Tajemnic — small progressive enhancements (no dependencies):
 *  1. floating spores / fireflies in hero banners
 *  2. hero titles rise word by word
 *  3. a "lantern" light following the cursor over the hero
 *  4. sections fade in as they scroll into view; cards follow one another
 *  5. reading progress as a thin line under the header
 * Parallax and galleries come from the base theme (controllers/scroll_fx_controller.js).
 * Does nothing for visitors who prefer reduced motion.
 */
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

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

// Wraps each word of the hero title so CSS can raise them one by one. Screen
// readers still get the plain title through aria-label.
function splitTitle(hero) {
    const title = hero.querySelector('.hero__title');
    if (!title || title.dataset.split) return;
    const text = title.textContent.trim();
    title.dataset.split = '1';
    title.setAttribute('aria-label', text);
    title.textContent = '';
    text.split(/\s+/).forEach((word, i) => {
        const outer = document.createElement('span');
        outer.className = 'lt-word';
        outer.setAttribute('aria-hidden', 'true');
        const inner = document.createElement('span');
        inner.style.setProperty('--w', i);
        inner.textContent = word;
        outer.appendChild(inner);
        title.append(outer, ' ');
    });
}

function addLantern(hero) {
    if (!finePointer || hero.querySelector('.lt-lantern')) return;
    const lantern = document.createElement('div');
    lantern.className = 'lt-lantern';
    lantern.setAttribute('aria-hidden', 'true');
    hero.appendChild(lantern);
    let queued = false;
    let x = 0;
    let y = 0;
    hero.addEventListener('pointermove', (event) => {
        const rect = hero.getBoundingClientRect();
        x = event.clientX - rect.left;
        y = event.clientY - rect.top;
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            lantern.style.setProperty('--mx', `${x}px`);
            lantern.style.setProperty('--my', `${y}px`);
        });
    });
}

function revealOnScroll() {
    if (!('IntersectionObserver' in window)) return;
    const targets = document.querySelectorAll('.block:not(.block--hero):not(.block--parallax-banner) > .container, .larp-section, .match-card');
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
            el.querySelectorAll('.card-grid > *').forEach((card, i) => card.style.setProperty('--i', i % 6));
            el.classList.add('lt-reveal');
            observer.observe(el);
        }
    });
}

// One listener for the whole visit (it survives Turbo navigations).
function trackScrollProgress() {
    if (window.ltScrollProgress) return;
    window.ltScrollProgress = true;
    const root = document.documentElement;
    let queued = false;
    const update = () => {
        queued = false;
        const max = root.scrollHeight - window.innerHeight;
        root.style.setProperty('--scroll', max > 0 ? (window.scrollY / max).toFixed(4) : '0');
    };
    const schedule = () => {
        if (!queued) {
            queued = true;
            requestAnimationFrame(update);
        }
    };
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
    document.addEventListener('turbo:load', schedule);
    update();
}

function init() {
    if (reduceMotion) return;
    document.querySelectorAll('.hero').forEach((hero) => {
        addMotes(hero);
        splitTitle(hero);
        addLantern(hero);
    });
    revealOnScroll();
    trackScrollProgress();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
// Turbo navigations
document.addEventListener('turbo:load', init);
