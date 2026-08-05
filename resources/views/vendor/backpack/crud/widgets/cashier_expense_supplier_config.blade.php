{{-- Suppliers per expense category, consumed by cashier-expense-supplier.js --}}
@push('after_scripts')
<script>
    window.cashierExpenseSupplierOptions = @json($widget['supplierOptions'] ?? []);
</script>
@endpush
