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
        Schema::table('opportunity_activities', function (Blueprint $table) {
            $table->string('channel', 20)->nullable()->after('type');
            $table->timestamp('reminder_at')->nullable()->after('due_date');
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opportunity_activities', function (Blueprint $table) {
            $table->dropColumn(['channel', 'reminder_at', 'reminder_sent_at']);
        });
    }
};
