<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A key lets the application find the template it must send, even after
     * the title is renamed. cabinet_access is the SMS with the private cabinet link.
     */
    public function up(): void
    {
        Schema::table('sms_templates', function (Blueprint $table) {
            $table->string('key', 64)->nullable()->unique();
        });

        $now = now();

        DB::table('sms_templates')->insert([
            'key' => 'cabinet_access',
            'title' => 'კაბინეტის ბმული',
            'text' => 'თქვენი ელექტრონული კაბინეტის მისამართი: {cabinet_url}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('sms_templates')->where('key', 'cabinet_access')->delete();

        Schema::table('sms_templates', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
        });
    }
};
