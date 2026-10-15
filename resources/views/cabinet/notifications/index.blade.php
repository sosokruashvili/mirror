@extends('cabinet.layout')

@section('title', __('cabinet.nav_notifications'))

@section('content')
    <div class="page-header d-print-none">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('cabinet.nav_notifications') }}</h2>
            </div>
        </div>
    </div>
    <div class="card">
        @include('cabinet.partials.notification-list', ['notifications' => $notifications])
        {{ $notifications->links('cabinet.partials.pager') }}
    </div>
@endsection
