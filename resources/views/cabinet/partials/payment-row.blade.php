@php
    $linked = $payment->relationLoaded('order') ? $payment->order : null;
    $visibleOrder = ($linked
        && (int) $linked->client_id === (int) $payment->client_id
        && $linked->status !== 'draft')
        ? $linked
        : null;
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
        @if ($visibleOrder)
            <a href="{{ route('cabinet.orders.show', $visibleOrder) }}">{{ __('cabinet.order') }} #{{ $visibleOrder->id }}</a>
        @else
            <span class="text-secondary">—</span>
        @endif
    </td>
</tr>
