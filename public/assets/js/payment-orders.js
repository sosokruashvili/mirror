// Payment create/update form, "Orders" field (a select2 multi-select):
// 1) Fill it with the selected client's orders, each showing what it still owes.
// 2) When the user changes the selection, set Amount to what the picked orders owe.
// 3) With two or more orders picked, show the total they owe: the server refuses a
//    multi-order payment that does not cover it (PaymentRequest).
//
// Replaces payment-create-order.js (one order per payment). Basset only cache-busts on
// composer.lock, so a behaviour change gets a new file name rather than an edit.
$(document).ready(function () {
    var $orderSelect = $('#order_ids_field');
    var $clientSelect = $('select[name="client_id"], #client_id_field');

    if (!$orderSelect.length) {
        return;
    }

    var ordersUrl = $orderSelect.attr('data-orders-url');
    var paymentId = $orderSelect.attr('data-payment-id') || '';
    var text = {
        selectClient: $orderSelect.attr('data-text-select-client') || '',
        loading: $orderSelect.attr('data-text-loading') || '',
        error: $orderSelect.attr('data-text-error') || '',
        owed: $orderSelect.attr('data-text-owed') || ':amount',
        summary: $orderSelect.attr('data-text-summary') || ':count / :amount'
    };

    var owedByOrder = {};
    // True while this script changes the select itself, so that loading the options
    // (or restoring the saved selection on edit) does not overwrite the Amount.
    var programmatic = false;
    var loadedClient = null;

    var $summary = $('<div class="form-text text-primary fw-medium" id="order_ids_summary" style="display: none;"></div>');
    $orderSelect.closest('.form-group').append($summary);

    function clientValue() {
        try {
            return String(crud.field('client_id').value || '');
        } catch (e) {
            return String($clientSelect.val() || '');
        }
    }

    function selectedIds() {
        var value = $orderSelect.val() || [];
        return Array.isArray(value) ? value.filter(Boolean).map(String) : [String(value)];
    }

    function money(amount) {
        var n = parseFloat(String(amount).replace(',', '.'));
        return isNaN(n) ? null : n.toFixed(2);
    }

    function owedTotal(ids) {
        return ids.reduce(function (sum, id) {
            return sum + (parseFloat(owedByOrder[id]) || 0);
        }, 0);
    }

    function setAmount(amount) {
        var formatted = money(amount);
        if (formatted === null) {
            return;
        }

        try {
            var field = crud.field('amount_gel');
            field.$input.val(formatted);
            field.change();
            return;
        } catch (e) {
            // Fall through to jQuery.
        }

        $('#amount_gel_field, input[name="amount_gel"]').first().val(formatted).trigger('change').trigger('input');
    }

    function renderSummary() {
        var ids = selectedIds();

        if (ids.length < 2) {
            $summary.hide().text('');
            return;
        }

        $summary
            .text(text.summary.replace(':count', ids.length).replace(':amount', money(owedTotal(ids))))
            .show();
    }

    function onSelectionChange() {
        renderSummary();

        if (programmatic) {
            return;
        }

        var ids = selectedIds();
        var total = owedTotal(ids);

        // Nothing owed (no order picked, or only paid-off ones): leave Amount as typed.
        if (ids.length && total > 0) {
            setAmount(total);
        }
    }

    function replaceOptions(options, keep) {
        programmatic = true;
        $orderSelect.empty();
        options.forEach(function (option) {
            $orderSelect.append(option);
        });
        $orderSelect.val(keep).trigger('change');
        programmatic = false;
        renderSummary();
    }

    function loadOrders() {
        var clientId = clientValue();

        if (clientId === loadedClient) {
            return;
        }
        loadedClient = clientId;

        var keep = selectedIds();
        owedByOrder = {};

        if (!clientId) {
            replaceOptions([$('<option value="" disabled></option>').text(text.selectClient)], []);
            return;
        }

        $orderSelect.prop('disabled', true);

        $.ajax({
            url: ordersUrl + '/' + encodeURIComponent(clientId) + (paymentId ? '?exclude_payment=' + encodeURIComponent(paymentId) : ''),
            method: 'GET',
            dataType: 'json',
            headers: { 'Accept': 'application/json' },
            success: function (orders) {
                if (clientId !== loadedClient) {
                    return; // the client was changed again while this was loading
                }

                var options = (orders || []).map(function (order) {
                    var id = String(order.id);
                    var owed = money(order.owed);
                    owedByOrder[id] = owed || '0.00';

                    return $('<option></option>')
                        .attr('value', id)
                        .text(order.text + ' — ' + text.owed.replace(':amount', owedByOrder[id]));
                });

                $orderSelect.prop('disabled', false);
                // Orders of another client never match, so switching client clears the pick.
                replaceOptions(options, keep.filter(function (id) {
                    return Object.prototype.hasOwnProperty.call(owedByOrder, id);
                }));
            },
            error: function () {
                $orderSelect.prop('disabled', false);
                replaceOptions([$('<option value="" disabled></option>').text(text.error)], []);
                loadedClient = null;
            }
        });
    }

    $clientSelect.on('change select2:select', loadOrders);
    try {
        crud.field('client_id').onChange(loadOrders);
    } catch (e) { /* ignore */ }

    $orderSelect.on('change', onSelectionChange);

    // Give Backpack time to initialise select2 on both fields first.
    setTimeout(loadOrders, 500);
});
