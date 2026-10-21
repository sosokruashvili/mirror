{{-- textarea with a live SMS counter: characters and SMS billed (assets/js/sms-counter.js) --}}
@php
    $smsCounter = [
        'samples' => $field['samples'] ?? [],
        'approx' => $field['approx'] ?? [],
        'labels' => __('sms_template.counter'),
    ];
@endphp
@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    @include('crud::fields.inc.translatable_icon')
    <textarea
    	name="{{ $field['name'] }}"
        data-sms-counter="{{ json_encode($smsCounter, JSON_UNESCAPED_UNICODE) }}"
        @include('crud::fields.inc.attributes')

    	>{{ old_empty_or_null($field['name'], '') ??  $field['value'] ?? $field['default'] ?? '' }}</textarea>

    <div class="small mt-1" data-sms-counter-output></div>

    {{-- HINT --}}
    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')

@once
    @push('crud_fields_scripts')
        <script src="{{ asset('assets/js/sms-counter.js') }}?v={{ filemtime(public_path('assets/js/sms-counter.js')) }}"></script>
    @endpush
@endonce
