@php
    $status = (string) $order->status;
    $classes = [
        'new' => 'bg-purple-lt',
        'working' => 'bg-warning-lt',
        'ready' => 'bg-azure-lt',
        'finished' => 'bg-success-lt',
    ];
@endphp
<span class="badge {{ $classes[$status] ?? 'bg-secondary-lt' }}">{{ \App\Support\Cabinet\Cabinet::enumLabel('status', $status) }}</span>
