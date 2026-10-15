<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('fields');
            $table->string('default_source')->nullable();
            $table->foreignId('default_assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('redirect_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('submission_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_forms');
    }
};
