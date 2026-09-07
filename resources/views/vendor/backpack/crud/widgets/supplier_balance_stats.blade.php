{{--
  Summary totals for the supplier balance list. The numbers cover every supplier
  matching the current filters, not just the page on screen, and count confirmed
  Expenses-Purchases only (drafts count nowhere).
--}}
<div class="row mb-3" id="supplier-balance-stats-widget">
    <div class="col-12 mb-2">
        <small class="text-muted">{{ __('supplier_balance.stats.confirmed_only') }}</small>
    </div>
    <div class="col-md-6 mb-3 mb-md-0">
        <div class="card bg-success text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('supplier_balance.stats.paid_total') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="supplier-stats-paid-total">{{ number_format($widget['paidTotal'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card {{ $widget['balanceTotal'] < 0 ? 'bg-danger' : 'bg-primary' }} text-white mb-0" id="supplier-stats-balance-card">
            <div class="card-header">
                <h4 class="mb-0">{{ __('supplier_balance.stats.balance_total') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" id="supplier-stats-balance-total">{{ number_format($widget['balanceTotal'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script>
    // Match PHP's number_format(): comma thousands, two decimals. Pinned to en-US
    // so a redraw can't switch separators on the values Blade already rendered.
    function formatSupplierBalanceAmount(value) {
        return (parseFloat(value) || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ₾';
    }

    // Refresh the totals whenever the list redraws (filter or search change), so
    // the widget always describes the currently filtered table.
    function updateSupplierBalanceStats() {
        var currentUrl = typeof crud !== 'undefined' && typeof crud.table !== 'undefined'
            ? crud.table.ajax.url()
            : window.location.href;

        var urlParts = currentUrl.split('?');
        var urlParams = new URLSearchParams(urlParts.length > 1 ? urlParts[1] : '');

        var params = {};
        if (urlParams.get('name')) params.name = urlParams.get('name');
        if (urlParams.get('only_debt')) params.only_debt = urlParams.get('only_debt');

        // The search box lives in DataTables' own state, not in the ajax URL.
        if (typeof crud !== 'undefined' && typeof crud.table !== 'undefined' && crud.table.search()) {
            params.search = crud.table.search();
        }

        $.ajax({
            url: '{{ url(config("backpack.base.route_prefix") . "/supplier-balance/get-balance-stats") }}',
            method: 'GET',
            data: params,
            success: function (response) {
                $('#supplier-stats-paid-total').text(formatSupplierBalanceAmount(response.paidTotal));
                $('#supplier-stats-balance-total').text(formatSupplierBalanceAmount(response.balanceTotal));
                $('#supplier-stats-balance-card')
                    .removeClass('bg-danger bg-primary')
                    .addClass(parseFloat(response.balanceTotal) < 0 ? 'bg-danger' : 'bg-primary');
            },
            error: function () {
                console.error('Failed to update supplier balance stats');
            }
        });
    }

    $(document).ready(function () {
        function bindDrawListener() {
            if (typeof crud !== 'undefined' && typeof crud.table !== 'undefined') {
                crud.table.on('draw.dt', function () {
                    clearTimeout(window.supplierBalanceStatsTimeout);
                    window.supplierBalanceStatsTimeout = setTimeout(updateSupplierBalanceStats, 300);
                });
            } else {
                setTimeout(bindDrawListener, 500);
            }
        }
        bindDrawListener();
    });
</script>
@endpush
