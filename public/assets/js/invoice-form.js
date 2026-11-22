/**
 * Draft invoice form: editable item table with product picker and shipping cost in the live totals.
 * Needs typeahead.js and document-items.js.
 */
(() => {
    'use strict';

    const form = document.getElementById('invoice-form');

    if (!form) {
        return;
    }

    const items = App.documentItems(
        document.getElementById('item-table'),
        document.getElementById('item-row-template'),
        JSON.parse(document.getElementById('item-data').textContent || '[]'),
        { shippingInput: document.getElementById('shipping_cost') },
    );

    App.productPicker(document.getElementById('product_search'), form.dataset.productsUrl, items);

    // Enter in a line input should not submit the whole form by accident.
    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.closest('#item-table')) {
            event.preventDefault();
        }
    });
})();
