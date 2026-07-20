// Payment create/update form: show the "Order" field only when Payment Type is
// "Order" (შეკვეთა), and populate it with the selected client's orders.
$(document).ready(function() {
    var $typeSelect  = $('select[name="type"], #payment_type_field');
    var $clientSelect = $('select[name="client_id"], #client_id_field');
    var $orderWrapper = $('#order_id_wrapper');
    var $orderSelect  = $('#order_id_field');

    // Nothing to do if the order field isn't on this page.
    if (!$orderWrapper.length || !$orderSelect.length) {
        return;
    }

    // The value stored for the "Order" (შეკვეთა) type — matches Payment::TYPE_ORDER.
    var ORDER_TYPE = 'Order';

    // Order that was linked before editing, so we can re-select it after loading.
    var preselectedOrderId = $orderSelect.attr('data-selected-order') || '';

    function isOrderType() {
        return $typeSelect.val() === ORDER_TYPE;
    }

    // Show/hide the order field based on the selected payment type.
    function syncOrderFieldVisibility() {
        if (isOrderType()) {
            $orderWrapper.show();
            loadOrders($clientSelect.val());
        } else {
            $orderWrapper.hide();
            // Clear the value so a hidden order isn't submitted for non-order payments.
            $orderSelect.val('').trigger('change');
        }
    }

    // Fetch the given client's orders and rebuild the order <select> options.
    function loadOrders(clientId) {
        // Remember whatever is currently selected so we can keep it if still valid.
        var keepId = $orderSelect.val() || preselectedOrderId || '';

        if (!clientId) {
            $orderSelect.empty().append('<option value="">- Select a client first -</option>');
            $orderSelect.trigger('change');
            return;
        }

        $orderSelect.empty().append('<option value="">Loading orders...</option>').prop('disabled', true);

        $.ajax({
            url: '/admin/order/get-orders-by-client/' + clientId,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
            },
            success: function(orders) {
                $orderSelect.empty().prop('disabled', false);
                $orderSelect.append('<option value="">- Select an order -</option>');

                (orders || []).forEach(function(order) {
                    $orderSelect.append(
                        $('<option></option>').attr('value', order.id).text(order.text)
                    );
                });

                // Restore the previously selected order if it belongs to this client.
                if (keepId && $orderSelect.find('option[value="' + keepId + '"]').length) {
                    $orderSelect.val(keepId);
                }

                // Only consume the edit-time preselection once.
                preselectedOrderId = '';

                $orderSelect.trigger('change');
            },
            error: function() {
                $orderSelect.empty().prop('disabled', false)
                    .append('<option value="">Error loading orders</option>')
                    .trigger('change');
            }
        });
    }

    // React to type changes.
    $typeSelect.on('change', syncOrderFieldVisibility);

    // When the client changes, refresh the order list (only matters if type = Order).
    $clientSelect.on('change select2:select', function() {
        if (isOrderType()) {
            loadOrders($clientSelect.val());
        }
    });

    // Set the initial state on load (handles both create defaults and edit values).
    setTimeout(syncOrderFieldVisibility, 500);
});
