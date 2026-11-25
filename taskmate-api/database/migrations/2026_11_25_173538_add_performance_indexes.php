<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite indexes that match the API's real filtering queries.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->index(['status', 'type', 'created_at'], 'tasks_status_type_created_at_index');
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->index(['task_id', 'status'], 'applications_task_id_status_index');
            $table->index(['user_id', 'status'], 'applications_user_id_status_index');
        });

        Schema::table('point_transactions', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'point_transactions_user_id_created_at_index');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['notifiable_id', 'read_at'], 'notifications_notifiable_id_read_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_notifiable_id_read_at_index');
        });

        Schema::table('point_transactions', function (Blueprint $table): void {
            $table->dropIndex('point_transactions_user_id_created_at_index');
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex('applications_user_id_status_index');
            $table->dropIndex('applications_task_id_status_index');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_status_type_created_at_index');
        });
    }
};
