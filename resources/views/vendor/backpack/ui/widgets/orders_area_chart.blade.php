<div class="col-12 col-lg-6">
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h3 class="card-title mb-0">Orders Area Summary</h3>
                    <div class="text-muted small">Total piece area (m²), excluding draft orders and draft pieces</div>
                </div>
                <div class="btn-group" role="group" aria-label="Chart period">
                    <button type="button" class="btn btn-sm btn-outline-primary active" data-period="days">30 Days</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-period="months">12 Months</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-period="years">10 Years</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-baseline gap-2 mb-3">
                <span class="text-muted">Total:</span>
                <span class="h3 mb-0" id="orders-area-chart-total">—</span>
                <span class="text-muted">m²</span>
            </div>
            <div style="position: relative; height: 320px;">
                <canvas id="orders-area-chart"></canvas>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    var chartInstance = null;
    var chartUrl = @json(url(config('backpack.base.route_prefix') . '/dashboard/orders-area-chart'));
    var $buttons = $('[data-period]');
    var $total = $('#orders-area-chart-total');
    var canvas = document.getElementById('orders-area-chart');

    function setActivePeriod(period) {
        $buttons.removeClass('active');
        $buttons.filter('[data-period="' + period + '"]').addClass('active');
    }

    function renderChart(data) {
        $total.text(Number(data.totalArea).toLocaleString(undefined, {
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
                    label: 'Area (m²)',
                    data: data.areas,
                    backgroundColor: 'rgba(32, 107, 196, 0.75)',
                    borderColor: 'rgba(32, 107, 196, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var value = context.parsed.y || 0;
                                return value.toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }) + ' m²';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 15
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Area (m²)'
                        },
                        ticks: {
                            callback: function (value) {
                                return Number(value).toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    function loadChart(period) {
        setActivePeriod(period);

        $.ajax({
            url: chartUrl,
            method: 'GET',
            data: { period: period },
            success: renderChart,
            error: function () {
                console.error('Failed to load orders area chart data');
            }
        });
    }

    $buttons.on('click', function () {
        loadChart($(this).data('period'));
    });

    $(document).ready(function () {
        loadChart('days');
    });
})();
</script>
@endpush
