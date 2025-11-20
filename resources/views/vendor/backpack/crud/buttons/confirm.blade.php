@if ($entry->status === 'draft')
    <a href="javascript:void(0)" onclick="confirmOrder(this)" bp-button="confirm" data-route="{{ url($crud->route.'/'.$entry->getKey().'/confirm') }}" class="btn btn-sm btn-success" data-button-type="confirm">
        <i class="la la-check"></i> <span>Confirm</span>
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
                title: "Confirm Order?",
                text: "Are you sure you want to confirm this order? The status will be changed to 'new'.",
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
                        text: "Confirm",
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
                                    text: "<strong>Order Confirmed</strong><br>The order status has been changed to 'new'."
                                }).show();

                                // Reload the page to reflect the change
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1000);
                            } else {
                                swal({
                                    title: "Error",
                                    text: result.message || "An error occurred while confirming the order.",
                                    icon: "error",
                                    timer: 4000,
                                    buttons: false,
                                });
                            }
                        },
                        error: function(result) {
                            swal({
                                title: "Error",
                                text: "An error occurred while confirming the order.",
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

