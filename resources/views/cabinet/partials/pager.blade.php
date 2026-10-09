@if ($paginator->hasPages())
    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary">{{ __('cabinet.page') }} {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</p>
        <ul class="pagination m-0 ms-auto">
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                @if ($paginator->onFirstPage())
                    <span class="page-link">{{ __('cabinet.previous') }}</span>
                @else
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}">{{ __('cabinet.previous') }}</a>
                @endif
            </li>
            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                @if ($paginator->hasMorePages())
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}">{{ __('cabinet.next') }}</a>
                @else
                    <span class="page-link">{{ __('cabinet.next') }}</span>
                @endif
            </li>
        </ul>
    </div>
@endif
