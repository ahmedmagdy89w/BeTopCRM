<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_products', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_products', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });
    }
};
