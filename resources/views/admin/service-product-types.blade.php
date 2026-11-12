@extends(backpack_view('blank'))

{{--
    Services × product types matrix (ServiceProductTypesController). A tick means the
    order form offers that service on orders of that product type; a row with no ticks
    is offered on every type. Grey numbers are past order lines, as a hint only.
--}}

@php
    $breadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        __('menu.services') => backpack_url('service'),
        __('service.matrix.title') => false,
    ];
@endphp

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h1 class="text-capitalize mb-0">{{ __('service.matrix.title') }}</h1>
        <p class="ms-2 ml-2 mb-0">{{ __('service.matrix.subtitle') }}</p>
    </section>
@endsection

@section('content')
<style>
    #spt-table thead th { position: sticky; top: 0; z-index: 2; background: var(--tblr-bg-surface, #fff); }
    #spt-table td.spt-cell, #spt-table th.spt-col { text-align: center; width: 9rem; }
    #spt-table td.spt-cell label { display: flex; align-items: center; justify-content: center; gap: .4rem; margin: 0; cursor: pointer; min-height: 1.75rem; }
    #spt-table td.spt-cell input { width: 1.1rem; height: 1.1rem; cursor: pointer; }
    #spt-table .spt-usage { font-size: .75rem; color: var(--tblr-secondary, #6c757d); min-width: 2.2rem; text-align: left; font-variant-numeric: tabular-nums; }
    #spt-table tr.spt-stage th { background: var(--tblr-bg-surface-secondary, #f6f8fb); font-weight: 600; }
    #spt-table .spt-col-toggle { font-weight: normal; font-size: .75rem; display: flex; align-items: center; justify-content: center; gap: .3rem; margin-top: .25rem; cursor: pointer; }
    #spt-table .spt-unit { color: var(--tblr-secondary, #6c757d); font-size: .8rem; }
</style>

<form method="POST" action="{{ route('service-product-types.update') }}" id="spt-form">
    @csrf
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <input type="search" id="spt-search" class="form-control form-control-sm" style="max-width: 18rem"
                   placeholder="{{ __('service.matrix.search') }}" autocomplete="off">
            <small class="text-muted me-auto">
                {{ __('service.matrix.empty_row_note') }} {{ __('service.matrix.usage_hint') }}
            </small>
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="la la-save"></i> {{ __('service.matrix.save') }}
            </button>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 75vh">
                <table id="spt-table" class="table table-hover table-vcenter table-sm mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('service.matrix.service') }}</th>
                            @foreach ($types as $type => $label)
                                <th class="spt-col">
                                    {{ $label }}
                                    <label class="spt-col-toggle">
                                        <input type="checkbox" data-col-toggle="{{ $type }}"> {{ __('service.matrix.whole_column') }}
                                    </label>
                                </th>
                            @endforeach
                            <th style="width: 9rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $stageTitle => $services)
                            <tr class="spt-stage" data-stage-row>
                                <th colspan="{{ count($types) + 2 }}">{{ $stageTitle }}</th>
                            </tr>
                            @foreach ($services as $service)
                                @php $ticked = $service->productTypes(); @endphp
                                <tr data-service-row data-search="{{ mb_strtolower($service->title) }}">
                                    <td>
                                        {{ $service->title }}
                                        @if ($service->unit)<span class="spt-unit">· {{ $service->unit }}</span>@endif
                                        <span class="text-muted small">#{{ $service->id }}</span>
                                    </td>
                                    @foreach ($types as $type => $label)
                                        @php $used = $usage[$service->id][$type] ?? 0; @endphp
                                        <td class="spt-cell">
                                            <label title="{{ $label }}">
                                                <input type="checkbox" name="types[{{ $service->id }}][]" value="{{ $type }}"
                                                       data-type="{{ $type }}" @checked(in_array($type, $ticked, true))>
                                                <span class="spt-usage">{{ $used ?: '' }}</span>
                                            </label>
                                        </td>
                                    @endforeach
                                    <td>
                                        <span class="badge bg-secondary-lt" data-everywhere @if ($ticked !== []) hidden @endif>
                                            {{ __('service.matrix.shown_everywhere') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">
                <i class="la la-save"></i> {{ __('service.matrix.save') }}
            </button>
        </div>
    </div>
</form>
@endsection

@push('after_scripts')
<script>
(function () {
    var form = document.getElementById('spt-form');
    var table = document.getElementById('spt-table');
    var dirty = false;

    function visibleRows() {
        return Array.prototype.filter.call(table.querySelectorAll('tr[data-service-row]'), function (tr) {
            return !tr.hidden;
        });
    }

    function refreshRow(tr) {
        var any = tr.querySelector('input[data-type]:checked') !== null;
        tr.querySelector('[data-everywhere]').hidden = any;
    }

    // A column's toggle is ticked when every visible service in it is ticked.
    function refreshColumnToggles() {
        table.querySelectorAll('input[data-col-toggle]').forEach(function (toggle) {
            var type = toggle.getAttribute('data-col-toggle');
            var boxes = visibleRows().map(function (tr) { return tr.querySelector('input[data-type="' + type + '"]'); });
            var on = boxes.filter(function (b) { return b.checked; }).length;
            toggle.checked = boxes.length > 0 && on === boxes.length;
            toggle.indeterminate = on > 0 && on < boxes.length;
        });
    }

    table.addEventListener('change', function (e) {
        var t = e.target;
        if (t.matches('input[data-col-toggle]')) {
            var type = t.getAttribute('data-col-toggle');
            visibleRows().forEach(function (tr) {
                tr.querySelector('input[data-type="' + type + '"]').checked = t.checked;
                refreshRow(tr);
            });
        } else if (t.matches('input[data-type]')) {
            refreshRow(t.closest('tr'));
        } else {
            return;
        }
        dirty = true;
        refreshColumnToggles();
    });

    // Search narrows the rows (and what a column toggle affects); stage headers with
    // no matching service are hidden too.
    document.getElementById('spt-search').addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        var stageRow = null, stageHasMatch = false;
        Array.prototype.forEach.call(table.tBodies[0].rows, function (tr) {
            if (tr.hasAttribute('data-stage-row')) {
                if (stageRow) { stageRow.hidden = !stageHasMatch; }
                stageRow = tr; stageHasMatch = false;
                return;
            }
            tr.hidden = q !== '' && tr.getAttribute('data-search').indexOf(q) === -1;
            if (!tr.hidden) { stageHasMatch = true; }
        });
        if (stageRow) { stageRow.hidden = !stageHasMatch; }
        refreshColumnToggles();
    });

    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    refreshColumnToggles();
})();
</script>
@endpush
