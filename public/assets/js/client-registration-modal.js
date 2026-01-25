document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('clientRegistrationModal');
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
    
    const form = document.getElementById('clientRegistrationForm');
    if (!form) return; // Exit if form doesn't exist
    
    const clientTypeSelect = form.querySelector('select[name="client_type"]');
    const personalIdField = form.querySelector('.personal-id-field');
    const legalIdField = form.querySelector('.legal-id-field');
    const personalIdInput = form.querySelector('input[name="personal_id"]');
    const legalIdInput = form.querySelector('input[name="legal_id"]');
    const errorsDiv = document.getElementById('clientFormErrors');

    // Toggle ID fields based on client type
    function toggleIdFields() {
        if (clientTypeSelect.value == "1") {
            personalIdField.style.display = "none";
            legalIdField.style.display = "block";
            personalIdInput.value = "";
            personalIdInput.removeAttribute("required");
            legalIdInput.setAttribute("required", "required");
        } else {
            personalIdField.style.display = "block";
            legalIdField.style.display = "none";
            legalIdInput.value = "";
            legalIdInput.removeAttribute("required");
            personalIdInput.setAttribute("required", "required");
        }
    }

    clientTypeSelect.addEventListener('change', toggleIdFields);

    // Open modal button handler
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('#newClientBtn');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            form.reset();
            errorsDiv.classList.add('d-none');
            errorsDiv.innerHTML = '';
            toggleIdFields();
            
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

        const actionUrl = form.getAttribute('data-action') || '/admin/client/create-ajax';
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
                // Update select2 field
                const clientSelect = document.querySelector('select[name="client_id"]');
                if (clientSelect) {
                    // Add option if it doesn't exist
                    let option = clientSelect.querySelector(`option[value="${data.client.id}"]`);
                    if (!option) {
                        option = new Option(data.client.name_with_id, data.client.id, true, true);
                        clientSelect.appendChild(option);
                    }
                    
                    // Set value and trigger select2 update
                    clientSelect.value = data.client.id;
                    if (typeof $ !== 'undefined' && $(clientSelect).data('select2')) {
                        $(clientSelect).val(data.client.id).trigger('change');
                    } else {
                        clientSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                
                modal.hide();
                if (typeof new Noty !== 'undefined') {
                    new Noty({ type: 'success', text: 'Client created successfully' }).show();
                }
            } else {
                throw new Error(data.message || 'Failed to create client');
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
