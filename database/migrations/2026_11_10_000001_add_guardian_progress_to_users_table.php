<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Progresi
            $table->unsignedInteger('xp')->default(0)->after('password');
            $table->unsignedSmallInteger('level')->default(1)->after('xp');
            $table->string('rank', 40)->default('novice_guardian')->after('level');

            // Vitalitas & engagement
            $table->unsignedTinyInteger('hp')->default(100)->after('rank');
            $table->timestamp('hp_updated_at')->nullable()->after('hp');
            $table->unsignedSmallInteger('current_streak')->default(0)->after('hp_updated_at');
            $table->unsignedSmallInteger('best_streak')->default(0)->after('current_streak');
            $table->timestamp('last_played_at')->nullable()->after('best_streak');

            // Index untuk leaderboard: ORDER BY xp DESC sangat sering dipakai.
            $table->index(['xp', 'level'], 'users_leaderboard_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_leaderboard_index');
            $table->dropColumn([
                'xp', 'level', 'rank', 'hp', 'hp_updated_at',
                'current_streak', 'best_streak', 'last_played_at',
            ]);
        });
    }
};
