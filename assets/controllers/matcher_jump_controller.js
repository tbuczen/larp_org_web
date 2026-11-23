import { Controller } from '@hotwired/stimulus';

/*
 * On phones the matcher is one long column: preferences, then results. A
 * floating button jumps between them: "Zobacz postacie" while the results are
 * below the screen, "Zmień wybory" while reading the results.
 *
 * The state lives on <html data-matcher-jump="down|up">, outside the live
 * component, so re-renders don't reset it. CSS shows the buttons on narrow
 * screens only.
 *
 * Usage: <div data-controller="matcher-jump"> with .matcher__prefs and
 * .matcher__results inside, and buttons with data-action="matcher-jump#toResults"
 * / "matcher-jump#toPrefs".
 */
export default class extends Controller {
    connect() {
        this.visible = new Map();
        this.observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                this.visible.set(entry.target.className.includes('results') ? 'results' : 'prefs', {
                    shown: entry.isIntersecting,
                    above: entry.boundingClientRect.top < 0,
                });
            }
            this.update();
        }, { rootMargin: '-15% 0px -25% 0px' });

        this.observed = [this.element.querySelector('.matcher__prefs'), this.element.querySelector('.matcher__results')].filter(Boolean);
        this.observed.forEach((el) => this.observer.observe(el));
    }

    disconnect() {
        this.observer?.disconnect();
        delete document.documentElement.dataset.matcherJump;
    }

    update() {
        const prefs = this.visible.get('prefs');
        const results = this.visible.get('results');
        let state = '';
        if (prefs?.shown && !results?.shown) {
            state = 'down';
        } else if (results?.shown && !prefs?.shown) {
            state = 'up';
        }
        if (state) {
            document.documentElement.dataset.matcherJump = state;
        } else {
            delete document.documentElement.dataset.matcherJump;
        }
    }

    toResults(event) {
        event.preventDefault();
        this.element.querySelector('.matcher__results')?.scrollIntoView({ behavior: this.behavior(), block: 'start' });
    }

    toPrefs(event) {
        event.preventDefault();
        this.element.querySelector('.matcher__prefs')?.scrollIntoView({ behavior: this.behavior(), block: 'start' });
    }

    behavior() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
    }
}
