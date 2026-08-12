{{--
    Desktop chrome under the sidebar logo (d-none d-lg-flex). The theme ships
    this holding only the color-mode switcher; the language switcher sits
    beside it, since the topbar that also renders it is mobile-only in the
    "vertical" layout. The theme's own CSS draws the "|" divider between them.
--}}
<div class="w-100 justify-content-center align-items-center d-none d-lg-flex sidebar-shortcuts">
    @includeWhen(backpack_theme_config('options.showColorModeSwitcher'), backpack_view('layouts.partials.switch_theme'))
    @include('partials.language-switcher')
</div>
