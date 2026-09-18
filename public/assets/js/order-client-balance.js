// Client balance next to the order form's client select, plus the history modal.
(function () {
    var controls = document.getElementById('orderClientBalanceControls');
    var modalEl = document.getElementById('clientBalanceModal');
    if (!controls || !modalEl) {
        return;
    }

    var valueEl = document.getElementById('orderClientBalanceValue');
    var buttonEl = document.getElementById('showClientBalanceBtn');
    var loadingEl = document.getElementById('clientBalanceModalLoading');
    var errorEl = document.getElementById('clientBalanceModalError');
    var contentEl = document.getElementById('clientBalanceModalContent');
    var titleEl = document.getElementById('clientBalanceModalLabel');
    var defaultTitle = titleEl ? titleEl.textContent : '';
    var baseUrl = (controls.getAttribute('data-balance-url') || '/admin/order/client-balance').replace(/\/$/, '');
    var attached = false;
    var currentClientId = null;
    var requestSeq = 0;
    var modal = null;
    var emptyTitle = buttonEl ? (buttonEl.getAttribute('title') || '') : '';

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="_token"]')?.value
            || '';
    }

    function loadingText() {
        return controls.getAttribute('data-loading-text') || '...';
    }

    function errorText() {
        return controls.getAttribute('data-error-text') || 'Error';
    }

    function clientSelect() {
        return document.querySelector('form [bp-field-name="client_id"] select[name="client_id"], form select[name="client_id"]');
    }

    function clientFieldWrapper() {
        var select = clientSelect();
        return select ? select.closest('[bp-field-name="client_id"]') : document.querySelector('[bp-field-name="client_id"]');
    }

    function placeControls(requireSelect2) {
        if (attached) {
            return true;
        }

        var field = clientFieldWrapper();
        var select = clientSelect();
        if (!field || !select) {
            return false;
        }

        var select2 = field.querySelector('.select2-container');
        if (requireSelect2 && !select2) {
            return false;
        }

        var row = field.querySelector('.order-client-field-row');
        if (!row) {
            row = document.createElement('div');
            row.className = 'order-client-field-row';

            var nodes = [];
            Array.from(field.children).forEach(function (child) {
                if (child === select || child === select2 || child.classList.contains('select2-container')) {
                    nodes.push(child);
                }
            });

            if (!nodes.length) {
                nodes.push(select);
                if (select2) {
                    nodes.push(select2);
                }
            }

            nodes[0].parentNode.insertBefore(row, nodes[0]);
            nodes.forEach(function (node) {
                row.appendChild(node);
            });
        }

        controls.hidden = false;
        row.appendChild(controls);
        if (select2) {
            select2.style.width = '100%';
        }
        attached = true;
        return true;
    }

    function setValue(text, balance) {
        if (!valueEl) {
            return;
        }

        valueEl.textContent = text;
        valueEl.hidden = !text;
        valueEl.classList.remove('text-success', 'text-danger');
        if (typeof balance === 'number' && !isNaN(balance)) {
            valueEl.classList.add(balance >= 0 ? 'text-success' : 'text-danger');
        }
    }

    function clearValue() {
        currentClientId = null;
        if (buttonEl) {
            buttonEl.disabled = true;
            buttonEl.setAttribute('title', emptyTitle);
        }
        setValue('', null);
        if (valueEl) {
            valueEl.hidden = true;
        }
    }

    function selectedClientId() {
        var select = clientSelect();
        return select && select.value ? String(select.value) : '';
    }

    function fetchJson(url) {
        return fetch(url, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok) {
                    throw new Error(data.error || response.statusText);
                }
                return data;
            }, function () {
                throw new Error(errorText());
            });
        });
    }

    function refreshBalance() {
        var clientId = selectedClientId();
        if (!clientId) {
            clearValue();
            return;
        }

        currentClientId = clientId;
        if (buttonEl) {
            buttonEl.disabled = false;
            buttonEl.removeAttribute('title');
        }
        var seq = ++requestSeq;
        setValue(loadingText(), null);

        fetchJson(baseUrl + '/' + encodeURIComponent(clientId))
            .then(function (data) {
                if (seq !== requestSeq || currentClientId !== clientId) {
                    return;
                }
                setValue(data.formatted || String(data.balance), parseFloat(data.balance));
            })
            .catch(function () {
                if (seq !== requestSeq || currentClientId !== clientId) {
                    return;
                }
                setValue(errorText(), null);
            });
    }

    function showModalState(state) {
        if (loadingEl) {
            loadingEl.classList.toggle('d-none', state !== 'loading');
        }
        if (errorEl) {
            errorEl.classList.toggle('d-none', state !== 'error');
        }
        if (contentEl && state !== 'content') {
            contentEl.innerHTML = '';
        }
    }

    function openModal() {
        var clientId = selectedClientId();
        if (!clientId) {
            return;
        }

        if (!modal) {
            modal = new bootstrap.Modal(modalEl, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
        }

        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        if (titleEl) {
            titleEl.textContent = defaultTitle;
        }
        if (errorEl) {
            errorEl.textContent = '';
        }
        showModalState('loading');
        modal.show();

        fetchJson(baseUrl + '/' + encodeURIComponent(clientId) + '/details')
            .then(function (data) {
                if (titleEl) {
                    titleEl.textContent = data.client_name
                        ? defaultTitle + ' — ' + data.client_name
                        : defaultTitle;
                }
                if (data.formatted) {
                    setValue(data.formatted, parseFloat(data.balance));
                }
                showModalState('content');
                if (contentEl) {
                    contentEl.innerHTML = data.html || '';
                }
            })
            .catch(function (error) {
                showModalState('error');
                if (errorEl) {
                    errorEl.textContent = error.message || errorEl.getAttribute('data-fallback-error') || errorText();
                    errorEl.classList.remove('d-none');
                }
            });
    }

    function bindClientChange() {
        if (typeof crud !== 'undefined' && typeof crud.field === 'function') {
            try {
                crud.field('client_id').onChange(function (field) {
                    if (field.value) {
                        refreshBalance();
                    } else {
                        clearValue();
                    }
                });
                return;
            } catch (e) {
                // Fall through to the native change listener.
            }
        }

        var select = clientSelect();
        if (!select || typeof $ === 'undefined') {
            return;
        }

        $(select).on('change.select2 select2:select select2:clear', function () {
            refreshBalance();
        });
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('#showClientBalanceBtn');
        if (!btn || btn.disabled) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        openModal();
    });

    function boot() {
        var attempts = 0;
        (function waitForSelect2() {
            attempts += 1;
            if (placeControls(true) || (attempts > 40 && placeControls(false))) {
                bindClientChange();
                refreshBalance();
                return;
            }
            setTimeout(waitForSelect2, 50);
        })();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
