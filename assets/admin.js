/*
 * Admin panel scripts (loaded by EasyAdmin via Assets::addAssetMapperEntry('admin')).
 *  - drag-and-drop ordering for lists marked with data-sortable-url (page blocks, menu items)
 *  - gallery photos: ordering inside the save form, and the bulk uploader
 *  - LARP colours: colour pickers next to the #rrggbb fields, live preview and contrast check
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

/*
 * LARP colour scheme (Larp form, "Kolory" tab). The text fields stay the source of
 * truth (empty = preset / theme colour); a picker sits next to each, and a preview
 * card shows the result with WCAG contrast ratios (same maths as App\Theme\LarpColors).
 */
const hexRe = /^#[0-9a-f]{6}$/i;
const rgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
const luminance = (hex) => {
    const [r, g, b] = rgb(hex).map((v) => {
        const c = v / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const contrast = (a, b) => {
    const [la, lb] = [luminance(a), luminance(b)];
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
};
const mix = (a, b, t) => '#' + rgb(a).map((v, i) => Math.round(v + (rgb(b)[i] - v) * t).toString(16).padStart(2, '0')).join('');

function initLarpColors(form) {
    const preset = form.querySelector('[data-color-presets]');
    const inputs = [...form.querySelectorAll('[data-color-input]')];
    if (!preset || inputs.length === 0) return;
    const presets = JSON.parse(preset.dataset.colorPresets || '{}');
    const field = (name) => inputs.find((i) => i.dataset.colorInput === name);

    const effective = () => {
        const base = presets[preset.value] || {};
        const out = {};
        inputs.forEach((input) => {
            const own = input.value.trim().toLowerCase();
            out[input.dataset.colorInput] = hexRe.test(own) ? own : (base[input.dataset.colorInput] || null);
        });
        return out;
    };

    // Picker + clear button next to each field.
    inputs.forEach((input) => {
        const picker = document.createElement('input');
        picker.type = 'color';
        picker.className = 'color-picker';
        picker.setAttribute('aria-label', input.labels?.[0]?.textContent?.trim() || input.dataset.colorInput);
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'btn btn-link color-clear';
        clear.textContent = '×';
        clear.title = preset.dataset.clearLabel || 'Reset';
        const wrap = document.createElement('div');
        wrap.className = 'color-field';
        input.parentNode.insertBefore(wrap, input);
        wrap.append(picker, input, clear);
        picker.addEventListener('input', () => { input.value = picker.value; update(); });
        clear.addEventListener('click', () => { input.value = ''; update(); });
        input.addEventListener('input', update);
    });
    preset.addEventListener('change', update);

    // Preview card after the colour fields.
    const preview = document.createElement('div');
    preview.className = 'larp-color-preview';
    preview.innerHTML = `
        <div class="larp-color-preview__card">
            <small class="larp-color-preview__eyebrow">Larp terenowy</small>
            <strong class="larp-color-preview__title"></strong>
            <span class="larp-color-preview__muted">6–9.VIII · Czyżowice</span>
            <span class="larp-color-preview__panel">Postać: Jarl Ulfgar</span>
            <span><span class="larp-color-preview__button">Zapisz się</span> <span class="larp-color-preview__accent">●</span></span>
        </div>
        <ul class="larp-color-preview__checks"></ul>`;
    preview.querySelector('.larp-color-preview__title').textContent = form.querySelector('input[name$="[name]"]')?.value || 'Larp';
    const lastRow = inputs[inputs.length - 1].closest('.form-group, .field-text, [class*="col-"]');
    (lastRow?.parentNode || form).insertBefore(preview, lastRow?.nextSibling || null);

    function update() {
        const c = effective();
        inputs.forEach((input) => {
            const picker = input.parentNode.querySelector('.color-picker');
            const own = hexRe.test(input.value.trim()) ? input.value.trim() : null;
            picker.value = own || c[input.dataset.colorInput] || '#000000';
            picker.classList.toggle('is-inherited', !own);
            input.placeholder = (presets[preset.value] || {})[input.dataset.colorInput] || '#rrggbb';
        });
        const card = preview.querySelector('.larp-color-preview__card');
        const checks = preview.querySelector('.larp-color-preview__checks');
        const bg = c.background || '#111318';
        const text = c.text || '#eceef3';
        const primary = c.primary || text;
        card.style.background = c.secondary || mix(bg, text, 0.05);
        card.style.color = text;
        card.style.outline = `12px solid ${bg}`;
        card.querySelector('.larp-color-preview__muted').style.color = mix(text, bg, 0.32);
        card.querySelector('.larp-color-preview__panel').style.background = mix(c.secondary || mix(bg, text, 0.05), text, 0.07);
        const button = card.querySelector('.larp-color-preview__button');
        button.style.background = primary;
        button.style.color = contrast(primary, '#0a0a0a') >= contrast(primary, '#ffffff') ? '#0a0a0a' : '#ffffff';
        card.querySelector('.larp-color-preview__accent').style.color = c.accent || primary;
        card.querySelector('.larp-color-preview__eyebrow').style.color = c.accent || mix(text, bg, 0.32);

        checks.replaceChildren();
        if (!c.background && !c.text && !c.primary && !c.accent && !c.secondary) {
            const li = document.createElement('li');
            li.textContent = preset.dataset.themeLabel || '—';
            checks.appendChild(li);
            return;
        }
        [['text', 4.5], ['primary', 3], ['accent', 3]].forEach(([name, min]) => {
            if (!c[name] || !c.background) return;
            const ratio = contrast(c[name], c.background);
            const li = document.createElement('li');
            li.className = ratio >= min ? 'is-ok' : 'is-low';
            li.textContent = `${field(name)?.labels?.[0]?.textContent?.trim() || name}: ${ratio.toFixed(1)}:1 ${ratio >= min ? '✓' : '⚠ min. ' + min + ':1'}`;
            checks.appendChild(li);
        });
    }
    update();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach((form) => {
        if (form.querySelector('[data-color-presets]')) initLarpColors(form);
    });
    document.querySelectorAll('[data-sortable-url]').forEach(initSortable);
    document.querySelectorAll('[data-photo-sortable]').forEach(initPhotoSortable);
    document.querySelectorAll('[data-photo-upload-url]').forEach(initPhotoUpload);
});
