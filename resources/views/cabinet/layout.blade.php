@php
    $cabinetSignedIn = request()->hasSession() && auth('cabinet')->check();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    @if (request()->hasSession())
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @endif
    <title>@yield('title', __('cabinet.cabinet')) — {{ __('cabinet.name') }}</title>
    @include('cabinet.partials.styles')
</head>
<body>
@hasSection('bare')
    @yield('bare')
@else
    <div class="page">
        <header class="navbar navbar-expand-md d-print-none">
            <div class="container-xl flex-nowrap">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#cabinet-menu" aria-controls="cabinet-menu" aria-expanded="false" aria-label="{{ __('cabinet.cabinet') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h1 class="navbar-brand navbar-brand-autodark pe-md-3 mb-0 text-truncate">
                    <a href="{{ $cabinetSignedIn ? route('cabinet.home') : route('cabinet.login') }}" class="text-decoration-none">
                        {!! config('backpack.ui.project_logo') !!}
                    </a>
                </h1>
                <div class="navbar-nav flex-row order-md-last align-items-center ms-auto flex-nowrap">
                    @include('cabinet.partials.locale')
                    @if ($cabinetSignedIn)
                        <form method="post" action="{{ route('cabinet.logout') }}" class="ms-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('cabinet.sign_out') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </header>
        @if ($cabinetSignedIn)
            <header class="navbar-expand-md">
                <div class="collapse navbar-collapse" id="cabinet-menu">
                    <div class="navbar">
                        <div class="container-xl">
                            <ul class="navbar-nav">
                                <li class="nav-item {{ request()->routeIs('cabinet.home') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('cabinet.home') }}">
                                        <span class="nav-link-title">{{ __('cabinet.nav_home') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('cabinet.notifications.*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('cabinet.notifications.index') }}">
                                        <span class="nav-link-title">{{ __('cabinet.nav_notifications') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('cabinet.orders.*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('cabinet.orders.index') }}">
                                        <span class="nav-link-title">{{ __('cabinet.nav_orders') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('cabinet.payments.index') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('cabinet.payments.index') }}">
                                        <span class="nav-link-title">{{ __('cabinet.nav_payments') }}</span>
                                    </a>
                                </li>
                            </ul>
                            <div class="ms-md-auto text-secondary d-none d-md-block">
                                {{ isset($cabinetClient) ? $cabinetClient->name : '' }}
                            </div>
                        </div>
                    </div>
                </div>
            </header>
        @endif
        <div class="page-wrapper">
            <div class="page-body">
                <div class="container-xl">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
@endif
<script src="{{ \App\Support\Cabinet\Cabinet::assetUrl('https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta19/dist/js/tabler.min.js') }}"></script>
</body>
</html>
