<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Cloudinary avatar columns. CV columns already exist in the base users table.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'avatar_public_id')) {
                $table->string('avatar_public_id')->nullable()->after('avatar');
            }

            if (! Schema::hasColumn('users', 'avatar_url')) {
                $table->string('avatar_url')->nullable()->after('avatar_public_id');
            }

            if (! Schema::hasColumn('users', 'cv_path')) {
                $table->string('cv_path')->nullable();
            }

            if (! Schema::hasColumn('users', 'cv_original_name')) {
                $table->string('cv_original_name')->nullable();
            }
        });
    }

    /**
     * Reverse only the columns this migration added.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'avatar_public_id')) {
                $table->dropColumn('avatar_public_id');
            }

            if (Schema::hasColumn('users', 'avatar_url')) {
                $table->dropColumn('avatar_url');
            }
        });
    }
};
