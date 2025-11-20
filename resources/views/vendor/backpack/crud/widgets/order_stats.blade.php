<div class="row mb-3">
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card bg-primary text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Orders Count</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['ordersCount']) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Total Price (GEL)</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['totalPriceGel'], 2) }}</h2>
            </div>
        </div>
    </div>

</div>

