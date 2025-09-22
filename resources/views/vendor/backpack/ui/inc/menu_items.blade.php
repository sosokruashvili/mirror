{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

<x-backpack::menu-item title="Orders" icon="la la-cart-plus" :link="backpack_url('order')" />
<x-backpack::menu-item title="Clients" icon="la la-user" :link="backpack_url('client')" />
<x-backpack::menu-item title="Users" icon="la la-users" :link="backpack_url('user')" />