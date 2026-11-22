/**
 * Record-payment form: live summary of cash + withheld taxes against the open balance (exact cents).
 * Needs document-items.js (App.toScaled, App.formatIdr).
 */
(() => {
    'use strict';

    const form = document.getElementById('payment-form');

    if (!form) {
        return;
    }

    const balance = App.toScaled(form.dataset.balance, 2);
    const out = (key, cents) => { form.querySelector(`[data-sum="${key}"]`).textContent = App.formatIdr(cents); };

    function update() {
        const cash = App.toScaled(document.getElementById('amount').value, 2);
        let withheld = 0n;
        form.querySelectorAll('[data-withheld]').forEach((input) => { withheld += App.toScaled(input.value, 2); });
        const after = balance - cash - withheld;
        out('cash', cash);
        out('withheld', withheld);
        out('after', after);
        form.querySelector('[data-over]').hidden = after >= 0n;
    }

    form.addEventListener('input', update);
    update();
})();
