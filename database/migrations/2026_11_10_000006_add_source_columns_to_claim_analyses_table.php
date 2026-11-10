<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Analisis tidak lagi hanya menerima teks. Sekarang ada tiga pintu masuk:
 * tempel teks, unggah gambar (screenshot berita / postingan), atau tautan.
 *
 * Yang disimpan: dari mana klaim itu datang, dan — untuk gambar — teks hasil
 * pembacaan mesin. Teks hasil bacaan WAJIB ikut disimpan: verdict tanpa
 * provenance tidak bisa diaudit, dan user berhak melihat mesin membaca apa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_analyses', function (Blueprint $table) {
            $table->string('source_type', 16)->default('text')->after('text_hash');
            $table->string('source_ref', 2048)->nullable()->after('source_type');
            $table->string('source_label', 160)->nullable()->after('source_ref');
            $table->string('media_path', 255)->nullable()->after('source_label');
            $table->text('extracted_text')->nullable()->after('media_path');
            $table->json('visual_cues')->nullable()->after('extracted_text');
        });
    }

    public function down(): void
    {
        Schema::table('claim_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'source_type', 'source_ref', 'source_label',
                'media_path', 'extracted_text', 'visual_cues',
            ]);
        });
    }
};