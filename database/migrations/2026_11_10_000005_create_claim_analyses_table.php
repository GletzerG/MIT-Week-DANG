<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache hasil deteksi hoax untuk teks yang dikirim user.
 *
 * KENAPA di-cache dan bukan langsung panggil LLM tiap request:
 *  - remaja sering paste berita yang sama (pesan berantai!), jadi hit rate
 *    tinggi dan biaya token turun drastis;
 *  - hasilnya bisa diaudit ulang kalau ada yang salah;
 *  - limiter per-user jadi lapis kedua, bukan satu-satunya perlindungan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_analyses', function (Blueprint $table) {
            $table->id();

            // sha256 dari teks yang sudah dinormalisasi (lowercase, buang spasi ganda).
            $table->char('text_hash', 64)->unique();

            $table->string('verdict', 12);            // fact | hoax | uncertain
            $table->unsignedTinyInteger('confidence'); // 0..100
            $table->json('payload');                  // reasons, red_flags, summary, recommendation, signals

            $table->string('model', 64)->nullable();
            $table->boolean('from_fallback')->default(false);
            $table->unsignedInteger('hits')->default(0);

            $table->timestamps();

            $table->index('verdict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_analyses');
    }
};