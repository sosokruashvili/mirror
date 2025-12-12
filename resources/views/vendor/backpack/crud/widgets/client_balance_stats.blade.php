<div class="row mb-3" id="client-balance-stats-widget">
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-primary text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Clients Count</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-clients-count">{{ number_format($widget['clientsCount']) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-success text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Total Payments</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-total-payments">{{ number_format($widget['totalPayments'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-info text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Total Orders</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-total-orders">{{ number_format($widget['totalOrders'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card {{ $widget['totalBalance'] >= 0 ? 'bg-success' : 'bg-danger' }} text-white mb-0" id="stats-balance-card">
            <div class="card-header">
                <h4 class="mb-0">Total Balance</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-total-balance">{{ number_format($widget['totalBalance'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script>
    // Function to update widget stats via AJAX
    function updateClientBalanceStats() {
        // Get current filter values from URL
        var currentUrl = typeof crud !== 'undefined' && typeof crud.table !== 'undefined' 
            ? crud.table.ajax.url() 
            : window.location.href;
        
        // Extract query parameters
        var urlParts = currentUrl.split('?');
        var queryString = urlParts.length > 1 ? urlParts[1] : '';
        var urlParams = new URLSearchParams(queryString);
        
        // Build query parameters object
        var params = {};
        if (urlParams.get('client_type')) params.client_type = urlParams.get('client_type');
        if (urlParams.get('name')) params.name = urlParams.get('name');
        if (urlParams.get('email')) params.email = urlParams.get('email');
        if (urlParams.get('phone_number')) params.phone_number = urlParams.get('phone_number');
        
        // Make AJAX request
        $.ajax({
            url: '{{ url(config("backpack.base.route_prefix") . "/client-balance/get-balance-stats") }}',
            method: 'GET',
            data: params,
            success: function(response) {
                // Update widget values
                $('#stats-clients-count').text(response.clientsCount.toLocaleString());
                $('#stats-total-payments').text(parseFloat(response.totalPayments).toFixed(2) + ' ₾');
                $('#stats-total-orders').text(parseFloat(response.totalOrders).toFixed(2) + ' ₾');
                $('#stats-total-balance').text(parseFloat(response.totalBalance).toFixed(2) + ' ₾');
                
                // Update balance card color
                var $balanceCard = $('#stats-balance-card');
                $balanceCard.removeClass('bg-success bg-danger');
                if (response.totalBalance >= 0) {
                    $balanceCard.addClass('bg-success');
                } else {
                    $balanceCard.addClass('bg-danger');
                }
            },
            error: function() {
                console.error('Failed to update client balance stats');
            }
        });
    }
    
    // Update stats when filters change
    $(document).ready(function() {
        // Wait for DataTable to be initialized
        if (typeof crud !== 'undefined') {
            // Listen for DataTable draw events (when filters are applied and table redraws)
            if (typeof crud.table !== 'undefined') {
                crud.table.on('draw.dt', function() {
                    // Debounce to avoid too many requests
                    clearTimeout(window.clientBalanceStatsTimeout);
                    window.clientBalanceStatsTimeout = setTimeout(updateClientBalanceStats, 300);
                });
            } else {
                // If table not ready, wait a bit and try again
                setTimeout(function() {
                    if (typeof crud.table !== 'undefined') {
                        crud.table.on('draw.dt', function() {
                            clearTimeout(window.clientBalanceStatsTimeout);
                            window.clientBalanceStatsTimeout = setTimeout(updateClientBalanceStats, 300);
                        });
                    }
                }, 500);
            }
        }
    });
</script>
@endpush

