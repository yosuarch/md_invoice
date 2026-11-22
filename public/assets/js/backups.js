/**
 * Settings → Backups: fill the restore modal with the chosen backup and enable "Restore" only after the
 * confirmation word is typed. Page script: works on its own elements only (safe to run after every navigation).
 */
(() => {
    'use strict';

    const modal = document.getElementById('restore-modal');

    if (!modal) {
        return;
    }

    const form = modal.querySelector('[data-restore-form]');
    const input = modal.querySelector('#restore-confirm');
    const submit = modal.querySelector('[data-restore-submit]');
    const title = modal.querySelector('[data-restore-title]');
    const warning = modal.querySelector('[data-restore-warning]');

    modal.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;

        if (!button) {
            return;
        }

        form.action = button.dataset.restoreUrl;
        title.textContent = title.dataset.template.replace('{file}', button.dataset.restoreFile);
        warning.textContent = warning.dataset.template.replace('{date}', button.dataset.restoreDate);
        input.value = '';
        submit.disabled = true;
    });

    modal.addEventListener('shown.bs.modal', () => input.focus());

    input.addEventListener('input', () => {
        submit.disabled = input.value.trim() !== input.dataset.confirmWord;
    });

    form.addEventListener('submit', () => {
        submit.disabled = true; // one restore at a time; the page reloads when it is done
        submit.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>');
    });
})();
