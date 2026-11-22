/**
 * Proposal form: customer picker (fills contacts and default sales rep) and the item table with product picker.
 * Needs typeahead.js and document-items.js.
 */
(() => {
    'use strict';

    const form = document.getElementById('proposal-form');

    if (!form) {
        return;
    }

    const customerId = document.getElementById('customer_id');
    const customerSearch = document.getElementById('customer_search');
    const customerInfo = document.getElementById('customer_info');
    const contactSelect = document.getElementById('contact_person_id');
    const repSelect = document.getElementById('sales_rep_id');
    const statusLabels = { PUBLIC: App.t('publicSchool'), PRIVATE: App.t('privateSchool') };

    // ---- Item table ----
    const items = App.documentItems(
        document.getElementById('item-table'),
        document.getElementById('item-row-template'),
        JSON.parse(document.getElementById('item-data').textContent || '[]'),
    );

    App.productPicker(document.getElementById('product_search'), form.dataset.productsUrl, items);

    // ---- Customer picker ----

    /**
     * Replace the options of a select, keeping the first "- None -" option.
     *
     * @param {HTMLSelectElement} select
     * @param {Array<{value: string, text: string}>} options
     * @param {string} selected
     */
    function fillSelect(select, options, selected) {
        const placeholder = select.options[0];
        select.replaceChildren(placeholder);
        options.forEach((o) => select.add(new Option(o.text, o.value, false, o.value === selected)));
    }

    async function loadCustomer(id) {
        try {
            const response = await fetch(`${form.dataset.customersUrl}/${id}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const customer = await response.json();
            const primary = customer.contacts.find((c) => c.is_primary) ?? customer.contacts[0];

            fillSelect(
                contactSelect,
                customer.contacts.map((c) => ({ value: String(c.id), text: c.name + (c.position ? ` (${c.position})` : '') })),
                primary ? String(primary.id) : '',
            );

            if (customer.default_rep_id && repSelect.querySelector(`option[value="${customer.default_rep_id}"]`)) {
                repSelect.value = String(customer.default_rep_id);
            }

            customerInfo.textContent = statusLabels[customer.school_status] ?? '';
        } catch (error) {
            customerInfo.textContent = App.t('contactsFailed');
        }
    }

    App.typeahead(customerSearch, {
        url: form.dataset.customersUrl,
        label: (c) => c.name,
        detail: (c) => [c.school_level, statusLabels[c.school_status], c.npsn ? `NPSN ${c.npsn}` : ''].filter(Boolean).join(' · '),
        onSelect: (c) => {
            customerId.value = String(c.id);
            customerSearch.value = c.name;
            customerSearch.classList.remove('is-invalid');
            loadCustomer(c.id);
        },
    });

    // Typing a new name clears the previous selection until a customer is picked again.
    customerSearch.addEventListener('input', () => {
        if (customerId.value !== '') {
            customerId.value = '';
            fillSelect(contactSelect, [], '');
            customerInfo.textContent = App.t('pickCustomer');
        }
    });

    // Enter in a line input should not submit the whole form by accident.
    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.closest('#item-table')) {
            event.preventDefault();
        }
    });

    // ---- Quick-add a new school (modal), then select it ----
    const quickForm = document.getElementById('quick-customer-form');

    if (quickForm) {
        const errorBox = quickForm.querySelector('[data-errors]');
        const submit = quickForm.querySelector('button[type="submit"]');

        quickForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            errorBox.hidden = true;
            submit.disabled = true;

            const csrf = App.getCsrf();

            try {
                const response = await fetch(quickForm.action, {
                    method: 'POST',
                    body: new FormData(quickForm),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', [csrf.header]: csrf.token },
                });
                const data = await response.json();

                App.refreshCsrf(data.csrf);

                if (!response.ok) {
                    errorBox.innerHTML = '';
                    Object.values(data.errors ?? { _: App.t('schoolNotSaved') }).forEach((message) => {
                        const line = document.createElement('div');
                        line.textContent = message;
                        errorBox.appendChild(line);
                    });
                    errorBox.hidden = false;

                    return;
                }

                customerId.value = String(data.id);
                customerSearch.value = data.name;
                customerSearch.classList.remove('is-invalid');
                loadCustomer(data.id);
                quickForm.reset();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('quick-customer')).hide();
            } catch (error) {
                errorBox.textContent = App.t('schoolNotSavedNet');
                errorBox.hidden = false;
            } finally {
                submit.disabled = false;
            }
        });
    }
})();
