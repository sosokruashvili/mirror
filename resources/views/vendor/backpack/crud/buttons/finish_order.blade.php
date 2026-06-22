@if ($entry->status === 'ready')
    <a href="javascript:void(0)" onclick="finishOrder(this)" bp-button="finish" data-route="{{ url($crud->route.'/'.$entry->getKey().'/finish') }}" class="btn btn-sm btn-link" data-button-type="finish">
        <i class="la la-check-circle"></i> <span>Finish</span>
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
                title: "Finish Order?",
                text: "Are you sure you want to finish this order? The order and all its pieces will be marked as finished.",
                icon: "info",
                buttons: {
                    cancel: {
                        text: "Cancel",
                        value: null,
                        visible: true,
                        className: "bg-secondary",
                        closeModal: true,
                    },
                    confirm: {
                        text: "Finish",
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
                                    text: "<strong>Order Finished</strong><br>The order and all pieces have been marked as finished."
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
                                    title: "Error",
                                    text: result.message || "An error occurred while finishing the order.",
                                    icon: "error",
                                    timer: 4000,
                                    buttons: false,
                                });
                            }
                        },
                        error: function(result) {
                            var message = "An error occurred while finishing the order.";
                            if (result.responseJSON && result.responseJSON.message) {
                                message = result.responseJSON.message;
                            }

                            swal({
                                title: "Error",
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
