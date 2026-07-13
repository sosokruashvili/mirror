@extends(backpack_view('blank'))

@php
    $breadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        'Global Settings' => false,
    ];
@endphp

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h1 class="text-capitalize mb-0">Global Settings</h1>
        <p class="ms-2 ml-2 mb-0">Application-wide parameters.</p>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('settings.update') }}">
            {!! csrf_field() !!}
            @method('PUT')

            @foreach ($settingGroups as $group => $settings)
                <div class="card">
                    <div class="card-header">
                        <div class="card-title mb-0">{{ $group ?: 'General' }}</div>
                    </div>
                    <div class="card-body">
                        @foreach ($settings as $setting)
                            @php
                                $fieldName = 'settings[' . $setting->key . ']';
                                $current = old('settings.' . $setting->key, $setting->value);
                            @endphp
                            <div class="mb-3">
                                <label class="form-label" for="setting-{{ $setting->key }}">{{ $setting->label }}</label>

                                @if ($setting->type === 'boolean')
                                    <div class="form-check">
                                        <input type="hidden" name="{{ $fieldName }}" value="0">
                                        <input class="form-check-input" type="checkbox"
                                               id="setting-{{ $setting->key }}"
                                               name="{{ $fieldName }}" value="1"
                                               @checked((bool) $current)>
                                    </div>
                                @elseif ($setting->type === 'integer')
                                    <div class="input-group">
                                        <input class="form-control @error('settings.' . $setting->key) is-invalid @enderror"
                                               type="number" step="1" min="0"
                                               id="setting-{{ $setting->key }}"
                                               name="{{ $fieldName }}"
                                               value="{{ $current }}">
                                        @if ($setting->key === 'cutting_size')
                                            <span class="input-group-text">mm</span>
                                        @endif
                                    </div>
                                @elseif ($setting->type === 'float')
                                    <input class="form-control @error('settings.' . $setting->key) is-invalid @enderror"
                                           type="number" step="any"
                                           id="setting-{{ $setting->key }}"
                                           name="{{ $fieldName }}"
                                           value="{{ $current }}">
                                @else
                                    <input class="form-control @error('settings.' . $setting->key) is-invalid @enderror"
                                           type="text"
                                           id="setting-{{ $setting->key }}"
                                           name="{{ $fieldName }}"
                                           value="{{ $current }}">
                                @endif

                                @if ($setting->description)
                                    <small class="form-text text-muted">{{ $setting->description }}</small>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($settingGroups->isEmpty())
                <div class="alert alert-info">No settings defined yet.</div>
            @endif

            <div class="mb-4">
                <button type="submit" class="btn btn-primary">
                    <i class="la la-save"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
