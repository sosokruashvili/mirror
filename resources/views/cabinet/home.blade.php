@extends('cabinet.layout')

@section('title', __('cabinet.nav_home'))

@section('content')
    @php
        $balance = (float) $components['balance'];
        $balanceClass = $balance < -0.009 ? 'text-danger' : ($balance > 0.009 ? 'text-success' : '');
        $balanceLabel = match (true) {
            $balance < -0.009 => __('cabinet.balance_debt'),
            $balance > 0.009 => __('cabinet.balance_credit'),
            default => __('cabinet.balance_settled'),
        };
    @endphp

    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle">{{ __('cabinet.hello') }}</div>
                <h2 class="page-title">{{ $client->name }}</h2>
            </div>
        </div>
    </div>

    @if ($cabinetNotices->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ __('cabinet.notifications') }}</h3>
                <div class="card-actions">
                    <a href="{{ route('cabinet.notifications.index') }}" class="btn btn-link">{{ __('cabinet.all_notifications') }}</a>
                </div>
            </div>
            @include('cabinet.partials.notification-list', ['notifications' => $cabinetNotices])
        </div>
    @endif

    <div class="row row-deck row-cards mb-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">{{ __('cabinet.balance') }}</div>
                    <div class="h1 mb-1 {{ $balanceClass }}">{{ \App\Support\Cabinet\Cabinet::money($balance) }}</div>
                    <div class="text-secondary">{{ $balanceLabel }}</div>
                    <div class="datagrid mt-3">
                        <div class="datagrid-item">
                            <div class="datagrid-title">{{ __('cabinet.opening') }}</div>
                            <div class="datagrid-content">{{ \App\Support\Cabinet\Cabinet::money($components['starting_balance']) }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">{{ __('cabinet.orders_total') }}</div>
                            <div class="datagrid-content">{{ \App\Support\Cabinet\Cabinet::money($components['orders_total']) }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">{{ __('cabinet.payments_total') }}</div>
                            <div class="datagrid-content">{{ \App\Support\Cabinet\Cabinet::money($components['payments_total']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">{{ __('cabinet.open_orders') }}</div>
                    <div class="h1 mb-0">{{ $openCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('cabinet.profile') }}</h3>
                </div>
                <div class="card-body">
                    <div class="datagrid">
                        <div class="datagrid-item">
                            <div class="datagrid-title">{{ __('cabinet.phone') }}</div>
                            <div class="datagrid-content">{{ $client->phone_number }}</div>
                        </div>
                        @if ($client->email)
                            <div class="datagrid-item">
                                <div class="datagrid-title">{{ __('cabinet.email') }}</div>
                                <div class="datagrid-content">{{ $client->email }}</div>
                            </div>
                        @endif
                        <div class="datagrid-item">
                            <div class="datagrid-title">{{ $client->client_type ? __('cabinet.id_legal') : __('cabinet.id_personal') }}</div>
                            <div class="datagrid-content">{{ $client->client_type ? ($client->legal_id ?: '—') : ($client->personal_id ?: '—') }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">{{ __('cabinet.address') }}</div>
                            <div class="datagrid-content">{!! nl2br(e($client->address)) !!}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ __('cabinet.recent_orders') }}</h3>
            <div class="card-actions">
                <a href="{{ route('cabinet.orders.index') }}" class="btn btn-link">{{ __('cabinet.all_orders') }}</a>
            </div>
        </div>
        @include('cabinet.partials.order-table', ['orders' => $recentOrders])
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ __('cabinet.recent_payments') }}</h3>
            <div class="card-actions">
                <a href="{{ route('cabinet.payments.index') }}" class="btn btn-link">{{ __('cabinet.all_payments') }}</a>
            </div>
        </div>
        @include('cabinet.partials.payment-table', ['payments' => $recentPayments])
    </div>
@endsection
