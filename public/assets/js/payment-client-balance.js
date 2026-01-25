// Display client balance when client is selected in payment form
$(document).ready(function() {
    // Use Backpack's onChange if available, otherwise use jQuery change event
    if (typeof crud !== 'undefined' && typeof crud.field === 'function') {
        try {
            crud.field('client_id').onChange(function(field) {
                if (field.value) {
                    fetchClientBalance(field.value);
                } else {
                    hideClientBalance();
                }
            });
        } catch (e) {
            console.log('Using jQuery fallback for client_id onChange');
            // Fallback to jQuery if Backpack API fails
            var $clientSelect = $('select[name="client_id"], #client_id_field');
            
            // Listen for regular change event
            $clientSelect.on('change', function() {
                var clientId = $(this).val();
                if (clientId) {
                    fetchClientBalance(clientId);
                } else {
                    hideClientBalance();
                }
            });
            
            // Also listen for select2:select event (select2 specific)
            $clientSelect.on('select2:select', function() {
                var clientId = $(this).val();
                if (clientId) {
                    fetchClientBalance(clientId);
                }
            });
        }
    } else {
        // Fallback if crud is not available
        var $clientSelect = $('select[name="client_id"], #client_id_field');
        
        // Listen for regular change event
        $clientSelect.on('change', function() {
            var clientId = $(this).val();
            if (clientId) {
                fetchClientBalance(clientId);
            } else {
                hideClientBalance();
            }
        });
        
        // Also listen for select2:select event (select2 specific)
        $clientSelect.on('select2:select', function() {
            var clientId = $(this).val();
            if (clientId) {
                fetchClientBalance(clientId);
            }
        });
    }
    
    // Check if client is already selected on page load (for edit page)
    setTimeout(function() {
        try {
            var clientId = crud.field('client_id').$input.val();
            if (clientId) {
                fetchClientBalance(clientId);
            }
        } catch (e) {
            // Fallback to jQuery
            var $clientSelect = $('select[name="client_id"], #client_id_field');
            if ($clientSelect.length) {
                var clientId = $clientSelect.val();
                if (clientId) {
                    fetchClientBalance(clientId);
                }
            }
        }
    }, 500);
});

function fetchClientBalance(clientId) {
    if (!clientId) {
        hideClientBalance();
        return;
    }

    // Show loading state
    $('#client_balance_display').show();
    $('#client_balance_value').text('Loading...');

    $.ajax({
        url: '/admin/payment/get-client-balance/' + clientId,
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
        },
        success: function(response) {
            var balance = parseFloat(response.balance);
            var formattedBalance = response.formatted || number_format(balance, 2) + ' ₾';
            
            $('#client_balance_value').text(formattedBalance);
            
            // Add color coding: green for positive, red for negative
            var $balanceDisplay = $('#client_balance_display .form-control');
            $balanceDisplay.removeClass('text-success text-danger');
            if (balance >= 0) {
                $balanceDisplay.addClass('text-success');
            } else {
                $balanceDisplay.addClass('text-danger');
            }
            
            $('#client_balance_display').show();
        },
        error: function(xhr) {
            console.error('Failed to fetch client balance');
            $('#client_balance_value').text('Error loading balance');
            $('#client_balance_display').show();
        }
    });
}

function hideClientBalance() {
    $('#client_balance_display').hide();
    $('#client_balance_value').text('-');
}

function number_format(number, decimals) {
    decimals = decimals || 0;
    number = parseFloat(number);
    if (isNaN(number)) return '0.00';
    
    return number.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
