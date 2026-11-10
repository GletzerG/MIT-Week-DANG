<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_quests', function (Blueprint $table) {
            $table->id();

            // ULID publik: jangan pernah bocorkan auto-increment id ke client,
            // karena id sekuensial memungkinkan user menebak/mengintip quest lain.
            $table->ulid('public_id')->unique();

            $table->string('title');
            $table->string('source_name');                 // sumber fiktif, mis. "KabarNusantara24"
            $table->string('source_url')->nullable();
            $table->string('image_url')->nullable();
            $table->text('excerpt');                       // cuplikan yang dibaca user
            $table->text('body')->nullable();              // versi panjang (opsional)

            // KUNCI ARSITEKTUR: verdict TIDAK PERNAH dikirim ke frontend.
            // Frontend menerima kartu saja; kebenaran diungkap setelah submit.
            $table->string('verdict', 8);                  // fact | hoax
            $table->unsignedTinyInteger('difficulty')->default(1); // 1..4
            $table->string('category', 32);                // kesehatan|politik|sains|...
            $table->string('locale', 5)->default('id');

            // Array red flag terstruktur: [{"type":"clickbait","note":"..."}]
            // Ini juga jadi FALLBACK penjelasan kalau LLM mati/timeout.
            $table->json('red_flags');

            // Cache hasil LLM. Diisi sekali, dipakai ribuan kali.
            $table->text('ai_explanation')->nullable();
            $table->string('ai_model', 64)->nullable();
            $table->timestamp('ai_generated_at')->nullable();

            $table->string('origin', 16)->default('seeder'); // seeder | ai
            $table->string('title_hash', 64)->unique();      // dedupe anti konten kembar

            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Query utama game: "ambil 1 quest aktif yang belum dikerjakan user".
            $table->index(['is_active', 'published_at'], 'news_quests_pool_index');
            $table->index(['is_active', 'difficulty'], 'news_quests_difficulty_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_quests');
    }
};
