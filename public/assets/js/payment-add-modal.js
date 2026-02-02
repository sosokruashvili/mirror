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

    if (clientSelect) {
        clientSelect.addEventListener('change', function() {
            if (this.value) {
                fetchPaymentModalClientBalance(this.value);
            } else {
                hidePaymentModalClientBalance();
            }
        });
    }

    // Pre-fill from order form and set defaults when opening
    function initFormForOpen() {
        const orderClientSelect = document.querySelector('form[action*="order"] select[name="client_id"]');
        if (orderClientSelect && orderClientSelect.value && clientSelect) {
            const opt = clientSelect.querySelector('option[value="' + orderClientSelect.value + '"]');
            if (opt) {
                clientSelect.value = orderClientSelect.value;
            }
        }
        const paymentDateInput = form.querySelector('input[name="payment_date"]');
        if (paymentDateInput) {
            paymentDateInput.value = new Date().toISOString().slice(0, 16);
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

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
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
                modal.hide();
                if (typeof new Noty !== 'undefined') {
                    new Noty({ type: 'success', text: 'Payment created successfully' }).show();
                }
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
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
});
