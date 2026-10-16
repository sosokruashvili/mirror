<button type="button" class="btn btn-sm btn-primary" id="clientSmsOpen" onclick="openClientSmsModal()">
    <i class="la la-sms"></i> <span>{{ __('client.sms.button') }}</span>
</button>

@push('after_scripts')
@php
    $clientSmsTemplates = \App\Models\SmsTemplate::query()->orderBy('title')->get(['id', 'title', 'text']);
@endphp
<div class="modal fade" id="clientSmsModal" tabindex="-1" aria-labelledby="clientSmsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clientSmsModalLabel">{{ __('client.sms.title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('client.modal.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="client_sms_scope" id="clientSmsScopeSelected" value="selected">
                        <label class="form-check-label" for="clientSmsScopeSelected" id="clientSmsScopeSelectedLabel">{{ __('client.sms.scope_selected', ['count' => 0]) }}</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="client_sms_scope" id="clientSmsScopeFiltered" value="filtered" checked>
                        <label class="form-check-label" for="clientSmsScopeFiltered">{{ __('client.sms.scope_filtered') }}</label>
                    </div>
                    <p id="clientSmsAllWarning" class="text-danger small mb-0 mt-2 d-none">{{ __('client.sms.all_warning') }}</p>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="clientSmsTemplate">{{ __('client.sms.template') }}</label>
                    <select id="clientSmsTemplate" class="form-select">
                        <option value="">{{ __('client.sms.template_empty') }}</option>
                        @foreach ($clientSmsTemplates as $template)
                            <option value="{{ $template->id }}">{{ $template->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="clientSmsBody">{{ __('client.sms.body') }}</label>
                    <textarea id="clientSmsBody" class="form-control" rows="5" maxlength="1000"></textarea>
                    <div class="d-flex justify-content-between">
                        <p class="help-block mb-0">{{ __('client.sms.hint') }}</p>
                        <p class="help-block mb-0" id="clientSmsCount"></p>
                    </div>
                </div>

                <div id="clientSmsError" class="alert alert-danger d-none mb-0" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ trans('backpack::crud.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="clientSmsSubmit">{{ __('client.sms.submit') }}</button>
            </div>
        </div>
    </div>
</div>
<script>
    if (typeof openClientSmsModal !== 'function') {
        var clientSmsTemplates = @json($clientSmsTemplates->mapWithKeys(fn ($template) => [(string) $template->id => $template->text]));
        var clientSmsFilters = @json(\App\Services\Notifications\ClientSmsBroadcast::FILTERS);
        var clientSmsPreviewUrl = @json(route('client.smsPreview'));
        var clientSmsSendUrl = @json(route('client.smsSend'));
        var clientSmsToken = @json(csrf_token());
        var clientSmsCharacters = @json(__('client.sms.characters'));
        var clientSmsSelectedLabel = @json(__('client.sms.scope_selected', ['count' => ':count']));
        var clientSmsSubmitLabel = @json(__('client.sms.submit'));
        var clientSmsWorkingLabel = @json(__('client.sms.working'));
        var clientSmsFailedLabel = @json(__('client.cabinet.sms_failed'));

        function openClientSmsModal() {
            var count = (window.crud && Array.isArray(crud.checkedItems)) ? crud.checkedItems.length : 0;
            var selected = document.getElementById('clientSmsScopeSelected');
            var filtered = document.getElementById('clientSmsScopeFiltered');

            document.getElementById('clientSmsScopeSelectedLabel').textContent = clientSmsSelectedLabel.replace(':count', String(count));
            selected.disabled = count === 0;

            if (count === 0) {
                filtered.checked = true;
            } else {
                selected.checked = true;
            }

            clientSmsShowWarning();
            clientSmsShowError('');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('clientSmsModal')).show();
        }

        function clientSmsSearchValue() {
            var input = document.querySelector('#datatable_search_stack input');

            if (input) {
                return input.value.trim();
            }

            if (window.crud && crud.table && typeof crud.table.search === 'function') {
                return String(crud.table.search() || '').trim();
            }

            return '';
        }

        function clientSmsIsEveryone() {
            var params = new URLSearchParams(window.location.search);
            var narrowed = clientSmsFilters.some(function (name) {
                var value = params.get(name);

                return value !== null && value !== '';
            });

            return !narrowed && clientSmsSearchValue() === '';
        }

        function clientSmsShowWarning() {
            var filtered = document.getElementById('clientSmsScopeFiltered').checked;
            document.getElementById('clientSmsAllWarning').classList.toggle('d-none', !(filtered && clientSmsIsEveryone()));
        }

        function clientSmsShowError(message) {
            var box = document.getElementById('clientSmsError');
            box.textContent = message || '';
            box.classList.toggle('d-none', !message);
        }

        function clientSmsErrorMessage(xhr) {
            var json = xhr.responseJSON || {};

            if (json.errors) {
                var first = Object.values(json.errors)[0];

                if (Array.isArray(first) && first.length) {
                    return first[0];
                }
            }

            return json.message || clientSmsFailedLabel;
        }

        function clientSmsPayload() {
            var template = document.getElementById('clientSmsTemplate').value;
            var scope = document.querySelector('input[name="client_sms_scope"]:checked');

            return {
                _token: clientSmsToken,
                scope: scope ? scope.value : '',
                entries: (window.crud && Array.isArray(crud.checkedItems)) ? crud.checkedItems : [],
                body: document.getElementById('clientSmsBody').value,
                search: clientSmsSearchValue(),
                sms_template_id: template
            };
        }

        function clientSmsPost(url, data) {
            return $.ajax({
                url: url + window.location.search,
                method: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': clientSmsToken,
                    'Accept': 'application/json'
                },
                data: data
            });
        }

        var clientSmsBody = document.getElementById('clientSmsBody');
        var clientSmsCount = document.getElementById('clientSmsCount');
        var clientSmsSubmit = document.getElementById('clientSmsSubmit');

        function clientSmsUpdateCount() {
            clientSmsCount.textContent = clientSmsCharacters.replace(':count', String(clientSmsBody.value.length));
        }

        clientSmsUpdateCount();
        clientSmsBody.addEventListener('input', clientSmsUpdateCount);

        document.getElementById('clientSmsTemplate').addEventListener('change', function () {
            if (this.value && Object.prototype.hasOwnProperty.call(clientSmsTemplates, this.value)) {
                clientSmsBody.value = clientSmsTemplates[this.value];
                clientSmsUpdateCount();
            }
        });

        document.querySelectorAll('input[name="client_sms_scope"]').forEach(function (input) {
            input.addEventListener('change', clientSmsShowWarning);
        });

        clientSmsSubmit.addEventListener('click', function () {
                clientSmsShowError('');
                clientSmsSubmit.disabled = true;
                clientSmsSubmit.textContent = clientSmsWorkingLabel;

                clientSmsPost(clientSmsPreviewUrl, clientSmsPayload()).done(function (result) {
                    swal({
                        title: @json(__('client.sms.title')),
                        text: result.confirm,
                        icon: 'warning',
                        buttons: {
                            cancel: {
                                text: @json(trans('backpack::crud.cancel')),
                                value: null,
                                visible: true,
                                className: 'bg-secondary',
                                closeModal: true
                            },
                            confirm: {
                                text: clientSmsSubmitLabel,
                                value: true,
                                visible: true,
                                className: 'bg-primary'
                            }
                        }
                    }).then(function (yes) {
                        if (!yes) {
                            clientSmsSubmit.disabled = false;
                            clientSmsSubmit.textContent = clientSmsSubmitLabel;
                            return;
                        }

                        clientSmsPost(clientSmsSendUrl, clientSmsPayload()).done(function (sent) {
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('clientSmsModal')).hide();
                            clientSmsBody.value = '';
                            document.getElementById('clientSmsTemplate').value = '';
                            clientSmsUpdateCount();
                            clientSmsSubmit.disabled = false;
                            clientSmsSubmit.textContent = clientSmsSubmitLabel;
                            new Noty({
                                type: 'success',
                                text: sent.message
                            }).show();
                        }).fail(function (xhr) {
                            clientSmsSubmit.disabled = false;
                            clientSmsSubmit.textContent = clientSmsSubmitLabel;
                            clientSmsShowError(clientSmsErrorMessage(xhr));
                        });
                    });
                }).fail(function (xhr) {
                    clientSmsSubmit.disabled = false;
                    clientSmsSubmit.textContent = clientSmsSubmitLabel;
                    clientSmsShowError(clientSmsErrorMessage(xhr));
                });
            });
    }
</script>
@endpush
