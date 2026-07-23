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
        <x-backpack::menu-dropdown-item title="User Stats" icon="la la-chart-bar" :link="backpack_url('user-stats')" />
    </x-backpack::menu-dropdown>
@else
    <li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>
@endif

@if($u && $u->can('team-order.view'))
    <x-backpack::menu-item title="Team Orders" icon="la la-hammer" :link="backpack_url('team/orders')" />
@endif

@if($u && $u->can('client.list'))
    <x-backpack::menu-item title="Clients" icon="la la-user-alt" :link="backpack_url('client')" />
@endif
@if($u && $u->can('client-balance.list'))
    <x-backpack::menu-item title="Client Balances" icon="la la-wallet" :link="backpack_url('client-balance')" />
@endif

@if($u && $u->can('product.list'))
    <x-backpack::menu-item title="Products" icon="la la-box" :link="backpack_url('product')" />
@endif
@if($u && ($u->can('service.list') || $u->can('service-stats.view')))
    @if($u->can('service-stats.view'))
        <x-backpack::menu-dropdown title="Services" icon="la la-cogs">
            @if($u->can('service.list'))
                <x-backpack::menu-dropdown-item title="Services" icon="la la-cogs" :link="backpack_url('service')" />
            @endif
            <x-backpack::menu-dropdown-item title="Service Stats" icon="la la-chart-pie" :link="backpack_url('service-stats')" />
        </x-backpack::menu-dropdown>
    @else
        <x-backpack::menu-item title="Services" icon="la la-cogs" :link="backpack_url('service')" />
    @endif
@endif

@if($u && ($u->can('warehouse.list') || $u->can('warehouse-expense.list') || $u->can('supplier.list')))
    <x-backpack::menu-dropdown title="Warehouse" icon="la la-warehouse">
        @if($u->can('warehouse.list'))
            <x-backpack::menu-dropdown-item title="Stock" icon="la la-boxes" :link="backpack_url('warehouse')" />
        @endif
        @if($u->can('warehouse-expense.list'))
            <x-backpack::menu-dropdown-item title="Out" icon="la la-receipt" :link="backpack_url('warehouse-expense')" />
        @endif
        @if($u->can('supplier.list'))
            <x-backpack::menu-dropdown-item title="Suppliers" icon="la la-truck" :link="backpack_url('supplier')" />
        @endif
    </x-backpack::menu-dropdown>
@endif

@if($u && $u->can('order.list'))
    <x-backpack::menu-item title="Orders" icon="la la-cart-plus" :link="backpack_url('order')" />
@endif

@if($u && ($u->can('piece.list') || $u->can('stage.list')))
    <x-backpack::menu-dropdown title="Pieces" icon="la la-puzzle-piece">
        @if($u->can('piece.list'))
            <x-backpack::menu-dropdown-item title="Pieces" icon="la la-puzzle-piece" :link="backpack_url('piece')" />
        @endif
        @if($u->can('stage.list'))
            <x-backpack::menu-dropdown-item title="Stages" icon="la la-layer-group" :link="backpack_url('stage')" />
        @endif
    </x-backpack::menu-dropdown>
@endif

@if($u && $u->can('payment.list'))
    <x-backpack::menu-item title="Payments" icon="la la-money-bill-wave" :link="backpack_url('payment')" />
@endif

@if($u && ($u->can('cashier.list') || $u->can('cashier-expense.list') || $u->can('expense-category.list')))
    <x-backpack::menu-dropdown title="Cashier" icon="la la-cash-register">
        @if($u->can('cashier.list'))
            <x-backpack::menu-dropdown-item title="Balance" icon="la la-wallet" :link="backpack_url('cashier')" />
        @endif
        @if($u->can('cashier-expense.list'))
            <x-backpack::menu-dropdown-item title="Expenses" icon="la la-receipt" :link="backpack_url('cashier-expense')" />
        @endif
        @if($u->can('expense-category.list'))
            <x-backpack::menu-dropdown-item title="Expense Categories" icon="la la-sitemap" :link="backpack_url('expense-category')" />
        @endif
    </x-backpack::menu-dropdown>
@endif

@if($u && $u->can('custom-price.list'))
    <x-backpack::menu-item title="Custom Prices" icon="la la-tag" :link="backpack_url('custom-price')" />
@endif

@if($u && ($u->can('settings.view') || $u->can('user.list') || $u->can('role.list') || $u->can('permission.list') || $u->can('audit-log.list')))
    <x-backpack::menu-dropdown title="Settings" icon="la la-cog">
        @if($u->can('settings.view'))
            <x-backpack::menu-dropdown-item title="Global Settings" icon="la la-sliders-h" :link="backpack_url('settings')" />
        @endif
        @if($u->can('user.list'))
            <x-backpack::menu-dropdown-item title="Users" icon="la la-user" :link="backpack_url('user')" />
        @endif
        @if($u->can('role.list'))
            <x-backpack::menu-dropdown-item title="Roles" icon="la la-user-cog" :link="backpack_url('role')" />
        @endif
        @if($u->can('permission.list'))
            <x-backpack::menu-dropdown-item title="Permissions" icon="la la-key" :link="backpack_url('permission')" />
        @endif
        @if($u->can('audit-log.list'))
            <x-backpack::menu-dropdown-item title="Activity Log" icon="la la-history" :link="backpack_url('audit-log')" />
        @endif
    </x-backpack::menu-dropdown>
@endif
