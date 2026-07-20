<div class="col-12 col-lg-6">
    <div class="card h-100">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h3 class="card-title mb-0">Orders by Product Type</h3>
                    <div class="text-muted small">Share of confirmed orders per product type, excluding draft orders</div>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-end gap-2 mt-2">
                <div>
                    <label class="form-label small mb-1" for="product-type-pie-from">From</label>
                    <input type="date" id="product-type-pie-from" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label small mb-1" for="product-type-pie-to">To</label>
                    <input type="date" id="product-type-pie-to" class="form-control form-control-sm">
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="product-type-pie-apply">Apply</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="product-type-pie-reset">Last 30 days</button>
                <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Pie chart metric">
                    <button type="button" class="btn btn-primary" id="product-type-pie-metric-count" data-metric="count">By orders count</button>
                    <button type="button" class="btn btn-outline-primary" id="product-type-pie-metric-value" data-metric="value">By income</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <div class="text-muted small">Total orders</div>
                    <div class="h3 mb-0" id="product-type-pie-total-orders">—</div>
                </div>
            </div>
            <div style="position: relative; height: 340px;">
                <canvas id="product-type-pie-chart"></canvas>
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
    var canvas = document.getElementById('product-type-pie-chart');
    var $from = $('#product-type-pie-from');
    var $to = $('#product-type-pie-to');

    var lastData = null;
    var metric = 'count'; // 'count' (orders count) or 'value' (income)

    // Fixed slice colors matching the product type order returned by the endpoint.
    var sliceColors = [
        'rgba(32, 107, 196, 0.85)',
        'rgba(47, 179, 68, 0.85)',
        'rgba(247, 103, 7, 0.85)',
        'rgba(174, 62, 201, 0.85)',
        'rgba(45, 194, 197, 0.85)'
    ];

    function formatMoney(value) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ₾';
    }

    function renderChart() {
        if (!lastData) {
            return;
        }

        var isValue = metric === 'value';
        var seriesData = isValue ? lastData.values : lastData.counts;
        var datasetLabel = isValue ? 'Income (₾)' : 'Orders count';

        if (chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(canvas, {
            type: 'pie',
            data: {
                labels: lastData.labels,
                datasets: [
                    {
                        label: datasetLabel,
                        data: seriesData,
                        backgroundColor: lastData.labels.map(function (_, i) {
                            return sliceColors[i % sliceColors.length];
                        }),
                        borderColor: '#fff',
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var value = context.parsed || 0;
                                var total = context.dataset.data.reduce(function (sum, v) {
                                    return sum + Number(v || 0);
                                }, 0);
                                var pct = total ? Math.round((value / total) * 100) : 0;
                                var display = isValue ? formatMoney(value) : Number(value).toLocaleString();
                                return context.label + ': ' + display + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    function applyData(data) {
        lastData = data;

        // Sync the pickers with the range the server actually used.
        $from.val(data.from);
        $to.val(data.to);

        $('#product-type-pie-total-orders').text(Number(data.totalOrders).toLocaleString());

        renderChart();
    }

    function loadChart(params) {
        $.ajax({
            url: chartUrl,
            method: 'GET',
            data: params || {},
            success: applyData,
            error: function () {
                console.error('Failed to load product type pie chart data');
            }
        });
    }

    function setMetric(next) {
        metric = next;
        $('#product-type-pie-metric-count')
            .toggleClass('btn-primary', metric === 'count')
            .toggleClass('btn-outline-primary', metric !== 'count');
        $('#product-type-pie-metric-value')
            .toggleClass('btn-primary', metric === 'value')
            .toggleClass('btn-outline-primary', metric !== 'value');
        renderChart();
    }

    $('#product-type-pie-apply').on('click', function () {
        loadChart({ from: $from.val(), to: $to.val() });
    });

    $('#product-type-pie-reset').on('click', function () {
        loadChart({});
    });

    $('#product-type-pie-metric-count').on('click', function () {
        setMetric('count');
    });

    $('#product-type-pie-metric-value').on('click', function () {
        setMetric('value');
    });

    $(document).ready(function () {
        loadChart({});
    });
})();
</script>
@endpush
