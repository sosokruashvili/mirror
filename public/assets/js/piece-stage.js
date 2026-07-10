/**
 * Inline per-piece stage updater.
 *
 * Binds to any `[data-piece-stage-select]` <select> and POSTs the chosen stage
 * to `order/piece/{id}/stage` as soon as it changes, so an admin can advance a
 * piece's production stage (მოჭრა → დასრულება) directly from the order edit
 * form or the order preview page — in any order status.
 *
 * The piece id is read from the element's `data-piece-id` attribute, or, on the
 * edit form, from the sibling hidden `[data-piece-field="id"]` input. When no id
 * is available yet (a brand-new, unsaved piece on the create/edit form) the
 * change is skipped here and persisted with the normal form submit instead.
 */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) { return meta.getAttribute('content'); }
        var input = document.querySelector('input[name="_token"]');
        return input ? input.value : null;
    }

    function stageBaseUrl() {
        // Mirrors the hardcoded admin prefix used elsewhere (order-pieces-services.js).
        return '/admin/order/piece/';
    }

    function pieceIdFor(select) {
        if (select.getAttribute('data-piece-id')) {
            return select.getAttribute('data-piece-id');
        }
        var card = select.closest('[data-piece-card]');
        if (card) {
            var idInput = card.querySelector('[data-piece-field="id"]');
            if (idInput && idInput.value) { return idInput.value; }
        }
        return null;
    }

    function flash(select, ok) {
        var original = select.style.borderColor;
        select.style.borderColor = ok ? '#2fb344' : '#d63939';
        setTimeout(function () { select.style.borderColor = original; }, 1200);
    }

    function onChange(event) {
        var select = event.target;
        if (!select.matches('[data-piece-stage-select]')) { return; }

        var pieceId = pieceIdFor(select);
        if (!pieceId) { return; } // unsaved piece → persisted on form submit

        var token = csrfToken();
        if (!token) { return; }

        select.disabled = true;
        var body = new FormData();
        body.append('_token', token);
        body.append('stage', select.value || '');

        fetch(stageBaseUrl() + encodeURIComponent(pieceId) + '/stage', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: body
        })
            .then(function (res) { return res.json().catch(function () { return {}; }).then(function (d) { return { ok: res.ok, data: d }; }); })
            .then(function (result) {
                flash(select, result.ok && result.data && result.data.success);
            })
            .catch(function () { flash(select, false); })
            .finally(function () { select.disabled = false; });
    }

    document.addEventListener('change', onChange);
})();
