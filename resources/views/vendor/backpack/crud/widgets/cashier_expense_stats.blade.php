<div class="row mb-3" id="cashier-expense-stats-widget">
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-primary text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Expenses Count</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-expenses-count">{{ number_format($widget['expensesCount']) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-warning text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Total Amount</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-total-amount">{{ number_format($widget['totalAmount'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-success text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Cash</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-total-cash">{{ number_format($widget['totalCash'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Transfer</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-total-transfer">{{ number_format($widget['totalTransfer'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script>
    // Refresh the summary widget whenever the list filters change (AJAX redraw),
    // so the totals always match the currently filtered table.
    function updateCashierExpenseStats() {
        var currentUrl = typeof crud !== 'undefined' && typeof crud.table !== 'undefined'
            ? crud.table.ajax.url()
            : window.location.href;

        var urlParts = currentUrl.split('?');
        var queryString = urlParts.length > 1 ? urlParts[1] : '';
        var urlParams = new URLSearchParams(queryString);

        var params = {};
        if (urlParams.get('type')) params.type = urlParams.get('type');
        if (urlParams.get('category')) params.category = urlParams.get('category');
        if (urlParams.get('expense_date')) params.expense_date = urlParams.get('expense_date');

        $.ajax({
            url: '{{ url(config("backpack.base.route_prefix") . "/cashier-expense/get-expense-stats") }}',
            method: 'GET',
            data: params,
            success: function(response) {
                $('#stats-expenses-count').text(Number(response.expensesCount).toLocaleString());
                $('#stats-total-amount').text(parseFloat(response.totalAmount).toFixed(2) + ' ₾');
                $('#stats-total-cash').text(parseFloat(response.totalCash).toFixed(2) + ' ₾');
                $('#stats-total-transfer').text(parseFloat(response.totalTransfer).toFixed(2) + ' ₾');
            },
            error: function() {
                console.error('Failed to update cashier expense stats');
            }
        });
    }

    $(document).ready(function() {
        function bindDrawListener() {
            if (typeof crud !== 'undefined' && typeof crud.table !== 'undefined') {
                crud.table.on('draw.dt', function() {
                    clearTimeout(window.cashierExpenseStatsTimeout);
                    window.cashierExpenseStatsTimeout = setTimeout(updateCashierExpenseStats, 300);
                });
            } else {
                setTimeout(bindDrawListener, 500);
            }
        }
        bindDrawListener();
    });
</script>
@endpush
