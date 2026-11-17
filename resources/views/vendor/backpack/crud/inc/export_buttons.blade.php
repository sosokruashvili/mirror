@if ($crud->exportButtons())
  @basset('https://cdn.datatables.net/buttons/2.3.3/css/buttons.bootstrap5.min.css')
  @basset('https://cdn.datatables.net/buttons/2.3.3/js/dataTables.buttons.min.js')
  @basset('https://cdn.datatables.net/buttons/2.3.3/js/buttons.bootstrap5.min.js')
  @basset('https://cdnjs.cloudflare.com/ajax/libs/jszip/2.5.0/jszip.min.js')
  @basset('https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.18/pdfmake.min.js')
  @basset('https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.18/vfs_fonts.js')
  @basset('https://cdn.datatables.net/buttons/2.3.2/js/buttons.html5.min.js')
  @basset('https://cdn.datatables.net/buttons/2.3.2/js/buttons.print.min.js')
  @basset('https://cdn.datatables.net/buttons/2.3.2/js/buttons.colVis.min.js')
  <script>
    let dataTablesExportStrip = text => {
        if ( typeof text !== 'string' ) {
            return text;
        }

        return text
            .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
            .replace(/<!\-\-.*?\-\->/g, '')
            .replace(/<[^>]*>/g, '')
            .replace(/^\s+|\s+$/g, '')
            .replace(/\s+([,.;:!\?])/g, '$1')
            .replace(/\s+/g, ' ')
            .replace(/[\n|\r]/g, ' ');
    };

    let dataTablesExportFormat = {
        body: (data, row, column, node) => 
            node.querySelector('input[type*="text"]')?.value ??
            node.querySelector('input[type*="checkbox"]:not(.crud_bulk_actions_line_checkbox)')?.checked ??
            node.querySelector('select')?.selectedOptions[0]?.value ??
            dataTablesExportStrip(data),
    };

    // Opt-in per CRUD with $crud->set('list.exportAllRows', true).
    // The table pages on the server, so a stock export only contains the rows on
    // screen. This fetches every filtered row once (length -1, which the
    // pageLengthMenu allows), exports them while the draw is cancelled, then
    // reloads the page the user was on.
    let dataTablesExportAllRows = @json((bool) $crud->get('list.exportAllRows'));
    let dataTablesExportRun = (dt, run) => {
        let settings = dt.settings()[0];
        if (!dataTablesExportAllRows || !settings.oFeatures.bServerSide) {
            return run();
        }
        let start = settings._iDisplayStart;
        dt.one('preXhr', (e, s, data) => {
            data.start = 0;
            data.length = -1;
            dt.one('preDraw', () => {
                try {
                    run();
                } finally {
                    dt.one('preXhr', (e2, s2, data2) => {
                        settings._iDisplayStart = start;
                        data2.start = start;
                    });
                    setTimeout(() => dt.ajax.reload(null, false), 0);
                }
                return false;
            });
        });
        dt.ajax.reload(null, false);
    };

    // Opt-in per CRUD with $crud->set('list.exportPdfGeorgian', true).
    // pdfmake's bundled Roboto has no Georgian glyphs (the text comes out blank),
    // so DejaVu Sans is loaded on the first PDF export, as on the cutting-stats page.
    let dataTablesExportPdfGeorgian = @json((bool) $crud->get('list.exportPdfGeorgian'));
    let dataTablesExportPdfFontSize = @json($crud->get('list.exportPdfFontSize'));
    let dataTablesPdfFontReady = null;
    let dataTablesExportPdfFont = () => {
        if (!dataTablesExportPdfGeorgian || (window.pdfMake && pdfMake.fonts && pdfMake.fonts.DejaVu)) {
            return Promise.resolve();
        }
        if (!dataTablesPdfFontReady) {
            let load = url => fetch(url).then(r => { if (!r.ok) throw new Error('font'); return r.arrayBuffer(); });
            let toBase64 = buffer => {
                let bytes = new Uint8Array(buffer), binary = '';
                for (let i = 0; i < bytes.length; i += 0x8000) {
                    binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
                }
                return btoa(binary);
            };
            dataTablesPdfFontReady = Promise.all([
                load(@json(asset('assets/fonts/DejaVuSans.ttf'))),
                load(@json(asset('assets/fonts/DejaVuSans-Bold.ttf')))
            ]).then(buffers => {
                pdfMake.vfs['DejaVuSans.ttf'] = toBase64(buffers[0]);
                pdfMake.vfs['DejaVuSans-Bold.ttf'] = toBase64(buffers[1]);
                // This pdfmake build reads only window.pdfMake.fonts and does not
                // create it itself, so Roboto has to be declared alongside.
                pdfMake.fonts = {
                    Roboto: { normal: 'Roboto-Regular.ttf', bold: 'Roboto-Medium.ttf', italics: 'Roboto-Italic.ttf', bolditalics: 'Roboto-Italic.ttf' },
                    DejaVu: { normal: 'DejaVuSans.ttf', bold: 'DejaVuSans-Bold.ttf', italics: 'DejaVuSans.ttf', bolditalics: 'DejaVuSans-Bold.ttf' }
                };
            }).catch(() => { dataTablesPdfFontReady = null; });
        }
        return dataTablesPdfFontReady;
    };

    window.crud.dataTableConfiguration.buttons = [
        @if($crud->get('list.showExportButton'))
        {
            extend: 'collection',
            text: '<i class="la la-download"></i> {{ trans('backpack::crud.export.export') }}',
            dropup: true,
            buttons: [
                {
                    name: 'copyHtml5',
                    extend: 'copyHtml5',
                    exportOptions: {
                        columns: function ( idx, data, node ) {
                            var $column = crud.table.column( idx );
                                return  ($column.visible() && $(node).attr('data-visible-in-export') != 'false') || $(node).attr('data-force-export') == 'true';
                        },
                        format: dataTablesExportFormat,
                    },
                    action: function(e, dt, button, config) {
                        let that = this;
                        dataTablesExportRun(dt, () => {
                            crud.responsiveToggle(dt);
                            $.fn.DataTable.ext.buttons.copyHtml5.action.call(that, e, dt, button, config);
                            crud.responsiveToggle(dt);
                        });
                    }
                },
                {
                    name: 'excelHtml5',
                    extend: 'excelHtml5',
                    exportOptions: {
                        columns: function ( idx, data, node ) {
                            var $column = crud.table.column( idx );
                                return  ($column.visible() && $(node).attr('data-visible-in-export') != 'false') || $(node).attr('data-force-export') == 'true';
                        },
                        format: dataTablesExportFormat,
                    },
                    action: function(e, dt, button, config) {
                        let that = this;
                        dataTablesExportRun(dt, () => {
                            crud.responsiveToggle(dt);
                            $.fn.DataTable.ext.buttons.excelHtml5.action.call(that, e, dt, button, config);
                            crud.responsiveToggle(dt);
                        });
                    }
                },
                {
                    name: 'csvHtml5',
                    extend: 'csvHtml5',
                    exportOptions: {
                        columns: function ( idx, data, node ) {
                            var $column = crud.table.column( idx );
                                return  ($column.visible() && $(node).attr('data-visible-in-export') != 'false') || $(node).attr('data-force-export') == 'true';
                        },
                        format: dataTablesExportFormat,
                    },
                    action: function(e, dt, button, config) {
                        let that = this;
                        dataTablesExportRun(dt, () => {
                            crud.responsiveToggle(dt);
                            $.fn.DataTable.ext.buttons.csvHtml5.action.call(that, e, dt, button, config);
                            crud.responsiveToggle(dt);
                        });
                    }
                },
                {
                    name: 'pdfHtml5',
                    extend: 'pdfHtml5',
                    exportOptions: {
                        columns: function ( idx, data, node ) {
                            var $column = crud.table.column( idx );
                                return  ($column.visible() && $(node).attr('data-visible-in-export') != 'false') || $(node).attr('data-force-export') == 'true';
                        },
                        format: dataTablesExportFormat,
                    },
                    orientation: 'landscape',
                    customize: function(doc) {
                        if (dataTablesExportPdfGeorgian && window.pdfMake && pdfMake.fonts && pdfMake.fonts.DejaVu) {
                            doc.defaultStyle.font = 'DejaVu';
                        }
                        if (dataTablesExportPdfFontSize) {
                            doc.defaultStyle.fontSize = dataTablesExportPdfFontSize;
                            if (doc.styles && doc.styles.tableHeader) doc.styles.tableHeader.fontSize = dataTablesExportPdfFontSize;
                        }
                    },
                    action: function(e, dt, button, config) {
                        let that = this;
                        let exportPdf = () => dataTablesExportRun(dt, () => {
                            crud.responsiveToggle(dt);
                            $.fn.DataTable.ext.buttons.pdfHtml5.action.call(that, e, dt, button, config);
                            crud.responsiveToggle(dt);
                        });
                        // Pages without the Georgian font keep the original synchronous export.
                        if (dataTablesExportPdfGeorgian) {
                            dataTablesExportPdfFont().then(exportPdf);
                        } else {
                            exportPdf();
                        }
                    }
                },
                {
                    name: 'print',
                    extend: 'print',
                    exportOptions: {
                        columns: function ( idx, data, node ) {
                            var $column = crud.table.column( idx );
                                return  ($column.visible() && $(node).attr('data-visible-in-export') != 'false') || $(node).attr('data-force-export') == 'true';
                        },
                        format: dataTablesExportFormat,
                    },
                    action: function(e, dt, button, config) {
                        let that = this;
                        dataTablesExportRun(dt, () => {
                            crud.responsiveToggle(dt);
                            $.fn.DataTable.ext.buttons.print.action.call(that, e, dt, button, config);
                            crud.responsiveToggle(dt);
                        });
                    }
                }
            ]
        }
        @endif
        @if($crud->get('list.showTableColumnPicker'))
        ,{
            extend: 'colvis',
            text: '<i class="la la-eye-slash"></i> {{ trans('backpack::crud.export.column_visibility') }}',
            columns: function ( idx, data, node ) {
                return $(node).attr('data-can-be-visible-in-table') == 'true';
            },
            dropup: true
        }
        @endif
    ];

    // move the datatable buttons in the top-right corner and make them smaller
    function moveExportButtonsToTopRight() {
      crud.table.buttons().each(function(button) {
        if (button.node.className.indexOf('buttons-columnVisibility') == -1 && button.node.nodeName=='BUTTON')
        {
          button.node.className = button.node.className + " btn-sm";
        }
      })
      $(".dt-buttons").appendTo($('#datatable_button_stack' ));
      $('.dt-buttons').addClass('d-xs-block')
                      .addClass('d-sm-inline-block')
                      .addClass('d-md-inline-block')
                      .addClass('d-lg-inline-block');
    }

    crud.addFunctionToDataTablesDrawEventQueue('moveExportButtonsToTopRight');
  </script>
@endif
