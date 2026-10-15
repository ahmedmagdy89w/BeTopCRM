<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['leads', 'customers', 'organizations', 'contacts', 'deals'];
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('custom_fields')->nullable();
            });
        }
    }

    public function down(): void
    {
        $tables = ['leads', 'customers', 'organizations', 'contacts', 'deals'];
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('custom_fields');
            });
        }
    }
};
