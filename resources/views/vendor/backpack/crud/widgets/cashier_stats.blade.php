<div class="row mb-3" id="cashier-stats-widget">
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-primary text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Current Balance</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['stats']['current_balance'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-secondary text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Opening (Today)</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['stats']['opening_balance'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-success text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Cash In (Today)</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['stats']['cash_in'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Cash Out (Today)</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['stats']['cash_out'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
</div>
