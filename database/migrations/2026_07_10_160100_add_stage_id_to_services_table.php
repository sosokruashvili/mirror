<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A service can be assigned to a production stage so the admin defines which
     * service belongs to which stage. Nullable: services without a stage are
     * allowed.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('stage_id')
                ->nullable()
                ->after('slug')
                ->constrained('stages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stage_id');
        });
    }
};
