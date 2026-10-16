@php
    $field['wrapper'] = $field['wrapper'] ?? $field['wrapperAttributes'] ?? [];
    $field['wrapper']['data-init-function'] = $field['wrapper']['data-init-function'] ?? 'bpFieldInitUploadElement';
    $field['wrapper']['data-field-name'] = $field['wrapper']['data-field-name'] ?? $field['name'];
    if (!empty($field['paste'])) {
        $field['wrapper']['data-paste'] = '1';
        $field['wrapper']['data-paste-label'] = __('order.attachment_paste_label');
        $field['wrapper']['data-paste-sub'] = __('order.attachment_paste_sub');
        $field['wrapper']['data-paste-replace'] = __('order.attachment_paste_replace');
        $field['wrapper']['data-paste-clear'] = __('order.attachment_paste_clear');
        $field['wrapper']['data-paste-empty'] = __('order.attachment_paste_empty');
        $field['wrapper']['data-paste-blocked'] = __('order.attachment_paste_blocked');
        $field['wrapper']['data-paste-type'] = __('order.attachment_paste_type');
    }
@endphp

{{-- text input --}}
@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    @include('crud::fields.inc.translatable_icon')

	{{-- Show the file name and a "Clear" button on EDIT form. --}}
    @if (!empty($field['value']))
    <div class="existing-file">
        @if (isset($field['disk']))
        @if (isset($field['temporary']))
            <a target="_blank" href="{{ (asset(\Storage::disk($field['disk'])->temporaryUrl(Arr::get($field, 'prefix', '').$field['value'], Carbon\Carbon::now()->addMinutes($field['temporary'])))) }}">
        @else
            <a target="_blank" href="{{ (asset(\Storage::disk($field['disk'])->url(Arr::get($field, 'prefix', '').$field['value']))) }}">
        @endif
        @else
            <a target="_blank" href="{{ (asset(Arr::get($field, 'prefix', '').$field['value'])) }}">
        @endif
            {{ $field['value'] }}
        </a>
    	<a href="#" class="file_clear_button btn btn-light btn-sm float-right" title="Clear file" data-filename="{{ $field['value'] }}"><i class="la la-remove"></i></a>
    	<div class="clearfix"></div>
    </div>
    @endif

	{{-- Show the file picker on CREATE form. --}}
    {{-- Paste fields keep the zone visible even when a file is already attached. --}}
    <div class="backstrap-file {{ empty($field['paste']) && isset($field['value']) && $field['value']!=null ? 'd-none' : '' }}">
        <input
            type="file"
            name="{{ $field['name'] }}"
            data-filename="{{ $field['value'] ?? '' }}"
            @include('crud::fields.inc.attributes', ['default_class' => 'file_input backstrap-file-input'])
        >
        <label class="backstrap-file-label" for="customFile">
            @if (!empty($field['paste']))
                <span class="attachment-paste-callout">
                    <i class="la la-cloud-upload attachment-paste-icon" aria-hidden="true"></i>
                    <span class="attachment-paste-title">{{ __('order.attachment_paste_label') }}</span>
                    <span class="attachment-paste-sub">{{ __('order.attachment_paste_sub') }}</span>
                    <span class="attachment-paste-actions">
                        <button type="button" class="btn btn-primary attachment-browse-button">{{ __('order.attachment_paste_browse') }}</button>
                        <button type="button" class="btn btn-primary attachment-paste-button">{{ __('order.attachment_paste_button') }}</button>
                    </span>
                    <span class="attachment-paste-status d-none" role="status"></span>
                </span>
            @endif
        </label>
    </div>

    @if (!empty($field['paste']))
        <div class="attachment-paste-preview d-none">
            <img alt="" class="d-none">
            <span class="attachment-paste-name"></span>
            <button type="button" class="btn btn-light btn-sm attachment-paste-clear" title="{{ __('order.attachment_paste_clear') }}">
                <i class="la la-times"></i>
            </button>
        </div>
    @endif

    {{-- HINT --}}
    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')



