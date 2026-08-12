<div class="modal fade" id="pieceBrokenDescModal" tabindex="-1" aria-labelledby="pieceBrokenDescModalLabel" aria-hidden="true"
     data-no-description="{{ __('piece.broken_modal.no_description') }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pieceBrokenDescModalLabel">{{ __('piece.broken_modal.title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('piece.broken_modal.close') }}"></button>
            </div>
            <div class="modal-body">
                <p id="pieceBrokenDescModalBody" class="mb-0 text-break"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('piece.broken_modal.close') }}</button>
            </div>
        </div>
    </div>
</div>

@push('after_styles')
<style>
    .piece-broken-x-btn {
        cursor: pointer;
    }
</style>
@endpush
