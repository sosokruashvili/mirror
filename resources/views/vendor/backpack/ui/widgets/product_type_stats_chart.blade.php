<div class="col-12 col-lg-6">
    <div class="card h-100">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h3 class="card-title mb-0">Orders by Product Type</h3>
                    <div class="text-muted small">Orders count and total value per product type, excluding draft orders</div>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-end gap-2 mt-2">
                <div>
                    <label class="form-label small mb-1" for="product-type-stats-from">From</label>
                    <input type="date" id="product-type-stats-from" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label small mb-1" for="product-type-stats-to">To</label>
                    <input type="date" id="product-type-stats-to" class="form-control form-control-sm">
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="product-type-stats-apply">Apply</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="product-type-stats-reset">Last 30 days</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <div class="text-muted small">Orders</div>
                    <div class="h3 mb-0" id="product-type-stats-total-orders">—</div>
                </div>
                <div class="col-6">
                    <div class="text-muted small">Total value</div>
                    <div class="h3 mb-0" id="product-type-stats-total-value">—</div>
                </div>
            </div>
            <div style="position: relative; height: 340px;">
                <canvas id="product-type-stats-chart"></canvas>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    var chartInstance = null;
    var chartUrl = @json(url(config('backpack.base.route_prefix') . '/dashboard/product-type-stats-chart'));
    var canvas = document.getElementById('product-type-stats-chart');
    var $from = $('#product-type-stats-from');
    var $to = $('#product-type-stats-to');

    function formatMoney(value) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ₾';
    }

    function renderChart(data) {
        // Sync the pickers with the range the server actually used.
        $from.val(data.from);
        $to.val(data.to);

        $('#product-type-stats-total-orders').text(Number(data.totalOrders).toLocaleString());
        $('#product-type-stats-total-value').text(formatMoney(data.totalValue));

        if (chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Value (₾)',
                        data: data.values,
                        backgroundColor: 'rgba(47, 179, 68, 0.85)',
                        yAxisID: 'yValue',
                        borderRadius: 3,
                        maxBarThickness: 48
                    },
                    {
                        label: 'Orders count',
                        data: data.counts,
                        backgroundColor: 'rgba(32, 107, 196, 0.85)',
                        yAxisID: 'yCount',
                        borderRadius: 3,
                        maxBarThickness: 48
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var value = context.parsed.y || 0;
                                if (context.dataset.yAxisID === 'yCount') {
                                    return context.dataset.label + ': ' + Number(value).toLocaleString();
                                }
                                return context.dataset.label + ': ' + formatMoney(value);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    yValue: {
                        position: 'left',
                        beginAtZero: true,
                        title: { display: true, text: 'Value (₾)' },
                        ticks: {
                            callback: function (value) {
                                return Number(value).toLocaleString();
                            }
                        }
                    },
                    yCount: {
                        position: 'right',
                        beginAtZero: true,
                        title: { display: true, text: 'Orders' },
                        grid: { drawOnChartArea: false },
                        ticks: {
                            precision: 0,
                            callback: function (value) {
                                return Number(value).toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    function loadChart(params) {
        $.ajax({
            url: chartUrl,
            method: 'GET',
            data: params || {},
            success: renderChart,
            error: function () {
                console.error('Failed to load product type stats chart data');
            }
        });
    }

    $('#product-type-stats-apply').on('click', function () {
        loadChart({ from: $from.val(), to: $to.val() });
    });

    $('#product-type-stats-reset').on('click', function () {
        loadChart({});
    });

    $(document).ready(function () {
        loadChart({});
    });
})();
</script>
@endpush
