<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outbound messages. Rows are created by the app; only the scheduler sends
     * the ones whose type is sms. This is not Laravel's database-notification
     * table (that channel is unused here).
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('recipient', 32);
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('gateway_reference', 32)->nullable();
            $table->nullableMorphs('notifiable');
            // Creating user's id, kept even if that user is later removed.
            $table->unsignedBigInteger('author')->nullable()->index();
            $table->timestamps();

            $table->index(['type', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
