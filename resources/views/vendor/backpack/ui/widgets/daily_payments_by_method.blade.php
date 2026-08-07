<div class="col-12">
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">Daily Payments by Method &amp; Type</h3>
                <div class="text-muted small">Paid payment totals by method and type (შეკვეთა / ვალი), based on payment date</div>
            </div>
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mt-2">
                <div class="d-flex flex-wrap align-items-end gap-2">
                    <div>
                        <label class="form-label small mb-1" for="daily-payments-from">From</label>
                        <input type="date" id="daily-payments-from" class="form-control form-control-sm">
                    </div>
                    <div>
                        <label class="form-label small mb-1" for="daily-payments-to">To</label>
                        <input type="date" id="daily-payments-to" class="form-control form-control-sm">
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" id="daily-payments-apply">Apply</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="daily-payments-reset">Reset range</button>
                </div>
                <div class="btn-group ms-auto" role="group" aria-label="Chart period">
                    <button type="button" class="btn btn-sm btn-outline-primary active" data-period="days">By day</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-period="months">By month</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-period="years">By year</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-2" id="daily-payments-summary-totals">
                <div class="col-6 col-md-2">
                    <div class="text-muted small">Total</div>
                    <div class="h3 mb-0" id="daily-payments-total-amount">—</div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="text-muted small">Payments</div>
                    <div class="h3 mb-0" id="daily-payments-count">—</div>
                </div>
            </div>
            <div class="row g-3 mb-3" id="daily-payments-type-totals"></div>
            <div class="row g-3 mb-3" id="daily-payments-method-totals"></div>
            <div style="position: relative; height: 400px;">
                <canvas id="daily-payments-chart"></canvas>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    var chartInstance = null;
    var chartUrl = @json(url(config('backpack.base.route_prefix') . '/dashboard/daily-payments-by-method'));
    var canvas = document.getElementById('daily-payments-chart');
    var $from = $('#daily-payments-from');
    var $to = $('#daily-payments-to');
    var $periodButtons = $('#daily-payments-chart').closest('.card').find('[data-period]');
    var currentPeriod = 'days';
    var methodColors = {
        'Cash': { order: 'rgba(47, 179, 68, 0.9)', debt: 'rgba(47, 179, 68, 0.45)' },
        'Transfer': { order: 'rgba(32, 107, 196, 0.9)', debt: 'rgba(32, 107, 196, 0.45)' },
        'Terminal': { order: 'rgba(247, 183, 49, 0.95)', debt: 'rgba(247, 183, 49, 0.45)' },
        'PM Transfer': { order: 'rgba(73, 80, 87, 0.9)', debt: 'rgba(73, 80, 87, 0.45)' }
    };
    var fallbackColors = [
        { order: 'rgba(132, 94, 194, 0.9)', debt: 'rgba(132, 94, 194, 0.45)' },
        { order: 'rgba(214, 57, 57, 0.9)', debt: 'rgba(214, 57, 57, 0.45)' },
        { order: 'rgba(18, 183, 172, 0.9)', debt: 'rgba(18, 183, 172, 0.45)' },
        { order: 'rgba(250, 140, 22, 0.9)', debt: 'rgba(250, 140, 22, 0.45)' }
    ];
    var typeAccent = {
        'Order': 'text-primary',
        'Debt': 'text-danger'
    };

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

    function colorFor(method, type, index) {
        var palette = methodColors[method] || fallbackColors[index % fallbackColors.length];
        return type === 'Debt' ? palette.debt : palette.order;
    }

    function renderTotalsRow($row, items, totals, labels, attrName, valueClassFn) {
        $row.empty();

        items.forEach(function (key) {
            var col = document.createElement('div');
            col.className = 'col-6 col-md-2';
            col.setAttribute(attrName, key);
            col.innerHTML =
                '<div class="text-muted small"></div>' +
                '<div class="h3 mb-0"></div>';
            col.querySelector('.text-muted').textContent = (labels && labels[key]) ? labels[key] : key;
            var valueEl = col.querySelector('.h3');
            valueEl.textContent = formatMoney(totals[key] || 0);
            if (valueClassFn) {
                var extra = valueClassFn(key);
                if (extra) {
                    valueEl.classList.add(extra);
                }
            }
            $row.append(col);
        });
    }

    function renderChart(data) {
        setActivePeriod(data.period || 'days');
        $from.val(data.from);
        $to.val(data.to);

        $('#daily-payments-total-amount').text(formatMoney(data.totalAmount));
        $('#daily-payments-count').text(Number(data.paymentsCount || 0).toLocaleString());

        renderTotalsRow(
            $('#daily-payments-type-totals'),
            data.types || [],
            data.typeTotals || {},
            data.typeLabels || {},
            'data-type-total',
            function (type) { return typeAccent[type] || ''; }
        );
        renderTotalsRow(
            $('#daily-payments-method-totals'),
            data.methods || [],
            data.methodTotals || {},
            null,
            'data-method-total'
        );

        if (chartInstance) {
            chartInstance.destroy();
        }

        var isDaily = (data.period || 'days') === 'days';
        var methods = data.methods || [];
        var types = data.types || [];
        var typeLabels = data.typeLabels || {};
        var datasets = [];

        types.forEach(function (type) {
            methods.forEach(function (method, index) {
                var typeData = (data.series && data.series[type]) ? data.series[type] : {};
                datasets.push({
                    label: method + ' · ' + (typeLabels[type] || type),
                    data: typeData[method] || [],
                    backgroundColor: colorFor(method, type, index),
                    stack: type,
                    borderRadius: 3,
                    maxBarThickness: 22
                });
            });
        });

        chartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: datasets
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
                            beforeBody: function (tooltipItems) {
                                if (!tooltipItems.length) {
                                    return '';
                                }
                                var chart = tooltipItems[0].chart;
                                var i = tooltipItems[0].dataIndex;
                                var byStack = {};
                                var total = 0;
                                chart.data.datasets.forEach(function (dataset) {
                                    var value = Number(dataset.data[i] || 0);
                                    total += value;
                                    byStack[dataset.stack] = (byStack[dataset.stack] || 0) + value;
                                });
                                var lines = ['Total: ' + formatMoney(total)];
                                Object.keys(byStack).forEach(function (stack) {
                                    lines.push((typeLabels[stack] || stack) + ': ' + formatMoney(byStack[stack]));
                                });
                                return lines;
                            },
                            label: function (context) {
                                return context.dataset.label + ': ' + formatMoney(context.parsed.y || 0);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false },
                        ticks: isDaily
                            ? {
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
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        title: { display: true, text: 'Payments (₾)' },
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

    function loadChart(params) {
        var data = $.extend({ period: currentPeriod }, params || {});

        $.ajax({
            url: chartUrl,
            method: 'GET',
            data: data,
            success: renderChart,
            error: function () {
                console.error('Failed to load daily payments by method chart data');
            }
        });
    }

    $periodButtons.on('click', function () {
        setActivePeriod($(this).data('period'));
        loadChart({});
    });

    $('#daily-payments-apply').on('click', function () {
        loadChart({ from: $from.val(), to: $to.val() });
    });

    $('#daily-payments-reset').on('click', function () {
        loadChart({});
    });

    $(document).ready(function () {
        loadChart({});
    });
})();
</script>
@endpush
