{{-- Undo a confirmation (removes the stock it returned). The click handler lives in admin/broken-glass/list-extras. --}}
@if ($entry->isConfirmed() && backpack_user()?->can('broken-glass.unconfirm'))
    <a href="javascript:void(0)" onclick="brokenGlassAction(this)" class="btn btn-sm btn-link text-danger"
       data-route="{{ url($crud->route . '/' . $entry->getKey() . '/unconfirm') }}"
       data-action="unconfirm">
        <i class="la la-undo"></i> {{ __('broken_glass.buttons.unconfirm') }}
    </a>
@endif
