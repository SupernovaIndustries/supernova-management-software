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
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('email_notifications')->default(true)->after('notes');
            $table->integer('notification_days_before')->default(7)->after('email_notifications');
            $table->timestamp('last_notification_sent')->nullable()->after('notification_days_before');
            $table->string('client_email')->nullable()->after('last_notification_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'email_notifications',
                'notification_days_before',
                'last_notification_sent',
                'client_email'
            ]);
        });
    }
};
