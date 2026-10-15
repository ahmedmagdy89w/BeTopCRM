<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create opportunities table (replaces lead pipeline won/lost flow)
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('title');
            $table->string('stage', 30)->default('prospecting');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->tinyInteger('probability')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();

            $table->index('stage');
            $table->index('customer_id');
        });

        // 2. Extend customers table: unified model (individual|company, is_lead flag, JSON contacts/addresses)
        Schema::table('customers', function (Blueprint $table) {
            $table->string('type', 20)->default('company')->after('id');
            $table->boolean('is_lead')->default(true)->after('type');
            $table->string('lead_status', 30)->nullable()->after('is_lead');

            // Ownership / pipeline (moved from leads)
            $table->foreignId('assigned_to')->nullable()->after('lead_status')->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->string('source', 50)->nullable()->after('created_by');
            $table->decimal('estimated_value', 12, 2)->nullable()->after('source');
            $table->date('expected_close_date')->nullable()->after('estimated_value');
            $table->text('description')->nullable()->after('expected_close_date');

            // Individual-specific
            $table->string('first_name', 100)->nullable()->after('description');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('email')->nullable()->after('last_name');
            $table->string('phone', 50)->nullable()->after('email');
            $table->string('job_title', 100)->nullable()->after('phone');
            $table->date('birthday')->nullable()->after('job_title');
            $table->string('gender', 20)->nullable()->after('birthday');

            // Company-specific additions (company_name already exists)
            $table->string('industry', 100)->nullable()->after('gender');
            $table->string('website')->nullable()->after('industry');
            $table->string('tax_id', 100)->nullable()->after('website');

            // Multi-value JSON
            $table->json('contacts')->nullable()->after('tax_id');
            $table->json('addresses')->nullable()->after('contacts');

            // Scoring (moved from leads)
            $table->integer('score')->nullable()->after('addresses');
            $table->json('score_breakdown')->nullable()->after('score');
            $table->timestamp('scored_at')->nullable()->after('score_breakdown');

            $table->index('type');
            $table->index('is_lead');
            $table->index('lead_status');
        });

        // 3. Drop FK columns that pointed to removed entities
        Schema::table('customers', function (Blueprint $table) {
            try {
                $table->dropForeign(['lead_id']);
            } catch (Throwable $e) {
            }
            try {
                $table->dropForeign(['organization_id']);
            } catch (Throwable $e) {
            }
        });
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'lead_id')) {
                $table->dropColumn('lead_id');
            }
            if (Schema::hasColumn('customers', 'organization_id')) {
                $table->dropColumn('organization_id');
            }
        });

        // 4. Point web_form_submissions at customers instead of leads
        if (Schema::hasTable('web_form_submissions') && Schema::hasColumn('web_form_submissions', 'lead_id')) {
            Schema::table('web_form_submissions', function (Blueprint $table) {
                try {
                    $table->dropForeign(['lead_id']);
                } catch (Throwable $e) {
                }
            });
            Schema::table('web_form_submissions', function (Blueprint $table) {
                $table->renameColumn('lead_id', 'customer_id');
            });
            Schema::table('web_form_submissions', function (Blueprint $table) {
                $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            });
        }

        // 5. Drop obsolete tables (fresh start — no data migration)
        Schema::dropIfExists('lead_products');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('organizations');
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');

        Schema::table('customers', function (Blueprint $table) {
            $columns = [
                'type', 'is_lead', 'lead_status', 'assigned_to', 'created_by',
                'source', 'estimated_value', 'expected_close_date', 'description',
                'first_name', 'last_name', 'email', 'phone', 'job_title',
                'birthday', 'gender', 'industry', 'website', 'tax_id',
                'contacts', 'addresses', 'score', 'score_breakdown', 'scored_at',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('customers', $col)) {
                    try {
                        $table->dropColumn($col);
                    } catch (Throwable $e) {
                    }
                }
            }
        });
    }
};
