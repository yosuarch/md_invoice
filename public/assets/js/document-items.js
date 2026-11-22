/**
 * Editable item table for documents (proposals, draft invoices).
 * Rows are built from <template id="item-row-template"> and posted as items[n][field].
 *
 * Live totals use exact decimal arithmetic (BigInt) with the same rules as App\Services\DocumentTotals and the
 * calculation settings in window.AppCalc (App\Services\CalcSettings: tax per line / per total, rounding mode,
 * amount decimals 2 / 0, unit price decimals 2–6):
 *   price excl. tax: line_total = R(qty × unit_price − discount), tax = R(line_total × rate / 100)
 *   price incl. tax: total_incl = R(qty × price_incl − discount_incl), line_total = R(total_incl × 100 / (100 + rate)),
 *                    tax = total_incl − line_total
 * Amounts are BigInt cents, unit prices excl. tax BigInt × 10^6. The server recomputes and stores the
 * authoritative amounts.
 */
(() => {
    'use strict';

    const UNIT = 1000000n; // unit prices excl. tax are scaled by 10^6

    /**
     * Calculation settings (window.AppCalc, rendered in the page head).
     *
     * @returns {{taxMode: string, rounding: string, amountDecimals: number, unitDecimals: number}}
     */
    function calc() {
        return Object.assign({ taxMode: 'LINE', rounding: 'HALF_UP', amountDecimals: 2, unitDecimals: 2 }, window.AppCalc ?? {});
    }

    /**
     * Parse a decimal string to a BigInt scaled by 10^scale (extra decimals are truncated). Invalid → 0n.
     *
     * @param {string|number|null} value
     * @param {number} scale
     * @returns {bigint}
     */
    function toScaled(value, scale) {
        const text = String(value ?? '').trim();
        const match = /^(-?)(\d*)(?:\.(\d*))?$/.exec(text);

        if (!match || (match[2] === '' && (match[3] ?? '') === '')) {
            return 0n;
        }

        const fraction = (match[3] ?? '').padEnd(scale, '0').slice(0, scale);
        const result = BigInt((match[2] || '0') + fraction);

        return match[1] === '-' ? -result : result;
    }

    /**
     * Integer division rounding half away from zero (or towards zero with mode 'DOWN').
     *
     * @param {bigint} numerator
     * @param {bigint} denominator (> 0)
     * @param {string} [mode] 'HALF_UP' (default) or 'DOWN'.
     * @returns {bigint}
     */
    function roundDiv(numerator, denominator, mode = 'HALF_UP') {
        if (mode === 'DOWN') {
            return numerator / denominator; // BigInt division truncates towards zero
        }

        const negative = numerator < 0n;
        const abs = negative ? -numerator : numerator;
        const quotient = (abs * 2n + denominator) / (denominator * 2n);

        return negative ? -quotient : quotient;
    }

    /**
     * Smallest amount step in cents (1 cent, or 100 cents for whole rupiah).
     *
     * @param {Object} c Settings.
     * @returns {bigint}
     */
    function amountStep(c) {
        return Number(c.amountDecimals) === 0 ? 100n : 1n;
    }

    /**
     * Amount numerator / denominator (in cents) rounded to the amount decimals with the rounding mode.
     *
     * @param {bigint} numerator
     * @param {bigint} denominator
     * @param {Object} [c] Settings.
     * @returns {bigint} Cents.
     */
    function roundAmount(numerator, denominator, c = calc()) {
        const step = amountStep(c);
        return roundDiv(numerator, denominator * step, c.rounding) * step;
    }

    /**
     * Format cents as Rupiah: 123456789n → "Rp 1.234.567,89" (whole rupiah without ",00" when the amount
     * decimals are 0).
     *
     * @param {bigint} cents
     * @returns {string}
     */
    function formatIdr(cents) {
        const negative = cents < 0n;
        const abs = negative ? -cents : cents;
        const whole = (abs / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        const fraction = (abs % 100n).toString().padStart(2, '0');
        const showFraction = Number(calc().amountDecimals) !== 0 || fraction !== '00';

        return (negative ? '-Rp ' : 'Rp ') + whole + (showFraction ? ',' + fraction : '');
    }

    /**
     * Format a unit price (× 10^6) as Rupiah with 2 up to 6 decimals ("Rp 171.171,1712").
     *
     * @param {bigint} units
     * @returns {string}
     */
    function formatUnitPrice(units) {
        const negative = units < 0n;
        const abs = negative ? -units : units;
        const whole = (abs / UNIT).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        const fraction = (abs % UNIT).toString().padStart(6, '0').replace(/0+$/, '').padEnd(2, '0');

        return (negative ? '-Rp ' : 'Rp ') + whole + ',' + fraction;
    }

    /**
     * Rate "11.00" → "11%".
     *
     * @param {string} rate
     * @returns {string}
     */
    function formatRate(rate) {
        const text = String(rate ?? '0');
        return (text.includes('.') ? text.replace(/0+$/, '').replace(/\.$/, '') : text) + '%';
    }

    /**
     * Whether a value is set (not undefined / null / '').
     *
     * @param {*} value
     * @returns {boolean}
     */
    function filled(value) {
        return value !== undefined && value !== null && value !== '';
    }

    /**
     * Amounts of one line with tax per line (same as DocumentTotals::lineAmounts).
     *
     * @param {{quantity: string, unit_price?: string, discount_amount?: string, tax_rate: string, price_base?: string, unit_price_incl?: string, discount_incl?: string}} line
     * @param {Object} [c] Settings.
     * @returns {{incl: boolean, rate: bigint, totalIncl: bigint, lineTotal: bigint, tax: bigint, discount: bigint}} Cents (rate × 100).
     */
    function lineAmounts(line, c = calc()) {
        const qty = toScaled(line.quantity, 3); // × 1000
        const rate = toScaled(line.tax_rate, 2); // × 100

        if (line.price_base === 'INCL' && filled(line.unit_price_incl)) {
            const priceIncl = toScaled(line.unit_price_incl, 2);
            const discountIncl = filled(line.discount_incl)
                ? toScaled(line.discount_incl, 2)
                : roundAmount(toScaled(line.discount_amount, 2) * (10000n + rate), 10000n, c);
            const totalIncl = roundAmount(qty * priceIncl - discountIncl * 1000n, 1000n, c);
            const lineTotal = roundAmount(totalIncl * 10000n, 10000n + rate, c);

            return { incl: true, rate, totalIncl, lineTotal, tax: totalIncl - lineTotal, discount: roundAmount(discountIncl * 10000n, 10000n + rate, c) };
        }

        const discount = roundAmount(toScaled(line.discount_amount, 2), 1n, c);
        // qty (× 10^3) × price (× 10^6) is scaled by 10^9; cents are 10^2
        const lineTotal = roundAmount(qty * toScaled(line.unit_price, 6) - discount * 10000000n, 10000000n, c);
        const tax = roundAmount(lineTotal * rate, 10000n, c);

        return { incl: false, rate, totalIncl: lineTotal + tax, lineTotal, tax, discount };
    }

    /**
     * Spread cents over weights in steps of the amount decimals; the remainder goes to the (first) largest weight
     * (same as DocumentTotals::spread).
     *
     * @param {bigint} amount
     * @param {bigint[]} weights (>= 0)
     * @param {Object} c Settings.
     * @returns {bigint[]}
     */
    function spread(amount, weights, c) {
        const step = amountStep(c);
        const units = amount / step;
        const total = weights.reduce((sum, w) => sum + w, 0n);
        let max = 0;
        weights.forEach((w, k) => {
            if (w > weights[max]) {
                max = k;
            }
        });
        const shares = weights.map((w) => (total === 0n ? 0n : (units * w) / total));
        if (shares.length > 0) {
            shares[max] += units - shares.reduce((sum, s) => sum + s, 0n);
        }
        return shares.map((s) => s * step);
    }

    /**
     * Line amounts and totals of a document (same as DocumentTotals::calculate).
     *
     * @param {Object[]} lines Line values (see lineAmounts).
     * @param {bigint} [shipping] Shipping cost in cents.
     * @param {Object} [c] Settings.
     * @returns {{lines: Object[], subtotal: bigint, discount_total: bigint, tax_total: bigint, shipping_cost: bigint, grand_total: bigint}}
     */
    function calculate(lines, shipping = 0n, c = calc()) {
        const rows = lines.map((line) => lineAmounts(line, c));

        if (c.taxMode === 'TOTAL') {
            const groups = new Map();
            rows.forEach((row, i) => {
                if (row.rate !== 0n) {
                    const key = `${row.rate}|${row.incl ? 'INCL' : 'EXCL'}`;
                    groups.set(key, [...(groups.get(key) ?? []), i]);
                }
            });
            groups.forEach((indexes, key) => {
                const incl = key.endsWith('INCL');
                const rate = rows[indexes[0]].rate;
                const weights = indexes.map((i) => (incl ? rows[i].totalIncl : rows[i].lineTotal));
                const sum = weights.reduce((a, b) => a + b, 0n);
                const tax = incl ? sum - roundAmount(sum * 10000n, 10000n + rate, c) : roundAmount(sum * rate, 10000n, c);
                spread(tax, weights, c).forEach((share, k) => {
                    const row = rows[indexes[k]];
                    row.tax = share;
                    if (incl) {
                        row.lineTotal = row.totalIncl - share;
                    }
                });
            });
        }

        const sum = (key) => rows.reduce((total, row) => total + row[key], 0n);
        const shippingCost = roundAmount(shipping, 1n, c);
        const subtotal = sum('lineTotal');
        const taxTotal = sum('tax');

        return { lines: rows, subtotal, discount_total: sum('discount'), tax_total: taxTotal, shipping_cost: shippingCost, grand_total: subtotal + taxTotal + shippingCost };
    }

    /**
     * Unit price excl. tax (× 10^6) from a price incl. tax in cents, rounded to the unit decimals
     * (same as DocumentTotals::exclFromIncl).
     *
     * @param {bigint} cents Price incl. tax in cents.
     * @param {bigint} rate  Tax rate × 100 (11.00% → 1100n).
     * @param {Object} [c] Settings.
     * @returns {bigint}
     */
    function exclFromIncl(cents, rate, c = calc()) {
        const step = 10n ** BigInt(6 - Number(c.unitDecimals));
        return roundDiv(cents * 10000n * 10000n, (10000n + rate) * step, c.rounding) * step;
    }

    /**
     * Cents incl. tax from a unit price excl. tax (× 10^6): price × (100 + rate) / 100, rounded to the amount decimals.
     *
     * @param {bigint} units Price excl. tax × 10^6.
     * @param {bigint} rate  Tax rate × 100.
     * @param {Object} [c] Settings.
     * @returns {bigint}
     */
    function inclFromExcl(units, rate, c = calc()) {
        return roundAmount(units * (10000n + rate), 10000n * 10000n, c);
    }

    /**
     * Cents → plain decimal string for an <input type=number> ("12345.60").
     *
     * @param {bigint} cents
     * @returns {string}
     */
    function centsToInput(cents) {
        const negative = cents < 0n;
        const abs = negative ? -cents : cents;
        return (negative ? '-' : '') + (abs / 100n).toString() + '.' + (abs % 100n).toString().padStart(2, '0');
    }

    /**
     * Unit price (× 10^6) → plain decimal string with 2 up to 6 decimals ("171171.1712").
     *
     * @param {bigint} units
     * @returns {string}
     */
    function unitsToInput(units) {
        const negative = units < 0n;
        const abs = negative ? -units : units;
        const fraction = (abs % UNIT).toString().padStart(6, '0').replace(/0+$/, '').padEnd(2, '0');
        return (negative ? '-' : '') + (abs / UNIT).toString() + '.' + fraction;
    }

    /**
     * Grow a textarea with its content (line descriptions).
     *
     * @param {HTMLTextAreaElement|null} area
     */
    function autoGrow(area) {
        if (!area) {
            return;
        }
        area.style.height = 'auto';
        area.style.height = area.value === '' ? '' : `${area.scrollHeight + 2}px`;
    }

    /**
     * Form row values → line for calculate(): on "incl. tax" rows the typed price / discount are incl. tax.
     *
     * @param {Object} data Row values.
     * @returns {Object}
     */
    function rowLine(data) {
        if (data.price_base === 'INCL') {
            return { quantity: data.quantity, tax_rate: data.tax_rate, price_base: 'INCL', unit_price_incl: data.unit_price, discount_incl: data.discount_amount || '0' };
        }
        return { quantity: data.quantity, tax_rate: data.tax_rate, price_base: 'EXCL', unit_price: data.unit_price, discount_amount: data.discount_amount };
    }

    /**
     * Set up the item table.
     *
     * @param {HTMLTableElement} table   Table with a first <tbody> for rows, a [data-empty-row] tbody and [data-total] cells.
     * @param {HTMLTemplateElement} template Row template; inputs carry data-name="field".
     * @param {Array<Object>} initialRows Rows to render first.
     * @param {{shippingInput?: HTMLInputElement}} [options] shippingInput: amount added to the grand total (invoices).
     * @returns {{addRow: function(Object): void}}
     */
    function documentItems(table, template, initialRows, options = {}) {
        const body = table.tBodies[0];
        const emptyRow = table.querySelector('[data-empty-row]');
        let counter = 0;

        function rowData(tr) {
            const data = {};
            tr.querySelectorAll('[data-name]').forEach((input) => {
                data[input.dataset.name] = input.value;
            });
            return data;
        }

        function recalc() {
            const rows = [...body.querySelectorAll('[data-item-row]')];
            const data = rows.map(rowData);
            const shipping = options.shippingInput ? toScaled(options.shippingInput.value, 2) : 0n;
            const result = calculate(data.map(rowLine), shipping);

            rows.forEach((tr, index) => {
                const amounts = result.lines[index];
                updateHints(tr, data[index], amounts);
                tr.querySelector('[data-line-no]').textContent = String(index + 1);
                tr.querySelector('[data-line-total]').textContent = formatIdr(amounts.lineTotal);
                tr.querySelector('[data-line-total]').classList.toggle('text-danger', amounts.lineTotal < 0n);
            });

            ['subtotal', 'discount_total', 'tax_total', 'shipping_cost', 'grand_total'].forEach((key) => {
                const cell = table.querySelector(`[data-total="${key}"]`);
                if (cell) {
                    cell.textContent = formatIdr(result[key]);
                }
            });

            emptyRow.hidden = rows.length > 0;
        }

        /**
         * Price in the other base (hint) and the minimum-price warning of a row.
         *
         * @param {HTMLTableRowElement} tr
         * @param {Object} data Row values.
         * @param {{discount: bigint}} amounts Line amounts (discount excl. tax in cents).
         */
        function updateHints(tr, data, amounts) {
            const rate = toScaled(data.tax_rate, 2);
            const incl = data.price_base === 'INCL';
            const priceExcl = incl ? exclFromIncl(toScaled(data.unit_price, 2), rate) : toScaled(data.unit_price, 6); // × 10^6
            const hint = tr.querySelector('[data-price-hint]');

            if (hint) {
                hint.textContent = rate === 0n || data.unit_price === '' ? ''
                    : (incl ? App.t('exclShort', formatUnitPrice(priceExcl)) : App.t('inclShort', formatIdr(inclFromExcl(priceExcl, rate))));
            }

            const warning = tr.querySelector('[data-min-warning]');
            const min = data.min_price ? toScaled(data.min_price, 6) : 0n;
            const qty = toScaled(data.quantity, 3);

            if (warning) {
                // net unit price excl. tax after discount (× 10^6)
                const net = qty > 0n ? roundDiv(priceExcl * qty - amounts.discount * 10000000n, qty) : priceExcl;
                warning.hidden = !(min > 0n && net < min);
                warning.textContent = warning.hidden ? '' : App.t('belowMin', formatUnitPrice(min));
            }
        }

        /**
         * Append a row.
         *
         * @param {Object} data Row values (id, product_id, variant_id, sku, item_title, item_description, unit_code, item_type, quantity, unit_price, discount_amount, tax_rate).
         * @param {boolean} focus Focus the quantity input.
         */
        function addRow(data, focus = false) {
            const tr = template.content.firstElementChild.cloneNode(true);
            const index = counter++;

            tr.querySelectorAll('[data-name]').forEach((input) => {
                input.name = `items[${index}][${input.dataset.name}]`;
                input.value = data[input.dataset.name] ?? '';
            });

            tr.querySelector('[data-unit]').textContent = data.unit_code ?? '';
            const taxSelect = tr.querySelector('[data-name="tax_code"]');
            if (taxSelect) {
                const code = data.tax_code ?? '';
                if (code !== '' && ![...taxSelect.options].some((o) => o.value === code)) {
                    const option = new Option(`${code} ${formatRate(data.tax_rate)}`, code);
                    option.dataset.rate = data.tax_rate;
                    taxSelect.add(option);
                }
                taxSelect.value = taxSelect.disabled ? '' : code;
            }
            const baseSelect = tr.querySelector('[data-name="price_base"]');
            if (baseSelect) {
                baseSelect.value = data.price_base === 'INCL' ? 'INCL' : 'EXCL';
            }
            tr.querySelector('[data-meta]').textContent = [
                data.sku,
                data.item_type === 'SERVICE' ? App.t('service') : App.t('goods'),
            ].filter(Boolean).join(' · ');
            autoGrow(tr.querySelector('[data-name="item_description"]'));

            body.appendChild(tr);
            recalc();

            if (focus) {
                const qty = tr.querySelector('[data-name="quantity"]');
                qty.focus();
                qty.select();
            }
        }

        body.addEventListener('change', (event) => {
            const tr = event.target.closest('[data-item-row]');
            if (!tr) {
                return;
            }
            const rateInput = tr.querySelector('[data-name="tax_rate"]');
            const price = tr.querySelector('[data-name="unit_price"]');
            const discount = tr.querySelector('[data-name="discount_amount"]');
            const rate = toScaled(rateInput.value, 2);

            if (event.target.matches('[data-name="tax_code"]')) {
                rateInput.value = event.target.selectedOptions[0]?.dataset.rate ?? '0';
            } else if (event.target.matches('[data-name="price_base"]')) {
                // keep the meaning of the typed price when switching between excl. and incl. tax
                const toIncl = event.target.value === 'INCL';
                if (price.value !== '') {
                    price.value = toIncl
                        ? centsToInput(inclFromExcl(toScaled(price.value, 6), rate))
                        : unitsToInput(exclFromIncl(toScaled(price.value, 2), rate));
                }
                if (discount.value !== '') {
                    const cents = toScaled(discount.value, 2);
                    discount.value = centsToInput(toIncl ? roundAmount(cents * (10000n + rate), 10000n) : roundAmount(cents * 10000n, 10000n + rate));
                }
            }
            recalc();
        });

        body.addEventListener('input', (event) => {
            if (event.target.matches('textarea')) {
                autoGrow(event.target);
                return;
            }
            recalc();
        });
        options.shippingInput?.addEventListener('input', recalc);
        body.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-row]');
            if (button) {
                button.closest('[data-item-row]').remove();
                recalc();
            }
        });

        (initialRows ?? []).forEach((row) => addRow(row));
        recalc();

        return { addRow };
    }

    /**
     * Product search box that adds a line to the item table (needs typeahead.js).
     * The new line copies SKU, title, description, unit, type, price and tax rate from the product or its variant
     * (a product with variants is listed once per variant).
     *
     * @param {HTMLInputElement} input Search input.
     * @param {string} url JSON endpoint (api/products).
     * @param {{addRow: function(Object, boolean): void}} items Result of documentItems().
     */
    function productPicker(input, url, items) {
        App.typeahead(input, {
            url,
            label: (p) => `${p.sku} · ${p.title.toUpperCase()}`,
            detail: (p) => `${formatIdr(toScaled(p.unit_price, 2))} / ${p.unit_code}`
                + ` · ${p.item_type === 'SERVICE' ? App.t('service') : App.t('goods')}`
                + (p.tax_code ? ` · ${p.tax_code} ${Number(p.tax_rate)}%` : ` · ${App.t('noTax')}`),
            image: (p) => p.image_url,
            onSelect: (p) => {
                items.addRow({
                    id: '',
                    product_id: p.id,
                    variant_id: p.variant_id ?? '',
                    sku: p.sku,
                    item_title: p.title.toUpperCase(),
                    item_description: p.description ?? '',
                    unit_code: p.unit_code,
                    item_type: p.item_type,
                    quantity: '1',
                    unit_price: p.unit_price,
                    discount_amount: '0',
                    tax_rate: p.tax_rate,
                    tax_code: p.tax_code ?? '',
                    price_base: p.price_incl_tax ? 'INCL' : 'EXCL',
                    min_price: p.min_price ?? '',
                }, true);
                input.value = '';
            },
        });
    }

    window.App = Object.assign(window.App ?? {}, {
        documentItems, productPicker, formatIdr, formatUnitPrice, lineAmounts, calculate, calc, toScaled, roundDiv, roundAmount, exclFromIncl, inclFromExcl,
    });
})();
