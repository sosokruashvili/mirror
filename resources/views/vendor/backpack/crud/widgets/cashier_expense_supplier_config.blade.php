{{-- Category-driven form config, consumed by cashier-expense-supplier-v3.js --}}
@push('after_scripts')
<script>
    window.cashierExpenseSupplierOptions = @json($widget['supplierOptions'] ?? []);
    window.cashierExpenseProductionCategoryIds = @json($widget['productionCategoryIds'] ?? []);
</script>
@endpush
