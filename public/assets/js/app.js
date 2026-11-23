/**
 * App-wide JavaScript, loaded once in <head>. Keep feature code in separate modules under assets/js/.
 *
 * The staff layout uses htmx boost: links and forms load the next page with AJAX and swap <body>, so this file
 * only registers delegated listeners and runs per-content setup through htmx.onLoad (initial page and every swap).
 */
(() => {
    'use strict';

    /**
     * Current CSRF token/header for fetch() POST requests. The token lives in <body> (#csrf-state), because
     * <head> is not replaced by htmx navigation while the token changes after every POST.
     *
     * @returns {{header: string, token: string}}
     */
    function getCsrf() {
        return {
            header: document.querySelector('meta[name="csrf-header"]')?.content ?? 'X-CSRF-TOKEN',
            token: document.getElementById('csrf-state')?.dataset.token
                ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        };
    }

    /**
     * CSRF tokens regenerate after every POST. After an AJAX POST, store the new token and put it into every form
     * on the page that still carries the old one, so the next normal submit is accepted.
     *
     * @param {string} token New token from the JSON response.
     */
    function refreshCsrf(token) {
        if (!token) {
            return;
        }

        const old = getCsrf().token;
        const state = document.getElementById('csrf-state');
        const meta = document.querySelector('meta[name="csrf-token"]');

        if (state) {
            state.dataset.token = token;
        }

        if (meta) {
            meta.content = token;
        }

        document.querySelectorAll('input[type="hidden"]').forEach((input) => {
            if (old !== '' && input.value === old) {
                input.value = token;
            }
        });
    }

    /**
     * Screen text in the user's language (window.AppLang from app/Language/<locale>/Js.php, set in layouts/common/head).
     * Placeholders {0}, {1}, ... are replaced by the extra arguments.
     *
     * @param {string} key
     * @param {...(string|number)} args
     * @returns {string}
     */
    function t(key, ...args) {
        const text = (window.AppLang ?? {})[key] ?? key;
        return args.reduce((result, value, i) => result.replaceAll(`{${i}}`, String(value)), text);
    }

    window.App = Object.assign(window.App ?? {}, { getCsrf, refreshCsrf, t });

    // ---------------------------------------------------------------------------------------------------------
    // Per-content setup (initial load and every htmx swap)
    // ---------------------------------------------------------------------------------------------------------

    /**
     * @param {Element} root Newly loaded content.
     */
    function setup(root) {
        root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));
        root.querySelectorAll('.toast[data-autoshow]').forEach((el) => bootstrap.Toast.getOrCreateInstance(el).show());

        // Lists stacked as cards on phones (.table-stack, CSS): every cell gets its column header as label.
        root.querySelectorAll('table.table-stack').forEach(labelCells);

        // Jump to an anchor of the new page (e.g. #files after an upload).
        if (root === document.body && location.hash.length > 1) {
            document.getElementById(location.hash.slice(1))?.scrollIntoView({ block: 'start' });
        }
    }

    /**
     * Copy the header text of each column into data-label of its body cells (used by .table-stack on phones).
     *
     * @param {HTMLTableElement} table
     */
    function labelCells(table) {
        const heads = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        table.querySelectorAll('tbody tr').forEach((tr) => {
            [...tr.children].forEach((td, i) => {
                if (td.colSpan === 1 && heads[i] && !td.hasAttribute('data-label')) {
                    td.setAttribute('data-label', heads[i]);
                }
            });
        });
    }

    if (window.htmx) {
        htmx.onLoad(setup);
    } else {
        document.addEventListener('DOMContentLoaded', () => setup(document.body));
    }

    // ---------------------------------------------------------------------------------------------------------
    // Upload size check and confirmation dialogs. Capture phase: they must run before htmx submits the form.
    // ---------------------------------------------------------------------------------------------------------

    /**
     * Upload forms with data-max-bytes: stop before sending a file that is too large (the server would reject the
     * whole request, including its CSRF token).
     */
    document.addEventListener('submit', (event) => {
        const form = event.target;
        const max = Number(form instanceof HTMLFormElement ? form.dataset.maxBytes : 0);

        if (!max) {
            return;
        }

        const tooBig = [...form.querySelectorAll('input[type="file"]')].some((input) => [...input.files].some((f) => f.size > max));
        const message = form.querySelector('[data-size-error]');

        if (message) {
            message.hidden = !tooBig;
            message.textContent = tooBig ? t('fileTooLarge', (max / 1048576).toFixed(1)) : '';
        }

        if (tooBig) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    /**
     * Confirmation dialog for forms with data-confirm="Message".
     * The form is submitted only after the user clicks the confirm button.
     */
    let confirmModal = null;
    let pendingForm = null;

    function getConfirmModal() {
        if (confirmModal && confirmModal.el.isConnected) {
            return confirmModal;
        }

        const el = document.createElement('div');
        el.className = 'modal fade';
        el.tabIndex = -1;
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${t('pleaseConfirm')}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${t('close')}"></button>
                    </div>
                    <div class="modal-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">${t('cancel')}</button>
                        <button type="button" class="btn btn-danger" data-confirm-ok>${t('confirm')}</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(el);

        const modal = new bootstrap.Modal(el);

        el.querySelector('[data-confirm-ok]').addEventListener('click', () => {
            const form = pendingForm;

            modal.hide();

            if (form) {
                form.dataset.confirmed = '1';
                form.requestSubmit();
                delete form.dataset.confirmed;
            }
        });

        confirmModal = { el, modal };

        return confirmModal;
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const { el, modal } = getConfirmModal();
        pendingForm = form;
        el.querySelector('.modal-body').textContent = form.dataset.confirm;
        modal.show();
    }, true);

    // ---------------------------------------------------------------------------------------------------------
    // htmx navigation: progress bar, clean-up, errors, login page
    // ---------------------------------------------------------------------------------------------------------

    let pending = 0;

    document.addEventListener('htmx:beforeRequest', () => {
        pending++;
        document.documentElement.classList.add('is-loading');
    });

    document.addEventListener('htmx:afterRequest', () => {
        pending = Math.max(0, pending - 1);

        if (pending === 0) {
            document.documentElement.classList.remove('is-loading');
        }
    });

    document.addEventListener('htmx:beforeSwap', (event) => {
        const { xhr, requestConfig } = event.detail;
        const url = xhr.responseURL || requestConfig?.path || location.href;

        // Session expired (login page) or an error page: load it as a normal page.
        if (xhr.status >= 400 || (typeof xhr.response === 'string' && xhr.response.includes('id="auth-page"'))) {
            event.detail.shouldSwap = false;

            if (requestConfig?.verb === 'get' || xhr.status < 400) {
                window.location.href = url;
            } else {
                showToast(t('requestFailed', xhr.status), 'danger');
            }

            return;
        }

        // A whole-page swap removes open modals/offcanvas; drop what Bootstrap left on <body>.
        if (event.detail.target === document.body) {
            document.querySelectorAll('.modal-backdrop, .offcanvas-backdrop, .tooltip').forEach((el) => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }
    });

    document.addEventListener('htmx:sendError', () => {
        showToast(t('noConnection'), 'danger');
    });

    /**
     * Small toast at the top right (used for client-side messages; server flash messages render their own).
     *
     * @param {string} message
     * @param {'success'|'danger'} type
     */
    function showToast(message, type = 'success') {
        let container = document.querySelector('.toast-container');

        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(container);
        }

        const el = document.createElement('div');
        el.className = `toast align-items-center text-bg-${type} border-0`;
        el.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        el.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="' + t('close') + '"></button></div>';
        el.querySelector('.toast-body').textContent = message;
        container.appendChild(el);
        el.addEventListener('hidden.bs.toast', () => el.remove());
        bootstrap.Toast.getOrCreateInstance(el, { delay: type === 'danger' ? 8000 : 4000 }).show();
    }

    window.App.toast = showToast;

    // ---------------------------------------------------------------------------------------------------------
    // Quick search dropdown (top bar): open while focused / typing, close on Escape or click outside
    // ---------------------------------------------------------------------------------------------------------

    document.addEventListener('focusin', (event) => {
        event.target.closest?.('.app-search')?.classList.add('is-open');
    });

    document.addEventListener('click', (event) => {
        document.querySelectorAll('.app-search.is-open').forEach((box) => {
            if (!box.contains(event.target)) {
                box.classList.remove('is-open');
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        const box = event.target.closest?.('.app-search');

        if (!box) {
            return;
        }

        if (event.key === 'Escape') {
            box.classList.remove('is-open');
            event.target.blur();
        } else if (event.key === 'ArrowDown') {
            box.querySelector('.search-item')?.focus();
            event.preventDefault();
        }
    });
    // ---------------------------------------------------------------------------------------------------------
    // Live list filters: forms with data-live-filter refresh #list-results while typing / choosing
    // ---------------------------------------------------------------------------------------------------------

    let filterTimer = null;

    /**
     * @param {HTMLFormElement} form
     */
    function refreshList(form) {
        const params = new URLSearchParams(new FormData(form));

        [...params.keys()].forEach((key) => params.get(key) === '' && params.delete(key));

        const query = params.toString();
        const url = (form.getAttribute('action') || location.pathname) + (query ? '?' + query : '');

        htmx.ajax('GET', url, { target: '#list-results', select: '#list-results', swap: 'outerHTML' })
            .then(() => history.replaceState(history.state, '', url));
    }

    document.addEventListener('input', (event) => {
        const form = event.target.closest?.('form[data-live-filter]');

        if (!form || !window.htmx || !document.getElementById('list-results') || event.target.matches('select')) {
            return;
        }

        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => refreshList(form), 350);
    });

    document.addEventListener('change', (event) => {
        const form = event.target.closest?.('form[data-live-filter]');

        if (!form || !window.htmx || !document.getElementById('list-results') || event.target.matches('input[type="search"], input[type="text"]')) {
            return;
        }

        clearTimeout(filterTimer);
        refreshList(form);
    });
})();
