/*
 * Admin panel scripts (loaded by EasyAdmin via Assets::addAssetMapperEntry('admin')).
 *  - drag-and-drop ordering for lists marked with data-sortable-url (page blocks, menu items)
 *  - gallery photos: ordering inside the save form, and the bulk uploader
 */
import Sortable from 'sortablejs';

function initSortable(list) {
    if (list.dataset.sortableReady) {
        return;
    }
    list.dataset.sortableReady = '1';
    const status = list.parentElement.querySelector('.block-list__status');

    Sortable.create(list, {
        handle: '.block-item__handle',
        animation: 150,
        ghostClass: 'is-ghost',
        onEnd: async () => {
            const ids = [...list.children].map((item) => item.dataset.id);
            if (status) status.textContent = '…';
            try {
                const response = await fetch(list.dataset.sortableUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': list.dataset.csrfToken },
                    body: JSON.stringify({ ids }),
                });
                if (!response.ok) throw new Error(String(response.status));
                if (status) status.textContent = list.dataset.savedLabel || '✓';
                // Reload so the up/down buttons and positions match the new order.
                window.location.reload();
            } catch (e) {
                if (status) status.textContent = '⚠ ' + e.message;
            }
        },
    });
}

// Gallery photos: the new order is saved with the form (inputs are submitted in DOM order).
function initPhotoSortable(list) {
    const renumber = () => {
        [...list.children].forEach((item, i) => {
            const number = item.querySelector('.photo-manager__number');
            if (number) number.textContent = String(i + 1);
            const cover = item.querySelector('.photo-manager__cover');
            if (cover && i !== 0) cover.remove();
        });
    };
    Sortable.create(list, {
        handle: '.photo-manager__media',
        animation: 150,
        ghostClass: 'is-ghost',
        onEnd: renumber,
    });
}

/*
 * Bulk photo upload: any number of files, sent one per request (three at a time),
 * so server limits on request size and file count don't matter. Big photos are
 * shrunk in the browser first (long side ≤ data-max-size px, file ≤ data-max-bytes).
 */
async function shrink(file, maxSize, maxBytes) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || !('createImageBitmap' in window)) return file;
    let bitmap;
    try {
        bitmap = await createImageBitmap(file); // applies EXIF orientation
    } catch {
        return file;
    }
    const longSide = Math.max(bitmap.width, bitmap.height);
    if (longSide <= maxSize && file.size <= maxBytes * 0.95) return file;

    let size = Math.min(maxSize, longSide);
    for (let attempt = 0; attempt < 6; attempt++) {
        const scale = size / longSide;
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const quality = attempt < 3 ? 0.88 - attempt * 0.08 : 0.72;
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
        if (blob && (blob.size <= maxBytes * 0.95 || attempt === 5)) {
            return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
        }
        if (attempt >= 2) size = Math.round(size * 0.8);
    }
    return file;
}

function initPhotoUpload(box) {
    const input = box.querySelector('[data-photo-upload-input]');
    const drop = box.querySelector('.photo-upload__drop');
    const panel = box.querySelector('.photo-upload__progress');
    const bar = panel.querySelector('.progress-bar');
    const status = panel.querySelector('.photo-upload__status');
    const errors = panel.querySelector('.photo-upload__errors');
    const maxSize = Number(box.dataset.maxSize) || 2560;
    const maxBytes = Number(box.dataset.maxBytes) || 2 * 1024 * 1024;

    async function send(file) {
        const prepared = await shrink(file, maxSize, maxBytes);
        const body = new FormData();
        body.append('photo', prepared);
        const response = await fetch(box.dataset.photoUploadUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': box.dataset.csrfToken },
            body,
        });
        if (!response.ok) {
            let message = String(response.status);
            try { message = (await response.json()).error || message; } catch { /* not JSON */ }
            throw new Error(message);
        }
    }

    async function upload(files) {
        files = [...files].filter((f) => f.type.startsWith('image/'));
        if (files.length === 0) return;
        panel.hidden = false;
        errors.replaceChildren();
        drop.classList.add('is-busy');
        let done = 0;
        let failed = 0;
        const update = () => {
            bar.style.width = `${Math.round(((done + failed) / files.length) * 100)}%`;
            status.textContent = `${done + failed} / ${files.length}`;
        };
        update();

        const queue = [...files];
        const worker = async () => {
            while (queue.length > 0) {
                const file = queue.shift();
                try {
                    await send(file);
                    done++;
                } catch (e) {
                    failed++;
                    const li = document.createElement('li');
                    li.textContent = `${file.name}: ${e.message}`;
                    errors.appendChild(li);
                }
                update();
            }
        };
        await Promise.all([worker(), worker(), worker()]);

        drop.classList.remove('is-busy');
        if (failed === 0) {
            status.textContent = box.dataset.labelDone || '✓';
            window.location.reload();
        } else {
            status.textContent = `${box.dataset.labelErrors || '⚠'} (${failed})`;
            if (done > 0) {
                const reload = document.createElement('button');
                reload.type = 'button';
                reload.className = 'btn btn-secondary btn-sm ms-2';
                reload.textContent = '↻';
                reload.addEventListener('click', () => window.location.reload());
                status.appendChild(reload);
            }
        }
    }

    input.addEventListener('change', () => { upload(input.files); input.value = ''; });
    ['dragenter', 'dragover'].forEach((type) => drop.addEventListener(type, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((type) => drop.addEventListener(type, () => drop.classList.remove('is-over')));
    drop.addEventListener('drop', (e) => { e.preventDefault(); upload(e.dataTransfer.files); });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-sortable-url]').forEach(initSortable);
    document.querySelectorAll('[data-photo-sortable]').forEach(initPhotoSortable);
    document.querySelectorAll('[data-photo-upload-url]').forEach(initPhotoUpload);
});
