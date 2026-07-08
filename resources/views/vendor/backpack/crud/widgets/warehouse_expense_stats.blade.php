<div class="row mb-3">
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-primary text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Orders Count</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['ordersCount']) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Total Expenses (m²)</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['totalExpenses'], 2) }}</h2>
            </div>
        </div>
    </div>
</div>
