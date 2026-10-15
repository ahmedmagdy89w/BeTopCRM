<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 50)->default('UTC')->after('email');
            $table->string('locale', 10)->default('en')->after('timezone');
            $table->string('date_format', 20)->default('Y-m-d')->after('locale');
            $table->json('notification_preferences')->nullable()->after('date_format');
            $table->string('avatar_path', 500)->nullable()->after('notification_preferences');
            $table->string('phone', 30)->nullable()->after('avatar_path');
            $table->boolean('is_active')->default(true)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'locale', 'date_format', 'notification_preferences', 'avatar_path', 'phone', 'is_active']);
        });
    }
};
