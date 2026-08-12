{{-- This file is used for menu items by any Backpack v6 theme --}}
{{--
    Each item is shown only if the current user holds the matching page
    permission (see config/access.php). We use backpack_user()->can() rather
    than @can, because Backpack authenticates on its own "backpack" guard while
    @can/Gate resolve against the default web guard. Administrators see
    everything via the Gate::before() bypass in AppServiceProvider.
--}}
@php($u = backpack_user())

@if($u && $u->can('user-stats.view'))
    <x-backpack::menu-dropdown title="{{ trans('backpack::base.dashboard') }}" icon="la la-home">
        <x-backpack::menu-dropdown-item title="{{ trans('backpack::base.dashboard') }}" icon="la la-home" :link="backpack_url('dashboard')" />
        <x-backpack::menu-dropdown-item title="{{ __('menu.user_stats') }}" icon="la la-chart-bar" :link="backpack_url('user-stats')" />
    </x-backpack::menu-dropdown>
@else
    <li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>
@endif

@if($u && $u->can('team-order.view'))
    <x-backpack::menu-item title="{{ __('menu.team_orders') }}" icon="la la-hammer" :link="backpack_url('team/orders')" />
@endif

@if($u && $u->can('client.list'))
    <x-backpack::menu-item title="{{ __('menu.clients') }}" icon="la la-user-alt" :link="backpack_url('client')" />
@endif
@if($u && $u->can('client-balance.list'))
    <x-backpack::menu-item title="{{ __('menu.client_balances') }}" icon="la la-wallet" :link="backpack_url('client-balance')" />
@endif

@if($u && $u->can('product.list'))
    <x-backpack::menu-item title="{{ __('menu.products') }}" icon="la la-box" :link="backpack_url('product')" />
@endif
@if($u && ($u->can('service.list') || $u->can('service-stats.view')))
    @if($u->can('service-stats.view'))
        <x-backpack::menu-dropdown title="{{ __('menu.services') }}" icon="la la-cogs">
            @if($u->can('service.list'))
                <x-backpack::menu-dropdown-item title="{{ __('menu.services') }}" icon="la la-cogs" :link="backpack_url('service')" />
            @endif
            <x-backpack::menu-dropdown-item title="{{ __('menu.service_stats') }}" icon="la la-chart-pie" :link="backpack_url('service-stats')" />
        </x-backpack::menu-dropdown>
    @else
        <x-backpack::menu-item title="{{ __('menu.services') }}" icon="la la-cogs" :link="backpack_url('service')" />
    @endif
@endif

@if($u && ($u->can('warehouse.list') || $u->can('warehouse-expense.list') || $u->can('warehouse-correction.list')))
    <x-backpack::menu-dropdown title="{{ __('menu.warehouse') }}" icon="la la-warehouse">
        @if($u->can('warehouse.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.warehouse_stock') }}" icon="la la-boxes" :link="backpack_url('warehouse')" />
        @endif
        @if($u->can('warehouse-expense.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.warehouse_out') }}" icon="la la-receipt" :link="backpack_url('warehouse-expense')" />
        @endif
        @if($u->can('warehouse-correction.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.warehouse_corrections') }}" icon="la la-balance-scale" :link="backpack_url('warehouse-correction')" />
        @endif
    </x-backpack::menu-dropdown>
@endif

@if($u && ($u->can('supplier.list') || $u->can('supplier-price.list') || $u->can('supplier-balance.list')))
    <x-backpack::menu-dropdown title="{{ __('menu.suppliers') }}" icon="la la-truck">
        @if($u->can('supplier.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.suppliers') }}" icon="la la-truck" :link="backpack_url('supplier')" />
        @endif
        @if($u->can('supplier-price.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.supplier_prices') }}" icon="la la-tags" :link="backpack_url('supplier-price')" />
        @endif
        @if($u->can('supplier-balance.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.supplier_balances') }}" icon="la la-wallet" :link="backpack_url('supplier-balance')" />
        @endif
    </x-backpack::menu-dropdown>
@endif

@if($u && $u->can('order.list'))
    <x-backpack::menu-item title="{{ __('menu.orders') }}" icon="la la-cart-plus" :link="backpack_url('order')" />
@endif

@if($u && ($u->can('piece.list') || $u->can('stage.list')))
    <x-backpack::menu-dropdown title="{{ __('menu.pieces') }}" icon="la la-puzzle-piece">
        @if($u->can('piece.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.pieces') }}" icon="la la-puzzle-piece" :link="backpack_url('piece')" />
        @endif
        @if($u->can('stage.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.stages') }}" icon="la la-layer-group" :link="backpack_url('stage')" />
        @endif
    </x-backpack::menu-dropdown>
@endif

@if($u && $u->can('payment.list'))
    <x-backpack::menu-item title="{{ __('menu.payments') }}" icon="la la-money-bill-wave" :link="backpack_url('payment')" />
@endif

@if($u && ($u->can('cashier.list') || $u->can('cashier-expense.list') || $u->can('expense-category.list')))
    <x-backpack::menu-dropdown title="{{ __('menu.cashier') }}" icon="la la-cash-register">
        @if($u->can('cashier.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.cashier_balance') }}" icon="la la-wallet" :link="backpack_url('cashier')" />
        @endif
        @if($u->can('cashier-expense.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.cashier_expenses') }}" icon="la la-receipt" :link="backpack_url('cashier-expense')" />
        @endif
        @if($u->can('expense-category.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.expense_categories') }}" icon="la la-sitemap" :link="backpack_url('expense-category')" />
        @endif
    </x-backpack::menu-dropdown>
@endif

@if($u && $u->can('custom-price.list'))
    <x-backpack::menu-item title="{{ __('menu.custom_prices') }}" icon="la la-tag" :link="backpack_url('custom-price')" />
@endif

@if($u && ($u->can('settings.view') || $u->can('user.list') || $u->can('role.list') || $u->can('permission.list') || $u->can('audit-log.list')))
    <x-backpack::menu-dropdown title="{{ __('menu.settings') }}" icon="la la-cog">
        @if($u->can('settings.view'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.global_settings') }}" icon="la la-sliders-h" :link="backpack_url('settings')" />
        @endif
        @if($u->can('user.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.users') }}" icon="la la-user" :link="backpack_url('user')" />
        @endif
        @if($u->can('role.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.roles') }}" icon="la la-user-cog" :link="backpack_url('role')" />
        @endif
        @if($u->can('permission.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.permissions') }}" icon="la la-key" :link="backpack_url('permission')" />
        @endif
        @if($u->can('audit-log.list'))
            <x-backpack::menu-dropdown-item title="{{ __('menu.activity_log') }}" icon="la la-history" :link="backpack_url('audit-log')" />
        @endif
    </x-backpack::menu-dropdown>
@endif
