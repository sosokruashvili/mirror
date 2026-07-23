<div class="col-12 col-lg-6">
    <div class="card h-100">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h3 class="card-title mb-0">Top Users by Stage Completions</h3>
                    <div class="text-muted small">Top 10 workers by production stages finished, broken down by stage — excluding draft orders and the auto-completed final stage</div>
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
                    <div class="text-muted small">Completions (top 10)</div>
                    <div class="h3 mb-0" id="stage-completions-total">—</div>
                </div>
                <div class="col-6">
                    <div class="text-muted small">Active users</div>
                    <div class="h3 mb-0" id="stage-completions-users">—</div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Range</div>
                    <div class="small mb-0" id="stage-completions-range-label">—</div>
                </div>
            </div>
            <div style="position: relative; height: 400px;">
                <canvas id="stage-completions-chart"></canvas>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    var chartInstance = null;
    var chartUrl = @json(route('user-stats.stageCompletionsChart'));
    var canvas = document.getElementById('stage-completions-chart');
    var $card = $(canvas).closest('.card');
    var $buttons = $card.find('[data-range]');

    function setActiveRange(range) {
        $buttons.removeClass('active');
        $buttons.filter('[data-range="' + range + '"]').addClass('active');
    }

    function renderChart(data) {
        setActiveRange(data.range);
        $('#stage-completions-total').text(Number(data.totalCompletions).toLocaleString());
        $('#stage-completions-users').text(Number(data.userCount).toLocaleString());
        $('#stage-completions-range-label').text(data.from + ' → ' + data.to);

        if (chartInstance) {
            chartInstance.destroy();
        }

        var datasets = (data.datasets || []).map(function (ds) {
            return {
                label: ds.label,
                data: ds.data,
                backgroundColor: ds.color,
                borderWidth: 0,
                borderRadius: 2,
                maxBarThickness: 22
            };
        });

        chartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: datasets
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
                                return context.dataset.label + ': ' + Number(value).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        title: { display: true, text: 'Stages completed' },
                        ticks: {
                            precision: 0,
                            callback: function (value) {
                                return Number(value).toLocaleString();
                            }
                        }
                    },
                    y: {
                        stacked: true,
                        grid: { display: false }
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
                console.error('Failed to load stage completions chart data');
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
