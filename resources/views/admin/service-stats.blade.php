@extends(backpack_view('blank'))

@php
    $breadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        __('menu.services') => backpack_url('service'),
        __('service.stats.title') => false,
    ];

    // Trim trailing zeros so 108.00 shows as "108" but 6.45 stays "6.45".
    $fmtQty = function ($n) {
        $s = number_format((float) $n, 2, '.', ',');
        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    };

    // Same per-user store the CRUD lists use (users.crud_column_visibility).
    $columnVisibilityUser = backpack_user();
    $savedColumnVisibility = $columnVisibilityUser
        ? $columnVisibilityUser->columnVisibilityFor('service-stats')
        : [];
@endphp

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h1 class="text-capitalize mb-0">{{ __('service.stats.title') }}</h1>
        <p class="ms-2 ml-2 mb-0">{{ __('service.stats.subtitle') }}</p>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-print-none">
                    <form method="GET" class="row g-2 align-items-end me-auto">
                        <div class="col-auto">
                            <label class="form-label mb-1" for="from">{{ __('service.stats.from') }}</label>
                            <input type="date" id="from" name="from" value="{{ $from }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <label class="form-label mb-1" for="to">{{ __('service.stats.to') }}</label>
                            <input type="date" id="to" name="to" value="{{ $to }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="la la-filter"></i> {{ __('service.stats.filter') }}
                            </button>
                            @if($from || $to)
                                <a href="{{ backpack_url('service-stats') }}" class="btn btn-sm btn-link">{{ __('service.stats.reset') }}</a>
                            @endif
                        </div>
                    </form>
                    <div id="serviceStatsButtons" class="ms-2 align-self-end"></div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="serviceStatsTable" class="table table-hover table-vcenter mb-0">
                            <thead>
                                <tr>
                                    <th data-column-name="row_number" class="text-muted" style="width:1%">#</th>
                                    <th data-column-name="title" data-can-be-visible-in-table="false">{{ __('service.stats.service_name') }}</th>
                                    <th data-column-name="stage">{{ __('service.stats.stage') }}</th>
                                    <th data-column-name="unit">{{ __('service.stats.unit') }}</th>
                                    <th data-column-name="quantity" class="text-end">{{ __('service.stats.quantity_done') }}</th>
                                    <th data-column-name="money" class="text-end">{{ __('service.stats.money_payable') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- No colspan "empty" row here: DataTables rejects it and shows its own emptyTable text. --}}
                                @foreach($rows as $i => $row)
                                    <tr @class(['text-muted' => ! $row['active']])>
                                        <td class="text-muted">{{ $i + 1 }}</td>
                                        <td>{{ $row['title'] }}</td>
                                        <td><span class="text-muted small">{{ $row['stage'] }}</span></td>
                                        <td>{{ $row['unit'] }}</td>
                                        {{-- data-export: raw numbers so Excel/CSV get numeric cells, not "1,234.50 ₾" text. --}}
                                        <td class="text-end" data-export="{{ round($row['quantity'], 2) }}">{{ $fmtQty($row['quantity']) }}</td>
                                        <td class="text-end" data-export="{{ round($row['money'], 2) }}">{{ number_format($row['money'], 2) }} ₾</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                {{-- One cell per column (no colspan) so hiding columns keeps the footer aligned. --}}
                                <tr class="fw-bold">
                                    <td></td>
                                    <td>{{ __('service.stats.grand_total') }}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td class="text-end" data-export="{{ round($grandMoney, 2) }}">{{ number_format($grandMoney, 2) }} ₾</td>
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
    @basset('https://cdn.datatables.net/buttons/2.3.2/js/buttons.colVis.min.js')
    <script>
    jQuery(function ($) {
        // Column visibility is saved like on the CRUD lists: per user in the DB
        // (column-visibility.update) with a localStorage copy as fallback.
        var visibility = {
            table: 'service-stats',
            saved: @json((object) $savedColumnVisibility),
            saveUrl: @json(route('column-visibility.update')),
            storageKey: 'crudColumnVisibility:' + @json(optional($columnVisibilityUser)->id ?? 'guest') + ':service-stats'
        };

        function readSavedVisibility() {
            if (Object.keys(visibility.saved).length) {
                return visibility.saved;
            }
            try {
                var local = JSON.parse(localStorage.getItem(visibility.storageKey) || '{}');
                return (local && typeof local === 'object' && !Array.isArray(local)) ? local : {};
            } catch (e) {
                return {};
            }
        }

        var $table = $('#serviceStatsTable');
        var saved = readSavedVisibility();

        var columns = $table.find('thead th').map(function () {
            var name = $(this).attr('data-column-name');
            var hideable = $(this).attr('data-can-be-visible-in-table') !== 'false';
            var visible = !(hideable && Object.prototype.hasOwnProperty.call(saved, name) && !saved[name]);
            return { visible: visible };
        }).get();

        var exportStrip = function (text) {
            return typeof text === 'string'
                ? text.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim()
                : text;
        };

        var exportOptions = {
            columns: ':visible',
            format: {
                body: function (data, row, column, node) {
                    return node && node.hasAttribute('data-export') ? node.getAttribute('data-export') : exportStrip(data);
                }
            }
        };

        var exportTitle = @json(__('service.stats.title') . ($from || $to ? ' ' . ($from ?? '…') . ' — ' . ($to ?? '…') : ''));

        var table = $table.DataTable({
            dom: 't',
            paging: false,
            searching: false,
            ordering: false,
            info: false,
            autoWidth: false,
            columns: columns,
            language: {
                emptyTable: @json(__('service.stats.empty'))
            }
        });

        new $.fn.dataTable.Buttons(table, {
            buttons: [
                {
                    extend: 'collection',
                    text: '<i class="la la-download"></i> {{ trans('backpack::crud.export.export') }}',
                    className: 'btn-sm',
                    buttons: ['copyHtml5', 'excelHtml5', 'csvHtml5', 'pdfHtml5', 'print'].map(function (type) {
                        return {
                            extend: type,
                            title: exportTitle,
                            footer: true,
                            exportOptions: exportOptions,
                            orientation: type === 'pdfHtml5' ? 'landscape' : undefined
                        };
                    })
                },
                {
                    extend: 'colvis',
                    text: '<i class="la la-eye-slash"></i> {{ trans('backpack::crud.export.column_visibility') }}',
                    className: 'btn-sm',
                    columns: function (idx, data, node) {
                        return $(node).attr('data-can-be-visible-in-table') !== 'false';
                    }
                }
            ]
        });

        table.buttons().container().appendTo('#serviceStatsButtons');
        $('#serviceStatsButtons .btn-secondary').removeClass('btn-secondary').addClass('btn-outline-secondary');

        var saveTimer = null;
        table.on('column-visibility.dt', function () {
            var map = {};
            table.columns().every(function () {
                var header = this.header();
                if (header.getAttribute('data-can-be-visible-in-table') === 'false') {
                    return;
                }
                map[header.getAttribute('data-column-name')] = !!this.visible();
            });

            visibility.saved = map;
            try {
                localStorage.setItem(visibility.storageKey, JSON.stringify(map));
            } catch (e) {}

            clearTimeout(saveTimer);
            saveTimer = setTimeout(function () {
                $.ajax({
                    url: visibility.saveUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ table: visibility.table, columns: map }),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    }
                });
            }, 400);
        });
    });
    </script>
@endpush
