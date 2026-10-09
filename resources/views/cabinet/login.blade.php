@extends('cabinet.layout')

@section('title', __('cabinet.login_title'))

@section('bare')
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="text-center mb-4">
                <a href="{{ route('cabinet.login') }}" class="navbar-brand navbar-brand-autodark text-decoration-none">
                    <span class="h2 mb-0">{!! config('backpack.ui.project_logo') !!}</span>
                </a>
                <div class="text-secondary mt-1">{{ __('cabinet.cabinet') }}</div>
            </div>
            <div class="card card-md">
                <div class="card-body">
                    <h2 class="h2 text-center mb-4">{{ __('cabinet.login_title') }}</h2>
                    <p class="text-secondary text-center">{{ __('cabinet.login_lead') }}</p>

                    @if (session('status'))
                        <div class="alert alert-info" role="status">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                    @endif

                    <form method="post" action="{{ route('cabinet.login') }}" autocomplete="off" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="phone">{{ __('cabinet.phone') }}</label>
                            <input id="phone" type="tel" name="phone" inputmode="tel" autocomplete="username" required maxlength="32" value="{{ old('phone') }}" placeholder="{{ __('cabinet.phone_placeholder') }}" class="form-control {{ $errors->has('phone') || $errors->has('login') ? 'is-invalid' : '' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">{{ __('cabinet.password') }}</label>
                            <input id="password" type="password" name="password" autocomplete="current-password" required maxlength="255" class="form-control {{ $errors->has('password') || $errors->has('login') ? 'is-invalid' : '' }}">
                        </div>
                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100">{{ __('cabinet.sign_in') }}</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center mt-3">
                @include('cabinet.partials.locale')
            </div>
        </div>
    </div>
@endsection
