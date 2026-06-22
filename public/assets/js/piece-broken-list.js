(function () {
    function getModal() {
        var modalEl = document.getElementById('pieceBrokenDescModal');
        if (!modalEl) {
            return null;
        }

        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return null;
        }

        return bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function showBrokenDescription(description) {
        var bodyEl = document.getElementById('pieceBrokenDescModalBody');
        var modal = getModal();

        if (!bodyEl || !modal) {
            window.alert(description.trim() !== '' ? description : 'No description provided.');
            return;
        }

        bodyEl.textContent = description.trim() !== '' ? description : 'No description provided.';
        modal.show();
    }

    jQuery(document).ready(function ($) {
        $(document).on('click', '.piece-broken-x-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            showBrokenDescription($(this).attr('data-description') || '');
        });

        $(document).on('keydown', '.piece-broken-x-btn', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                showBrokenDescription($(this).attr('data-description') || '');
            }
        });
    });
})();
