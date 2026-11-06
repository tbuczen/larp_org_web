import { Controller } from '@hotwired/stimulus';

/*
 * Public site header: opens/closes the menu on small screens and keeps at most
 * one submenu (<details>) open at a time. Without JavaScript the menu is simply
 * shown expanded (see .site-nav in base.css).
 */
export default class extends Controller {
    static targets = ['button', 'menu', 'submenu'];

    connect() {
        this.element.classList.add('js-nav');
        this.onKeydown = (event) => { if (event.key === 'Escape') this.close(); };
        this.onClickOutside = (event) => {
            if (!this.element.contains(event.target)) this.submenuTargets.forEach((d) => { d.open = false; });
        };
        document.addEventListener('keydown', this.onKeydown);
        document.addEventListener('click', this.onClickOutside);
        this.submenuTargets.forEach((details) => details.addEventListener('toggle', () => {
            if (details.open) this.submenuTargets.forEach((other) => { if (other !== details) other.open = false; });
        }));
    }

    disconnect() {
        document.removeEventListener('keydown', this.onKeydown);
        document.removeEventListener('click', this.onClickOutside);
    }

    toggle() {
        const open = this.buttonTarget.getAttribute('aria-expanded') !== 'true';
        this.buttonTarget.setAttribute('aria-expanded', String(open));
        this.element.classList.toggle('is-open', open);
        document.body.classList.toggle('nav-open', open);
    }

    close() {
        this.buttonTarget.setAttribute('aria-expanded', 'false');
        this.element.classList.remove('is-open');
        document.body.classList.remove('nav-open');
        this.submenuTargets.forEach((d) => { d.open = false; });
    }
}
