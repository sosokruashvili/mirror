<div class="d-inline-flex gap-1" role="group" aria-label="{{ __('locale.change_language') }}">
    @foreach (config('locales.supported') as $code => $meta)
        <form method="post" action="{{ route('locale.switch', $code) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm {{ app()->getLocale() === $code ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $meta['short'] }}</button>
        </form>
    @endforeach
</div>
