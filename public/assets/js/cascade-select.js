/**
 * Cascading (nested) dropdowns, e.g. province → regency/city.
 *
 * Parent:  <select data-cascade-target="#city" data-cascade-url="/api/regions?parent=">
 *            <option value="Sulawesi Selatan" data-code="73">…
 * Child:   <select id="city" data-cascade-placeholder="- Select a province first -">
 *
 * When the parent changes, the child is reloaded from `data-cascade-url + data-code` of the selected option
 * (JSON {results: [{code, name}]}); option values are the names (stored as text), codes go in data-code.
 * A child that is itself a parent (data-cascade-target) is cleared too, so more levels can be chained.
 * The server renders the initial options, so the form also works before/without this script.
 */
(() => {
    'use strict';

    /**
     * Reset a select to its placeholder option (and its own children, recursively).
     *
     * @param {HTMLSelectElement} select
     * @param {string} text Placeholder text.
     */
    function reset(select, text) {
        select.replaceChildren(new Option(text, ''));
        const next = select.dataset.cascadeTarget ? document.querySelector(select.dataset.cascadeTarget) : null;

        if (next) {
            reset(next, next.dataset.cascadePlaceholder || '-');
        }
    }

    /**
     * Load the child options for the parent's selected option.
     *
     * @param {HTMLSelectElement} parent
     */
    async function load(parent) {
        const child = document.querySelector(parent.dataset.cascadeTarget);
        const code = parent.selectedOptions[0]?.dataset.code || '';

        if (!child) {
            return;
        }

        reset(child, code ? App.t('loading') : (child.dataset.cascadePlaceholder || '-'));

        if (!code) {
            return;
        }

        child.disabled = true;

        try {
            const response = await fetch(parent.dataset.cascadeUrl + encodeURIComponent(code), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();
            const options = [new Option(child.dataset.cascadeEmpty || '- Select -', '')];

            data.results.forEach((row) => {
                const option = new Option(row.name.toUpperCase(), row.name.toUpperCase());
                option.dataset.code = row.code;
                options.push(option);
            });

            child.replaceChildren(...options);
        } catch (error) {
            reset(child, App.t('listLoadFailed'));
        } finally {
            child.disabled = false;
        }
    }

    document.addEventListener('change', (event) => {
        const select = event.target.closest('select[data-cascade-target]');

        if (select) {
            load(select);
        }
    });
})();
