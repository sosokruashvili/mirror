{{--
    Client balance controls for the order create/edit form.

    JS (public/assets/js/order-client-balance.js) moves the value + "Show Balance"
    button to the end of the client select, fetches the live balance when a client
    is chosen, and fills this modal with the same recap used on Client Balances.
--}}
<div id="orderClientBalanceControls"
     class="order-client-balance-controls"
     data-balance-url="{{ url(config('backpack.base.route_prefix', 'admin') . '/order/client-balance') }}"
     data-loading-text="{{ __('order.loading_balance') }}"
     data-error-text="{{ __('order.balance_load_error') }}"
     hidden>
    <span id="orderClientBalanceValue"
          class="order-client-balance-value"
          hidden
          aria-live="polite">-</span>
    <button type="button"
            id="showClientBalanceBtn"
            class="btn btn-outline-primary"
            disabled
            title="{{ __('order.select_client_to_see_balance') }}">
        <i class="la la-wallet"></i>
        {{ __('order.show_balance') }}
    </button>
</div>

<div class="modal fade" id="clientBalanceModal" tabindex="-1" aria-labelledby="clientBalanceModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clientBalanceModalLabel">{{ __('order.client_balance') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('order.close') }}"></button>
            </div>
            <div class="modal-body p-0">
                <div id="clientBalanceModalLoading" class="text-center text-secondary py-5 px-3">
                    <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                    <div class="mt-2">{{ __('order.loading_balance') }}</div>
                </div>
                <div id="clientBalanceModalError" class="alert alert-danger m-3 d-none" data-fallback-error="{{ __('order.balance_load_error') }}"></div>
                <div id="clientBalanceModalContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('order.close') }}</button>
            </div>
        </div>
    </div>
</div>

<style>
    .order-client-field-row {
        display: flex;
        align-items: stretch;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .order-client-field-row > select,
    .order-client-field-row > .select2-container {
        flex: 1 1 12rem;
        min-width: 0;
    }
    .order-client-field-row > select.select2-hidden-accessible {
        flex: 0 0 0;
        width: 1px !important;
        position: absolute !important;
    }
    .order-client-balance-controls {
        display: flex;
        align-items: stretch;
        gap: 0.5rem;
        flex: 0 0 auto;
    }
    .order-client-balance-value {
        display: inline-flex;
        align-items: center;
        font-weight: 600;
        white-space: nowrap;
        padding: 0 0.25rem;
        font-size: 1rem;
    }
    #showClientBalanceBtn {
        white-space: nowrap;
        min-height: 38px;
    }
    .client-balance-subtable {
        max-height: 22rem;
        overflow-y: auto;
    }
    .client-balance-details .card-table th {
        position: sticky;
        top: 0;
        z-index: 1;
        background-color: var(--tblr-card-bg, #fff);
    }
</style>
