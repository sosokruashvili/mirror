<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reusable SMS texts. A notification stores which template it used;
     * the message that is actually sent is the filled text on the notification.
     */
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->text('text');
            $table->timestamps();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('sms_template_id')
                ->nullable()
                ->constrained('sms_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sms_template_id');
        });

        Schema::dropIfExists('sms_templates');
    }
};
