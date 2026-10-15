<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['opportunity_products', 'quotation_items', 'invoice_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('discount_percent', 5, 2)->nullable()->after('discount_amount');
            });
        }
    }

    public function down(): void
    {
        foreach (['opportunity_products', 'quotation_items', 'invoice_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('discount_percent');
            });
        }
    }
};
