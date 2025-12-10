{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

<x-backpack::menu-item title="Clients" icon="la la-user-alt" :link="backpack_url('client')" />
<x-backpack::menu-item title="Client Balances" icon="la la-wallet" :link="backpack_url('client-balance')" />

<x-backpack::menu-item title="Products" icon="la la-box" :link="backpack_url('product')" />
<x-backpack::menu-item title="Services" icon="la la-cogs" :link="backpack_url('service')" />
<x-backpack::menu-item title="Warehouse" icon="la la-warehouse" :link="backpack_url('warehouse')" />
<x-backpack::menu-item title="Orders" icon="la la-cart-plus" :link="backpack_url('order')" />
<x-backpack::menu-item title="Pieces" icon="la la-puzzle-piece" :link="backpack_url('piece')" />


<x-backpack::menu-item title="Payments" icon="la la-money-bill-wave" :link="backpack_url('payment')" />
<x-backpack::menu-item title="Custom Prices" icon="la la-tag" :link="backpack_url('custom-price')" />
<x-backpack::menu-dropdown title="Settings" icon="la la-cog">
    <x-backpack::menu-dropdown-item title="Users" icon="la la-user" :link="backpack_url('user')" />
    <x-backpack::menu-dropdown-item title="Roles" icon="la la-user-cog" :link="backpack_url('role')" />
</x-backpack::menu-dropdown>

