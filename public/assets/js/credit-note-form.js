/**
 * Credit note form: live line amounts and totals with the same rules as App\Services\CreditNoteService:
 *   discount = R(invoice discount × qty / invoiced qty); a line priced incl. tax and credited at its invoice price is
 *   credited incl. tax (exact); amounts and tax per App.calculate() (calculation settings in window.AppCalc)
 *   total = Σ line_total + Σ tax + shipping
 * "Credit everything" fills every line with its remaining quantity at the invoice price, the remaining shipping,
 * and selects a cancellation type if none is chosen.
 * Needs document-items.js (App.toScaled, App.calculate, App.roundAmount, App.formatIdr).
 */
(() => {
    'use strict';

    const form = document.getElementById('credit-note-form');

    if (!form) {
        return;
    }

    const limit = App.toScaled(form.dataset.balance, 2); // not yet credited on the invoice
    const shippingInput = document.getElementById('shipping_amount');

    /**
     * Decimal string from cents.
     *
     * @param {bigint} cents
     * @returns {string}
     */
    function centsText(cents) {
        return (cents / 100n).toString() + '.' + (cents % 100n).toString().padStart(2, '0');
    }

    /**
     * Credit line for App.calculate() from a table row.
     *
     * @param {HTMLTableRowElement} tr
     * @param {HTMLInputElement} qtyInput
     * @returns {Object}
     */
    function creditLine(tr, qtyInput) {
        const qty = App.toScaled(qtyInput.value, 3);
        const invQty = App.toScaled(tr.dataset.quantity, 3);
        const price = tr.querySelector('[data-field="unit_price"]').value;
        const share = (amount) => (invQty > 0n ? App.roundAmount(App.toScaled(amount, 2) * qty, invQty) : 0n);
        const line = { quantity: qtyInput.value, unit_price: price, tax_rate: tr.dataset.taxRate };

        if (tr.dataset.base === 'INCL' && tr.dataset.priceIncl !== '' && App.toScaled(price, 6) === App.toScaled(tr.dataset.price, 6)) {
            const discountIncl = tr.dataset.discountIncl !== ''
                ? tr.dataset.discountIncl
                : centsText(App.roundAmount(App.toScaled(tr.dataset.discount, 2) * (10000n + App.toScaled(tr.dataset.taxRate, 2)), 10000n));
            return { ...line, price_base: 'INCL', unit_price_incl: tr.dataset.priceIncl, discount_incl: centsText(share(discountIncl)) };
        }

        return { ...line, price_base: 'EXCL', discount_amount: centsText(share(tr.dataset.discount)) };
    }

    function update() {
        const rows = [];
        const lines = [];

        form.querySelectorAll('[data-credit-line]').forEach((tr) => {
            const qtyInput = tr.querySelector('[data-field="quantity"]');

            if (!qtyInput.disabled) {
                rows.push(tr);
                lines.push(creditLine(tr, qtyInput));
            }
        });

        const shipping = shippingInput && !shippingInput.disabled ? App.toScaled(shippingInput.value, 2) : 0n;
        const result = App.calculate(lines, shipping);

        rows.forEach((tr, i) => {
            tr.querySelector('[data-line-amount]').textContent = App.formatIdr(result.lines[i].lineTotal);
        });

        form.querySelector('[data-shipping-amount]').textContent = App.formatIdr(result.shipping_cost);
        form.querySelector('[data-total="subtotal"]').textContent = App.formatIdr(result.subtotal);
        form.querySelector('[data-total="tax"]').textContent = App.formatIdr(result.tax_total);
        form.querySelector('[data-total="shipping"]').textContent = App.formatIdr(result.shipping_cost);
        form.querySelector('[data-total="grand"]').textContent = App.formatIdr(result.grand_total);
        form.querySelector('[data-over]').hidden = result.grand_total <= limit;
    }

    document.getElementById('credit-all')?.addEventListener('click', () => {
        form.querySelectorAll('[data-credit-line]').forEach((tr) => {
            const qtyInput = tr.querySelector('[data-field="quantity"]');

            if (!qtyInput.disabled) {
                qtyInput.value = tr.dataset.remaining;
                tr.querySelector('[data-field="unit_price"]').value = tr.dataset.price;
            }
        });

        if (shippingInput && !shippingInput.disabled) {
            shippingInput.value = shippingInput.dataset.max;
        }

        const type = document.getElementById('reason_type');

        if (type.value === '' || type.value === 'RETURN' || type.value === 'PRICE_CORRECTION') {
            type.value = 'CANCEL_BY_CLIENT';
        }

        update();
        document.getElementById('reason').focus();
    });

    form.addEventListener('input', update);
    update();
})();
