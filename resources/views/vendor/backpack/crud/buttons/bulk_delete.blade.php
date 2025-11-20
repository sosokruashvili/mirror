@if ($crud->hasAccess('delete') && $crud->getOperationSetting('bulkActions'))
    <a href="javascript:void(0)" onclick="bulkDeleteEntries(this)" class="btn btn-sm btn-danger bulk-button disabled">
        <i class="la la-trash"></i> <span>Delete Selected</span>
    </a>
@endif

{{-- Button Javascript --}}
@push('after_scripts')
@bassetBlock('backpack/crud/buttons/bulk-delete-button.js')
<script>
    if (typeof bulkDeleteEntries != 'function') {
        function bulkDeleteEntries(button) {
            if (typeof crud.checkedItems === 'undefined' || crud.checkedItems.length == 0) {
                new Noty({
                    type: "warning",
                    text: "<strong>{{ trans('backpack::base.warning') ?? 'Warning' }}</strong><br>{{ trans('backpack::crud.no_entries_selected') ?? 'Please select one or more items to delete.' }}"
                }).show();
                return;
            }

            var message = "{{ trans('backpack::crud.delete_confirm') ?? 'Are you sure you want to delete these entries?' }}";
            if (crud.checkedItems.length > 1) {
                message = "{{ trans('backpack::crud.delete_confirm_bulk') ?? 'Are you sure you want to delete these :number entries?' }}".replace(':number', crud.checkedItems.length);
            }

            // show confirm message
            swal({
                title: "{{ trans('backpack::base.warning') ?? 'Warning' }}",
                text: message,
                icon: "warning",
                buttons: {
                    cancel: {
                        text: "{{ trans('backpack::crud.cancel') ?? 'Cancel' }}",
                        value: null,
                        visible: true,
                        className: "bg-secondary",
                        closeModal: true,
                    },
                    delete: {
                        text: "{{ trans('backpack::crud.delete') ?? 'Delete' }}",
                        value: true,
                        visible: true,
                        className: "bg-danger",
                    }
                },
                dangerMode: true,
            }).then((value) => {
                if (value) {
                    var delete_route = "{{ url($crud->route) }}/bulk-delete";

                    // submit an AJAX delete call
                    $.ajax({
                        url: delete_route,
                        type: 'POST',
                        data: { 
                            entries: crud.checkedItems,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(result) {
                            // Show an alert with the result
                            new Noty({
                                type: "success",
                                text: "<strong>{{ trans('backpack::base.success') ?? 'Success' }}</strong><br>" + (result.message || result.deleted + " {{ trans('backpack::crud.entries_deleted') ?? 'entries have been deleted.' }}")
                            }).show();

                            // Clear checked items
                            crud.checkedItems = [];
                            enableOrDisableBulkButtons();
                            
                            // Reload the table
                            if (typeof crud.table !== 'undefined') {
                                crud.table.ajax.reload();
                            } else {
                                location.reload();
                            }
                        },
                        error: function(result) {
                            // Show an alert with the result
                            var errorMessage = "{{ trans('backpack::crud.delete_failed') ?? 'Deletion failed' }}";
                            if (result.responseJSON && result.responseJSON.message) {
                                errorMessage = result.responseJSON.message;
                            }
                            new Noty({
                                type: "error",
                                text: "<strong>{{ trans('backpack::base.error') ?? 'Error' }}</strong><br>" + errorMessage
                            }).show();
                        }
                    });
                }
            });
        }
    }
</script>
@endBassetBlock
@endpush

