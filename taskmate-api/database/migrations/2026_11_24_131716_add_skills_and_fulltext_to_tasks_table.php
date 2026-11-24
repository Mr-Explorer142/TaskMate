<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->json('skills')->nullable()->after('deadline');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('tasks', function (Blueprint $table) {
                $table->fullText(['title', 'description']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropFullText(['title', 'description']);
            });
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('skills');
        });
    }
};
