@if ($crud->hasAccess('list'))
    <a href="javascript:void(0)" id="recalculateBalancesBtn" class="btn btn-sm btn-secondary shadow-sm">
        <i class="la la-sync"></i> Recalculate Balances
    </a>
@endif

@push('after_scripts')
<script>
    $(function () {
        $('#recalculateBalancesBtn').on('click', function (e) {
            e.preventDefault();

            var $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }

            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Recalculating...');

            $.ajax({
                url: '{{ route('client-balance.recalculate') }}',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function (response) {
                    // Refresh the table rows (they read the latest stored snapshot).
                    if (typeof crud !== 'undefined' && crud.table) {
                        crud.table.ajax.reload(null, false);
                    }

                    // Update the stats widget from the fresh values returned above.
                    $('#stats-clients-count').text(Number(response.clientsCount).toLocaleString());
                    $('#stats-total-payments').text(parseFloat(response.totalPayments).toFixed(0) + ' ₾');
                    $('#stats-total-orders').text(parseFloat(response.totalOrders).toFixed(0) + ' ₾');
                    $('#stats-total-balance').text(parseFloat(response.totalBalance).toFixed(0) + ' ₾');

                    var $balanceCard = $('#stats-balance-card');
                    $balanceCard.removeClass('bg-success bg-danger');
                    $balanceCard.addClass(response.totalBalance >= 0 ? 'bg-success' : 'bg-danger');

                    new Noty({
                        type: 'success',
                        text: response.message || 'Balances recalculated.',
                        timeout: 3000,
                    }).show();
                },
                error: function () {
                    new Noty({
                        type: 'error',
                        text: 'Failed to recalculate balances. Please try again.',
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
