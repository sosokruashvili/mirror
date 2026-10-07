<div class="row mb-3" id="order-stats-widgets" data-stats-url="{{ url(config('backpack.base.route_prefix') . '/order/stats') }}">
    <div class="col-6 col-md mb-3 mb-md-0">
        <div class="card bg-primary text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('order.stats.orders_count') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" data-stat="ordersCount" data-decimals="0" aria-busy="true"><span class="spinner-border spinner-border-sm" role="status"></span></h2>
            </div>
        </div>
    </div>
    <div class="col-6 col-md mb-3 mb-md-0">
        <div class="card bg-blue text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('order.stats.total_price') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" data-stat="totalPriceGel" data-decimals="2" aria-busy="true"><span class="spinner-border spinner-border-sm" role="status"></span></h2>
            </div>
        </div>
    </div>
    <div class="col-6 col-md mb-3 mb-md-0">
        <div class="card bg-success text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('order.stats.total_paid') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" data-stat="totalPaid" data-decimals="2" data-suffix=" ₾" aria-busy="true"><span class="spinner-border spinner-border-sm" role="status"></span></h2>
            </div>
        </div>
    </div>
    <div class="col-6 col-md mb-3 mb-md-0">
        <div class="card bg-danger text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('order.stats.left_to_pay') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" data-stat="totalUnpaid" data-decimals="2" data-suffix=" ₾" aria-busy="true"><span class="spinner-border spinner-border-sm" role="status"></span></h2>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card bg-warning text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('order.stats.total_expenses') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0" data-stat="totalExpenses" data-decimals="2" aria-busy="true"><span class="spinner-border spinner-border-sm" role="status"></span></h2>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
    {{-- Fills the cards above once the page has loaded. Loaded with a version string, not Basset, so a stale cached copy cannot leave the cards spinning. --}}
    <script src="{{ asset('assets/js/order-stats-refresh.js') }}?v={{ filemtime(public_path('assets/js/order-stats-refresh.js')) }}"></script>
@endpush
