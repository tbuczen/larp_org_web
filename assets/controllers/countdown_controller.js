import { Controller } from '@hotwired/stimulus';

/* Live countdown for the "countdown" page block. */
export default class extends Controller {
    static values = { target: String };
    static targets = ['days', 'hours', 'minutes', 'seconds'];

    connect() {
        this.end = Date.parse(this.targetValue);
        this.tick();
        this.timer = setInterval(() => this.tick(), 1000);
    }

    disconnect() {
        clearInterval(this.timer);
    }

    tick() {
        const left = Math.max(0, Math.floor((this.end - Date.now()) / 1000));
        const parts = { days: Math.floor(left / 86400), hours: Math.floor(left % 86400 / 3600), minutes: Math.floor(left % 3600 / 60), seconds: left % 60 };
        for (const [unit, value] of Object.entries(parts)) {
            if (this[`has${unit[0].toUpperCase()}${unit.slice(1)}Target`]) this[`${unit}Target`].textContent = String(value);
        }
        if (left === 0) clearInterval(this.timer);
    }
}
