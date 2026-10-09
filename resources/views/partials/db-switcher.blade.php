{{--
    Developer database switch + "you are on the development database" banner.

    Mounted twice like the language switcher (inc/topbar_right_content for
    mobile, layouts/partials/sidebar_shortcuts for desktop), hence @once around
    the styles. The <style> is INLINE on purpose: the <head> (and its
    @stack('after_styles')) is already rendered when these partials run, so a
    @push here would be silently lost.

    - Production host, developer: badge + select that POSTs admin/db-connection.
    - Dev host (App\Support\DevEnvironment): fixed "DEV · host" badge, no select,
      shown to everyone — every request there runs on the dev database.
    - Whenever the dev database is active: red top banner + red header border.
--}}
@php
    $dbSwitchHostMode = \App\Support\DevEnvironment::isActive();
    $dbSwitchDevDb = isDevDatabase();
    $dbSwitchDeveloper = isDeveloper();
    $dbSwitchShow = $dbSwitchHostMode || $dbSwitchDeveloper;
    $dbSwitchBanner = __('dbswitch.banner', ['db' => \App\Support\DevEnvironment::devDatabaseName()]);
    // CSS string literal (not JS/HTML escaping: entities are not decoded inside <style>).
    $dbSwitchBannerCss = '"'.str_replace(['\\', '"', "\n", "\r", '<', '>'], ['\\\\', '\\"', ' ', ' ', '\\3c ', '\\3e '], $dbSwitchBanner).'"';
@endphp

@if ($dbSwitchShow)
    @once
        <style>
            .db-switcher { gap: .4rem; white-space: nowrap; }
            .db-switcher .db-switcher-badge {
                display: inline-block; font-size: .7rem; font-weight: 700; letter-spacing: .04em;
                line-height: 1; padding: .3rem .45rem; border-radius: .25rem; color: #fff;
            }
            .db-switcher .db-switcher-badge-prod { background: #2fb344; }
            .db-switcher .db-switcher-badge-dev { background: #dc3545; }
            .db-switcher select.db-switcher-select {
                font-size: .75rem; line-height: 1.2; padding: .15rem 1.4rem .15rem .4rem; height: auto;
                width: auto; display: inline-block; cursor: pointer;
            }
            .sidebar-shortcuts .db-switcher .db-switcher-badge:before,
            .sidebar-shortcuts .db-switcher select:before,
            .sidebar-shortcuts .db-switcher input:before { content: none !important; margin: 0 !important; }
            @if ($dbSwitchDevDb)
            body { padding-top: 28px !important; }
            body::before {
                content: {!! $dbSwitchBannerCss !!};
                position: fixed; top: 0; left: 0; right: 0; z-index: 1090; height: 28px; line-height: 28px;
                background: #dc3545; color: #fff; text-align: center; font-weight: 700; font-size: .8rem;
                letter-spacing: .03em; text-transform: uppercase; box-shadow: 0 2px 6px rgba(220, 53, 69, .45);
            }
            .page > .navbar-vertical, .page > .page-wrapper { border-top: 4px solid #dc3545 !important; }
            @endif
        </style>
    @endonce

    @if ($dbSwitchHostMode)
        <span class="db-switcher d-inline-flex align-items-center m-0" title="{{ $dbSwitchBanner }}">
            <span class="db-switcher-badge db-switcher-badge-dev">{{ __('dbswitch.host_badge', ['host' => request()->getHost()]) }}</span>
        </span>
    @else
        <form method="POST" action="{{ route('db-connection.update') }}"
              class="db-switcher d-inline-flex align-items-center m-0"
              title="{{ __('dbswitch.label') }}">
            <span class="db-switcher-badge {{ $dbSwitchDevDb ? 'db-switcher-badge-dev' : 'db-switcher-badge-prod' }}">
                {{ $dbSwitchDevDb ? __('dbswitch.badge_dev') : __('dbswitch.badge_prod') }}
            </span>
            <select name="connection" class="form-select form-select-sm db-switcher-select"
                    aria-label="{{ __('dbswitch.label') }}" onchange="this.form.submit()">
                <option value="prod" @selected(! $dbSwitchDevDb)>{{ __('dbswitch.prod') }}</option>
                <option value="dev" @selected($dbSwitchDevDb)>{{ __('dbswitch.dev') }}</option>
            </select>
            @csrf
        </form>
    @endif
@endif
