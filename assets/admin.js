/*
 * Admin panel scripts (loaded by EasyAdmin via Assets::addAssetMapperEntry('admin')).
 * Drag-and-drop ordering for lists marked with data-sortable-url (page blocks, menu items).
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

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-sortable-url]').forEach(initSortable);
});
