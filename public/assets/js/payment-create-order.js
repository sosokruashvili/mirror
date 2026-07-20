// Payment create/update form:
// 1) Show the Order field only when Payment Type is "Order" (შეკვეთა)
// 2) Populate it with the selected client's orders
// 3) Auto-fill Amount GEL from the selected order's price (keeps 2 decimals)
$(document).ready(function() {
    var ORDER_TYPE = 'Order';
    var orderPrices = {};
    var suppressAmountFill = false;
    var preselectedOrderId = '';

    var $typeSelect = $('select[name="type"], #payment_type_field');
    var $clientSelect = $('select[name="client_id"], #client_id_field');
    var $orderWrapper = $('#order_id_wrapper');
    var $orderSelect = $('#order_id_field, select[name="order_id"]');

    if (!$orderWrapper.length || !$orderSelect.length) {
        return;
    }

    $orderSelect = $orderSelect.first();
    preselectedOrderId = $orderSelect.attr('data-selected-order') || '';

    function isOrderType() {
        return String($typeSelect.val()) === ORDER_TYPE;
    }

    function getOrderValue() {
        try {
            return String(crud.field('order_id').value || '');
        } catch (e) {
            return String($orderSelect.val() || '');
        }
    }

    function getClientValue() {
        try {
            return String(crud.field('client_id').value || '');
        } catch (e) {
            return String($clientSelect.val() || '');
        }
    }

    // Always keep exactly 2 decimal places as a string (e.g. "150.50", not 150.5 / 150).
    function formatAmount(amount) {
        var n = parseFloat(String(amount).replace(',', '.'));
        if (isNaN(n)) {
            return null;
        }
        return n.toFixed(2);
    }

    function setAmountGel(amount) {
        var formatted = formatAmount(amount);
        if (formatted === null) {
            return;
        }

        try {
            var amountField = crud.field('amount_gel');
            amountField.$input.val(formatted);
            amountField.change();
            return;
        } catch (e) {
            // Fall through to jQuery.
        }

        var $amount = $('#amount_gel_field, input[name="amount_gel"]').first();
        $amount.val(formatted).trigger('change').trigger('input');
    }

    function fillAmountFromSelectedOrder() {
        if (suppressAmountFill) {
            return;
        }

        var orderId = getOrderValue();
        if (!orderId || !Object.prototype.hasOwnProperty.call(orderPrices, orderId)) {
            return;
        }

        setAmountGel(orderPrices[orderId]);
    }

    function syncOrderFieldVisibility() {
        if (isOrderType()) {
            $orderWrapper.show();
            loadOrders(getClientValue());
        } else {
            $orderWrapper.hide();
            suppressAmountFill = true;
            $orderSelect.val('').trigger('change');
            suppressAmountFill = false;
        }
    }

    function loadOrders(clientId) {
        var keepId = getOrderValue() || preselectedOrderId || '';

        orderPrices = {};

        if (!clientId) {
            suppressAmountFill = true;
            $orderSelect.empty().append('<option value="">- Select a client first -</option>');
            $orderSelect.trigger('change');
            suppressAmountFill = false;
            return;
        }

        $orderSelect.empty().append('<option value="">Loading orders...</option>').prop('disabled', true);

        $.ajax({
            url: '/admin/order/get-orders-by-client/' + clientId,
            method: 'GET',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
                'Accept': 'application/json'
            },
            success: function(orders) {
                $orderSelect.empty().prop('disabled', false);
                $orderSelect.append('<option value="">- Select an order -</option>');

                (orders || []).forEach(function(order) {
                    var id = String(order.id);
                    // Keep the API string as-is ("123.45") so decimals are never coerced away.
                    var price = formatAmount(order.price);
                    if (price !== null) {
                        orderPrices[id] = price;
                    }

                    $orderSelect.append(
                        $('<option></option>')
                            .attr('value', id)
                            .attr('data-price', price || '')
                            .text(order.text)
                    );
                });

                suppressAmountFill = true;
                if (keepId && $orderSelect.find('option[value="' + keepId + '"]').length) {
                    $orderSelect.val(String(keepId));
                }
                preselectedOrderId = '';
                $orderSelect.trigger('change');
                suppressAmountFill = false;
            },
            error: function() {
                suppressAmountFill = true;
                $orderSelect.empty().prop('disabled', false)
                    .append('<option value="">Error loading orders</option>')
                    .trigger('change');
                suppressAmountFill = false;
            }
        });
    }

    $typeSelect.on('change', syncOrderFieldVisibility);

    $clientSelect.on('change select2:select', function() {
        if (isOrderType()) {
            loadOrders(getClientValue());
        }
    });

    $orderSelect.on('change select2:select', fillAmountFromSelectedOrder);

    try {
        crud.field('type').onChange(function() {
            syncOrderFieldVisibility();
        });
    } catch (e) { /* ignore */ }

    try {
        crud.field('client_id').onChange(function() {
            if (isOrderType()) {
                loadOrders(getClientValue());
            }
        });
    } catch (e) { /* ignore */ }

    try {
        crud.field('order_id').onChange(function() {
            fillAmountFromSelectedOrder();
        });
    } catch (e) { /* ignore */ }

    setTimeout(syncOrderFieldVisibility, 500);
});
