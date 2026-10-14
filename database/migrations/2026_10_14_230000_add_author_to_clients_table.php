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
            // Nullable so existing clients remain valid; new rows set author on create.
            // Matches orders.author / payments.author: the creating user's id, kept
            // even if that user is later removed.
            $table->integer('author')->nullable()->after('id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['author']);
            $table->dropColumn('author');
        });
    }
};
