<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->integer('score')->default(0)->after('description');
            $table->json('score_breakdown')->nullable()->after('score');
            $table->timestamp('scored_at')->nullable()->after('score_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['score', 'score_breakdown', 'scored_at']);
        });
    }
};
