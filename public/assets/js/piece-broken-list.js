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

    // The modal markup carries the translated fallback, so this asset stays
    // language-agnostic.
    function noDescriptionText() {
        var modalEl = document.getElementById('pieceBrokenDescModal');

        return (modalEl && modalEl.getAttribute('data-no-description')) || 'No description provided.';
    }

    function showBrokenDescription(description) {
        var bodyEl = document.getElementById('pieceBrokenDescModalBody');
        var modal = getModal();
        var text = description.trim() !== '' ? description : noDescriptionText();

        if (!bodyEl || !modal) {
            window.alert(text);
            return;
        }

        bodyEl.textContent = text;
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
