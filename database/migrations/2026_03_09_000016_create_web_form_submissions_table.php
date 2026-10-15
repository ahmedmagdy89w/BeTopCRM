<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data');
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->string('status')->default('processed'); // processed, duplicate, failed
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_form_submissions');
    }
};
