<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Two columns rather than one merged map: a model-level 'size' and a category-level
            // 'size' can legitimately coexist, and a single JSON object would collide silently.
            $table->json('custom_fields')->nullable();
            $table->json('category_custom_fields')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['custom_fields', 'category_custom_fields']);
        });
    }
};
