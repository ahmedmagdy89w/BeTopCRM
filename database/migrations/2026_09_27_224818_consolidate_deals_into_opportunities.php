<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('type', 30)->default('initial')->after('title');
            $table->date('start_date')->nullable()->after('expected_close_date');
            $table->date('end_date')->nullable()->after('start_date');
        });

        Schema::dropIfExists('deal_items');
        Schema::dropIfExists('deals');
    }

    public function down(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 30);
            $table->decimal('value', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 30)->default('active');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });

        Schema::create('deal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn(['type', 'start_date', 'end_date']);
        });
    }
};
