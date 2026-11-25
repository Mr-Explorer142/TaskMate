<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('escrow_amount')->default(0)->after('reward');
        });

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->string('note')->nullable()->after('type');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'id']);
        });

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('escrow_amount');
        });
    }
};
