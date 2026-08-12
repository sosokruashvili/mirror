@if ($crud->hasAccess('list'))
    <a href="javascript:void(0)" id="recalculateCashierBtn" bp-button="recalculate-cashier" class="btn btn-primary" data-style="zoom-in">
        <i class="la la-sync"></i> <span>&nbsp;{{ __('cashier.recalculate.button') }}</span>
    </a>
@endif

@push('after_scripts')
<script>
    $(function () {
        $('#recalculateCashierBtn').on('click', function (e) {
            e.preventDefault();

            var $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }

            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> ' + @json(__('cashier.recalculate.running')));

            $.ajax({
                url: '{{ route('cashier.recalculate') }}',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function (response) {
                    // Refresh the table rows (they show the stored snapshots).
                    if (typeof crud !== 'undefined' && crud.table) {
                        crud.table.ajax.reload(null, false);
                    }

                    // Update the today-stats widget from the fresh values returned above.
                    $('#stats-cashier-current').text(parseFloat(response.current_balance).toFixed(2) + ' ₾');
                    $('#stats-cashier-opening').text(parseFloat(response.opening_balance).toFixed(2) + ' ₾');
                    $('#stats-cashier-in').text(parseFloat(response.cash_in).toFixed(2) + ' ₾');
                    $('#stats-cashier-out').text(parseFloat(response.cash_out).toFixed(2) + ' ₾');

                    new Noty({
                        type: 'success',
                        text: response.message || @json(__('cashier.recalculate.success')),
                        timeout: 3000,
                    }).show();
                },
                error: function () {
                    new Noty({
                        type: 'error',
                        text: @json(__('cashier.recalculate.error')),
                        timeout: 3000,
                    }).show();
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });
    });
</script>
@endpush
