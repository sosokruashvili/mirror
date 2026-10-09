@php
    $total = (float) $order->calculateTotalPrice(false);
    $products = $order->products->pluck('title')->filter()->implode(', ');
@endphp
<tr>
    <td>
        <a href="{{ route('cabinet.orders.show', $order) }}" class="fw-medium text-reset">{{ __('cabinet.order') }} #{{ $order->id }}</a>
        @if ($products !== '')
            <div class="text-secondary">{{ $products }}</div>
        @endif
    </td>
    <td>@include('cabinet.partials.status', ['order' => $order])</td>
    <td class="text-secondary">{{ $order->created_at?->timezone(config('app.timezone'))->format('d.m.Y') }}</td>
    <td>@include('cabinet.partials.due', ['order' => $order])</td>
    <td>
        <span class="badge {{ $order->paid ? 'bg-success-lt' : 'bg-secondary-lt' }}">{{ $order->paid ? __('cabinet.paid') : __('cabinet.unpaid') }}</span>
    </td>
    <td class="text-end fw-medium">{{ \App\Support\Cabinet\Cabinet::money($total) }}</td>
</tr>