{{-- ########################################## --}}
{{-- Extra CSS and JS for this particular field --}}
{{-- If a field type is shown multiple times on a form, the CSS and JS will only be loaded once --}}

@push('crud_fields_styles')
  @bassetBlock('backpack/crud/fields/upload-field.css')
    <style type="text/css">
        .existing-file {
            border: 1px solid rgba(0,40,100,.12);
            border-radius: 5px;
            padding-left: 10px;
            vertical-align: middle;
        }
        .existing-file a {
            padding-top: 5px;
            display: inline-block;
            font-size: 0.9em;
        }
        .backstrap-file {
          position: relative;
          display: inline-block;
          width: 100%;
          height: calc(1.5em + 0.75rem + 2px);
          margin-bottom: 0;
        }

        .backstrap-file-input {
          position: relative;
          z-index: 2;
          width: 100%;
          height: calc(1.5em + 0.75rem + 2px);
          margin: 0;
          opacity: 0;
        }

        .backstrap-file-input:focus ~ .backstrap-file-label {
          border-color: #acc5ea;
          box-shadow: 0 0 0 0rem rgba(70, 127, 208, 0.25);
        }

        .backstrap-file-input:disabled ~ .backstrap-file-label {
          background-color: #e4e7ea;
        }

        .backstrap-file-input:lang(en) ~ .backstrap-file-label::after {
          content: "Browse";
        }

        .backstrap-file-input ~ .backstrap-file-label[data-browse]::after {
          content: attr(data-browse);
        }

        .backstrap-file-label {
          position: absolute;
          top: 0;
          right: 0;
          left: 0;
          z-index: 1;
          height: calc(1.5em + 0.75rem + 2px);
          padding: 0.375rem 0.75rem;
          font-weight: 400;
          line-height: 1.5;
          color: #5c6873;
          background-color: #fff;
          border: 1px solid #e4e7ea;
          border-radius: 0.25rem;
          font-weight: 400!important;
        }

        .backstrap-file-label::after {
          position: absolute;
          top: 0;
          right: 0;
          bottom: 0;
          z-index: 3;
          display: block;
          height: calc(1.5em + 0.75rem);
          padding: 0.375rem 0.75rem;
          line-height: 1.5;
          color: #5c6873;
          content: "Browse";
          background-color: #f0f3f9;
          border-left: inherit;
          border-radius: 0 0.25rem 0.25rem 0;
        }

        [data-paste="1"] .existing-file {
          margin-bottom: 10px;
        }

        [data-paste="1"] .backstrap-file {
          height: auto;
          min-height: 0;
        }

        [data-paste="1"] .backstrap-file-input {
          position: absolute;
          width: 1px;
          height: 1px;
          padding: 0;
          margin: -1px;
          overflow: hidden;
          clip: rect(0, 0, 0, 0);
          white-space: nowrap;
          border: 0;
          opacity: 0;
          pointer-events: none;
        }

        [data-paste="1"] .backstrap-file-label,
        [data-paste="1"] .backstrap-file-input:lang(en) ~ .backstrap-file-label {
          position: relative;
          display: flex;
          align-items: center;
          justify-content: center;
          height: auto;
          min-height: 168px;
          padding: 22px 18px;
          white-space: normal;
          text-align: center;
          color: #1a4f8b;
          background: rgba(32, 107, 196, 0.08);
          border: 2px dashed #206bc4;
          border-radius: 12px;
          cursor: default;
        }

        [data-paste="1"] .backstrap-file-label::after,
        [data-paste="1"] .backstrap-file-input:lang(en) ~ .backstrap-file-label::after {
          content: none !important;
          display: none !important;
        }

        [data-paste="1"]:focus-within .backstrap-file-label {
          border-color: #206bc4;
          box-shadow: 0 0 0 4px rgba(32, 107, 196, 0.22);
        }

        [data-paste="1"][data-paste-filled="1"] .backstrap-file-label {
          border-style: solid;
          background: rgba(32, 107, 196, 0.12);
        }

        [data-paste="1"] .backstrap-file.is-dragover .backstrap-file-label {
          border-style: solid;
          background: rgba(32, 107, 196, 0.2);
          box-shadow: 0 0 0 4px rgba(32, 107, 196, 0.22);
        }

        .attachment-paste-callout {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 6px;
          width: 100%;
          pointer-events: none;
        }

        .attachment-paste-icon {
          font-size: 36px;
          line-height: 1;
          color: #206bc4;
        }

        .attachment-paste-actions {
          display: flex;
          justify-content: center;
          flex-wrap: wrap;
          gap: 12px;
          margin-top: 6px;
          pointer-events: auto;
        }

        .attachment-paste-actions .btn {
          min-width: 132px;
          padding: 0.5rem 1.25rem;
          font-size: 1rem;
          font-weight: 700;
          line-height: 1.5;
        }

        .attachment-paste-title {
          max-width: 100%;
          overflow-wrap: anywhere;
          font-size: 1.2rem;
          font-weight: 700;
          line-height: 1.3;
          color: #163e6b;
        }

        .attachment-paste-sub {
          font-size: 0.95rem;
          line-height: 1.4;
          color: #3d5a80;
        }

        .attachment-paste-sub:empty {
          display: none;
        }

        .attachment-paste-status {
          min-height: 1.2em;
          font-size: 0.85rem;
          line-height: 1.3;
          color: #b42318;
        }

        [data-bs-theme=dark] [data-paste="1"] .attachment-paste-status {
          color: #ffb4b4;
        }

        [data-bs-theme=dark] [data-paste="1"] .backstrap-file-label,
        [data-bs-theme=dark] [data-paste="1"] .backstrap-file-input:lang(en) ~ .backstrap-file-label {
          color: #d7e6ff;
          background: rgba(32, 107, 196, 0.2);
          border-color: #7aafff;
        }

        [data-bs-theme=dark] [data-paste="1"] .backstrap-file.is-dragover .backstrap-file-label {
          background: rgba(32, 107, 196, 0.34);
          border-color: #9ec5fe;
        }

        [data-bs-theme=dark] [data-paste="1"] .attachment-paste-icon,
        [data-bs-theme=dark] [data-paste="1"] .attachment-paste-title {
          color: #e7f0ff;
        }

        [data-bs-theme=dark] [data-paste="1"] .attachment-paste-sub {
          color: #b9d4ff;
        }

        .attachment-paste-preview {
          display: flex;
          align-items: center;
          gap: 10px;
          margin-top: 8px;
          border: 1px solid rgba(0,40,100,.12);
          border-radius: 5px;
          padding: 6px 10px;
        }

        .attachment-paste-preview img {
          max-height: 64px;
          max-width: 96px;
          object-fit: contain;
          border-radius: 3px;
          background: #f8fafc;
        }

        .attachment-paste-name {
          flex: 1;
          min-width: 0;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
          font-size: 0.9em;
        }

        .attachment-paste-clear {
          flex: none;
        }
    </style>
  @endBassetBlock
@endpush

@push('crud_fields_scripts')
  @bassetBlock('backpack/crud/fields/upload-field.js')
    <script>
        function bpAttachmentFileFromBlob(fileBlob) {
            if (!fileBlob) {
                return null;
            }

            var allowed = {
                'image/png': 'png',
                'image/jpeg': 'jpg',
                'image/jpg': 'jpg',
                'application/pdf': 'pdf'
            };
            var type = (fileBlob.type || '').toLowerCase();
            var ext = allowed[type] || '';

            if (!ext) {
                var fromName = ((fileBlob.name || '').split('.').pop() || '').toLowerCase();
                if (fromName === 'jpeg') {
                    fromName = 'jpg';
                }
                if (fromName === 'png' || fromName === 'jpg' || fromName === 'pdf') {
                    ext = fromName;
                    type = ext === 'pdf' ? 'application/pdf' : (ext === 'png' ? 'image/png' : 'image/jpeg');
                }
            }

            if (!ext) {
                return null;
            }

            var original = fileBlob.name || '';
            var generic = !original || /^(image\.(png|jpe?g)|blob)$/i.test(original);
            var stamp = new Date();
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            var filename = generic
                ? 'screenshot-' + stamp.getFullYear() + pad(stamp.getMonth() + 1) + pad(stamp.getDate()) + '-' + pad(stamp.getHours()) + pad(stamp.getMinutes()) + pad(stamp.getSeconds()) + '-' + stamp.getMilliseconds() + '.' + ext
                : original;
            var mime = type === 'image/jpg' ? 'image/jpeg' : type;

            return new File([fileBlob], filename, { type: mime });
        }

        function bpFirstAllowedAttachmentFile(fileList) {
            if (!fileList) {
                return null;
            }
            for (var i = 0; i < fileList.length; i++) {
                var file = bpAttachmentFileFromBlob(fileList[i]);
                if (file) {
                    return file;
                }
            }
            return null;
        }

        // Clipboard images/PDFs allowed on the order attachment. Matches OrderRequest mimes.
        function bpAttachmentFileFromClipboard(clipboard) {
            if (!clipboard) {
                return null;
            }

            var blobs = [];
            var items = clipboard.items;

            if (items && items.length) {
                for (var i = 0; i < items.length; i++) {
                    if (items[i].kind !== 'file') {
                        continue;
                    }
                    var itemFile = items[i].getAsFile();
                    if (itemFile) {
                        blobs.push(itemFile);
                    }
                }
            }

            if (!blobs.length && clipboard.files && clipboard.files.length) {
                for (var j = 0; j < clipboard.files.length; j++) {
                    blobs.push(clipboard.files[j]);
                }
            }

            for (var k = 0; k < blobs.length; k++) {
                var file = bpAttachmentFileFromBlob(blobs[k]);
                if (file) {
                    return file;
                }
            }

            return null;
        }

        function bpRenderAttachmentPreview(element, file) {
            var box = element.find('.attachment-paste-preview');
            var img = box.find('img');
            var previous = element.data('pastePreviewUrl');

            if (previous) {
                URL.revokeObjectURL(previous);
                element.removeData('pastePreviewUrl');
            }

            box.find('.attachment-paste-name').text(file.name);

            if (file.type && file.type.indexOf('image/') === 0) {
                var url = URL.createObjectURL(file);
                element.data('pastePreviewUrl', url);
                img.attr('src', url).attr('alt', file.name).removeClass('d-none');
            } else {
                img.attr('src', '').attr('alt', '').addClass('d-none');
            }

            box.removeClass('d-none');
        }

        function bpHiddenAttachmentInputs(element, fieldName) {
            return element.find('input[type="hidden"]').filter(function () {
                return this.name === fieldName;
            });
        }

        function bpSetAttachmentPasteStatus(element, message) {
            var status = element.find('.attachment-paste-status');
            if (!status.length) {
                return;
            }
            status.text(message || '');
            status.toggleClass('d-none', !message);
        }

        function bpReadClipboardAttachment(element) {
            var button = element.find('.attachment-paste-button');
            bpSetAttachmentPasteStatus(element, '');

            if (!navigator.clipboard || typeof navigator.clipboard.read !== 'function') {
                bpSetAttachmentPasteStatus(element, element.attr('data-paste-blocked') || '');
                return;
            }

            button.prop('disabled', true);
            navigator.clipboard.read().then(function (items) {
                var allowed = {
                    'image/png': true,
                    'image/jpeg': true,
                    'image/jpg': true,
                    'application/pdf': true
                };
                var chosen = null;
                var mime = '';

                for (var i = 0; i < items.length && !chosen; i++) {
                    var types = items[i].types || [];
                    for (var t = 0; t < types.length; t++) {
                        if (allowed[String(types[t] || '').toLowerCase()]) {
                            chosen = items[i];
                            mime = types[t];
                            break;
                        }
                    }
                }

                if (!chosen) {
                    bpSetAttachmentPasteStatus(element, element.attr('data-paste-empty') || '');
                    return null;
                }

                return chosen.getType(mime).then(function (blob) {
                    if (!blob.type) {
                        blob = new Blob([blob], { type: mime });
                    }
                    var file = bpAttachmentFileFromBlob(blob);
                    if (!file) {
                        bpSetAttachmentPasteStatus(element, element.attr('data-paste-empty') || '');
                        return;
                    }
                    bpApplyOrderAttachmentPaste(element, file);
                });
            }).catch(function () {
                bpSetAttachmentPasteStatus(element, element.attr('data-paste-blocked') || '');
            }).then(function () {
                button.prop('disabled', false);
            });
        }

        function bpApplyOrderAttachmentPaste(element, file) {
            var fieldName = element.attr('data-field-name');
            var input = element.find('.file_input');

            if (!input.length || typeof DataTransfer === 'undefined') {
                return;
            }

            bpSetAttachmentPasteStatus(element, '');

            var picker = element.find('.backstrap-file');
            var pickerHidden = picker.hasClass('d-none');
            picker.removeClass('d-none');

            var transfer = new DataTransfer();
            transfer.items.add(file);
            input[0].files = transfer.files;

            if (!input[0].files || !input[0].files.length) {
                if (pickerHidden) {
                    picker.addClass('d-none');
                }
                return;
            }

            var existing = element.find('.existing-file');
            var existingVisible = existing.length > 0 && !existing.hasClass('d-none');

            element.attr('data-hid-existing', existingVisible ? '1' : '0');
            element.attr('data-had-clear', bpHiddenAttachmentInputs(element, fieldName).length ? '1' : '0');

            if (existingVisible) {
                existing.addClass('d-none');
            }

            input.trigger('change');
        }

        function bpSetAttachmentPasteCallout(element, filename) {
            var title = element.find('.attachment-paste-title');
            var sub = element.find('.attachment-paste-sub');

            if (!title.length) {
                return;
            }

            if (filename) {
                element.attr('data-paste-filled', '1');
                title.text(filename);
                sub.text(element.attr('data-paste-replace') || '');
            } else {
                element.removeAttr('data-paste-filled');
                title.text(element.attr('data-paste-label') || '');
                sub.text(element.attr('data-paste-sub') || '');
            }
        }

        function bpClearOrderAttachmentPaste(element) {
            var fieldName = element.attr('data-field-name');
            var input = element.find('.file_input');
            // cloneNode keeps the selected File in Chrome, so emptying the value is what actually drops it.
            input.val('');

            var previous = element.data('pastePreviewUrl');
            if (previous) {
                URL.revokeObjectURL(previous);
                element.removeData('pastePreviewUrl');
            }

            var box = element.find('.attachment-paste-preview');
            box.addClass('d-none');
            box.find('img').attr('src', '').addClass('d-none');
            box.find('.attachment-paste-name').text('');

            if (element.attr('data-hid-existing') === '1') {
                element.find('.existing-file').removeClass('d-none');
                if (element.attr('data-paste') !== '1') {
                    element.find('.backstrap-file').addClass('d-none');
                }
                bpHiddenAttachmentInputs(element, fieldName).remove();
            } else {
                element.find('.backstrap-file').removeClass('d-none');
                bpHiddenAttachmentInputs(element, fieldName).remove();
                if (element.attr('data-had-clear') === '1') {
                    $('<input type="hidden">').attr('name', fieldName).val('').insertAfter(input);
                }
            }

            bpSetAttachmentPasteCallout(element, '');
            bpSetAttachmentPasteStatus(element, '');

            element.removeAttr('data-hid-existing');
            element.removeAttr('data-had-clear');
        }

        function bpFieldInitUploadElement(element) {
            var fileInput = element.find(".file_input");
            var fileClearButton = element.find(".file_clear_button");
            var fieldName = element.attr('data-field-name');
            var inputWrapper = element.find(".backstrap-file");
            var inputLabel = element.find(".backstrap-file-label");

            if(fileInput.attr('data-row-number')) {
              $('<input type="hidden" class="order_uploads" name="_order_'+fieldName+'" value="'+fileInput.data('filename')+'">').insertAfter(fileInput);

              var observer = new MutationObserver(function(mutations) {
                
                mutations.forEach(function(mutation) {
                  if(mutation.attributeName == 'data-row-number') {                    
                    let field = $(mutation.target);
                    field = field.next('input[name="'+mutation.target.getAttribute('name')+'"]');
                    field.attr('name', '_order_'+mutation.target.getAttribute('name'));
                    field.val(mutation.target.getAttribute('data-filename'));                  
                  }
                });
              });

              observer.observe(fileInput[0], {
                attributes: true,
              });
            }

            fileClearButton.click(function(e) {
                e.preventDefault();
                fileInput = element.find(".file_input");
                $(this).parent().addClass('d-none');
                // if the file input has a data-row-number attribute, it means it's inside a repeatable field
                // in that case, will send the value of the cleared input to the server
                if(fileInput.attr('data-row-number')) {
                  $("<input type='hidden' name='_clear_"+fieldName+"' value='"+fileInput.data('filename')+"'>").insertAfter(fileInput);
                  fileInput.siblings('.order_uploads').remove();
                }
                fileInput.parent().removeClass('d-none');
                fileInput.attr("value", "").replaceWith(fileInput.clone(true));

                // redo the selector, so we can use the same fileInput variable going forward
                fileInput = element.find(".file_input");

                // add a hidden input with the same name, so that the setXAttribute method is triggered
                $("<input type='hidden' name='"+fieldName+"' value=''>").insertAfter(fileInput);
            });

            element.on('change', '.file_input', function() {
                var path = $(this).val();
                path = path.replace("C:\\fakepath\\", "");
                if (!path && element.attr('data-paste') === '1' && this.files && this.files[0]) {
                    path = this.files[0].name;
                }
                if (element.attr('data-paste') === '1') {
                    if (this.files && this.files[0]) {
                        bpSetAttachmentPasteCallout(element, path || this.files[0].name);
                        bpRenderAttachmentPreview(element, this.files[0]);
                    }
                } else {
                    inputLabel.text(path);
                }
                // remove the hidden input that marks an existing file as cleared
                $(this).next("input[type=hidden]").remove();
            });

            element.on('CrudField:disable', function(e) {
              element.children('.backstrap-file').find('input').prop('disabled', 'disabled');

              let $deleteButton = element.children('.existing-file').children('a.file_clear_button');

              if($deleteButton.length > 0) {
                $deleteButton.on('click.prevent', function(e) {
                    e.stopImmediatePropagation();
                    return false;
                  });
                  // make the event we just registered, the first to be triggered
                  $._data($deleteButton.get(0), "events").click.reverse();
              }
          });

          element.on('CrudField:enable', function(e) {
            element.children('.backstrap-file').find('input').removeAttr('disabled');
            element.children('.existing-file').children('a.file_clear_button').unbind('click.prevent');
          });

          if (element.attr('data-paste') === '1') {
              element.on('click', '.attachment-paste-clear', function (e) {
                  e.preventDefault();
                  bpClearOrderAttachmentPaste(element);
              });

              element.on('click', '.attachment-browse-button', function (e) {
                  e.preventDefault();
                  e.stopPropagation();
                  var input = element.find('.file_input')[0];
                  if (input) {
                      input.click();
                  }
              });

              element.on('click', '.attachment-paste-button', function (e) {
                  e.preventDefault();
                  e.stopPropagation();
                  bpReadClipboardAttachment(element);
              });

              var dropZone = element.find('.backstrap-file');
              dropZone.on('dragenter dragover', function (e) {
                  e.preventDefault();
                  e.stopPropagation();
                  if (e.originalEvent && e.originalEvent.dataTransfer) {
                      e.originalEvent.dataTransfer.dropEffect = 'copy';
                  }
                  dropZone.addClass('is-dragover');
              });
              dropZone.on('dragleave', function (e) {
                  e.preventDefault();
                  e.stopPropagation();
                  var oe = e.originalEvent || {};
                  var rect = dropZone[0].getBoundingClientRect();
                  if (oe.clientX >= rect.left && oe.clientX <= rect.right && oe.clientY >= rect.top && oe.clientY <= rect.bottom) {
                      return;
                  }
                  dropZone.removeClass('is-dragover');
              });
              dropZone.on('drop', function (e) {
                  e.preventDefault();
                  e.stopPropagation();
                  dropZone.removeClass('is-dragover');
                  var transfer = e.originalEvent && e.originalEvent.dataTransfer;
                  var file = bpFirstAllowedAttachmentFile(transfer && transfer.files);
                  if (!file) {
                      bpSetAttachmentPasteStatus(element, element.attr('data-paste-type') || '');
                      return;
                  }
                  bpApplyOrderAttachmentPaste(element, file);
              });

              if (!window.bpOrderAttachmentPasteBound) {
                  window.bpOrderAttachmentPasteBound = true;
                  document.addEventListener('paste', function (e) {
                      var zone = document.querySelector('[data-paste="1"]');
                      if (!zone || !e.clipboardData) {
                          return;
                      }

                      var target = e.target;
                      if (target && target.closest && target.closest('.modal')) {
                          return;
                      }

                      var typing = false;
                      if (target && target.closest) {
                          if (target.closest('textarea, [contenteditable="true"]')) {
                              typing = true;
                          }
                          var focusedInput = target.closest('input');
                          if (focusedInput && focusedInput.type !== 'file') {
                              typing = true;
                          }
                      }

                      var pastedText = '';
                      try {
                          pastedText = e.clipboardData.getData('text/plain') || '';
                      } catch (err) {
                          pastedText = '';
                      }

                      if (typing && pastedText.trim() !== '') {
                          return;
                      }

                      var file = bpAttachmentFileFromClipboard(e.clipboardData);
                      if (!file) {
                          return;
                      }

                      e.preventDefault();
                      bpApplyOrderAttachmentPaste($(zone), file);
                  });
              }
          }

        }
    </script>
  @endBassetBlock
@endpush
