@if ($crud->hasAccess('update') && filled($entry->phone_number))
    <a href="javascript:void(0)"
       onclick="allowCabinet(this)"
       bp-button="allowCabinet"
       data-route="{{ route('client.allowCabinet', $entry->getKey()) }}"
       data-confirm="{{ __('client.cabinet.allow_confirm') }}"
       class="btn btn-sm btn-link"
       data-button-type="allow-cabinet">
        <i class="la la-unlock"></i> <span>{{ __('client.cabinet.allow') }}</span>
    </a>
@endif

@push('after_scripts') @if (request()->ajax()) @endpush @endif
@bassetBlock('backpack/crud/buttons/allow-cabinet-'.app()->getLocale().'.js')
<script>
    if (typeof allowCabinet != 'function') {
        function allowCabinet(button) {
            var route = $(button).attr('data-route');
            var text = $(button).attr('data-confirm');

            swal({
                title: @json(__('client.cabinet.allow')),
                text: text,
                icon: "warning",
                buttons: {
                    cancel: {
                        text: @json(trans('backpack::crud.cancel')),
                        value: null,
                        visible: true,
                        className: "bg-secondary",
                        closeModal: true,
                    },
                    confirm: {
                        text: @json(__('client.cabinet.allow_confirm_button')),
                        value: true,
                        visible: true,
                        className: "bg-success",
                    },
                },
            }).then((value) => {
                if (!value) {
                    return;
                }

                $(button).addClass('disabled').css('pointer-events', 'none');

                $.ajax({
                    url: route,
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
                    },
                    success: function (result) {
                        $(button).removeClass('disabled').css('pointer-events', '');

                        if (result.success) {
                            new Noty({
                                type: "success",
                                text: result.message || @json(__('client.cabinet.sms_queued'))
                            }).show();
                            return;
                        }

                        swal({
                            title: @json(trans('backpack::base.error')),
                            text: result.message || @json(__('client.cabinet.sms_failed')),
                            icon: "error",
                            timer: 4000,
                            buttons: false,
                        });
                    },
                    error: function (xhr) {
                        $(button).removeClass('disabled').css('pointer-events', '');

                        var message = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : @json(__('client.cabinet.sms_failed'));

                        swal({
                            title: @json(trans('backpack::base.error')),
                            text: message,
                            icon: "error",
                            timer: 5000,
                            buttons: false,
                        });
                    }
                });
            });
        }
    }
</script>
@endBassetBlock
@if (!request()->ajax()) @endpush @endif
