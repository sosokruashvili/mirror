@php
    // A payment can cover several orders. Only link the client's own, billed ones.
    $visibleOrders = ($payment->relationLoaded('orders') ? $payment->orders : collect())
        ->filter(fn ($order) => (int) $order->client_id === (int) $payment->client_id
            && $order->status !== 'draft')
        ->sortBy('id');
@endphp
<tr>
    <td class="text-secondary">{{ $payment->payment_date?->timezone(config('app.timezone'))->format('d.m.Y') }}</td>
    <td class="fw-medium">{{ \App\Support\Cabinet\Cabinet::money($payment->amount_gel) }}</td>
    <td>
        <span class="badge {{ $payment->status === 'Paid' ? 'bg-success-lt' : 'bg-warning-lt' }}">
            {{ \App\Support\Cabinet\Cabinet::enumLabel('payment.statuses', $payment->status) }}
        </span>
    </td>
    <td class="text-secondary">{{ \App\Support\Cabinet\Cabinet::enumLabel('payment.methods', $payment->method) }}</td>
    <td class="text-secondary">{{ \App\Support\Cabinet\Cabinet::enumLabel('payment.types', $payment->type) }}</td>
    <td>
        @forelse ($visibleOrders as $visibleOrder)
            <a href="{{ route('cabinet.orders.show', $visibleOrder) }}">{{ __('cabinet.order') }} #{{ $visibleOrder->id }}</a>@unless ($loop->last), @endunless
        @empty
            <span class="text-secondary">—</span>
        @endforelse
    </td>
</tr>
