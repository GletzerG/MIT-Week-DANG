<?php

namespace App\Services\Ai;

use App\Models\NewsQuest;

/**
 * DTO hasil analisis. Bentuk ini yang dikirim ke Vue dan disimpan di
 * user_attempts.ai_feedback, sehingga frontend tidak pernah tahu dari mana
 * datanya berasal (LLM atau fallback).
 */
final readonly class AiFeedback
{
    public function __construct(
        public string $headline,
        public string $analysis,
        public array $redFlags,
        public string $tip,
        public bool $fromFallback = false,
        public ?string $model = null,
    ) {}

    /**
     * Fallback deterministik — dibangun dari kolom red_flags di database.
     *
     * KENAPA ini ada: kalau API LLM down / rate-limited / user offline,
     * game TIDAK BOLEH menggantung atau menampilkan error. User tetap dapat
     * penjelasan edukatif yang benar, hanya kurang "hidup".
     */
    public static function fromQuestFlags(NewsQuest $quest): self
    {
        $flags  = collect($quest->red_flags ?? []);
        $isHoax = $quest->verdict->value === 'hoax';

        $headline = $isHoax
            ? 'Berita ini adalah HOAX.'
            : 'Berita ini FAKTA.';

        $body = $flags->isNotEmpty()
            ? $flags->map(fn ($f) => '• **'.($f['label'] ?? $f['type'] ?? 'Sinyal').'** — '.($f['note'] ?? ''))->implode("\n")
            : '• Tidak ada sinyal manipulasi yang terdeteksi pada berita ini.';

        // Netral terhadap benar/salah: fungsi ini membangun penjelasan KANONIK
        // yang di-cache per quest. Kalimat "kamu melewatkan..." akan salah kalau
        // user menjawab benar, jadi beban itu diserahkan ke `headline`.
        $analysis = $isHoax
            ? "Sinyal manipulasi yang bisa dikenali dari berita ini:\n\n{$body}"
            : "Yang membuat berita ini kredibel:\n\n{$body}";

        $tip = $isHoax
            ? 'Sebelum percaya: cek sumber aslinya, tanggal terbit, dan cari 1 berita pembanding dari media arus utama.'
            : 'Tanda berita sehat: sumber jelas, ada nama penulis, ada data yang bisa diverifikasi, dan tidak memancing emosi berlebihan.';

        return new self(
            headline: $headline,
            analysis: $analysis,
            redFlags: $flags->all(),
            tip: $tip,
            fromFallback: true,
        );
    }

    public function toArray(): array
    {
        return [
            'headline'      => $this->headline,
            'analysis'      => $this->analysis,
            'red_flags'     => $this->redFlags,
            'tip'           => $this->tip,
            'from_fallback' => $this->fromFallback,
            'model'         => $this->model,
        ];
    }
}
