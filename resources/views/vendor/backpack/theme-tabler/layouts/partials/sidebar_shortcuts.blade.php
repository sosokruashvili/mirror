{{--
    Desktop chrome under the sidebar logo (d-none d-lg-flex). The theme ships
    this holding only the color-mode switcher; the language switcher and the
    developer DB switch (partials/db-switcher) sit beside it, since the topbar
    that also renders them is mobile-only in the "vertical" layout. The theme's
    own CSS draws the "|" divider between them.

    The sidebar content box is about 225px (15rem minus the always-on
    scrollbar). One nowrap row is wider than that, and justify-content:center
    clips the color-mode icon off the left edge. Wrap so the icon stays on the
    first line with the language toggle and the DB switch drops below. The
    theme pipe on the DB form would otherwise sit alone at the start of that
    second line.
--}}
<div class="w-100 justify-content-center align-items-center d-none d-lg-flex sidebar-shortcuts">
    @includeWhen(backpack_theme_config('options.showColorModeSwitcher'), backpack_view('layouts.partials.switch_theme'))
    @include('partials.language-switcher')
    @include('partials.db-switcher')
</div>
<style>
    .sidebar-shortcuts {
        flex-wrap: wrap;
        row-gap: .35rem;
    }
    .sidebar-shortcuts > .db-switcher {
        max-width: 100%;
        min-width: 0;
    }
    .sidebar-shortcuts > .db-switcher:before {
        content: none !important;
        margin: 0 !important;
    }
    .sidebar-shortcuts .db-switcher-select {
        min-width: 0;
        max-width: 100%;
    }
</style>
