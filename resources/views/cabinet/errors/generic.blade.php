@extends('cabinet.layout')

@section('title', __('cabinet.errors.'.$code))

@section('bare')
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="card card-md">
                <div class="card-body text-center">
                    <div class="text-secondary mb-2">{{ __('cabinet.name') }}</div>
                    <h2 class="h2 mb-3">{{ __('cabinet.errors.'.$code) }}</h2>
                    <a href="{{ route('cabinet.login') }}" class="btn btn-primary">{{ __('cabinet.error_home') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection
