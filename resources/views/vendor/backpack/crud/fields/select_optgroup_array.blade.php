{{-- select_optgroup_array: options as [groupLabel => [id => label]]; empty label = ungrouped --}}
@php
    $field['allows_null'] = $field['allows_null'] ?? $crud->model::isColumnNullable($field['name']);
    $field['value'] = old_empty_or_null($field['name'], '') ?? $field['value'] ?? $field['default'] ?? '';
@endphp
@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    @include('crud::fields.inc.translatable_icon')
    <select
        name="{{ $field['name'] }}"
        @include('crud::fields.inc.attributes', ['default_class' => 'form-control form-select'])
        >

        @if ($field['allows_null'])
            <option value="">-</option>
        @endif

        @foreach (($field['options'] ?? []) as $groupLabel => $items)
            @if ($groupLabel === '' || $groupLabel === null)
                @foreach ($items as $key => $value)
                    <option value="{{ $key }}" @selected((string) $field['value'] === (string) $key)>{{ $value }}</option>
                @endforeach
            @else
                <optgroup label="{{ $groupLabel }}">
                    @foreach ($items as $key => $value)
                        <option value="{{ $key }}" @selected((string) $field['value'] === (string) $key)>{{ $value }}</option>
                    @endforeach
                </optgroup>
            @endif
        @endforeach
    </select>

    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')
