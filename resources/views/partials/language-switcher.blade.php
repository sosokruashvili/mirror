{{--
    Admin-panel language selector.

    Mounted twice, because the tabler "vertical" layout splits chrome by
    breakpoint: inc/topbar_right_content (mobile, d-block d-lg-none) and
    layouts/partials/sidebar_shortcuts (desktop, d-none d-lg-flex).

    Deliberately an inline segmented toggle rather than a dropdown:
      * the sidebar is overflow-y:scroll when sidebarFixed is false, which
        makes overflow-x clip - a floating dropdown gets cut off;
      * the theme's ".sidebar-shortcuts :not(:first-child):not(.d-none):before"
        rule injects "|" separators into ANY descendant, which mangled the
        dropdown internals.
    That same rule now works for us: it draws the divider between the two
    language buttons, matching the theme's own shortcut styling.

    One form with a formaction per locale, so switching stays a POST. The
    buttons are emitted BEFORE @csrf so the first button is :first-child and
    does not get a leading "|" (the hidden csrf input renders no box, so it
    never shows a separator of its own).
--}}
@php
    $locales = config('locales.supported', []);
    $current = app()->getLocale();
@endphp

@if (count($locales) > 1)
    <form method="POST" action="{{ route('locale.switch', $current) }}"
          class="language-switcher d-inline-flex align-items-center m-0">
        @foreach ($locales as $code => $locale)
            <button type="submit"
                    formaction="{{ route('locale.switch', $code) }}"
                    lang="{{ $code }}"
                    title="{{ $locale['native'] }}"
                    aria-label="{{ __('locale.change_language') }}: {{ $locale['native'] }}"
                    @if($code === $current) aria-current="true" @endif
                    class="btn-link border-0 bg-transparent shadow-none text-decoration-none nav-link px-1 py-0 @if($code === $current) fw-bold text-primary @else text-secondary @endif">
                {{ $locale['short'] ?? strtoupper($code) }}
            </button>
        @endforeach
        @csrf
    </form>
@endif
