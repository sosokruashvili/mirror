<div class="row mb-3">
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-primary text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Expenses Count</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['expensesCount']) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-warning text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Total Amount</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['totalAmount'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-success text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Cash</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['totalCash'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white mb-0">
            <div class="card-header">
                <h4 class="mb-0">Transfer</h4>
            </div>
            <div class="card-body">
                <h2 class="mb-0">{{ number_format($widget['totalTransfer'], 2) }} ₾</h2>
            </div>
        </div>
    </div>
</div>
