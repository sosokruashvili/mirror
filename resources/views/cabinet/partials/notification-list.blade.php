<div class="list-group list-group-flush">
    @forelse ($notifications as $notification)
        <div class="list-group-item">
            <div class="text-secondary small mb-1">{{ $notification->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</div>
            <div>{!! nl2br(e($notification->body)) !!}</div>
        </div>
    @empty
        <div class="list-group-item text-secondary">{{ __('cabinet.empty_notifications') }}</div>
    @endforelse
</div>
