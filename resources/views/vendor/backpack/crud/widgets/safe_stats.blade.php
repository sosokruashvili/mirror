<div class="row mb-3" id="safe-stats-widget">
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-primary text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('safe.stats.balance') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-1">{{ number_format($widget['stats']['balance'], 2) }} ₾</h2>
                <div class="small">{{ __('safe.stats.balance_hint') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-success text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('safe.stats.deposited') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['stats']['deposited'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-secondary text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('safe.stats.returned') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['stats']['returned'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">{{ __('safe.stats.expenses') }}</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['stats']['expenses'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
</div>
