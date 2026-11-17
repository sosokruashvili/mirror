{{-- Confirm a pending broken-glass record. The click handler lives in admin/broken-glass/list-extras. --}}
@if ($entry->isPending() && backpack_user()?->can('broken-glass.confirm'))
    <a href="javascript:void(0)" onclick="brokenGlassAction(this)" class="btn btn-sm btn-link text-success"
       data-route="{{ url($crud->route . '/' . $entry->getKey() . '/confirm') }}"
       data-action="confirm"
       data-usable="{{ \App\Support\BrokenGlass\BreakDetails::formatArea($entry->usable_area ?? 0) }}">
        <i class="la la-check-circle"></i> {{ __('broken_glass.buttons.confirm') }}
    </a>
@endif
