import { Controller } from '@hotwired/stimulus';

/*
 * Narrows the matcher's gameplay rows and triggers to tags whose name contains
 * the typed text (no case, no diacritics: "smier" finds "Śmierć"). Rows carry
 * data-tag-search with the name as an ASCII slug.
 *
 * The matcher re-renders on the server after every choice, so the filter is a
 * <style> rule (re-renders don't touch it), and trigger groups with matches are
 * opened again after each re-render.
 */
const toKey = (text) => text
    .toLowerCase()
    .replace(/ł/g, 'l')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

export default class extends Controller {
    static targets = ['input', 'none'];

    connect() {
        this.style = document.createElement('style');
        document.head.appendChild(this.style);
        this.matcher = this.element.closest('.matcher') || document;
        this.observer = new MutationObserver(() => this.openMatches());
        this.observer.observe(this.matcher, { childList: true, subtree: true });
        if (this.inputTarget.value) this.filter();
    }

    disconnect() {
        this.observer.disconnect();
        this.style.remove();
    }

    filter() {
        this.key = toKey(this.inputTarget.value);
        this.style.textContent = this.key
            ? `.matcher [data-tag-search]:not([data-tag-search*="${CSS.escape(this.key)}"]) { display: none !important; }`
            : '';
        this.openMatches();
    }

    openMatches() {
        if (!this.key) {
            this.noneTarget.hidden = true;
            return;
        }
        const matches = [...this.matcher.querySelectorAll(`[data-tag-search*="${CSS.escape(this.key)}"]`)];
        matches.forEach((el) => {
            const group = el.closest('details');
            if (group && !group.open) group.open = true;
        });
        this.noneTarget.hidden = matches.length > 0;
    }
}
