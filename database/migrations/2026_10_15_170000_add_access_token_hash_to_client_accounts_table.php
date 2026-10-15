<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_accounts', function (Blueprint $table) {
            $table->string('access_token_hash', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('client_accounts', function (Blueprint $table) {
            $table->dropUnique(['access_token_hash']);
            $table->dropColumn('access_token_hash');
        });
    }
};
