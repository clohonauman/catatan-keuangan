/* V83 — Live Indonesian nominal/currency input formatter.
 * Display: 3000000 -> 3.000.000
 * Internal JS/form value: 3000000
 * Keeps the native input value API compatible so existing app code can keep
 * reading input.value / Number(input.value) without per-form rewrites.
 */
(function () {
    'use strict';

    if (window.__CK_CURRENCY_INPUT_V83__) return;
    window.__CK_CURRENCY_INPUT_V83__ = true;

    var descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    if (!descriptor || typeof descriptor.get !== 'function' || typeof descriptor.set !== 'function') return;

    var nativeGet = descriptor.get;
    var nativeSet = descriptor.set;

    var selectors = [
        '#bulkLimit',
        '.day-limit',
        '#walletInitial',
        '#walletReserved',
        '#walletMinimum',
        '#walletCreditLimit',
        '#walletOpeningDebt',
        '#transferAmount',
        '#receivableLendAmount',
        '#receivableRepayAmount',
        '#receivableInterestAmount',
        '#monthlyBudgetLimit',
        '#billAmount',
        '#recurringAmount',
        '#goalTarget',
        '#goalCurrent',
        '#quickCaptureAmount',
        '#createTxAmount',
        '#editTxAmount',
        '#notifyLowThreshold',
        '[data-confirm-field="amount"]',
        '[data-initial-wallet]',
        '[data-reserved-wallet]',
        '[data-minimum-wallet]',
        '[data-simulation-amount]'
    ].join(',');

    var selectorFallback = [
        '[data-nominal]',
        '[data-currency-input]',
        'input.currency-input',
        'input.money-input'
    ].join(',');

    function digits(value) {
        return String(value == null ? '' : value).replace(/\D/g, '');
    }

    function formatDigits(value) {
        var raw = digits(value);
        return raw ? raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
    }

    function digitCountBefore(value, position) {
        return digits(String(value || '').slice(0, Math.max(0, position))).length;
    }

    function caretForDigitCount(formatted, count) {
        if (!count) return 0;
        var seen = 0;
        for (var i = 0; i < formatted.length; i++) {
            if (/\d/.test(formatted.charAt(i))) {
                seen++;
                if (seen >= count) return i + 1;
            }
        }
        return formatted.length;
    }

    function rawValue(input) {
        return String(input && input.dataset ? (input.dataset.ckCurrencyRaw || nativeGet.call(input) || '') : '');
    }

    function setDisplayed(input, raw, caretDigits) {
        var clean = digits(raw);
        var formatted = formatDigits(clean);
        nativeSet.call(input, formatted);
        input.dataset.ckCurrencyRaw = clean;

        if (document.activeElement === input && typeof input.setSelectionRange === 'function') {
            var pos = caretForDigitCount(formatted, caretDigits == null ? clean.length : caretDigits);
            try { input.setSelectionRange(pos, pos); } catch (_) {}
        }
    }

    function isMoneyInput(input) {
        if (!(input instanceof HTMLInputElement)) return false;
        if (input.dataset.currencyInput === '1') return true;
        if (input.matches(selectors) || input.matches(selectorFallback)) return true;

        // Covers future nominal fields added to the application without
        // touching unrelated numeric fields such as percentages, dates,
        // billing days, intervals, OTP, version codes, etc.
        var meta = [
            input.id,
            input.name,
            input.getAttribute('aria-label'),
            input.getAttribute('placeholder')
        ].filter(Boolean).join(' ').toLowerCase();

        if (!meta) return false;
        return /(^|[-_\s])(nominal|amount|saldo|balance|budget|batas|limit|target|pemasukan|pengeluaran|income|expense|debt|hutang|piutang|pelunasan|repayment|tagihan|cicilan|saving|tabungan|dana)([-_\s]|$)/i.test(meta);
    }

    function install(input) {
        if (!isMoneyInput(input) || input.dataset.ckCurrencyInstalled === '1') return;

        input.dataset.ckCurrencyInstalled = '1';
        input.dataset.currencyInput = '1';

        var initial = nativeGet.call(input) || input.getAttribute('value') || '';
        var cleanInitial = digits(initial);

        // A number input cannot display grouping separators. Convert only
        // identified monetary fields to a text control while retaining the
        // numeric keyboard on mobile.
        if (input.type === 'number') input.type = 'text';
        input.inputMode = 'numeric';
        input.autocomplete = input.autocomplete || 'off';
        input.setAttribute('data-currency-input', '1');

        Object.defineProperty(input, 'value', {
            configurable: true,
            enumerable: descriptor.enumerable,
            get: function () {
                return this.dataset.ckCurrencyRaw || nativeGet.call(this) || '';
            },
            set: function (value) {
                var clean = digits(value);
                nativeSet.call(this, formatDigits(clean));
                this.dataset.ckCurrencyRaw = clean;
            }
        });

        // Normalize the initial value after the custom accessor is installed.
        nativeSet.call(input, formatDigits(cleanInitial));
        input.dataset.ckCurrencyRaw = cleanInitial;

        input.addEventListener('input', function () {
            var displayBefore = nativeGet.call(input) || '';
            var caret = typeof input.selectionStart === 'number' ? input.selectionStart : displayBefore.length;
            var beforeDigits = digitCountBefore(displayBefore, caret);
            var clean = digits(displayBefore);

            nativeSet.call(input, formatDigits(clean));
            input.dataset.ckCurrencyRaw = clean;

            if (document.activeElement === input && typeof input.setSelectionRange === 'function') {
                var newCaret = caretForDigitCount(formatDigits(clean), beforeDigits);
                try { input.setSelectionRange(newCaret, newCaret); } catch (_) {}
            }
        });

        input.addEventListener('change', function () {
            var clean = digits(nativeGet.call(input) || '');
            nativeSet.call(input, formatDigits(clean));
            input.dataset.ckCurrencyRaw = clean;
        });

        input.addEventListener('blur', function () {
            var clean = digits(nativeGet.call(input) || '');
            nativeSet.call(input, formatDigits(clean));
            input.dataset.ckCurrencyRaw = clean;
        });
    }

    function scan(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var list = [];

        if (scope instanceof HTMLInputElement) {
            list.push(scope);
        } else {
            try {
                list = Array.prototype.slice.call(scope.querySelectorAll('input[type="number"], input[data-nominal], input[data-currency-input], input.currency-input, input.money-input'));
            } catch (_) {}
        }

        list.forEach(install);

        // The application contains some monetary inputs generated dynamically
        // (wallet rows, confirmation dialogs and simulation rows).
        if (scope === document) {
            try {
                document.querySelectorAll(selectors).forEach(install);
            } catch (_) {}
        }
    }

    window.getRawCurrencyValue = function (inputOrId) {
        var input = typeof inputOrId === 'string' ? document.getElementById(inputOrId) : inputOrId;
        return input instanceof HTMLInputElement && input.dataset.currencyInput === '1'
            ? (input.dataset.ckCurrencyRaw || digits(nativeGet.call(input) || ''))
            : (input instanceof HTMLInputElement ? digits(input.value) : '');
    };

    window.formatCurrencyInput = function (inputOrId) {
        var input = typeof inputOrId === 'string' ? document.getElementById(inputOrId) : inputOrId;
        if (!(input instanceof HTMLInputElement)) return;
        install(input);
        var clean = digits(nativeGet.call(input) || '');
        nativeSet.call(input, formatDigits(clean));
        input.dataset.ckCurrencyRaw = clean;
    };

    document.addEventListener('reset', function (event) {
        var form = event.target;
        if (!form || !form.querySelectorAll) return;
        window.setTimeout(function () { scan(form); }, 0);
    }, true);

    if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes || [], function (node) {
                    if (node.nodeType === 1) scan(node);
                });
            });
        });
        observer.observe(document.documentElement || document, { childList: true, subtree: true });
    }

    scan(document);
})();
