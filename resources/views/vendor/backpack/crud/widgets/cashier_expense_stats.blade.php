<div class="row mb-3" id="cashier-expense-stats-widget">
    {{-- Draft expenses count nowhere, so these totals can differ from the rows listed below. --}}
    <div class="col-12 mb-2">
        <small class="text-muted">{{ __('cashier_expense.stats.confirmed_only') }}</small>
    </div>
    <div class="col-md-6 col-lg mb-3 mb-lg-0">
        <div class="card bg-warning text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('cashier_expense.stats.total_amount') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-total-amount">{{ number_format($widget['totalAmount'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg mb-3 mb-lg-0">
        <div class="card bg-danger text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('cashier_expense.stats.total_credit') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-total-credit">{{ number_format($widget['totalCredit'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg mb-3 mb-lg-0">
        <div class="card bg-success text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('cashier_expense.stats.total_cash') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-total-cash">{{ number_format($widget['totalCash'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg mb-3 mb-lg-0">
        <div class="card bg-info text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('cashier_expense.stats.total_transfer') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-total-transfer">{{ number_format($widget['totalTransfer'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg">
        <div class="card bg-primary text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('cashier_expense.stats.total_pm_transfer') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="stats-total-pm-transfer">{{ number_format($widget['totalPmTransfer'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script>
    // Match PHP's number_format(): comma thousands, two decimals. Pinned to en-US
    // so a redraw can't switch separators on the values Blade already rendered.
    function formatCashierExpenseAmount(value) {
        return (parseFloat(value) || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ₾';
    }

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
        if (urlParams.get('category_id')) params.category_id = urlParams.get('category_id');
        if (urlParams.get('supplier_id')) params.supplier_id = urlParams.get('supplier_id');
        if (urlParams.get('product_id')) params.product_id = urlParams.get('product_id');
        if (urlParams.get('expense_date')) params.expense_date = urlParams.get('expense_date');

        $.ajax({
            url: '{{ url(config("backpack.base.route_prefix") . "/cashier-expense/get-expense-stats") }}',
            method: 'GET',
            data: params,
            success: function(response) {
                $('#stats-total-amount').text(formatCashierExpenseAmount(response.totalAmount));
                $('#stats-total-credit').text(formatCashierExpenseAmount(response.totalCredit));
                $('#stats-total-cash').text(formatCashierExpenseAmount(response.totalCash));
                $('#stats-total-transfer').text(formatCashierExpenseAmount(response.totalTransfer));
                $('#stats-total-pm-transfer').text(formatCashierExpenseAmount(response.totalPmTransfer));
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
