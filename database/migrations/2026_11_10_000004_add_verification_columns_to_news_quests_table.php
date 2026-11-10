<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode "Feed Terverifikasi" butuh data yang tidak dibutuhkan mode "Trial".
 *
 * Trial Quest  : verdict disembunyikan, user menebak.
 * Feed         : verdict DITAMPILKAN, plus skor kredibilitas dan rujukan
 *                cek fakta supaya remaja bisa belajar membaca bukti.
 *
 * Dua mode berbagi tabel yang sama, tapi hanya `VerifiedNewsResource` yang
 * boleh mengekspos verdict.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_quests', function (Blueprint $table) {
            // 0..100. Bukan hasil AI — ini penilaian redaksi atas kualitas bukti.
            $table->unsignedTinyInteger('credibility')->default(50)->after('category');

            // Rujukan eksternal: "siapa yang bilang ini hoax/fakta".
            $table->string('fact_check_source', 64)->nullable()->after('credibility');
            $table->string('fact_check_url')->nullable()->after('fact_check_source');

            // Penulis asli. Kolom kosong = salah satu sinyal hoax paling tua.
            $table->string('author')->nullable()->after('source_name');

            // Dipakai tab filter di dashboard: fakta | hoax.
            $table->index(['is_active', 'verdict'], 'news_quests_verdict_index');
        });
    }

    public function down(): void
    {
        Schema::table('news_quests', function (Blueprint $table) {
            $table->dropIndex('news_quests_verdict_index');
            $table->dropColumn(['credibility', 'fact_check_source', 'fact_check_url', 'author']);
        });
    }
};