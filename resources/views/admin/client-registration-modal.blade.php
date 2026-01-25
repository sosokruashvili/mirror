<!-- Client Registration Modal -->
<div class="modal fade" id="clientRegistrationModal" tabindex="-1" aria-labelledby="clientRegistrationModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clientRegistrationModalLabel">New Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="clientRegistrationForm" data-action="{{ url(config('backpack.base.route_prefix', 'admin') . '/client/create-ajax') }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Client Type <span class="text-danger">*</span></label>
                            <select name="client_type" class="form-control" required>
                                <option value="0">Individual</option>
                                <option value="1">Legal</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3 personal-id-field">
                            <label class="form-label">Personal ID <span class="text-danger">*</span></label>
                            <input type="text" name="personal_id" class="form-control" placeholder="Enter personal ID number">
                        </div>
                        
                        <div class="col-md-6 mb-3 legal-id-field" style="display: none;">
                            <label class="form-label">Legal ID <span class="text-danger">*</span></label>
                            <input type="text" name="legal_id" class="form-control" placeholder="Enter legal ID number">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea name="address" class="form-control" rows="2" required></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" name="phone_number" class="form-control" required>
                        </div>
                    </div>
                    <div id="clientFormErrors" class="alert alert-danger d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Client</button>
                </div>
            </form>
        </div>
    </div>
</div>
