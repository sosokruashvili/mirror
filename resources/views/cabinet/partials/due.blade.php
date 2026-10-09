@if ($order->due_date)
    @php
        $tone = \App\Support\Cabinet\Cabinet::dueTone($order->due_date);
        $pulse = $tone === 'urgent' && $order->status !== 'finished';
        $statusClass = match ($tone) {
            'ok' => 'status-green',
            'soon' => 'status-yellow',
            default => 'status-red',
        };
    @endphp
    <span class="status {{ $statusClass }}">
        <span @class(['status-dot', 'urgent-red-pulse' => $pulse])></span>
        {{ $order->due_date->format('d.m.Y') }}
        <span class="text-secondary ms-1">{{ \App\Support\Cabinet\Cabinet::dueText($order->due_date) }}</span>
    </span>
@else
    <span class="text-secondary">—</span>
@endif
