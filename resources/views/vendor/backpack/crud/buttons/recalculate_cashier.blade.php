@if ($crud->hasAccess('list'))
    <a href="javascript:void(0)" id="recalculateCashierBtn" bp-button="recalculate-cashier" class="btn btn-primary" data-style="zoom-in">
        <i class="la la-sync"></i> <span>&nbsp;Recalculate Balances</span>
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
            $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Recalculating...');

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
                        text: response.message || 'Cashier balances recalculated.',
                        timeout: 3000,
                    }).show();
                },
                error: function () {
                    new Noty({
                        type: 'error',
                        text: 'Failed to recalculate cashier balances. Please try again.',
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
