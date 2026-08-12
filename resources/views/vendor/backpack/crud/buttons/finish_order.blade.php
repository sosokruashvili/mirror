@if ($entry->status === 'ready')
    <a href="javascript:void(0)" onclick="finishOrder(this)" bp-button="finish" data-route="{{ url($crud->route.'/'.$entry->getKey().'/finish') }}" class="btn btn-sm btn-link" data-button-type="finish">
        <i class="la la-check-circle"></i> <span>{{ __('order.buttons.finish') }}</span>
    </a>
@endif

{{-- Button Javascript --}}
@push('after_scripts') @if (request()->ajax()) @endpush @endif
@bassetBlock('backpack/crud/buttons/finish-order-button-'.app()->getLocale().'.js')
<script>
    if (typeof finishOrder != 'function') {
        $("[data-button-type=finish]").unbind('click');

        function finishOrder(button) {
            var route = $(button).attr('data-route');

            swal({
                title: @json(__('order.finish_dialog.title')),
                text: @json(__('order.finish_dialog.text')),
                icon: "info",
                buttons: {
                    cancel: {
                        text: @json(trans('backpack::crud.cancel')),
                        value: null,
                        visible: true,
                        className: "bg-secondary",
                        closeModal: true,
                    },
                    confirm: {
                        text: @json(__('order.buttons.finish')),
                        value: true,
                        visible: true,
                        className: "bg-success",
                    },
                },
            }).then((value) => {
                if (value) {
                    $.ajax({
                        url: route,
                        type: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
                        },
                        success: function(result) {
                            if (result.success) {
                                new Noty({
                                    type: "success",
                                    text: @json('<strong>'.e(__('order.finish_dialog.success_title')).'</strong><br>'.e(__('order.finish_dialog.success_text')))
                                }).show();

                                if (typeof crud != 'undefined' && typeof crud.table != 'undefined') {
                                    crud.table.draw(false);
                                } else {
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 1000);
                                }
                            } else {
                                swal({
                                    title: @json(trans('backpack::base.error')),
                                    text: result.message || @json(__('order.finish_dialog.error')),
                                    icon: "error",
                                    timer: 4000,
                                    buttons: false,
                                });
                            }
                        },
                        error: function(result) {
                            var message = @json(__('order.finish_dialog.error'));
                            if (result.responseJSON && result.responseJSON.message) {
                                message = result.responseJSON.message;
                            }

                            swal({
                                title: @json(trans('backpack::base.error')),
                                text: message,
                                icon: "error",
                                timer: 4000,
                                buttons: false,
                            });
                        }
                    });
                }
            });
        }
    }
</script>
@endBassetBlock
@if (!request()->ajax()) @endpush @endif
