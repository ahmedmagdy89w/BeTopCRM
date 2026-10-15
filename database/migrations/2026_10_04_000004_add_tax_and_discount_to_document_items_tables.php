<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotation_items', 'invoice_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('tax_id')->nullable()->after('product_id')->constrained('taxes')->nullOnDelete();
                $table->string('tax_name', 100)->nullable()->after('tax_id');
                $table->decimal('tax_percent', 5, 2)->default(0)->after('tax_name');
                $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal');
                $table->decimal('tax_amount', 12, 2)->default(0)->after('discount_amount');
            });
        }
    }

    public function down(): void
    {
        foreach (['quotation_items', 'invoice_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('tax_id');
                $table->dropColumn(['tax_name', 'tax_percent', 'discount_amount', 'tax_amount']);
            });
        }
    }
};
