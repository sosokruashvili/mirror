{{-- Suppliers per expense category, consumed by cashier-expense-supplier-v2.js --}}
@push('after_scripts')
<script>
    window.cashierExpenseSupplierOptions = @json($widget['supplierOptions'] ?? []);
</script>
@endpush
