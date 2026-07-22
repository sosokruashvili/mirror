<div class="col-12 col-lg-6">
    <div class="card h-100">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h3 class="card-title mb-0">Top Users by Orders</h3>
                    <div class="text-muted small">Top 10 authors by order count, with total value — excluding draft orders</div>
                </div>
                <div class="btn-group flex-wrap" role="group" aria-label="Chart range">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-range="this_week">This week</button>
                    <button type="button" class="btn btn-sm btn-outline-primary active" data-range="this_month">This month</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-range="last_month">Last month</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-range="last_year">Last year</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <div class="text-muted small">Orders (top 10)</div>
                    <div class="h3 mb-0" id="top-users-total-orders">—</div>
                </div>
                <div class="col-6">
                    <div class="text-muted small">Total value (top 10)</div>
                    <div class="h3 mb-0" id="top-users-total-value">—</div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Range</div>
                    <div class="small mb-0" id="top-users-range-label">—</div>
                </div>
            </div>
            <div style="position: relative; height: 400px;">
                <canvas id="top-users-chart"></canvas>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    var chartInstance = null;
    var chartUrl = @json(url(config('backpack.base.route_prefix') . '/dashboard/top-users-chart'));
    var canvas = document.getElementById('top-users-chart');
    var $card = $(canvas).closest('.card');
    var $buttons = $card.find('[data-range]');

    function formatMoney(value) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ₾';
    }

    function setActiveRange(range) {
        $buttons.removeClass('active');
        $buttons.filter('[data-range="' + range + '"]').addClass('active');
    }

    function renderChart(data) {
        setActiveRange(data.range);
        $('#top-users-total-orders').text(Number(data.totalOrders).toLocaleString());
        $('#top-users-total-value').text(formatMoney(data.totalValue));
        $('#top-users-range-label').text(data.from + ' → ' + data.to);

        if (chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Orders count',
                        data: data.counts,
                        backgroundColor: 'rgba(32, 107, 196, 0.85)',
                        xAxisID: 'xCount',
                        borderRadius: 3,
                        maxBarThickness: 18
                    },
                    {
                        label: 'Value (₾)',
                        data: data.values,
                        backgroundColor: 'rgba(47, 179, 68, 0.85)',
                        xAxisID: 'xValue',
                        borderRadius: 3,
                        maxBarThickness: 18
                    }
                ]
            },
            options: {
                indexAxis: 'y',
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
                                var value = context.parsed.x || 0;
                                if (context.dataset.xAxisID === 'xCount') {
                                    return context.dataset.label + ': ' + Number(value).toLocaleString();
                                }
                                return context.dataset.label + ': ' + formatMoney(value);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        grid: { display: false }
                    },
                    xCount: {
                        position: 'top',
                        beginAtZero: true,
                        title: { display: true, text: 'Orders' },
                        ticks: {
                            precision: 0,
                            callback: function (value) {
                                return Number(value).toLocaleString();
                            }
                        }
                    },
                    xValue: {
                        position: 'bottom',
                        beginAtZero: true,
                        title: { display: true, text: 'Value (₾)' },
                        grid: { drawOnChartArea: false },
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

    function loadChart(range) {
        $.ajax({
            url: chartUrl,
            method: 'GET',
            data: { range: range || 'this_month' },
            success: renderChart,
            error: function () {
                console.error('Failed to load top users chart data');
            }
        });
    }

    $buttons.on('click', function () {
        var range = $(this).data('range');
        setActiveRange(range);
        loadChart(range);
    });

    $(document).ready(function () {
        loadChart('this_month');
    });
})();
</script>
@endpush
