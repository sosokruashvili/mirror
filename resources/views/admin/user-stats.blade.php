@extends(backpack_view('blank'))

@php
    $breadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        'User Stats' => false,
    ];
@endphp

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h1 class="text-capitalize mb-0">User Stats</h1>
        <p class="ms-2 ml-2 mb-0">Widgets about user activity and performance.</p>
    </section>
@endsection

@section('content')
    <div class="row">
        @include('vendor.backpack.ui.widgets.top_users_chart')
    </div>
@endsection
