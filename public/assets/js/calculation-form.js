/**
 * Settings → Calculation: recompute the examples live with the chosen settings (App.calculate from
 * document-items.js, same rules as App\Services\DocumentTotals). Page script: works on its own elements only.
 */
(() => {
    'use strict';

    const form = document.getElementById('calculation-form');
    const card = document.getElementById('calculation-examples');

    if (!form || !card) {
        return;
    }

    const examples = JSON.parse(card.dataset.examples);

    /**
     * Settings chosen in the form.
     *
     * @returns {{taxMode: string, rounding: string, amountDecimals: number, unitDecimals: number}}
     */
    function chosen() {
        const value = (name) => form.querySelector(`[name="${name}"]:checked`)?.value ?? form.querySelector(`[name="${name}"]`)?.value;
        return {
            taxMode: value('calc_tax_mode'),
            rounding: value('calc_rounding'),
            amountDecimals: Number(value('calc_amount_decimals')),
            unitDecimals: Number(value('calc_unit_decimals')),
        };
    }

    /**
     * Cents with 2 decimals ("Rp 28.500.000,00"), independent of the saved settings.
     *
     * @param {bigint} cents
     * @returns {string}
     */
    function money(cents) {
        const whole = (cents / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return `Rp ${whole},${(cents % 100n).toString().padStart(2, '0')}`;
    }

    function update() {
        const settings = chosen();

        Object.entries(examples).forEach(([key, lines]) => {
            const result = App.calculate(lines, 0n, settings);
            card.querySelectorAll(`[data-example="${key}"] [data-ex]`).forEach((cell) => {
                cell.textContent = money(result[cell.dataset.ex]);
            });
        });
    }

    form.addEventListener('change', update);
    update();
})();
