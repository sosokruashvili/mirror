<div class="col-12 col-lg-6">
    <div class="card h-100">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h3 class="card-title mb-0">{{ __('dashboard.product_type_area.title') }}</h3>
                    <div class="text-muted small">{{ __('dashboard.product_type_area.subtitle') }}</div>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-end gap-2 mt-2">
                <div>
                    <label class="form-label small mb-1" for="product-type-area-from">{{ __('dashboard.chart.from') }}</label>
                    <input type="date" id="product-type-area-from" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label small mb-1" for="product-type-area-to">{{ __('dashboard.chart.to') }}</label>
                    <input type="date" id="product-type-area-to" class="form-control form-control-sm">
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="product-type-area-apply">{{ __('dashboard.chart.apply') }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="product-type-area-reset">{{ __('dashboard.chart.last_30_days') }}</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <div class="text-muted small">{{ __('dashboard.product_type_area.total') }}</div>
                    <div class="h3 mb-0">
                        <span id="product-type-area-total">—</span>
                        <span class="text-muted fs-5">{{ __('dashboard.product_type_area.unit') }}</span>
                    </div>
                </div>
            </div>
            <div style="position: relative; height: 340px;">
                <canvas id="product-type-area-chart"></canvas>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    var i18n = @json(__('dashboard.product_type_area'));
    var chartInstance = null;
    var chartUrl = @json(url(config('backpack.base.route_prefix') . '/dashboard/product-type-area-chart'));
    var canvas = document.getElementById('product-type-area-chart');
    var $from = $('#product-type-area-from');
    var $to = $('#product-type-area-to');

    // Colors match the product-type pie slices (mirror / glass / lamix / glass_pkg).
    var barColors = [
        'rgba(32, 107, 196, 0.85)',
        'rgba(47, 179, 68, 0.85)',
        'rgba(247, 103, 7, 0.85)',
        'rgba(174, 62, 201, 0.85)'
    ];

    function formatArea(value) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ' + i18n.unit;
    }

    function renderChart(data) {
        $from.val(data.from);
        $to.val(data.to);

        $('#product-type-area-total').text(Number(data.totalArea).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));

        if (chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: i18n.label,
                    data: data.areas,
                    backgroundColor: data.labels.map(function (_, i) {
                        return barColors[i % barColors.length];
                    }),
                    borderRadius: 4,
                    maxBarThickness: 36
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { right: 72 }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return formatArea(context.parsed.x || 0);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        title: { display: true, text: i18n.label },
                        ticks: {
                            callback: function (value) {
                                return Number(value).toLocaleString();
                            }
                        }
                    },
                    y: {
                        grid: { display: false }
                    }
                }
            },
            plugins: [{
                id: 'barValue',
                afterDatasetsDraw: function (chart) {
                    var ctx = chart.ctx;
                    var meta = chart.getDatasetMeta(0);
                    ctx.save();
                    ctx.fillStyle = '#6c757d';
                    ctx.font = '12px sans-serif';
                    ctx.textBaseline = 'middle';
                    meta.data.forEach(function (bar, i) {
                        var value = chart.data.datasets[0].data[i] || 0;
                        var pos = bar.tooltipPosition();
                        ctx.fillText(formatArea(value), pos.x + 8, pos.y);
                    });
                    ctx.restore();
                }
            }]
        });
    }

    function loadChart(params) {
        $.ajax({
            url: chartUrl,
            method: 'GET',
            data: params || {},
            success: renderChart,
            error: function () {
                console.error('Failed to load product type area chart data');
            }
        });
    }

    $('#product-type-area-apply').on('click', function () {
        loadChart({ from: $from.val(), to: $to.val() });
    });

    $('#product-type-area-reset').on('click', function () {
        loadChart({});
    });

    $(document).ready(function () {
        loadChart({});
    });
})();
</script>
@endpush
