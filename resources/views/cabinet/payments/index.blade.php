@extends('cabinet.layout')

@section('title', __('cabinet.nav_payments'))

@section('content')
    <div class="page-header d-print-none">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('cabinet.nav_payments') }}</h2>
            </div>
        </div>
    </div>
    <div class="card">
        @include('cabinet.partials.payment-table', ['payments' => $payments])
        {{ $payments->links('cabinet.partials.pager') }}
    </div>
@endsection
