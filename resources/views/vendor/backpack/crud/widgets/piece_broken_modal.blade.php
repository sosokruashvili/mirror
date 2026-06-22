<div class="modal fade" id="pieceBrokenDescModal" tabindex="-1" aria-labelledby="pieceBrokenDescModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pieceBrokenDescModalLabel">Broken – Description</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="pieceBrokenDescModalBody" class="mb-0 text-break"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
