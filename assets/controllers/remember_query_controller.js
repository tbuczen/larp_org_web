import { Controller } from '@hotwired/stimulus';

/*
 * Remembers the page's query string (the matcher's choices) in localStorage and
 * restores it when the visitor comes back to the same page without one.
 * A link that already has choices in it (a shared result) always wins.
 *
 * Usage: <div data-controller="remember-query">
 */
export default class extends Controller {
    connect() {
        this.key = `query:${window.location.pathname}`;
        this.save = this.save.bind(this);
        window.addEventListener('pagehide', this.save);

        const stored = this.read();
        if (!window.location.search && stored) {
            window.location.replace(window.location.pathname + stored);
        }
    }

    disconnect() {
        this.save();
        window.removeEventListener('pagehide', this.save);
    }

    save() {
        try {
            if (window.location.search) {
                localStorage.setItem(this.key, window.location.search);
            } else {
                localStorage.removeItem(this.key);
            }
        } catch {
            // Storage unavailable (private mode, blocked): the URL still works.
        }
    }

    read() {
        try {
            return localStorage.getItem(this.key);
        } catch {
            return null;
        }
    }
}
