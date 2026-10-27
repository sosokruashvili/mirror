@extends(backpack_view('blank'))

@php
    $breadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        __('menu.warehouse') => false,
        __('warehouse.cutting.title') => false,
    ];

    $period = $from === $to ? $from : $from . ' — ' . $to;
@endphp

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h1 class="text-capitalize mb-0">{{ __('warehouse.cutting.title') }}</h1>
        <p class="ms-2 ml-2 mb-0">{{ __('warehouse.cutting.subtitle') }}</p>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-print-none flex-wrap gap-2">
                    <form method="GET" class="row g-2 align-items-end me-auto">
                        <div class="col-auto">
                            <label class="form-label mb-1" for="from">{{ __('warehouse.cutting.from') }}</label>
                            <input type="date" id="from" name="from" value="{{ $from }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <label class="form-label mb-1" for="to">{{ __('warehouse.cutting.to') }}</label>
                            <input type="date" id="to" name="to" value="{{ $to }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="la la-filter"></i> {{ __('warehouse.cutting.filter') }}
                            </button>
                        </div>
                        <div class="col-auto">
                            <div class="btn-group btn-group-sm" role="group">
                                @foreach($presets as $key => [$presetFrom, $presetTo])
                                    @php($active = $presetFrom->toDateString() === $from && $presetTo->toDateString() === $to)
                                    <a href="{{ backpack_url('cutting-stats') . '?' . http_build_query(['from' => $presetFrom->toDateString(), 'to' => $presetTo->toDateString()]) }}"
                                       @class(['btn', 'btn-primary' => $active, 'btn-outline-secondary' => ! $active])>{{ __('warehouse.cutting.presets.' . $key) }}</a>
                                @endforeach
                            </div>
                        </div>
                    </form>
                    <div id="cuttingStatsButtons" class="align-self-end"></div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="cuttingStatsTable" class="table table-hover table-vcenter mb-0">
                            <thead>
                                <tr>
                                    <th class="text-muted" style="width:1%">#</th>
                                    <th>{{ __('warehouse.cutting.product') }}</th>
                                    <th class="text-end">{{ __('warehouse.cutting.clean') }}</th>
                                    <th class="text-end">{{ __('warehouse.cutting.offcut') }}</th>
                                    <th class="text-end">{{ __('warehouse.cutting.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- No colspan "empty" row here: DataTables rejects it and shows its own emptyTable text. --}}
                                @foreach($rows as $i => $row)
                                    <tr>
                                        <td class="text-muted">{{ $i + 1 }}</td>
                                        <td>{{ $row->title }}</td>
                                        {{-- data-export: raw numbers so Excel/CSV get numeric cells. --}}
                                        <td class="text-end" data-export="{{ round($row->clean, 2) }}">{{ number_format($row->clean, 2) }}</td>
                                        <td class="text-end" data-export="{{ round($row->offcut, 2) }}">
                                            <span class="text-muted small me-1">{{ rtrim(rtrim(number_format($row->offcut_percent, 2), '0'), '.') }}%</span>
                                            {{ number_format($row->offcut, 2) }}
                                        </td>
                                        <td class="text-end fw-bold" data-export="{{ round($row->total, 2) }}">{{ number_format($row->total, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td></td>
                                    <td>{{ __('warehouse.cutting.grand_total') }}</td>
                                    <td class="text-end" data-export="{{ round($totals['clean'], 2) }}">{{ number_format($totals['clean'], 2) }}</td>
                                    <td class="text-end" data-export="{{ round($totals['offcut'], 2) }}">{{ number_format($totals['offcut'], 2) }}</td>
                                    <td class="text-end" data-export="{{ round($totals['total'], 2) }}">{{ number_format($totals['total'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_styles')
    @basset('https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css')
    @basset('https://cdn.datatables.net/buttons/2.3.3/css/buttons.bootstrap5.min.css')
@endpush

@push('after_scripts')
    @basset('https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js')
    @basset('https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js')
    @basset('https://cdn.datatables.net/buttons/2.3.3/js/dataTables.buttons.min.js')
    @basset('https://cdn.datatables.net/buttons/2.3.3/js/buttons.bootstrap5.min.js')
    @basset('https://cdnjs.cloudflare.com/ajax/libs/jszip/2.5.0/jszip.min.js')
    @basset('https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.18/pdfmake.min.js')
    @basset('https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.18/vfs_fonts.js')
    @basset('https://cdn.datatables.net/buttons/2.3.2/js/buttons.html5.min.js')
    @basset('https://cdn.datatables.net/buttons/2.3.2/js/buttons.print.min.js')
    <script>
    jQuery(function ($) {
        var exportStrip = function (text) {
            return typeof text === 'string'
                ? text.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim()
                : text;
        };

        var exportOptions = {
            format: {
                body: function (data, row, column, node) {
                    return node && node.hasAttribute('data-export') ? node.getAttribute('data-export') : exportStrip(data);
                },
                // Totals past 1,000 m² would otherwise reach Excel as text.
                footer: function (data) {
                    return exportStrip(data).replace(/,/g, '');
                }
            }
        };

        var exportTitle = @json(__('warehouse.cutting.title') . ' ' . $period);

        var table = $('#cuttingStatsTable').DataTable({
            dom: 't',
            paging: false,
            searching: false,
            ordering: false,
            info: false,
            autoWidth: false,
            language: {
                emptyTable: @json(__('warehouse.cutting.empty'))
            }
        });

        new $.fn.dataTable.Buttons(table, {
            buttons: [
                {
                    extend: 'collection',
                    text: '<i class="la la-download"></i> {{ trans('backpack::crud.export.export') }}',
                    className: 'btn-sm btn-outline-secondary',
                    buttons: ['copyHtml5', 'excelHtml5', 'csvHtml5', 'pdfHtml5', 'print'].map(function (type) {
                        return {
                            extend: type,
                            title: exportTitle,
                            footer: true,
                            exportOptions: exportOptions
                        };
                    })
                }
            ]
        });

        table.buttons().container().appendTo('#cuttingStatsButtons');
        $('#cuttingStatsButtons .btn-secondary').removeClass('btn-secondary');
    });
    </script>
@endpush
