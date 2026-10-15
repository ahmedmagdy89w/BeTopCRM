<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->text('secret')->change();
        });

        DB::table('webhook_endpoints')->orderBy('id')->chunkById(100, function ($endpoints): void {
            foreach ($endpoints as $endpoint) {
                DB::table('webhook_endpoints')
                    ->where('id', $endpoint->id)
                    ->update(['secret' => Crypt::encryptString($endpoint->secret)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('webhook_endpoints')->orderBy('id')->chunkById(100, function ($endpoints): void {
            foreach ($endpoints as $endpoint) {
                DB::table('webhook_endpoints')
                    ->where('id', $endpoint->id)
                    ->update(['secret' => Crypt::decryptString($endpoint->secret)]);
            }
        });

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->string('secret')->change();
        });
    }
};
