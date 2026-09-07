{{-- Live warehouse remaining check. Driven by public/assets/js/order-warehouse-stock.js. --}}
@php
    $excludeOrderId = isset($entry) ? $entry->getKey() : null;
@endphp
<style>
    /* Tabler's dark alert-danger is only a thin left bar — fill the banner so it cannot be missed. */
    #order-stock-warning,
    #order-stock-warning-save {
        font-size: 0.95rem;
        line-height: 1.45;
    }
    #order-stock-warning.is-ok,
    #order-stock-warning-save.is-ok,
    #order-stock-warning.is-exceeded,
    #order-stock-warning-save.is-exceeded {
        position: sticky;
        top: 12px;
        z-index: 30;
        color: #fff !important;
        padding: 0.95rem 1.15rem;
        border-radius: 0.5rem;
        border-width: 2px;
        border-style: solid;
    }
    #order-stock-warning.is-ok,
    #order-stock-warning-save.is-ok {
        background: #206bc4 !important;
        border-color: #206bc4 !important;
        box-shadow: 0 6px 22px rgba(32, 107, 196, 0.45);
    }
    #order-stock-warning.is-exceeded,
    #order-stock-warning-save.is-exceeded {
        background: #dc3545 !important;
        border-color: #dc3545 !important;
        box-shadow: 0 6px 22px rgba(220, 53, 69, 0.45);
    }
    #order-stock-warning-save.is-ok,
    #order-stock-warning-save.is-exceeded {
        position: static;
        z-index: auto;
    }
    #order-stock-warning .order-stock-title,
    #order-stock-warning-save .order-stock-title {
        font-size: 1.15rem;
        font-weight: 700;
        letter-spacing: 0.01em;
    }
    #order-stock-warning .order-stock-line,
    #order-stock-warning-save .order-stock-line {
        font-size: 1.05rem;
        font-weight: 600;
    }
    .order-stock-ok-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 1.75rem;
        height: 1.75rem;
        margin-top: 0.1rem;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        font-size: 1.1rem;
    }
    .order-stock-pulse-dot {
        display: inline-block;
        flex: 0 0 auto;
        width: 12px;
        height: 12px;
        margin-top: 0.35rem;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        animation: urgent-red-pulse 1.4s ease-in-out infinite !important;
    }
    @keyframes urgent-red-pulse {
        0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); transform: scale(1); }
        50% { opacity: 0.2; box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); transform: scale(1.08); }
    }
</style>
<script>
window.orderWarehouseStock = {
    url: @json(url(config('backpack.base.route_prefix') . '/order/warehouse-remaining')),
    excludeOrderId: @json($excludeOrderId),
    i18n: {
        warningTitle: @json(__('order.stock_warning.title')),
        okTitle: @json(__('order.stock_warning.ok_title')),
        rowOk: @json(__('order.stock_warning.row_ok')),
        rowOkNeed: @json(__('order.stock_warning.row_ok_need')),
        rowOver: @json(__('order.stock_warning.row_over')),
        confirmTitle: @json(__('order.stock_warning.confirm_title')),
        confirmIntro: @json(__('order.stock_warning.confirm_intro')),
        confirmQuestion: @json(__('order.stock_warning.confirm_question')),
        saveAnyway: @json(__('order.stock_warning.save_anyway')),
        cancel: @json(trans('backpack::crud.cancel'))
    }
};
</script>
<script src="{{ asset('assets/js/order-warehouse-stock.js') }}?v={{ filemtime(public_path('assets/js/order-warehouse-stock.js')) }}"></script>
