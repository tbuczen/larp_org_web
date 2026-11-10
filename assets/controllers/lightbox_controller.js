import { Controller } from '@hotwired/stimulus';

/*
 * Full-screen photo viewer on a native <dialog>: arrows / swipe / keyboard to move,
 * Esc or a click beside the photo to close. Photos come as JSON:
 *   data-lightbox-photos-value='[{"src": "...", "caption": "..."}]'
 * and buttons open it at a photo:
 *   <button data-action="lightbox#open" data-lightbox-index-param="2">
 */
export default class extends Controller {
    static targets = ['dialog', 'image', 'caption', 'counter'];
    static values = { photos: Array };

    connect() {
        this.index = 0;
        this.dialogTarget.addEventListener('pointerdown', (e) => { this.startX = e.clientX; });
        this.dialogTarget.addEventListener('pointerup', (e) => {
            const dx = e.clientX - (this.startX ?? e.clientX);
            if (Math.abs(dx) > 50) {
                this.swiped = true;
                dx < 0 ? this.next() : this.prev();
            }
        });
    }

    open({ params }) {
        this.opener = document.activeElement;
        this.show(params.index ?? 0);
        this.dialogTarget.showModal();
        document.documentElement.classList.add('has-lightbox');
    }

    close() {
        this.dialogTarget.close();
        document.documentElement.classList.remove('has-lightbox');
        this.opener?.focus?.({ preventScroll: true });
    }

    prev() { this.show(this.index - 1); }

    next() { this.show(this.index + 1); }

    key(event) {
        if (event.key === 'ArrowLeft') this.prev();
        if (event.key === 'ArrowRight') this.next();
        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
        }
    }

    // A click on the dark area around the photo closes the viewer (not after a swipe).
    backdrop(event) {
        if (this.swiped) {
            this.swiped = false;
            return;
        }
        if (event.target === this.dialogTarget || event.target.classList.contains('lightbox__figure')) this.close();
    }

    show(index) {
        const photos = this.photosValue;
        if (photos.length === 0) return;
        this.index = (index + photos.length) % photos.length;
        const photo = photos[this.index];

        this.imageTarget.classList.remove('is-loaded');
        this.imageTarget.onload = () => this.imageTarget.classList.add('is-loaded');
        this.imageTarget.src = photo.src;
        this.imageTarget.alt = photo.caption || '';
        this.captionTarget.textContent = photo.caption || '';
        this.captionTarget.hidden = !photo.caption;
        this.counterTarget.textContent = photos.length > 1 ? `${this.index + 1} / ${photos.length}` : '';

        // Warm the cache for the neighbours, so moving on is instant.
        [this.index + 1, this.index - 1].forEach((i) => {
            new Image().src = photos[(i + photos.length) % photos.length].src;
        });
    }
}
