import './bootstrap';
import * as Turbo from '@hotwired/turbo';
import './alerts';

// Turbo loads the next page in the background and swaps it in, so moving between pages never reloads
// the whole site. Its thin loading bar only appears when a page takes a moment.
Turbo.config.drive.progressBarDelay = 200;

/**
 * Auto-format number inputs marked with `data-number`: thousands commas and at most 2 decimals,
 * e.g. typing 1250.5 shows "1,250.5". The server strips the commas before validating.
 */
function formatNumberInput(text) {
    const cleaned = text.replace(/[^\d.]/g, '');
    const dot = cleaned.indexOf('.');
    let integerPart = dot === -1 ? cleaned : cleaned.slice(0, dot);
    const fractionPart = dot === -1 ? null : cleaned.slice(dot + 1).replace(/\./g, '').slice(0, 2);
    integerPart = integerPart.replace(/^0+(?=\d)/, '').slice(0, 9);
    if (integerPart === '' && fractionPart !== null) {
        integerPart = '0';
    }
    const grouped = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return fractionPart === null ? grouped : `${grouped}.${fractionPart}`;
}

document.addEventListener('input', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-number')) {
        return;
    }
    const digitsBeforeCaret = input.value.slice(0, input.selectionStart ?? input.value.length).replace(/[^\d.]/g, '').length;
    const formatted = formatNumberInput(input.value);
    if (formatted === input.value) {
        return;
    }
    input.value = formatted;
    // Keep the caret after the same digit the user just typed.
    let caret = 0;
    for (let seen = 0; caret < formatted.length && seen < digitsBeforeCaret; caret++) {
        if (/[\d.]/.test(formatted[caret])) {
            seen++;
        }
    }
    input.setSelectionRange(caret, caret);
});

/* ------------------------------------------------------------------ sidebar (small screens) */

// Listeners sit on the document, so they keep working after Turbo swaps in the next page.
function setSidebar(open) {
    document.querySelector('[data-sidebar]')?.classList.toggle('-translate-x-full', !open);
    document.querySelector('[data-sidebar-backdrop]')?.classList.toggle('hidden', !open);
}

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }
    if (event.target.closest('[data-sidebar-open]')) {
        setSidebar(true);
    } else if (event.target.closest('[data-sidebar-backdrop]')) {
        setSidebar(false);
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setSidebar(false);
    }
});

/* ------------------------------------------------------------------ show / hide password */

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-toggle-password]') : null;
    if (!button) {
        return;
    }
    const input = document.getElementById(button.getAttribute('data-toggle-password'));
    if (!(input instanceof HTMLInputElement)) {
        return;
    }
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    button.querySelector('[data-icon-show]')?.classList.toggle('hidden', show);
    button.querySelector('[data-icon-hide]')?.classList.toggle('hidden', !show);
});

/* ------------------------------------------------------------------ field errors */

// A form field with a problem turns red, shows its message right under it, and gets the focus.
// Server errors come from <x-field-error> (data-field-error); empty required fields are caught here first.

function fieldNote(control) {
    const form = control.form ?? document;
    return form.querySelector(`[data-field-error="${CSS.escape(control.name)}"]`);
}

function markInvalid(control, message) {
    control.setAttribute('aria-invalid', 'true');
    let note = fieldNote(control);
    if (!note) {
        note = document.createElement('p');
        note.className = 'mt-1 text-sm text-red-600';
        note.dataset.fieldError = control.name;
        // Put the message under the whole field (icons and buttons sit in a wrapper with the input).
        const anchor = control.parentElement?.classList.contains('relative') ? control.parentElement : control;
        anchor.after(note);
    }
    note.hidden = false;
    note.textContent = message;
}

function clearInvalid(control) {
    if (control.getAttribute('aria-invalid') !== 'true') {
        return;
    }
    control.removeAttribute('aria-invalid');
    const note = fieldNote(control);
    if (note) {
        note.hidden = true;
    }
}

let focusQueued = false;

// The browser's own check (required, etc.): show it our way instead of the pop-up bubble.
document.addEventListener(
    'invalid',
    (event) => {
        const control = event.target;
        if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) {
            return;
        }
        event.preventDefault();
        markInvalid(control, control.validity.valueMissing ? 'This field is required.' : control.validationMessage);
        if (!focusQueued) {
            focusQueued = true;
            requestAnimationFrame(() => {
                focusQueued = false;
                control.form?.querySelector('[aria-invalid="true"]')?.focus();
            });
        }
    },
    true,
);

// Typing or choosing a new value clears that field's error.
for (const type of ['input', 'change']) {
    document.addEventListener(type, (event) => {
        if (event.target instanceof Element && event.target.matches('input, select, textarea')) {
            clearInvalid(event.target);
        }
    });
}

// After the server sends a form back with errors: mark those fields and focus the first one.
function showServerErrors() {
    let first = null;
    for (const note of document.querySelectorAll('[data-field-error]')) {
        const control = (note.closest('form') ?? document).querySelector(`[name="${CSS.escape(note.dataset.fieldError)}"]:not([type="hidden"])`);
        if (control) {
            control.setAttribute('aria-invalid', 'true');
            first ??= control;
        }
    }
    first?.focus();
}

document.addEventListener('turbo:load', showServerErrors);
