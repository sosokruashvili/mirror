<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which order product types (mirror, glass, lamix, glass_pkg, service) a
     * service can be done for. The order form offers a service only on orders
     * of those types. Empty (null) means every type, so nothing is hidden until
     * an administrator fills in the matrix on Services → product types.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('product_types')->nullable()->after('companion_service_ids');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('product_types');
        });
    }
};
