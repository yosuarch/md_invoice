/**
 * Delivery note form: courier fields are only relevant for a third-party shipment (THIRD_PARTY);
 * they stay visible but are marked required only then (the server enforces the courier name).
 */
(() => {
    'use strict';

    const select = document.getElementById('shipment_id');

    if (!select) {
        return;
    }

    function update() {
        const thirdParty = select.selectedOptions[0]?.dataset.code === 'THIRD_PARTY';

        document.querySelectorAll('[data-courier]').forEach((el) => el.classList.toggle('opacity-50', !thirdParty));
        document.querySelectorAll('[data-courier-required]').forEach((el) => { el.hidden = !thirdParty; });
        document.getElementById('courier_name').required = thirdParty;
    }

    select.addEventListener('change', update);
    update();
})();
