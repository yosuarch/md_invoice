/**
 * Minimal typeahead (search-as-you-type) for text inputs backed by a JSON endpoint
 * returning {results: [...]}. Renders a Bootstrap dropdown menu below the input.
 *
 * Usage:
 *   App.typeahead(input, {
 *       url: '/api/products',            // ?q=<text> is appended
 *       label: (item) => 'text',         // main line (text, escaped)
 *       detail: (item) => 'text',        // optional second line (text, escaped)
 *       image: (item) => 'url' | null,   // optional thumbnail
 *       onSelect: (item) => { ... },
 *       minLength: 1,
 *   });
 */
(() => {
    'use strict';

    /**
     * Attach a typeahead to an input.
     *
     * @param {HTMLInputElement} input
     * @param {{url: string, label: function(Object): string, detail?: function(Object): string, onSelect: function(Object): void, minLength?: number}} options
     * @returns {{close: function(): void}}
     */
    function typeahead(input, options) {
        const minLength = options.minLength ?? 1;
        const menu = document.createElement('div');
        menu.className = 'dropdown-menu typeahead-menu shadow-sm';
        menu.setAttribute('role', 'listbox');
        input.insertAdjacentElement('afterend', menu);
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');

        let results = [];
        let active = -1;
        let timer = null;
        let controller = null;

        function close() {
            menu.classList.remove('show');
            input.setAttribute('aria-expanded', 'false');
            active = -1;
        }

        function highlight(index) {
            const items = menu.querySelectorAll('.dropdown-item');
            items.forEach((el, i) => el.classList.toggle('active', i === index));
            active = index;
            items[index]?.scrollIntoView({ block: 'nearest' });
        }

        function choose(index) {
            const item = results[index];
            if (item === undefined) {
                return;
            }
            close();
            options.onSelect(item);
        }

        function render(message) {
            menu.replaceChildren();

            if (message) {
                const el = document.createElement('div');
                el.className = 'dropdown-item-text small text-body-secondary';
                el.textContent = message;
                menu.appendChild(el);
            }

            results.forEach((item, index) => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'dropdown-item';
                el.setAttribute('role', 'option');

                const imageUrl = options.image ? options.image(item) : null;
                let body = el;
                if (options.image) {
                    el.classList.add('d-flex', 'align-items-center', 'gap-2');
                    const img = document.createElement(imageUrl ? 'img' : 'span');
                    img.className = 'typeahead-thumb' + (imageUrl ? '' : ' product-thumb-empty');
                    if (imageUrl) {
                        img.src = imageUrl;
                        img.alt = '';
                        img.loading = 'lazy';
                    }
                    el.appendChild(img);
                    body = document.createElement('div');
                    body.className = 'overflow-hidden';
                    el.appendChild(body);
                }

                const main = document.createElement('div');
                main.className = 'text-truncate';
                main.textContent = options.label(item);
                body.appendChild(main);

                const detailText = options.detail ? options.detail(item) : '';
                if (detailText) {
                    const detail = document.createElement('div');
                    detail.className = 'small text-body-secondary text-truncate';
                    detail.textContent = detailText;
                    body.appendChild(detail);
                }

                // mousedown fires before the input's blur closes the menu
                el.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    choose(index);
                });
                menu.appendChild(el);
            });

            menu.classList.add('show');
            input.setAttribute('aria-expanded', 'true');
            active = -1;
        }

        async function search() {
            const q = input.value.trim();

            if (q.length < minLength) {
                close();
                return;
            }

            controller?.abort();
            controller = new AbortController();

            try {
                const url = options.url + (options.url.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q);
                const response = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                results = (await response.json()).results ?? [];
                render(results.length === 0 ? App.t('noMatches') : '');
            } catch (error) {
                if (error.name !== 'AbortError') {
                    results = [];
                    render(App.t('searchFailed'));
                }
            }
        }

        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(search, 200);
        });

        input.addEventListener('focus', () => {
            if (input.value.trim().length >= minLength && results.length > 0) {
                render('');
            }
        });

        input.addEventListener('blur', () => setTimeout(close, 100));

        input.addEventListener('keydown', (event) => {
            if (!menu.classList.contains('show')) {
                if (event.key === 'ArrowDown') {
                    search();
                }
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlight(Math.min(active + 1, results.length - 1));
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(Math.max(active - 1, 0));
            } else if (event.key === 'Enter') {
                event.preventDefault(); // never submit the form from the search box
                choose(active >= 0 ? active : 0);
            } else if (event.key === 'Escape') {
                close();
            }
        });

        return { close };
    }

    window.App = Object.assign(window.App ?? {}, { typeahead });
})();
