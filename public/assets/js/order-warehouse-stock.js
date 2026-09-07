/**
 * Live warehouse-stock warning on the Order create/edit form.
 *
 * Compares the form's expenses (piece area + product offcut %, already computed
 * by order-pieces-services.js) with live remaining stock per selected product.
 * Shows an inline banner while editing; on submit, asks for confirmation if
 * any product would go over remaining stock. Does not block saving.
 *
 * Isolated from price/service logic: it only reads expenses + product selects
 * and intercepts the order CRUD form's submit (never other forms/modals).
 */
(function () {
    'use strict';

    var CFG = window.orderWarehouseStock;
    if (!CFG || !CFG.url) { return; }

    var remainingById = {};
    var lastProductKey = '';
    var fetchXhr = null;
    var bannerTimer = null;
    var stockConfirmPassed = false;
    var stockCheckInProgress = false;
    var stockNoty = null;
    var lastNotyKey = '';
    var notyDismissedKey = '';
    var notyGeneration = 0;

    function i18nReplace(template, vars) {
        return String(template || '').replace(/:([a-z_]+)/g, function (_, key) {
            return vars[key] != null ? vars[key] : '';
        });
    }

    function formatM2(value, decimals) {
        var n = parseFloat(value);
        if (isNaN(n)) { n = 0; }
        return n.toFixed(decimals == null ? 3 : decimals);
    }

    function isServiceType() {
        return $('select[name="product_type"], input[name="product_type"]').val() === 'service';
    }

    function orderForm() {
        return $('[bp-section="crud-operation-create"] form, [bp-section="crud-operation-update"] form').first();
    }

    function selectedProductIds() {
        var ids = [];
        $('[data-repeatable-identifier="products"][data-row-number]').each(function () {
            if ($(this).find('input, select').first().prop('disabled')) { return; }
            var val = $(this).find('select[name*="[product_id]"]').val();
            if (val) { ids.push(String(val)); }
        });
        return ids.filter(function (id, i, arr) { return arr.indexOf(id) === i; });
    }

    function currentNeeded() {
        var raw = $('input[name="expenses"]').val();
        var n = parseFloat(raw);
        return (!raw || isNaN(n) || n < 0) ? 0 : n;
    }

    function exceeds(needed, remaining) {
        return Math.round(needed * 1000) / 1000 > Math.round(remaining * 1000) / 1000;
    }

    function shortagesFor(needed) {
        var ids = selectedProductIds();
        var rows = [];
        ids.forEach(function (id) {
            var info = remainingById[id];
            if (!info) { return; }
            var remaining = parseFloat(info.remaining);
            if (isNaN(remaining)) { remaining = 0; }
            if (needed > 0 && exceeds(needed, remaining)) {
                rows.push({
                    id: id,
                    title: info.title || ('#' + id),
                    needed: needed,
                    remaining: remaining,
                    over: needed - remaining
                });
            }
        });
        return rows;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function ensureBanner(id, place) {
        var $el = $('#' + id);
        if ($el.length) { return $el; }

        $el = $('<div id="' + id + '" class="d-none mb-3" role="alert"></div>');
        if (place === 'save') {
            var $save = $('#saveActions');
            if ($save.length) { $save.before($el); }
            else { orderForm().append($el); }
        } else {
            var $products = $('[bp-field-name="products"]');
            if ($products.length) { $products.after($el); }
            else { orderForm().prepend($el); }
        }
        return $el;
    }

    function fillBanner($el, anyOver, bodyHtml) {
        var title = anyOver ? CFG.i18n.warningTitle : CFG.i18n.okTitle;
        var icon = anyOver
            ? '<span class="order-stock-pulse-dot" aria-hidden="true"></span>'
            : '<span class="order-stock-ok-icon" aria-hidden="true"><i class="la la-info-circle"></i></span>';
        var titleIcon = anyOver ? 'la-exclamation-triangle' : 'la-info-circle';

        $el.removeClass('d-none alert alert-info alert-danger is-exceeded is-ok').empty();
        $el.addClass('alert ' + (anyOver ? 'is-exceeded' : 'is-ok')).append(
            '<div class="d-flex gap-3 align-items-start">' +
                icon +
                '<div class="flex-grow-1">' +
                    '<div class="order-stock-title mb-1">' +
                        '<i class="la ' + titleIcon + ' me-1"></i>' + escapeHtml(title) +
                    '</div>' +
                    bodyHtml +
                '</div>' +
            '</div>'
        );
    }

    function closeStockNoty() {
        notyGeneration += 1;
        if (!stockNoty) { return; }
        var pending = stockNoty;
        stockNoty = null;
        pending.close();
    }

    function showStockNoty(type, key, html, sticky) {
        if (typeof Noty !== 'function') { return; }
        if (!key) {
            closeStockNoty();
            lastNotyKey = '';
            notyDismissedKey = '';
            return;
        }
        if (notyDismissedKey === key) { return; }
        if (stockNoty && lastNotyKey === key) { return; }

        closeStockNoty();
        lastNotyKey = key;
        var gen = notyGeneration;
        stockNoty = new Noty({
            type: type,
            text: html,
            timeout: sticky ? false : 7000,
            layout: 'topRight',
            closeWith: ['click', 'button'],
            callbacks: {
                afterClose: function () {
                    if (gen !== notyGeneration) { return; }
                    notyDismissedKey = lastNotyKey;
                    stockNoty = null;
                }
            }
        }).show();
    }

    function okNotyKey() {
        var parts = selectedProductIds().map(function (id) {
            var info = remainingById[id];
            var remaining = info ? parseFloat(info.remaining) : '';
            return id + ':' + (isNaN(remaining) ? '' : formatM2(remaining, 3));
        });
        return 'ok|' + parts.join(',');
    }

    function exceedKey(needed) {
        return 'err|' + selectedProductIds().join(',') + '|' + formatM2(needed, 2);
    }

    function renderBanner() {
        var $el = ensureBanner('order-stock-warning', 'products');
        var $saveEl = ensureBanner('order-stock-warning-save', 'save');
        var $expenses = $('input[name="expenses"]');

        $el.addClass('d-none').removeClass('alert alert-info alert-danger is-exceeded is-ok').empty();
        $saveEl.addClass('d-none').removeClass('alert alert-info alert-danger is-exceeded is-ok').empty();
        $expenses.removeClass('is-invalid border-danger');

        if (isServiceType()) {
            showStockNoty('info', '', '', false);
            return;
        }

        var ids = selectedProductIds();
        if (!ids.length) {
            showStockNoty('info', '', '', false);
            return;
        }

        var needed = currentNeeded();
        var lines = [];
        var notyLines = [];
        var anyOver = false;
        var anyKnown = false;

        ids.forEach(function (id) {
            var info = remainingById[id];
            if (!info) { return; }
            anyKnown = true;
            var remaining = parseFloat(info.remaining);
            if (isNaN(remaining)) { remaining = 0; }
            var over = needed > 0 && exceeds(needed, remaining);
            if (over) { anyOver = true; }
            var vars = {
                product: info.title || ('#' + id),
                needed: formatM2(needed, 2),
                remaining: formatM2(remaining, 3),
                over: formatM2(Math.max(0, needed - remaining), 2)
            };
            var line;
            if (over) {
                line = i18nReplace(CFG.i18n.rowOver, vars);
            } else if (needed > 0 && CFG.i18n.rowOkNeed) {
                line = i18nReplace(CFG.i18n.rowOkNeed, vars);
            } else {
                line = i18nReplace(CFG.i18n.rowOk, vars);
            }
            lines.push('<div class="order-stock-line">' + escapeHtml(line) + '</div>');
            notyLines.push(escapeHtml(line));
        });

        if (!anyKnown) {
            return;
        }

        var bodyHtml = lines.join('');
        fillBanner($el, anyOver, bodyHtml);
        fillBanner($saveEl, anyOver, bodyHtml);
        if (anyOver) {
            $expenses.addClass('is-invalid border-danger');
        }

        if (anyOver) {
            showStockNoty(
                'error',
                exceedKey(needed),
                '<strong>' + escapeHtml(CFG.i18n.warningTitle) + '</strong><br>' + notyLines.join('<br>'),
                true
            );
        } else {
            showStockNoty(
                'info',
                okNotyKey(),
                '<strong>' + escapeHtml(CFG.i18n.okTitle) + '</strong><br>' + notyLines.join('<br>'),
                false
            );
        }
    }

    function scheduleBanner() {
        clearTimeout(bannerTimer);
        bannerTimer = setTimeout(renderBanner, 120);
    }

    function fetchRemaining() {
        var ids = selectedProductIds();
        var key = ids.slice().sort().join(',') + '|' + (CFG.excludeOrderId || '');
        if (!ids.length) {
            remainingById = {};
            lastProductKey = '';
            if (fetchXhr && fetchXhr.readyState !== 4) { fetchXhr.abort(); }
            return $.Deferred().resolve().promise();
        }
        if (key === lastProductKey && Object.keys(remainingById).length) {
            return $.Deferred().resolve().promise();
        }

        if (fetchXhr && fetchXhr.readyState !== 4) { fetchXhr.abort(); }

        var params = { product_ids: ids };
        if (CFG.excludeOrderId) { params.exclude_order_id = CFG.excludeOrderId; }

        fetchXhr = $.ajax({
            url: CFG.url,
            method: 'GET',
            data: params,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
            }
        });

        return fetchXhr.then(function (response) {
            remainingById = {};
            lastProductKey = key;
            ((response && response.products) || []).forEach(function (p) {
                remainingById[String(p.id)] = p;
            });
        }, function (xhr) {
            if (xhr && xhr.statusText === 'abort') { return; }
            lastProductKey = '';
        });
    }

    function refreshStock() {
        if (isServiceType()) {
            remainingById = {};
            lastProductKey = '';
            renderBanner();
            return;
        }
        fetchRemaining().always(scheduleBanner);
    }

    function confirmShortages(rows) {
        var lines = rows.map(function (row) {
            return i18nReplace(CFG.i18n.rowOver, {
                product: row.title,
                needed: formatM2(row.needed, 2),
                remaining: formatM2(row.remaining, 3),
                over: formatM2(row.over, 2)
            });
        });
        var text = [CFG.i18n.confirmIntro, '', lines.join('\n'), '', CFG.i18n.confirmQuestion].join('\n');

        if (typeof swal !== 'function') {
            return $.Deferred().resolve(window.confirm(text)).promise();
        }

        var deferred = $.Deferred();
        swal({
            title: CFG.i18n.confirmTitle,
            text: text,
            icon: 'warning',
            buttons: {
                cancel: {
                    text: CFG.i18n.cancel,
                    value: null,
                    visible: true,
                    className: 'bg-secondary',
                    closeModal: true
                },
                confirm: {
                    text: CFG.i18n.saveAnyway,
                    value: true,
                    visible: true,
                    className: 'bg-warning'
                }
            },
            dangerMode: true
        }).then(function (value) {
            deferred.resolve(!!value);
        });
        return deferred.promise();
    }

    function enableSaveButtons($form) {
        // Backpack's form_content disables submit buttons on the submit event
        // (anti double-click). If we cancel the save we have to turn them back on.
        $form.find('button[type="submit"]').prop('disabled', false);
    }

    function bindSubmit($form) {
        $form.on('submit', function (e) {
            if (stockConfirmPassed) { return; }
            if (stockCheckInProgress) {
                e.preventDefault();
                return;
            }
            if (isServiceType()) { return; }

            var ids = selectedProductIds();
            var needed = currentNeeded();
            if (!ids.length || !(needed > 0)) { return; }

            e.preventDefault();
            stockCheckInProgress = true;

            var formEl = this;
            lastProductKey = '';

            fetchRemaining().then(function () {
                renderBanner();
                var rows = shortagesFor(needed);
                if (!rows.length) {
                    stockConfirmPassed = true;
                    if (formEl.requestSubmit) { formEl.requestSubmit(); }
                    else { formEl.submit(); }
                    return;
                }
                return confirmShortages(rows).then(function (ok) {
                    if (ok) {
                        stockConfirmPassed = true;
                        if (formEl.requestSubmit) { formEl.requestSubmit(); }
                        else { formEl.submit(); }
                    } else {
                        enableSaveButtons($form);
                    }
                });
            }, function () {
                // API failure must not block saving.
                stockConfirmPassed = true;
                if (formEl.requestSubmit) { formEl.requestSubmit(); }
                else { formEl.submit(); }
            }).always(function () {
                stockCheckInProgress = false;
            });
        });
    }

    $(function () {
        var $form = orderForm();
        if (!$form.length) { return; }

        bindSubmit($form);

        $(document).on('order-expenses-updated order-products-changed', refreshStock);
        $(document).on('change', '[data-repeatable-identifier="products"] select[name*="[product_id]"]', refreshStock);
        $(document).on('change', 'select[name="product_type"], input[name="product_type"]', refreshStock);
        $(document).on('input', 'input[name="expenses"]', scheduleBanner);

        setTimeout(refreshStock, 400);
    });
})();
