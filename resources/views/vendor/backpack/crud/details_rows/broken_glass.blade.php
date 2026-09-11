@php
    /** @var \App\Models\Piece $entry */
    // Newest break first, matching the list's default sort on last_broken_at.
    $breaks = $entry->brokenGlasses()->orderByDesc('created_at')->orderByDesc('id')->get();
    $showQuantity = $breaks->contains(fn ($break) => (int) $break->quantity > 1);
@endphp

<div class="broken-glass-details p-3" bp-section="crud-details-row">

    <div class="table-responsive">
        <table class="table table-sm table-vcenter mb-0">
            <thead>
                <tr>
                    <th>{{ __('broken_glass.details.date') }}</th>
                    <th>{{ __('broken_glass.details.user') }}</th>
                    @if($showQuantity)
                        <th class="text-end">{{ __('broken_glass.details.quantity') }}</th>
                    @endif
                    <th>{{ __('broken_glass.details.description') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($breaks as $break)
                    <tr>
                        <td class="text-nowrap">{{ optional($break->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>{{ $break->user_label }}</td>
                        @if($showQuantity)
                            <td class="text-end">
                                @if((int) $break->quantity > 1)
                                    {{-- A size group broken in one click: one sheet, several pieces. --}}
                                    <span class="badge bg-warning">{{ $break->quantity }}</span>
                                @else
                                    {{ $break->quantity }}
                                @endif
                            </td>
                        @endif
                        <td>
                            @if(filled($break->description))
                                {{ $break->description }}
                            @else
                                <span class="text-muted">{{ __('piece.broken_modal.no_description') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showQuantity ? 4 : 3 }}" class="text-muted">
                            {{ __('broken_glass.details.empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
