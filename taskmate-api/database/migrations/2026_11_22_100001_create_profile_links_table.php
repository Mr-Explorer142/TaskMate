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
        Schema::create('profile_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform')->default('other');
            $table->string('label')->nullable();
            $table->string('url', 2048);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('user_id');
        });

        // MySQL cannot build a full unique index over a 2048-character
        // utf8mb4 column, so use a 700-character prefix there. The full URL
        // is still checked by application-level validation.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `profile_links` ADD UNIQUE `profile_links_user_id_url_unique` (`user_id`, `url`(700))'
            );
        } else {
            Schema::table('profile_links', function (Blueprint $table) {
                $table->unique(['user_id', 'url']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_links');
    }
};
