import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/*
 * Remembers the matcher's choices (the page's query string) in localStorage and
 * restores them when the visitor comes back to the same page without any, e.g.
 * after closing the tab by accident. A link that already has choices in it (a
 * shared result) always wins.
 *
 * Saved after every change, not only when leaving: phones often kill a tab in
 * the background without any "page closed" event. Saved choices expire 30 days
 * after the last change. After a restore, the notice target is shown ("your
 * choices were restored · start over").
 *
 * Usage: <div data-controller="remember-query"> … <p data-remember-query-target="notice" hidden>
 */
const RESTORED_FLAG = 'remember-query:restored';
const MAX_AGE_MS = 30 * 24 * 60 * 60 * 1000;

export default class extends Controller {
    static targets = ['notice'];

    connect() {
        this.key = `query:${window.location.pathname}`;
        this.save = this.save.bind(this);
        this.saveWhenHidden = () => document.visibilityState === 'hidden' && this.save();
        window.addEventListener('pagehide', this.save);
        document.addEventListener('visibilitychange', this.saveWhenHidden);

        const stored = this.read();
        if (!window.location.search && stored) {
            // Leaving this empty page must not "save" its empty choices over the stored ones.
            this.restoring = true;
            this.flag(window.location.pathname);
            window.location.replace(window.location.pathname + stored);

            return;
        }

        if (this.flag() === window.location.pathname && window.location.search) {
            this.showNotice();
        }
        this.flag(null);

        getComponent(this.element).then((component) => {
            component.on('render:finished', () => {
                this.save();
                if (!window.location.search) {
                    this.dismiss();
                }
            });
        }).catch(() => {});
    }

    disconnect() {
        this.save();
        window.removeEventListener('pagehide', this.save);
        document.removeEventListener('visibilitychange', this.saveWhenHidden);
    }

    save() {
        if (this.restoring) {
            return;
        }
        try {
            const query = window.location.search;
            if (query) {
                // Unchanged choices keep their date: the 30 days count from the last change, not the last visit.
                if (query !== this.read()) {
                    localStorage.setItem(this.key, JSON.stringify({ query, savedAt: Date.now() }));
                }
            } else {
                localStorage.removeItem(this.key);
            }
        } catch {
            // Storage unavailable (private mode, blocked): the URL still works.
        }
    }

    dismiss() {
        if (this.hasNoticeTarget) {
            this.noticeTarget.hidden = true;
        }
    }

    showNotice() {
        if (this.hasNoticeTarget) {
            this.noticeTarget.hidden = false;
        }
    }

    /** The saved query string, or null when there is none or it is older than 30 days. */
    read() {
        try {
            const raw = localStorage.getItem(this.key);
            if (!raw) {
                return null;
            }
            let entry;
            try {
                entry = JSON.parse(raw);
            } catch {
                entry = null;
            }
            // Saved before expiry existed (a bare query string): keep it and date it from now.
            if (typeof entry !== 'object' || entry === null) {
                if (!raw.startsWith('?')) {
                    return null;
                }
                localStorage.setItem(this.key, JSON.stringify({ query: raw, savedAt: Date.now() }));

                return raw;
            }
            if (typeof entry.query !== 'string' || !(Date.now() - entry.savedAt < MAX_AGE_MS)) {
                localStorage.removeItem(this.key);

                return null;
            }

            return entry.query;
        } catch {
            return null;
        }
    }

    /** Remembers across the reload that the choices were just restored. */
    flag(value) {
        try {
            if (value === undefined) {
                return sessionStorage.getItem(RESTORED_FLAG);
            }
            if (value === null) {
                sessionStorage.removeItem(RESTORED_FLAG);
            } else {
                sessionStorage.setItem(RESTORED_FLAG, value);
            }
        } catch {
            // No session storage: no notice, the choices are restored all the same.
        }

        return null;
    }
}
