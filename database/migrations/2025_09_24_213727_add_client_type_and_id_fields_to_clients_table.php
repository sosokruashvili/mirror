<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Add client type field (0 = personal, 1 = legal)
            $table->boolean('client_type')->default(0)->after('email');
            
            // Add separate ID fields
            $table->string('personal_id')->nullable()->after('client_type');
            $table->string('legal_id')->nullable()->after('personal_id');
            
            // Remove the old id_number field
            $table->dropColumn('id_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Restore the old id_number field
            $table->string('id_number')->after('email');
            
            // Remove the new fields
            $table->dropColumn(['client_type', 'personal_id', 'legal_id']);
        });
    }
};
