/**
 * Order Stats Widgets Refresh
 *
 * Keeps the stats cards above the orders list in sync with the list itself.
 * Every time the DataTable fetches rows with a different set of filters
 * (filter change, remove filters), this asks the order/stats endpoint for the
 * totals using the same filter parameters the table used, and updates the
 * cards in place — no full page reload needed. Redraws that don't change the
 * filters (pagination, sorting) reuse the already-displayed stats.
 */

(function () {
    'use strict';

    var pendingRequest = null;
    // The server rendered the widgets for the filters the page loaded with,
    // so that signature is already "displayed".
    var lastSignature = null;

    // Canonical "key=value" list of the non-empty query params of a query
    // string, sorted so ordering differences don't matter when comparing.
    function paramSignature(queryString) {
        var params = new URLSearchParams(queryString);
        var parts = [];
        params.forEach(function (value, key) {
            if (value !== '' && key !== 'persistent-table') {
                parts.push(key + '=' + value);
            }
        });
        return parts.sort().join('&');
    }

    function formatNumber(value, decimals) {
        return Number(value).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    }

    function refreshStats(queryString) {
        var $widgets = jQuery('#order-stats-widgets');
        if (!$widgets.length) {
            return;
        }

        var statsUrl = $widgets.data('stats-url');
        if (!statsUrl) {
            return;
        }

        if (pendingRequest) {
            pendingRequest.abort();
        }

        pendingRequest = jQuery.getJSON(statsUrl + (queryString ? '?' + queryString : ''), function (stats) {
            $widgets.find('[data-stat]').each(function () {
                var $el = jQuery(this);
                var key = $el.data('stat');
                if (stats[key] === undefined) {
                    return;
                }
                var decimals = parseInt($el.data('decimals'), 10) || 0;
                var suffix = $el.data('suffix') || '';
                $el.text(formatNumber(stats[key], decimals) + suffix);
            });
        }).always(function () {
            pendingRequest = null;
        });
    }

    jQuery(document).ready(function ($) {
        lastSignature = paramSignature(window.location.search);

        // DataTables events bubble up to the document, so this catches the
        // table's every ajax load without having to wait for crud.table to
        // be initialized.
        $(document).on('xhr.dt', '#crudTable', function () {
            var ajaxUrl = (typeof crud !== 'undefined' && crud.table && crud.table.ajax)
                ? crud.table.ajax.url()
                : window.location.href;
            var queryString = ajaxUrl.indexOf('?') !== -1 ? ajaxUrl.split('?').slice(1).join('?') : '';

            var signature = paramSignature(queryString);
            if (signature === lastSignature) {
                return;
            }
            lastSignature = signature;

            refreshStats(queryString);
        });
    });
})();
