@if ($entry->status === 'draft')
    <a href="javascript:void(0)" onclick="confirmOrder(this)" bp-button="confirm" data-route="{{ url($crud->route.'/'.$entry->getKey().'/confirm') }}" class="btn btn-sm btn-success" data-button-type="confirm">
        <i class="la la-check"></i> <span>{{ __('order.buttons.confirm') }}</span>
    </a>
@endif

{{-- Button Javascript --}}
@push('after_scripts') @if (request()->ajax()) @endpush @endif
@bassetBlock('backpack/crud/buttons/confirm-button-'.app()->getLocale().'.js')
<script>
    if (typeof confirmOrder != 'function') {
        $("[data-button-type=confirm]").unbind('click');

        function confirmOrder(button) {
            var route = $(button).attr('data-route');

            swal({
                title: @json(__('order.confirm_dialog.title')),
                text: @json(__('order.confirm_dialog.text')),
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
                        text: @json(__('order.buttons.confirm')),
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
                                // Show success notification
                                new Noty({
                                    type: "success",
                                    text: @json('<strong>'.e(__('order.confirm_dialog.success_title')).'</strong><br>'.e(__('order.confirm_dialog.success_text')))
                                }).show();

                                // Reload the page to reflect the change
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1000);
                            } else {
                                swal({
                                    title: @json(trans('backpack::base.error')),
                                    text: result.message || @json(__('order.confirm_dialog.error')),
                                    icon: "error",
                                    timer: 4000,
                                    buttons: false,
                                });
                            }
                        },
                        error: function(result) {
                            swal({
                                title: @json(trans('backpack::base.error')),
                                text: @json(__('order.confirm_dialog.error')),
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

