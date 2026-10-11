/**
 * Stops duplicate submissions (double-clicks, Enter held down, impatient clicking on a slow server).
 *
 * For every <form> that is not method="get":
 *   1. The first submit goes through normally.
 *   2. Any further submit of the same form is cancelled (this also catches the Enter key).
 *   3. Right after, the submit buttons are disabled and the main one shows a spinner + "Saving…".
 *   4. If the user comes back with the Back button, the form is unlocked again.
 *
 * Opt out for one form (e.g. a POST that downloads a file):  <form data-no-lock>
 * Custom button text for one form or button:                 data-loading-text="Placing order…"
 */
const SPINNER = '<span class="btn-spinner" aria-hidden="true"></span>';
const SUBMIT_BUTTONS = 'button:not([type="button"]):not([type="reset"]), input[type="submit"]';

function loadingText(form, button) {
    if (button && button.dataset.loadingText) return button.dataset.loadingText;
    if (form.dataset.loadingText) return form.dataset.loadingText;

    const action = form.getAttribute('action') || '';
    const spoofed = form.querySelector('input[name="_method"]');
    if (spoofed && spoofed.value.toUpperCase() === 'DELETE') return 'Deleting…';
    if (/\/logout$/.test(action)) return 'Logging out…';
    if (/\/login$/.test(action)) return 'Signing in…';
    if ([...form.querySelectorAll('input[type="file"]')].some((i) => i.files && i.files.length)) return 'Uploading…';

    return 'Saving…';
}

function lock(form, submitter) {
    const buttons = [...form.querySelectorAll(SUBMIT_BUTTONS)];
    const main = submitter && form.contains(submitter) ? submitter : buttons[0];

    buttons.forEach((btn) => {
        btn.disabled = true;
        btn.classList.add('is-submitting');
    });

    if (!main) return;
    const text = loadingText(form, main);
    if (main.tagName === 'BUTTON') {
        main.dataset.originalHtml = main.innerHTML;
        main.innerHTML = SPINNER + text;
    } else {
        main.dataset.originalValue = main.value;
        main.value = text;
    }
}

function unlock(form) {
    delete form.dataset.submitting;
    form.querySelectorAll('.is-submitting').forEach((btn) => {
        btn.disabled = false;
        btn.classList.remove('is-submitting');
        if (btn.dataset.originalHtml !== undefined) {
            btn.innerHTML = btn.dataset.originalHtml;
            delete btn.dataset.originalHtml;
        }
        if (btn.dataset.originalValue !== undefined) {
            btn.value = btn.dataset.originalValue;
            delete btn.dataset.originalValue;
        }
    });
}

// Registered on window (bubble phase) so it runs AFTER inline onsubmit="return confirm(...)"
// handlers and Alpine handlers. If any of them cancelled the submit, we must not lock the form.
window.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (event.defaultPrevented) return;
    if (form.hasAttribute('data-no-lock')) return;
    if (form.method.toLowerCase() === 'get') return;          // search / filter forms

    if (form.dataset.submitting === '1') {                    // second click, Enter key, etc.
        event.preventDefault();
        return;
    }

    form.dataset.submitting = '1';
    const submitter = event.submitter;
    // Wait one tick so the browser has already collected the form data before we disable buttons.
    setTimeout(() => lock(form, submitter), 0);
});

// Back button (or restored page): never leave a form stuck on "Saving…".
window.addEventListener('pageshow', function () {
    document.querySelectorAll('form[data-submitting]').forEach(unlock);
});