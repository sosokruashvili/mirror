@extends('cabinet.layout')

@section('title', __('cabinet.order').' #'.$order->id)

@section('content')
    <div class="page-header d-print-none">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle">
                    <a href="{{ route('cabinet.orders.index') }}">{{ __('cabinet.back_orders') }}</a>
                </div>
                <h2 class="page-title d-flex align-items-center gap-2">
                    {{ __('cabinet.order') }} #{{ $order->id }}
                    @include('cabinet.partials.status', ['order' => $order])
                </h2>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">{{ __('cabinet.created') }}</div>
                    <div class="h3 mb-0">{{ $order->created_at?->timezone(config('app.timezone'))->format('d.m.Y') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">{{ __('cabinet.due') }}</div>
                    <div>@include('cabinet.partials.due', ['order' => $order])</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">{{ __('cabinet.total') }}</div>
                    <div class="h3 mb-0">{{ \App\Support\Cabinet\Cabinet::money($total) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">{{ __('cabinet.paid_amount') }}</div>
                    <div class="h3 mb-0">{{ \App\Support\Cabinet\Cabinet::money($paid) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">{{ __('cabinet.remaining') }}</div>
                    <div class="h3 mb-0">{{ \App\Support\Cabinet\Cabinet::money(max(0, $total - $paid)) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($order->products->isNotEmpty())
        <p class="text-secondary">{{ $order->products->pluck('title')->filter()->implode(', ') }}</p>
    @endif

    @if ($order->tracksStagesOnOrder())
        {{-- Service orders have no pieces: list their services with the stage
             the order itself has reached (order_stage). Orders handed out
             before service orders had stages have no stage rows. --}}
        @php
            $stage = $order->currentStageName();
            $stageLabel = $stage
                ? piece_stage_ge($stage)
                : ($order->status === 'finished' ? piece_stage_ge(\App\Services\OrderPieceStatusSync::FINISHED_STAGE) : __('cabinet.stage_waiting'));
        @endphp
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ __('cabinet.services') }}</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>{{ __('cabinet.service') }}</th>
                            <th>{{ __('cabinet.quantity') }}</th>
                            <th>{{ __('cabinet.stage') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($order->services->filter(fn ($service) => $service->pivot->piece_id === null) as $service)
                            <tr>
                                <td class="fw-medium">{{ $service->title }}</td>
                                <td>{{ $service->pivot->quantity ?: '—' }}</td>
                                <td>{{ $stageLabel }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-secondary">—</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ __('cabinet.pieces') }}</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>{{ __('cabinet.piece_size') }}</th>
                        <th>{{ __('cabinet.quantity') }}</th>
                        <th>{{ __('cabinet.product') }}</th>
                        <th>{{ __('cabinet.stage') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($order->pieces as $piece)
                        @php
                            $stage = $piece->currentStageName();
                            $stageLabel = $stage ? piece_stage_ge($stage) : __('cabinet.stage_waiting');
                        @endphp
                        <tr>
                            <td class="fw-medium">{{ \App\Support\Cabinet\Cabinet::measure($piece->width) }} × {{ \App\Support\Cabinet\Cabinet::measure($piece->height) }} {{ __('cabinet.mm') }}</td>
                            <td>{{ (int) $piece->quantity }}</td>
                            <td>{{ $piece->product?->title ?: '—' }}</td>
                            <td>
                                {{ $stageLabel }}
                                @if ((int) $piece->broken > 0)
                                    <span class="badge bg-danger-lt ms-1">{{ __('cabinet.damaged') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-secondary">—</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ __('cabinet.nav_payments') }}</h3>
        </div>
        @include('cabinet.partials.payment-table', ['payments' => $order->payments])
    </div>
@endsection
