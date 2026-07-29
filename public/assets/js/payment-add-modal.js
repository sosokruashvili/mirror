document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('paymentAddModal');
    if (!modalEl) return; // Exit if modal doesn't exist on this page

    // Initialize modal with proper configuration
    const modal = new bootstrap.Modal(modalEl, {
        backdrop: true,
        keyboard: true,
        focus: true
    });

    // Ensure modal is appended to body (Bootstrap requirement)
    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    const form = document.getElementById('paymentAddForm');
    if (!form) return; // Exit if form doesn't exist

    const errorsDiv = document.getElementById('paymentFormErrors');
    const clientSelect = form.querySelector('select[name="client_id"]');
    const balanceDisplay = document.getElementById('paymentAddModal_balance_display');
    const balanceValue = document.getElementById('paymentAddModal_balance_value');
    const balanceFormControl = balanceDisplay ? balanceDisplay.querySelector('.form-control') : null;

    function fetchPaymentModalClientBalance(clientId) {
        if (!clientId || !balanceDisplay || !balanceValue) return;
        const baseUrl = form.getAttribute('data-balance-url') || (window.location.origin + '/admin/payment/get-client-balance');
        const url = baseUrl + (baseUrl.endsWith('/') ? '' : '/') + clientId;

        balanceDisplay.style.display = '';
        balanceValue.textContent = 'Loading...';
        if (balanceFormControl) {
            balanceFormControl.classList.remove('text-success', 'text-danger');
        }

        fetch(url, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                               document.querySelector('input[name="_token"]')?.value,
                'Accept': 'application/json'
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var balance = parseFloat(data.balance);
            var formatted = data.formatted || (isNaN(balance) ? '-' : balance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' ₾');
            balanceValue.textContent = formatted;
            if (balanceFormControl) {
                balanceFormControl.classList.remove('text-success', 'text-danger');
                balanceFormControl.classList.add(balance >= 0 ? 'text-success' : 'text-danger');
            }
            balanceDisplay.style.display = '';
        })
        .catch(function() {
            balanceValue.textContent = 'Error loading balance';
            if (balanceFormControl) balanceFormControl.classList.remove('text-success', 'text-danger');
            balanceDisplay.style.display = '';
        });
    }

    function hidePaymentModalClientBalance() {
        if (balanceDisplay) balanceDisplay.style.display = 'none';
        if (balanceValue) balanceValue.textContent = '-';
        if (balanceFormControl) balanceFormControl.classList.remove('text-success', 'text-danger');
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.content;
        const input = document.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    // Toast helper. Silently skips if Noty is not on the page — the previous inline
    // check (`typeof new Noty !== 'undefined'`) constructed a Noty in order to test for
    // it, so a missing Noty threw and turned a successful payment into a visible error.
    function notify(type, text) {
        if (typeof Noty === 'undefined') return;
        new Noty({ type: type, text: text, timeout: 3000 }).show();
    }

    /* ------------------------------------------------------------- payments list */
    // The payments already on this order, rendered next to the Add Payment button.
    // Existing ones come from the field view as JSON; ones created through the modal are
    // appended as soon as the server confirms them. Before this list the page looked
    // exactly the same before and after adding a payment, so users who wanted to correct
    // a wrong method or status simply added a second payment.

    const paymentsField = document.getElementById('orderPaymentsField');
    const paymentsList = document.getElementById('orderPaymentsList');
    const paymentsEmpty = document.getElementById('orderPaymentsEmpty');
    const paymentBaseUrl = paymentsField ? paymentsField.getAttribute('data-payment-url') : '';
    const canUpdatePayments = !!paymentsField && paymentsField.getAttribute('data-can-update') === '1';
    const canDeletePayments = !!paymentsField && paymentsField.getAttribute('data-can-delete') === '1';

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : value;
        return div.innerHTML;
    }

    function formatAmountGel(value) {
        const amount = parseFloat(value);
        if (isNaN(amount)) return '-';
        return amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' ₾';
    }

    function updatePaymentsEmptyState() {
        if (!paymentsList) return;
        const hasRows = paymentsList.querySelector('[data-payment-row]') !== null;
        if (paymentsEmpty) paymentsEmpty.style.display = hasRows ? 'none' : '';
        // Only rendered for users who may not edit/delete payments themselves.
        const readonlyNote = document.getElementById('orderPaymentsReadonlyNote');
        if (readonlyNote) readonlyNote.style.display = hasRows ? '' : 'none';
    }

    function hasPaymentRow(id) {
        return !!paymentsList && paymentsList.querySelector('[data-payment-row][data-payment-id="' + id + '"]') !== null;
    }

    function addPaymentRow(payment, isNew) {
        if (!paymentsList || !payment || !payment.id || hasPaymentRow(payment.id)) return;

        const badgeClass = payment.status === 'Paid' ? 'bg-success' : 'bg-warning text-dark';

        let actions = '';
        if (canUpdatePayments) {
            actions += '<a href="' + paymentBaseUrl + '/' + encodeURIComponent(payment.id) + '/edit"' +
                ' target="_blank" rel="noopener" class="btn btn-outline-secondary" title="Edit payment">' +
                '<i class="la la-edit"></i></a>';
        }
        if (canDeletePayments) {
            actions += '<button type="button" class="btn btn-outline-danger" data-delete-payment' +
                ' title="Delete payment"><i class="la la-trash"></i></button>';
        }

        const row = document.createElement('div');
        row.className = 'list-group-item d-flex justify-content-between align-items-center py-2';
        row.setAttribute('data-payment-row', '');
        row.setAttribute('data-payment-id', payment.id);
        row.innerHTML =
            '<div>' +
                '<span class="fw-bold">' + formatAmountGel(payment.amount_gel) + '</span> ' +
                '<span class="badge ' + badgeClass + '">' + escapeHtml(payment.status) + '</span> ' +
                '<span class="text-muted small">' + escapeHtml(payment.method) +
                    (payment.payment_date ? ' · ' + escapeHtml(payment.payment_date) : '') + '</span>' +
                (isNew ? ' <span class="badge bg-info">just added</span>' : '') +
            '</div>' +
            (actions ? '<div class="btn-group btn-group-sm">' + actions + '</div>' : '');

        paymentsList.appendChild(row);
        updatePaymentsEmptyState();
    }

    // Hydrate the list with the payments already stored on this order (edit page).
    if (paymentsField && paymentsList) {
        let initialPayments = [];
        try {
            initialPayments = JSON.parse(paymentsField.getAttribute('data-payments') || '[]');
        } catch (e) {
            initialPayments = [];
        }
        initialPayments.forEach(function(payment) { addPaymentRow(payment, false); });
        updatePaymentsEmptyState();
    }

    // Deleting a payment straight from the order form, so correcting one never means
    // "add another and remember to delete the old one".
    if (paymentsList) {
        paymentsList.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-delete-payment]');
            if (!btn) return;

            const row = btn.closest('[data-payment-row]');
            const paymentId = row ? row.getAttribute('data-payment-id') : null;
            if (!paymentId) return;

            if (!window.confirm('Delete this payment? This cannot be undone.')) return;

            btn.disabled = true;
            fetch(paymentBaseUrl + '/' + encodeURIComponent(paymentId), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
                // A denied delete redirects to the dashboard, which fetch follows and
                // reports as a perfectly fine 200 — the row must not disappear for that.
                if (response.redirected) throw new Error('You are not allowed to delete this payment');
                if (!response.ok) throw new Error('Failed to delete payment');
                row.remove();
                // A payment created on the order create page is linked by a hidden input
                // on the order form; drop it too or store() would try to link a deleted row.
                const pendingLink = document.querySelector(
                    'input[name="created_payment_ids[]"][data-payment-id="' + paymentId + '"]'
                );
                if (pendingLink) pendingLink.remove();
                updatePaymentsEmptyState();
                notify('success', 'Payment deleted');
            })
            .catch(function(error) {
                btn.disabled = false;
                notify('error', error.message || 'Failed to delete payment');
            });
        });
    }

    if (clientSelect) {
        clientSelect.addEventListener('change', function() {
            if (this.value) {
                fetchPaymentModalClientBalance(this.value);
            } else {
                hidePaymentModalClientBalance();
            }
        });
    }

    // Current time formatted for a datetime-local input, in the application timezone.
    // toISOString() was used here before, which emits UTC: on a correctly configured
    // workstation every payment opened through this modal defaulted to 4 hours behind
    // the Tbilisi wall clock. Formatting through the app timezone keeps the default
    // right regardless of what timezone the workstation itself is set to.
    function appNowForDatetimeLocal() {
        const now = new Date();
        const timezone = modalEl.dataset.appTimezone;
        if (timezone) {
            try {
                const parts = new Intl.DateTimeFormat('en-CA', {
                    timeZone: timezone,
                    year: 'numeric', month: '2-digit', day: '2-digit',
                    hour: '2-digit', minute: '2-digit', hour12: false
                }).formatToParts(now).reduce(function(acc, part) {
                    acc[part.type] = part.value;
                    return acc;
                }, {});
                // Some engines render midnight as hour 24; datetime-local rejects it.
                const hour = parts.hour === '24' ? '00' : parts.hour;
                return parts.year + '-' + parts.month + '-' + parts.day + 'T' + hour + ':' + parts.minute;
            } catch (e) {
                // Unknown timezone id or no Intl support: fall through to local time.
            }
        }
        const pad = function(n) { return String(n).padStart(2, '0'); };
        return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) +
            'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    }

    // Generate a token unique to this modal open. Sent with the create request so
    // the server can reject a duplicate submission (double-click, retry, session
    // re-POST) at the database level instead of inserting a second payment.
    function generateIdempotencyKey() {
        const field = form.querySelector('input[name="idempotency_key"]');
        if (!field) return;
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            field.value = window.crypto.randomUUID();
        } else {
            field.value = 'p-' + Date.now() + '-' + Math.random().toString(36).slice(2) +
                Math.random().toString(36).slice(2);
        }
    }

    // Pre-fill from order form and set defaults when opening
    function initFormForOpen() {
        generateIdempotencyKey();
        const orderClientSelect = document.querySelector('form[action*="order"] select[name="client_id"]');
        if (orderClientSelect && orderClientSelect.value && clientSelect) {
            const opt = clientSelect.querySelector('option[value="' + orderClientSelect.value + '"]');
            if (opt) {
                clientSelect.value = orderClientSelect.value;
            }
        }
        const paymentDateInput = form.querySelector('input[name="payment_date"]');
        if (paymentDateInput) {
            paymentDateInput.value = appNowForDatetimeLocal();
        }
        if (clientSelect && clientSelect.value) {
            fetchPaymentModalClientBalance(clientSelect.value);
        } else {
            hidePaymentModalClientBalance();
        }
    }

    // Open modal button handler
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('#addPaymentBtn');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            form.reset();
            errorsDiv.classList.add('d-none');
            errorsDiv.innerHTML = '';
            hidePaymentModalClientBalance();
            initFormForOpen();

            // Show modal
            modal.show();

            // Fix z-index after modal is shown
            modalEl.addEventListener('shown.bs.modal', function() {
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.style.zIndex = '1050';
                }
                const modalDialog = modalEl.querySelector('.modal-dialog');
                if (modalDialog) {
                    modalDialog.style.zIndex = '1055';
                }
                modalEl.style.zIndex = '1055';
            }, { once: true });
        }
    });

    // Guards against a second POST while one is already in flight. Disabling the
    // submit button is not enough on its own: a queued double-click or an Enter
    // keypress can fire another `submit` event before the disable takes effect,
    // which previously created duplicate payments.
    let isSubmitting = false;

    // Blocks implicit submission: Enter in any input (or on a focused select, which
    // submits in Chrome) used to save the payment mid-way through filling the form, with
    // whatever the untouched fields happened to hold. Only the button submits now.
    form.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        const target = e.target;
        if (target.tagName === 'TEXTAREA') return;
        if (target.tagName === 'BUTTON' && target.type === 'submit') return;
        e.preventDefault();
    });

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (isSubmitting) return;
        isSubmitting = true;
        errorsDiv.classList.add('d-none');
        errorsDiv.innerHTML = '';

        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating...';

        const actionUrl = form.getAttribute('data-action') || '/admin/payment/create-ajax';
        fetch(actionUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                               document.querySelector('input[name="_token"]')?.value,
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => Promise.reject(err));
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const payment = data.payment || {};

                // On the create page the order does not exist yet, so the payment cannot be
                // linked at creation time. Remember the new payment id in a hidden field on the
                // order form; OrderCrudController::store() attaches it to the order once saved.
                const orderIdField = form.querySelector('input[name="order_id"]');
                const alreadyLinked = orderIdField && orderIdField.value;
                if (!alreadyLinked && payment.id) {
                    const orderForm = document.querySelector('form[action*="/order"]');
                    const alreadyPending = document.querySelector(
                        'input[name="created_payment_ids[]"][data-payment-id="' + payment.id + '"]'
                    );
                    if (orderForm && !alreadyPending) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'created_payment_ids[]';
                        hidden.value = payment.id;
                        hidden.setAttribute('data-payment-id', payment.id);
                        orderForm.appendChild(hidden);
                    }
                }

                // Show it in the list right away — this is the only lasting sign on the page
                // that the payment exists. addPaymentRow() ignores ids it already shows, so a
                // response served from the idempotency guard cannot add the row twice.
                addPaymentRow(payment, true);

                modal.hide();
                notify('success', 'Payment created successfully');
            } else {
                throw new Error(data.message || 'Failed to create payment');
            }
        })
        .catch(error => {
            let errorMessage = error.message || 'An error occurred';
            if (error.errors) {
                const errorList = Object.values(error.errors).flat().join('<br>');
                errorsDiv.innerHTML = errorList;
            } else {
                errorsDiv.innerHTML = error.message || errorMessage;
            }
            errorsDiv.classList.remove('d-none');
        })
        .finally(() => {
            isSubmitting = false;
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
});
