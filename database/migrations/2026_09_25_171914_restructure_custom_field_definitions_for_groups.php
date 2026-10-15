<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('id')
                ->constrained('custom_field_groups')
                ->cascadeOnDelete();
        });

        // Backfill each existing definition into the entity-scope group of its own entity type.
        $entityTypes = DB::table('custom_field_definitions')
            ->whereNotNull('entity_type')
            ->distinct()
            ->pluck('entity_type');

        $fallbackUserId = DB::table('users')->orderBy('id')->value('id');

        foreach ($entityTypes as $entityType) {
            $groupId = DB::table('custom_field_groups')->insertGetId([
                'entity_type' => $entityType,
                'scope' => 'entity',
                'category_id' => null,
                'scope_key' => 'entity',
                'name' => Str::headline($entityType).' Fields',
                'created_by' => $fallbackUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('custom_field_definitions')
                ->where('entity_type', $entityType)
                ->update(['group_id' => $groupId]);
        }

        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->dropUnique(['entity_type', 'name']);
            $table->dropColumn('entity_type');
        });

        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->unique(['group_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->dropUnique(['group_id', 'name']);
        });

        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->string('entity_type', 30)->nullable()->after('id');
        });

        DB::statement(
            'UPDATE custom_field_definitions d
             JOIN custom_field_groups g ON g.id = d.group_id
             SET d.entity_type = g.entity_type'
        );

        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
            $table->unique(['entity_type', 'name']);
        });
    }
};
