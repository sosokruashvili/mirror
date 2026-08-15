<?php

/*
| Orders module: OrderCrudController columns/fields/filters, the order views
| under resources/views/vendor/backpack/crud/order/, the order buttons and
| the printable invoice.
|
| Order statuses live in the status.php lang file, order/product types in
| order_type.php and product_type.php, since those are keyed by stored
| database values and are shared with other modules.
*/

return [

    // Columns & fields
    'id' => 'ID',
    'client' => 'Client',
    'author' => 'Author',
    'status' => 'Status',
    'paid' => 'Paid',
    'order_type' => 'Order Type',
    'product_type' => 'Product Type',
    'order_product_type' => 'Order Product Type',
    'currency_rate' => 'Currency Rate',
    'usd_rate' => 'USD Rate',
    'price_gel' => 'Price (₾)',
    'price_usd' => 'Price (USD)',
    'paid_amount' => 'Paid Amount',
    'paid_amount_gel' => 'Paid Amount (₾)',
    'created_at' => 'Created At',
    'confirm_date' => 'Confirm Date',
    'due_date' => 'Due Date',
    'due_date_progress' => 'Due',
    'no_due_date' => 'No due date set',
    'days_left' => ':days d',
    'due_today' => 'today',
    'overdue_days' => '-:days d',
    'order_date_range' => 'Order Date Range',
    'products' => 'Products',
    'product' => 'Product',
    'pieces' => 'Pieces',
    'payments' => 'Payments',
    'attachment' => 'Attachment',
    'expenses_m2' => 'Expenses (m²)',
    'comment' => 'Comment',
    'finish_comment' => 'Finish Comment',

    // Buttons & placeholders
    'new_client' => 'New Client',
    'new_product' => 'New Product',
    'select_client' => 'Select a client',
    'select_product' => 'Select a product',
    'comment_placeholder' => 'Optional notes for the production team...',

    // Field hints
    'hints' => [
        'usd_rate' => 'Actual current USD rate: :rate',
        'product_price_locked' => 'Auto-filled from the product (or client custom price). Only administrators can change it.',
        'products' => 'Add products to this order (filtered by Order Product Type)',
        'pieces' => 'Add pieces to this order. Each piece can have its own services.',
        'payments_create' => 'A payment is saved the moment you add it, and is linked to this order when you save the order. Check the list above before adding another one — to fix a payment, edit or delete it instead of creating a second one.',
        'payments_edit' => 'Payments already on this order are listed above. To correct one, edit or delete it — adding a second payment leaves the wrong one behind.',
        'attachment' => 'Allowed types: PDF, PNG, JPEG, JPG',
        'expenses_create' => 'Auto-calculated from piece width, height and quantity. You can override it manually if it does not match the real expense.',
        'expenses_edit' => 'Auto-calculated from pieces. You can override it manually if it does not match the real expense.',
        'product_type_locked' => 'Order Product Type cannot be changed after creation',
        'due_date' => 'Optional. Planned finish date — the orders list shows a days-left progress bar.',
    ],

    // Pieces detail block on the order show/preview page
    'piece' => [
        'title' => 'Piece #:id',
        'size' => 'Size:',
        'quantity' => 'Quantity:',
        'area' => 'Area:',
        'stage' => 'Stage',
        'services' => 'Services (:count):',
        'no_services' => 'No services assigned to this piece',
    ],

    // Flash / JSON responses
    'messages' => [
        'no_permission_edit' => 'You do not have permission to edit this order.',
        'no_entries_selected' => 'No entries selected.',
        'bulk_deleted' => '{1} :count entry deleted successfully.|[2,*] :count entries deleted successfully.',
        'bulk_skipped' => '{1} :count entry was skipped (only draft orders can be deleted; new orders require an administrator).|[2,*] :count entries were skipped (only draft orders can be deleted; new orders require an administrator).',
        'not_found' => 'Order not found',
        'only_draft_confirm' => 'Only draft orders can be confirmed',
        'confirmed' => 'Order confirmed successfully',
        'only_ready_finish' => 'Only ready orders can be finished',
        'finished' => 'Order finished successfully',
        'piece_not_found' => 'Piece not found',
        'invalid_stage' => 'Invalid stage',
    ],

    // Row buttons and their confirmation dialogs
    'buttons' => [
        'invoice' => 'Invoice',
        'pieces' => 'Pieces',
        'confirm' => 'Confirm',
        'finish' => 'Finish',
    ],

    'confirm_dialog' => [
        'title' => 'Confirm Order?',
        'text' => "Are you sure you want to confirm this order? The status will be changed to 'new'.",
        'success_title' => 'Order Confirmed',
        'success_text' => "The order status has been changed to 'new'.",
        'error' => 'An error occurred while confirming the order.',
    ],

    'finish_dialog' => [
        'title' => 'Finish Order?',
        'text' => 'Are you sure you want to finish this order? The order and all its pieces will be marked as finished.',
        'success_title' => 'Order Finished',
        'success_text' => 'The order and all pieces have been marked as finished.',
        'error' => 'An error occurred while finishing the order.',
    ],

    // Entity names used in breadcrumbs, headings and Backpack's "Add a new X"
    'entity' => 'order',
    'entity_plural' => 'orders',

    // order_stats widget above the orders list
    'stats' => [
        'orders_count' => 'Orders Count',
        'total_price' => 'Total Price (₾)',
        'total_paid' => 'Total Paid (₾)',
        'left_to_pay' => 'Left to Pay (₾)',
        'total_expenses' => 'Total Expenses (m²)',
    ],

];
