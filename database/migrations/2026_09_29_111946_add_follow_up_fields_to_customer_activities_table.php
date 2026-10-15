<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_activities', function (Blueprint $table) {
            $table->string('channel', 30)->nullable()->after('type');
            $table->dateTime('reminder_at')->nullable()->after('due_date');
            $table->dateTime('reminder_sent_at')->nullable()->after('reminder_at');
            $table->index(['reminder_at', 'reminder_sent_at']);
            $table->index(['customer_id', 'due_date']);
        });

        // Backfill: historic whatsapp activities used the type as the channel.
        DB::table('customer_activities')
            ->where('type', 'whatsapp')
            ->whereNull('channel')
            ->update(['channel' => 'whatsapp']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_activities', function (Blueprint $table) {
            $table->dropIndex(['reminder_at', 'reminder_sent_at']);
            $table->dropIndex(['customer_id', 'due_date']);
            $table->dropColumn(['channel', 'reminder_at', 'reminder_sent_at']);
        });
    }
};
