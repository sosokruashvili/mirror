{{--
  Standard CRUD list, with the whole day row acting as the expand/collapse
  trigger for the details row (cash payments + cash expenses breakdown).
--}}
@extends('crud::list')

@push('crud_list_styles')
<style>
    /* The whole row toggles the details row, so advertise it as clickable. */
    #crudTable tbody tr:not(.no-padding) {
        cursor: pointer;
    }

    #crudTable tbody tr.shown {
        background-color: var(--tblr-active-bg, rgba(0, 0, 0, .04));
    }

    /* The expanded panel is a container of its own; drop the cell padding and
       the striping the parent table applies to it. DataTables puts the
       'no-padding' class Backpack passes to row.child() on both tr and td. */
    #crudTable tbody tr.no-padding > td {
        padding: 0 !important;
        background-color: var(--tblr-bg-surface-secondary, #f6f8fb);
        box-shadow: inset 0 1px 0 var(--tblr-border-color, #e6e7e9),
                    inset 0 -1px 0 var(--tblr-border-color, #e6e7e9);
    }

    /* Sub-tables can get long: keep the expanded row a sane height. */
    .cashier-subtable {
        max-height: 22rem;
        overflow-y: auto;
    }

    .cashier-details .card-table th {
        position: sticky;
        top: 0;
        z-index: 1;
        background-color: var(--tblr-card-bg, #fff);
    }
</style>
@endpush

@push('crud_list_scripts')
<script>
    jQuery(document).ready(function ($) {
        var $tbody = $('#crudTable tbody');

        // Clicking anywhere on a day row expands/collapses it. Backpack only
        // binds the +/- icon, so forward row clicks to it.
        $tbody.on('click', 'tr', function (e) {
            var $control = $(this).find('.details-control');

            // Details-row content and any interactive cell content keep their own behaviour.
            if (!$control.length) {
                return;
            }
            if ($(e.target).closest('a, button, input, select, label, .details-control').length) {
                return;
            }

            $control.trigger('click');
        });

        // Backpack toggles la-*-square-o classes but the icon ships with la-plus-square,
        // so the base class sticks and the glyph never flips. Own the icon state here.
        $tbody.on('click', '.details-control', function () {
            var willOpen = !$(this).closest('tr').hasClass('shown');

            $(this).find('.details-row-button')
                .removeClass('la-plus-square la-plus-square-o la-minus-square la-minus-square-o')
                .addClass(willOpen ? 'la-minus-square la-minus-square-o' : 'la-plus-square la-plus-square-o');
        });
    });
</script>
@endpush
