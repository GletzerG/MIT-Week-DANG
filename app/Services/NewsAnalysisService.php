<?php

namespace App\Services;

use App\Enums\QuestVerdict;
use App\Models\NewsQuest;
use App\Services\Ai\AiFeedback;
use App\Services\Ai\LlmManager;
use Illuminate\Support\Facades\Cache;

/**
 * ============================================================
 *  INTEGRASI AI — MENGAPA BERITA INI HOAX / FAKTA
 * ============================================================
 *
 * Alur biaya-efisien (penting!):
 *
 *   quest pertama kali dijawab oleh SIAPA PUN
 *        └─> panggil LLM SEKALI  ──> simpan ke news_quests.ai_explanation
 *
 *   user ke-2, ke-3, ... ke-10.000
 *        └─> baca kolom cache  ──> 0 token, 0 latency
 *
 * Kalau LLM mati / key kosong / kena rate-limit:
 *        └─> AiFeedback::fromQuestFlags()  ──> game tetap jalan
 *
 * Jadi 1 quest = maksimal 1 panggilan LLM seumur hidup kontennya.
 */
final class NewsAnalysisService
{
    public function __construct(private LlmManager $llm) {}

    public function enabled(): bool
    {
        return $this->llm->enabled();
    }

    /**
     * Penjelasan kanonik untuk sebuah quest. Selalu di-cache.
     */
    public function canonical(NewsQuest $quest, bool $force = false): AiFeedback
    {
        if (! $force && $this->hasCachedExplanation($quest)) {
            return $this->fromCachedPayload($quest);
        }

        $feedback = $this->requestFromLlm($quest);

        if ($feedback === null) {
            // LLM gagal. JANGAN simpan fallback ke cache permanen supaya
            // kunjungan berikutnya masih bisa mencoba AI lagi.
            return AiFeedback::fromQuestFlags($quest);
        }

        $this->storeCanonical($quest, $feedback);

        return $feedback;
    }

    /**
     * Feedback yang dipersonalisasi untuk satu attempt.
     * Tidak memanggil LLM — hanya membungkus hasil kanonik dengan konteks
     * benar/salah. Ini yang dikirim ke frontend.
     */
    public function forAttempt(NewsQuest $quest, QuestVerdict $chosen, bool $correct): AiFeedback
    {
        $canonical = $this->canonical($quest);
        $isHoax    = $quest->verdict === QuestVerdict::Hoax;

        $headline = match (true) {
            $correct && $isHoax  => 'Tepat! Berita ini HOAX.',
            $correct             => 'Tepat! Berita ini FAKTA.',
            $isHoax              => 'Meleset. Berita ini HOAX.',
            default              => 'Meleset. Berita ini sebenarnya FAKTA.',
        };

        return new AiFeedback(
            headline: $headline,
            analysis: $canonical->analysis,
            redFlags: $canonical->redFlags,
            tip: $canonical->tip,
            fromFallback: $canonical->fromFallback,
            model: $canonical->model,
        );
    }

    // ---------------------------------------------------------------- internal

    private function hasCachedExplanation(NewsQuest $quest): bool
    {
        return filled($quest->ai_explanation)
            && $quest->ai_generated_at !== null
            && $quest->ai_generated_at->gt(now()->subSeconds((int) config('guardian.ai.cache_ttl', 7776000)));
    }

    private function fromCachedPayload(NewsQuest $quest): AiFeedback
    {
        $payload = json_decode((string) $quest->ai_explanation, true) ?: [];

        return new AiFeedback(
            headline: (string) ($payload['headline'] ?? 'Analisis Genesis'),
            analysis: (string) ($payload['analysis'] ?? ''),
            redFlags: (array) ($payload['red_flags'] ?? $quest->red_flags ?? []),
            tip: (string) ($payload['tip'] ?? ''),
            fromFallback: (bool) ($payload['from_fallback'] ?? false),
            model: $quest->ai_model,
        );
    }

    private function storeCanonical(NewsQuest $quest, AiFeedback $feedback): void
    {
        $quest->forceFill([
            'ai_explanation'  => json_encode($feedback->toArray(), JSON_UNESCAPED_UNICODE),
            'ai_model'        => $feedback->model,
            'ai_generated_at' => now(),
        ])->save();

        Cache::forget($this->cacheKey($quest));
    }

    private function cacheKey(NewsQuest $quest): string
    {
        return "guardian:explain:{$quest->id}";
    }

    /**
     * Panggilan LLM sesungguhnya. Mengembalikan null kalau gagal.
     */
    private function requestFromLlm(NewsQuest $quest): ?AiFeedback
    {
        $factCheck = $quest->verdict === QuestVerdict::Hoax ? 'HOAX' : 'FAKTA';

        $flags = collect($quest->red_flags ?? [])
            ->map(fn ($f) => '- '.($f['type'] ?? 'signal').': '.($f['note'] ?? ''))
            ->implode("\n");

        $system = <<<'TXT'
Kamu adalah "Genesis Oracle", mentor AI di platform edukasi literasi media
untuk remaja Indonesia usia 12-18 tahun.

Tugasmu: jelaskan SECARA EDUKATIF mengapa sebuah berita tergolong hoax atau fakta.
Gunakan bahasa Indonesia santai tapi cerdas. Tanpa emoji. Tanpa menggurui.
Fokus pada sinyal yang bisa dipelajari ulang oleh remaja (clickbait, sumber
tidak kredibel, manipulasi emosi, tanggal hilang, foto hasil suntingan,
klaim tanpa data, fallacy logika, pesan berantai).

Balas HANYA dengan JSON valid, tanpa teks lain, dengan skema:
{
  "headline": "satu kalimat, maksimal 60 karakter",
  "analysis": "2-4 paragraf singkat, gunakan \\n untuk pemisah paragraf",
  "red_flags": [{"type": "clickbait", "label": "Judul Clickbait", "note": "penjelasan 1 kalimat"}],
  "tip": "satu tips praktis untuk remaja, maksimal 160 karakter"
}
TXT;

        $prompt = <<<TXT
Verdict berita ini: {$factCheck}

Judul: {$quest->title}
Sumber: {$quest->source_name}
Kategori: {$quest->category}
Cuplikan: {$quest->excerpt}

Sinyal yang sudah teridentifikasi sistem:
{$flags}

Tuliskan analisisnya.
TXT;

        $raw = $this->llm->safeJson($system, $prompt, [
            'temperature' => 0.4,
            'max_tokens'  => 800,
        ]);

        if ($raw === null || blank($raw['analysis'] ?? null)) {
            return null;
        }

        return new AiFeedback(
            headline: (string) ($raw['headline'] ?? ($factCheck === 'HOAX' ? 'Berita ini HOAX.' : 'Berita ini FAKTA.')),
            analysis: (string) $raw['analysis'],
            redFlags: is_array($raw['red_flags'] ?? null) ? $raw['red_flags'] : ($quest->red_flags ?? []),
            tip: (string) ($raw['tip'] ?? ''),
            fromFallback: false,
            model: $this->llm->model(),
        );
    }
}
