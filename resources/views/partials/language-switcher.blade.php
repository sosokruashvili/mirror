{{--
    Admin-panel language selector.

    Rendered in the topbar (see vendor/backpack/theme-tabler/inc/topbar_right_content),
    but kept standalone so it can also be dropped onto the login screen.
    Posts to locale.switch, which stores the choice in the session and on the
    user row. Hidden entirely when only one language is configured.
--}}
@php
    $locales = config('locales.supported', []);
    $current = app()->getLocale();
@endphp

@if (count($locales) > 1)
    <li class="nav-item dropdown">
        <a href="#" class="nav-link d-flex lh-1 text-reset align-items-center px-2"
           data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"
           aria-label="{{ __('locale.change_language') }}">
            <i class="la la-globe fs-2 m-0"></i>
            <span class="d-none d-lg-block ps-1 small">{{ $locales[$current]['short'] ?? strtoupper($current) }}</span>
        </a>

        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <h6 class="dropdown-header">{{ __('locale.change_language') }}</h6>

            @foreach ($locales as $code => $locale)
                <form method="POST" action="{{ route('locale.switch', $code) }}" class="m-0">
                    @csrf
                    <button type="submit" lang="{{ $code }}"
                            class="dropdown-item d-flex align-items-center @if($code === $current) active @endif"
                            @if($code === $current) aria-current="true" @endif>
                        <span class="flex-fill text-start">{{ $locale['native'] }}</span>
                        @if ($code === $current)
                            <i class="la la-check ms-3"></i>
                        @endif
                    </button>
                </form>
            @endforeach
        </div>
    </li>
@endif
