import * as Turbo from '@hotwired/turbo';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.css';
import Toastify from 'toastify-js';
import 'toastify-js/src/toastify.css';

/* ------------------------------------------------------------------ confirmation modal */

/**
 * Any form with `data-turbo-confirm="Question?"` asks first in a SweetAlert2 modal.
 * Optional: data-confirm-title, data-confirm-button (button label), data-confirm-tone="danger" (red button).
 */
Turbo.config.forms.confirm = async (message, form) => {
    const danger = form.dataset.confirmTone === 'danger';
    const result = await Swal.fire({
        title: form.dataset.confirmTitle ?? 'Are you sure?',
        text: message,
        icon: danger ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButton ?? 'Yes, continue',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        focusCancel: danger,
        buttonsStyling: false,
        customClass: {
            popup: 'swal-flat',
            actions: 'swal-flat-actions',
            confirmButton: danger ? 'btn btn-danger' : 'btn btn-primary',
            cancelButton: 'btn btn-secondary',
        },
    });

    return result.isConfirmed;
};

/* ------------------------------------------------------------------ toasts */

const toastIcons = {
    success: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    error: 'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z',
    info: 'm11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
};

/** Show a small notice in the top-right corner: type is success, error or info. */
export function toast(message, type = 'success') {
    const node = document.createElement('div');
    node.className = 'toast-body';
    node.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="${toastIcons[type] ?? toastIcons.info}"/></svg><span></span>`;
    node.querySelector('span').textContent = message;

    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.className = 'toast-dismiss';
    dismiss.setAttribute('aria-label', 'Close');
    dismiss.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>';
    node.append(dismiss);

    const instance = Toastify({
        node,
        duration: type === 'error' ? 6000 : 4000,
        gravity: 'top',
        position: 'right',
        close: false,
        stopOnFocus: true,
        className: `toast-flat toast-${type}`,
        style: { background: '' },
        offset: { x: 0, y: 16 },
    }).showToast();
    dismiss.addEventListener('click', () => instance.hideToast());
}

window.toast = toast;

// Messages the server leaves for the next page (<x-flash>): show each once, then remove it so
// going Back to a cached page doesn't show it again.
document.addEventListener('turbo:load', () => {
    for (const flash of document.querySelectorAll('[data-flash]')) {
        toast(flash.dataset.message, flash.dataset.type);
        flash.remove();
    }
});
