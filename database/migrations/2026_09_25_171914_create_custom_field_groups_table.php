<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_groups', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 30);
            $table->string('scope', 20)->default('entity');
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->cascadeOnDelete();
            // Derived, never user supplied: 'entity' or 'category:<id>'. Exists because MySQL
            // treats NULLs as distinct, so a unique on (entity_type, scope, category_id) would
            // silently permit more than one entity-scope group per model.
            $table->string('scope_key', 40);
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['entity_type', 'scope_key']);
            $table->index(['entity_type', 'scope', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_groups');
    }
};
