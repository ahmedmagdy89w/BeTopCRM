<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('company_name')->nullable()->change();
            $table->string('contact_name', 200)->nullable()->change();
            $table->timestamp('converted_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep nullable on rollback (safe for fresh-start model)
    }
};
