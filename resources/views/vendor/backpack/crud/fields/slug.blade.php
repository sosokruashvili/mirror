{{-- slug input with generate-from-title button --}}
@php
    $sourceField = $field['source'] ?? 'title';
    $generateUrl = $field['generate_url'] ?? url(config('backpack.base.route_prefix') . '/service/generate-slug');
    $field['wrapper']['data-init-function'] = $field['wrapper']['data-init-function'] ?? 'bpFieldInitSlug';
    $field['wrapper']['data-source'] = $sourceField;
    $field['wrapper']['data-generate-url'] = $generateUrl;
@endphp

@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    @include('crud::fields.inc.translatable_icon')

    <div class="input-group">
        <input
            type="text"
            name="{{ $field['name'] }}"
            value="{{ old_empty_or_null($field['name'], '') ??  $field['value'] ?? $field['default'] ?? '' }}"
            @include('crud::fields.inc.attributes')
        >
        <button
            type="button"
            class="btn btn-outline-secondary bp-generate-slug"
            title="Generate slug from title"
        >
            Generate
        </button>
    </div>

    {{-- HINT --}}
    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')

{{-- FIELD JS - will be loaded in the after_scripts section --}}
@push('crud_fields_scripts')
    @bassetBlock('backpack/crud/fields/slug-field.js')
    <script>
        function bpFieldInitSlug(element) {
            const btn = element.find('.bp-generate-slug');
            const slugInput = element.find('input[type="text"]').first();
            const sourceName = element.attr('data-source') || 'title';
            const url = element.attr('data-generate-url');

            btn.off('click.bpSlug').on('click.bpSlug', function () {
                const sourceInput = $('[name="' + sourceName + '"]').first();
                const title = (sourceInput.val() || '').trim();

                if (!title) {
                    new Noty({
                        type: 'warning',
                        text: 'Please enter a title first.',
                    }).show();
                    sourceInput.trigger('focus');
                    return;
                }

                btn.prop('disabled', true);

                $.ajax({
                    url: url,
                    method: 'GET',
                    data: { title: title },
                    dataType: 'json',
                }).done(function (data) {
                    if (data.slug !== undefined) {
                        slugInput.val(data.slug).trigger('input').trigger('change');
                    }
                }).fail(function () {
                    new Noty({
                        type: 'error',
                        text: 'Could not generate slug.',
                    }).show();
                }).always(function () {
                    btn.prop('disabled', false);
                });
            });
        }
    </script>
    @endBassetBlock
@endpush
