<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('news_quest_id')->constrained()->cascadeOnDelete();

            $table->string('chosen_verdict', 8);   // fact | hoax
            $table->boolean('is_correct');
            $table->boolean('is_first_attempt')->default(true); // XP hanya dari percobaan pertama

            $table->integer('xp_delta');
            $table->smallInteger('hp_delta');
            $table->unsignedInteger('xp_before');
            $table->unsignedInteger('xp_after');
            $table->unsignedSmallInteger('streak_after')->default(0);

            $table->unsignedInteger('response_ms')->nullable(); // kecepatan analisis
            $table->string('ai_model', 64)->nullable();
            $table->boolean('ai_fallback')->default(false);     // true = LLM gagal, pakai red_flags

            $table->json('ai_feedback')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'news_quest_id'], 'attempts_user_quest_index');
            $table->index(['user_id', 'created_at'], 'attempts_user_recent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_attempts');
    }
};
