/**
 * Document numbering form: live example and problems while typing (server-side check, same rules as on save).
 */
(() => {
    'use strict';

    const form = document.getElementById('numbering-form');

    if (!form) {
        return;
    }

    const pattern = form.querySelector('#pattern');
    const period = form.querySelector('#period');
    const example = form.querySelector('[data-example]');
    const problems = form.querySelector('[data-problems]');
    let timer = null;

    async function check() {
        const url = `${form.dataset.previewUrl}?pattern=${encodeURIComponent(pattern.value)}&period=${encodeURIComponent(period.value)}`;

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            example.textContent = data.example ?? '-';
            problems.textContent = data.errors.join(' ');
        } catch (error) {
            problems.textContent = App.t('formatCheckFailed');
        }
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(check, 250);
    }

    pattern.addEventListener('input', schedule);
    period.addEventListener('change', check);
    check();
})();
