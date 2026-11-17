{{-- Read-only facts above the Broken Glass edit form. --}}
@php
    /** @var \App\Models\BrokenGlass $entry */
    $piece = $entry->piece;
    $trim = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp
<div class="row g-3 mb-1">
    <div class="col-6 col-md-3">
        <div class="text-muted small">{{ __('broken_glass.order_id') }}</div>
        <div class="fw-bold">{{ $entry->order_id ?? '—' }}</div>
    </div>
    <div class="col-6 col-md-3">
        <div class="text-muted small">{{ __('broken_glass.piece_id') }}</div>
        <div class="fw-bold">{{ $entry->piece_id }}@if($piece && $piece->width && $piece->height) · {{ $trim($piece->width) }} × {{ $trim($piece->height) }} სმ @endif</div>
    </div>
    <div class="col-6 col-md-3">
        <div class="text-muted small">{{ __('broken_glass.damaged_area') }}</div>
        <div class="fw-bold">{{ $entry->damaged_area !== null ? \App\Support\BrokenGlass\BreakDetails::formatArea($entry->damaged_area) : '—' }}</div>
    </div>
    <div class="col-6 col-md-3">
        <div class="text-muted small">{{ __('broken_glass.author') }}</div>
        <div class="fw-bold">{{ $entry->user_label }} · {{ optional($entry->created_at)->format('Y-m-d H:i') }}</div>
    </div>
</div>
