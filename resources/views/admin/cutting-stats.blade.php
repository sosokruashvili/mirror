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

        // pdfmake's built-in Roboto has no Georgian glyphs, so product names
        // would be blank. DejaVu is loaded only when PDF is exported.
        var pdfFonts = {
            regular: @json(asset('assets/fonts/DejaVuSans.ttf')),
            bold: @json(asset('assets/fonts/DejaVuSans-Bold.ttf'))
        };
        var dejavuReady = null;

        function bufferToBase64(buffer) {
            var bytes = new Uint8Array(buffer);
            var binary = '';
            var chunk = 0x8000;
            for (var i = 0; i < bytes.length; i += chunk) {
                binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
            }
            return btoa(binary);
        }

        function ensureDejaVu() {
            if (window.pdfMake && pdfMake.fonts && pdfMake.fonts.DejaVu) {
                return Promise.resolve();
            }
            if (!dejavuReady) {
                dejavuReady = Promise.all([
                    fetch(pdfFonts.regular).then(function (r) { if (!r.ok) throw new Error('font'); return r.arrayBuffer(); }),
                    fetch(pdfFonts.bold).then(function (r) { if (!r.ok) throw new Error('font'); return r.arrayBuffer(); })
                ]).then(function (bufs) {
                    pdfMake.vfs['DejaVuSans.ttf'] = bufferToBase64(bufs[0]);
                    pdfMake.vfs['DejaVuSans-Bold.ttf'] = bufferToBase64(bufs[1]);
                    // This pdfmake build only reads window.pdfMake.fonts, and
                    // does not create that object itself (it falls back to Roboto).
                    pdfMake.fonts = {
                        Roboto: {
                            normal: 'Roboto-Regular.ttf',
                            bold: 'Roboto-Medium.ttf',
                            italics: 'Roboto-Italic.ttf',
                            bolditalics: 'Roboto-Italic.ttf'
                        },
                        DejaVu: {
                            normal: 'DejaVuSans.ttf',
                            bold: 'DejaVuSans-Bold.ttf',
                            italics: 'DejaVuSans.ttf',
                            bolditalics: 'DejaVuSans-Bold.ttf'
                        }
                    };
                });
            }
            return dejavuReady;
        }

        var table = $('#cuttingStatsTable').DataTable({
            dom: 't',
            paging: false,
            searching: false,
            ordering: false,
            info: false,
            autoWidth: false,
            language: {
                emptyTable: @json(__('warehouse.cutting.empty')),
                buttons: {
                    copy: @json(trans('backpack::crud.export.copy')),
                    excel: @json(trans('backpack::crud.export.excel')),
                    csv: @json(trans('backpack::crud.export.csv')),
                    pdf: @json(trans('backpack::crud.export.pdf')),
                    print: @json(trans('backpack::crud.export.print'))
                }
            }
        });

        new $.fn.dataTable.Buttons(table, {
            buttons: [
                {
                    extend: 'collection',
                    text: '<i class="la la-download"></i> {{ trans('backpack::crud.export.export') }}',
                    className: 'btn-sm btn-outline-secondary',
                    buttons: ['copyHtml5', 'excelHtml5', 'csvHtml5', 'pdfHtml5', 'print'].map(function (type) {
                        var button = {
                            extend: type,
                            title: exportTitle,
                            footer: true,
                            exportOptions: exportOptions
                        };
                        if (type === 'pdfHtml5') {
                            button.orientation = 'landscape';
                            button.customize = function (doc) {
                                if (pdfMake.fonts && pdfMake.fonts.DejaVu) {
                                    doc.defaultStyle.font = 'DejaVu';
                                }
                            };
                            button.action = function (e, dt, node, config) {
                                var that = this;
                                ensureDejaVu().catch(function () {}).then(function () {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(that, e, dt, node, config);
                                });
                            };
                        }
                        return button;
                    })
                }
            ]
        });

        table.buttons().container().appendTo('#cuttingStatsButtons');
        // Backpack's common.css hides every .dt-buttons; CRUD lists reveal
        // theirs with a display utility after moving them into the list footer.
        $('#cuttingStatsButtons .dt-buttons').addClass('d-inline-flex');
        $('#cuttingStatsButtons .btn-secondary').removeClass('btn-secondary');
    });
    </script>
@endpush
