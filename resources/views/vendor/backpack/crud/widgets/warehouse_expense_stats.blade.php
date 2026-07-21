<div class="row mb-3" id="warehouse-expense-stats-widget">
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-primary text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Orders Count</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-orders-count">{{ number_format($widget['ordersCount']) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Total Expenses (m²)</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-total-expenses">{{ number_format($widget['totalExpenses'], 2) }}</h2>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script>
    // Refresh the summary widget whenever the list filters change (AJAX redraw),
    // so the totals always match the currently filtered table.
    function updateWarehouseExpenseStats() {
        var currentUrl = typeof crud !== 'undefined' && typeof crud.table !== 'undefined'
            ? crud.table.ajax.url()
            : window.location.href;

        var urlParts = currentUrl.split('?');
        var queryString = urlParts.length > 1 ? urlParts[1] : '';
        var urlParams = new URLSearchParams(queryString);

        var params = {};
        if (urlParams.get('client_id')) params.client_id = urlParams.get('client_id');
        if (urlParams.get('product_type')) params.product_type = urlParams.get('product_type');
        if (urlParams.get('status')) params.status = urlParams.get('status');
        if (urlParams.get('created_at')) params.created_at = urlParams.get('created_at');

        $.ajax({
            url: '{{ url(config("backpack.base.route_prefix") . "/warehouse-expense/get-expense-stats") }}',
            method: 'GET',
            data: params,
            success: function(response) {
                $('#stats-orders-count').text(Number(response.ordersCount).toLocaleString());
                $('#stats-total-expenses').text(parseFloat(response.totalExpenses).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }));
            },
            error: function() {
                console.error('Failed to update warehouse expense stats');
            }
        });
    }

    $(document).ready(function() {
        function bindDrawListener() {
            if (typeof crud !== 'undefined' && typeof crud.table !== 'undefined') {
                crud.table.on('draw.dt', function() {
                    clearTimeout(window.warehouseExpenseStatsTimeout);
                    window.warehouseExpenseStatsTimeout = setTimeout(updateWarehouseExpenseStats, 300);
                });
            } else {
                setTimeout(bindDrawListener, 500);
            }
        }
        bindDrawListener();
    });
</script>
@endpush
