<div class="col-12">
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">{{ __('dashboard.daily.title') }}</h3>
                <div class="text-muted small">{{ __('dashboard.daily.subtitle') }}</div>
            </div>
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mt-2">
                <div class="d-flex flex-wrap align-items-end gap-2">
                    <div>
                        <label class="form-label small mb-1" for="daily-stats-from">{{ __('dashboard.chart.from') }}</label>
                        <input type="date" id="daily-stats-from" class="form-control form-control-sm">
                    </div>
                    <div>
                        <label class="form-label small mb-1" for="daily-stats-to">{{ __('dashboard.chart.to') }}</label>
                        <input type="date" id="daily-stats-to" class="form-control form-control-sm">
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" id="daily-stats-apply">{{ __('dashboard.chart.apply') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="daily-stats-reset">{{ __('dashboard.chart.reset_range') }}</button>
                </div>
                <div class="btn-group ms-auto" role="group" aria-label="{{ __('dashboard.chart.period') }}">
                    <button type="button" class="btn btn-sm btn-outline-primary active" data-period="days">{{ __('dashboard.chart.by_day') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-period="months">{{ __('dashboard.chart.by_month') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-period="years">{{ __('dashboard.chart.by_year') }}</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="text-muted small">{{ __('dashboard.daily.orders') }}</div>
                    <div class="h3 mb-0" id="daily-stats-total-orders">—</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">{{ __('dashboard.daily.income') }}</div>
                    <div class="h3 mb-0" id="daily-stats-total-income">—</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">{{ __('dashboard.daily.paid') }}</div>
                    <div class="h3 mb-0 text-success" id="daily-stats-total-paid">—</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">{{ __('dashboard.daily.credit') }}</div>
                    <div class="h3 mb-0 text-danger" id="daily-stats-total-credit">—</div>
                </div>
            </div>
            <div style="position: relative; height: 400px;">
                <canvas id="daily-stats-chart"></canvas>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    var i18n = @json(__('dashboard.daily'));
    var chartInstance = null;
    var chartUrl = @json(url(config('backpack.base.route_prefix') . '/dashboard/daily-stats-chart'));
    var canvas = document.getElementById('daily-stats-chart');
    var $from = $('#daily-stats-from');
    var $to = $('#daily-stats-to');
    var $periodButtons = $('#daily-stats-chart').closest('.card').find('[data-period]');
    var currentPeriod = 'days';

    function setActivePeriod(period) {
        currentPeriod = period;
        $periodButtons.removeClass('active');
        $periodButtons.filter('[data-period="' + period + '"]').addClass('active');
    }

    function formatMoney(value) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ₾';
    }

    function renderChart(data) {
        // Sync the controls with what the server actually used.
        setActivePeriod(data.period || 'days');
        $from.val(data.from);
        $to.val(data.to);

        $('#daily-stats-total-orders').text(Number(data.totalOrders).toLocaleString());
        $('#daily-stats-total-income').text(formatMoney(data.totalIncome));
        $('#daily-stats-total-paid').text(formatMoney(data.totalPaid));
        $('#daily-stats-total-credit').text(formatMoney(data.totalCredit));

        if (chartInstance) {
            chartInstance.destroy();
        }

        var isDaily = (data.period || 'days') === 'days';

        chartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: i18n.paid,
                        data: data.paid,
                        backgroundColor: 'rgba(47, 179, 68, 0.85)',
                        stack: 'income',
                        yAxisID: 'yMoney',
                        borderRadius: 3,
                        maxBarThickness: 26
                    },
                    {
                        label: i18n.credit,
                        data: data.credit,
                        backgroundColor: 'rgba(214, 57, 57, 0.85)',
                        stack: 'income',
                        yAxisID: 'yMoney',
                        borderRadius: 3,
                        maxBarThickness: 26
                    },
                    {
                        label: i18n.orders_count,
                        data: data.counts,
                        backgroundColor: 'rgba(32, 107, 196, 0.85)',
                        stack: 'orders',
                        yAxisID: 'yCount',
                        borderRadius: 3,
                        maxBarThickness: 26
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
                            // Total income (paid + credit) sits under the date, above the series rows.
                            beforeBody: function (tooltipItems) {
                                if (!tooltipItems.length) {
                                    return '';
                                }
                                var chart = tooltipItems[0].chart;
                                var i = tooltipItems[0].dataIndex;
                                var paid = Number(chart.data.datasets[0].data[i] || 0);
                                var credit = Number(chart.data.datasets[1].data[i] || 0);
                                return i18n.income + ': ' + formatMoney(paid + credit);
                            },
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
                        grid: { display: false },
                        ticks: isDaily
                            ? {
                                // Show every day; rotate labels vertically so they fit.
                                autoSkip: false,
                                maxRotation: 90,
                                minRotation: 90,
                                font: { size: 10 }
                            }
                            : {
                                maxRotation: 45,
                                minRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 15
                            }
                    },
                    yMoney: {
                        position: 'left',
                        stacked: true,
                        beginAtZero: true,
                        title: { display: true, text: i18n.axis_income },
                        ticks: {
                            callback: function (value) {
                                return Number(value).toLocaleString();
                            }
                        }
                    },
                    yCount: {
                        position: 'right',
                        stacked: true,
                        beginAtZero: true,
                        title: { display: true, text: i18n.axis_orders },
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
        var data = $.extend({ period: currentPeriod }, params || {});

        $.ajax({
            url: chartUrl,
            method: 'GET',
            data: data,
            success: renderChart,
            error: function () {
                console.error('Failed to load daily stats chart data');
            }
        });
    }

    $periodButtons.on('click', function () {
        // Switching the grouping resets to that period's default range.
        setActivePeriod($(this).data('period'));
        loadChart({});
    });

    $('#daily-stats-apply').on('click', function () {
        loadChart({ from: $from.val(), to: $to.val() });
    });

    $('#daily-stats-reset').on('click', function () {
        loadChart({});
    });

    $(document).ready(function () {
        loadChart({});
    });
})();
</script>
@endpush
