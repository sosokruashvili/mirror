@extends(backpack_view('blank'))

@php
    $breadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        'Services' => backpack_url('service'),
        'Service Stats' => false,
    ];

    // Trim trailing zeros so 108.00 shows as "108" but 6.45 stays "6.45".
    $fmtQty = function ($n) {
        $s = number_format((float) $n, 2, '.', ',');
        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    };
@endphp

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h1 class="text-capitalize mb-0">Service Stats</h1>
        <p class="ms-2 ml-2 mb-0">დასრულებული სამუშაოს რაოდენობა და ჯამური თანხა თითო სერვისზე.</p>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-print-none">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-auto">
                            <label class="form-label mb-1" for="from">დან (დასრულების თარიღი)</label>
                            <input type="date" id="from" name="from" value="{{ $from }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <label class="form-label mb-1" for="to">მდე</label>
                            <input type="date" id="to" name="to" value="{{ $to }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="la la-filter"></i> გაფილტვრა
                            </button>
                            @if($from || $to)
                                <a href="{{ backpack_url('service-stats') }}" class="btn btn-sm btn-link">გასუფთავება</a>
                            @endif
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-vcenter mb-0">
                            <thead>
                                <tr>
                                    <th class="text-muted" style="width:1%">#</th>
                                    <th>სერვისის დასახელება</th>
                                    <th>ეტაპი</th>
                                    <th>საზომი ერთეული</th>
                                    <th class="text-end">რა რაოდენობა გაკეთდა</th>
                                    <th class="text-end">რამდენი ლარი გაიცემა</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $i => $row)
                                    <tr @class(['text-muted' => ! $row['active']])>
                                        <td class="text-muted">{{ $i + 1 }}</td>
                                        <td>{{ $row['title'] }}</td>
                                        <td><span class="text-muted small">{{ $row['stage'] }}</span></td>
                                        <td>{{ $row['unit'] }}</td>
                                        <td class="text-end">{{ $fmtQty($row['quantity']) }}</td>
                                        <td class="text-end">{{ number_format($row['money'], 2) }} ₾</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">მონაცემები ვერ მოიძებნა.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td colspan="5" class="text-end">ჯამური თანხა</td>
                                    <td class="text-end">{{ number_format($grandMoney, 2) }} ₾</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
