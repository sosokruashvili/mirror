/**
 * Order Stats Widgets Refresh
 *
 * Fills the stats cards above the orders list and keeps them in sync with the
 * list itself. The page renders the cards empty so the list does not wait for
 * the stats; this asks the order/stats endpoint for the totals as soon as the
 * page is ready, in parallel with the table's first fetch. After that, every
 * time the DataTable fetches rows with a different set of filters (filter
 * change, remove filters), it asks again with the same filter parameters the
 * table used and updates the cards in place — no full page reload needed.
 * Redraws that don't change the filters (pagination, sorting) reuse the
 * already-displayed stats.
 */

(function () {
    'use strict';

    var pendingRequest = null;
    // Signature of the filters whose stats are displayed or being fetched.
    var lastSignature = null;

    var SPINNER = '<span class="spinner-border spinner-border-sm" role="status"></span>';

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

        // Show the spinner while fetching, so the old totals are never read as
        // the totals of the new filters.
        var $values = $widgets.find('[data-stat]');
        $values.attr('aria-busy', 'true').html(SPINNER);

        var request = jQuery.getJSON(statsUrl + (queryString ? '?' + queryString : ''), function (stats) {
            $values.each(function () {
                var $el = jQuery(this);
                var key = $el.data('stat');
                if (stats[key] === undefined) {
                    $el.text('—');
                    return;
                }
                var decimals = parseInt($el.data('decimals'), 10) || 0;
                var suffix = $el.data('suffix') || '';
                $el.text(formatNumber(stats[key], decimals) + suffix);
            });
        }).fail(function (xhr, status) {
            // An aborted request was replaced by a newer one, which owns the cards.
            if (status !== 'abort') {
                $values.text('—');
            }
        }).always(function () {
            if (pendingRequest === request) {
                pendingRequest = null;
                $values.removeAttr('aria-busy');
            }
        });
        pendingRequest = request;
    }

    jQuery(document).ready(function ($) {
        // The table's first fetch uses the page's own query string, so these
        // stats match the rows it is about to show.
        lastSignature = paramSignature(window.location.search);
        refreshStats(window.location.search.replace(/^\?/, ''));

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
