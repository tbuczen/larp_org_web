import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/*
 * Remembers the matcher's choices (the page's query string) in localStorage and
 * restores them when the visitor comes back to the same page without any, e.g.
 * after closing the tab by accident. A link that already has choices in it (a
 * shared result) always wins.
 *
 * Saved after every change, not only when leaving: phones often kill a tab in
 * the background without any "page closed" event. After a restore, the notice
 * target is shown ("your choices were restored · start over").
 *
 * Usage: <div data-controller="remember-query"> … <p data-remember-query-target="notice" hidden>
 */
const RESTORED_FLAG = 'remember-query:restored';

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

    read() {
        try {
            return localStorage.getItem(this.key);
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
