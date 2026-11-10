<?php

namespace App\Services;

use App\Enums\ClaimVerdict;
use App\Models\ClaimAnalysis;
use App\Services\Ai\LlmManager;
use App\Support\HeuristicClaimScanner;
use Illuminate\Support\Str;

/**
 * ============================================================
 *  DETEKSI HOAX — DUA LAPIS, SATU JAWABAN
 * ============================================================
 *
 * Lapis 1 (selalu jalan, 0 token):
 *   HeuristicClaimScanner membaca sinyal linguistik + struktural.
 *   Dipakai kalau LLM belum dikonfigurasi, timeout, atau kena limit.
 *
 * Lapis 2 (kalau tersedia):
 *   LLM diminta menilai teks bebas — jauh lebih paham konteks, sarkasme,
 *   dan klaim yang butuh pengetahuan dunia.
 *
 * Hasil Lapis 2 selalu divalidasi ulang: verdict di luar enum ditolak.
 * Jangan pernah percaya keluaran model secara buta — model bisa "yakin"
 * pada hal yang tidak ada di teks.
 *
 * Cache: hash teks → 1 analisis. Pesan berantai yang sama tidak
 * membakar token dua kali.
 */
final class ClaimAnalysisService
{
    public function __construct(
        private LlmManager $llm,
        private HeuristicClaimScanner $scanner,
    ) {}

    public function enabled(): bool
    {
        return $this->llm->enabled();
    }

    public function analyze(string $text, bool $force = false, array $context = []): ClaimAnalysis
    {
        $text    = trim($text);
        $context = $this->normalizeContext($context);

        $hash = $this->fingerprint($text, $context);

        if (! $force && ($cached = $this->cached($hash)) !== null) {
            return $cached;
        }

        $payload = $this->fromLlm($text, $context) ?? $this->fromRules($text, $context);

        $payload['context'] = $context;

        return ClaimAnalysis::updateOrCreate(
            ['text_hash' => $hash],
            [
                'verdict'        => $payload['verdict'],
                'confidence'     => $payload['confidence'],
                'payload'        => $payload,
                'model'          => $payload['model'] ?? null,
                'from_fallback'  => $payload['engine'] === 'rules',
                'hits'           => 1,
                'source_type'    => $context['type'],
                'source_ref'     => $context['ref'],
                'source_label'   => $context['label'],
                'media_path'     => $context['media_path'],
                'extracted_text' => $context['type'] === 'text' ? null : $text,
                'visual_cues'    => $context['cues'] ?: null,
            ],
        );
    }

    /**
     * Konteks selalu berbentuk sama, supaya prompt, DB, dan UI tidak perlu
     * menebak-nebak kunci apa yang ada.
     *
     * @param  array<string, mixed>  $context
     * @return array{type:string, ref:string|null, label:string|null, title:string|null, media_path:string|null, cues:list<string>, reputation:array<string,mixed>|null, attribution:array<string,bool>|null}
     */
    private function normalizeContext(array $context): array
    {
        $type = (string) ($context['type'] ?? 'text');

        return [
            'type'        => in_array($type, ['text', 'image', 'link'], true) ? $type : 'text',
            'ref'         => isset($context['ref']) ? mb_substr((string) $context['ref'], 0, 2048) : null,
            'label'       => isset($context['label']) ? mb_substr((string) $context['label'], 0, 160) : null,
            'title'       => isset($context['title']) ? mb_substr((string) $context['title'], 0, 300) : null,
            'media_path'  => $context['media_path'] ?? null,
            'cues'        => array_values(array_filter((array) ($context['cues'] ?? []), 'is_string')),
            'reputation'  => is_array($context['reputation'] ?? null) ? $context['reputation'] : null,
            'attribution' => is_array($context['attribution'] ?? null) ? $context['attribution'] : null,
        ];
    }

    // ------------------------------------------------------------------ cache

    private function cached(string $hash): ?ClaimAnalysis
    {
        $ttl = (int) config('guardian.ai.cache_ttl', 7776000);

        $row = ClaimAnalysis::where('text_hash', $hash)
            ->where('updated_at', '>=', now()->subSeconds($ttl))
            ->first();

        if ($row === null) {
            return null;
        }

        $row->increment('hits');

        return $row;
    }

    private function fingerprint(string $text, array $context): string
    {
        // Normalisasi dulu: "Halo  DUNIA" dan "halo dunia" adalah klaim yang sama.
        $normal = preg_replace('/\s+/u', ' ', mb_strtolower($text)) ?? $text;

        // Provenance ikut masuk hash. Teks yang sama dari domain berbeda bisa
        // punya verdict berbeda (reputasi sumber), dan sinyal visual dari gambar
        // juga mengubah penilaian. Tanpa ini, hasil lama akan menimpa yang baru.
        $cues = implode('|', array_map(
            fn ($c) => mb_strtolower(trim((string) $c)),
            $context['cues'] ?? [],
        ));

        $tier = (string) ($context['reputation']['tier'] ?? '');

        return hash('sha256', implode('#', [$normal, $context['type'], $tier, $cues]));
    }

    // --------------------------------------------------------------- lapis AI

    private function fromLlm(string $text, array $context = []): ?array
    {
        if (! $this->llm->enabled()) {
            return null;
        }

        $system = <<<'TXT'
Kamu adalah "Genesis Oracle", mesin verifikasi klaim untuk platform literasi
media remaja Indonesia (12-18 tahun).

Tugas: menilai apakah teks yang dikirim pengguna merupakan klaim yang kredibel,
klaim manipulatif (hoax), atau tidak bisa diputuskan dari teks saja.

Aturan penilaian:
- "uncertain" WAJIB dipakai kalau teks tidak punya cukup bukti. Jangan menebak.
- Jangan mengarang nama lembaga, angka, atau tautan. Kalau tidak ada di teks,
  jangan tambahkan.
- Alasan harus menunjuk bagian teks yang konkret (kata/frasa yang dipakai).
- Bahasa Indonesia santai tapi cerdas. Tanpa emoji.

Balas HANYA JSON valid:
{
  "verdict": "fact|hoax|uncertain",
  "confidence": 0-100,
  "summary": "1-2 kalimat, maksimal 200 karakter",
  "reasons": ["alasan konkret 1", "alasan konkret 2"],
  "red_flags": [{"label": "Nama sinyal", "note": "penjelasan 1 kalimat"}],
  "recommendation": "langkah verifikasi praktis, maksimal 180 karakter"
}
TXT;

        $prompt = "Teks yang perlu dinilai:\n\n\"\"\"\n".Str::limit($text, 4000, '')."\n\"\"\"".$this->contextBrief($context);

        $raw = $this->llm->safeJson($system, $prompt, [
            'temperature' => 0.2,
            'max_tokens'  => 700,
        ]);

        if ($raw === null) {
            return null;
        }

        // Validasi: model bisa mengembalikan verdict yang tidak dikenal.
        $verdict = ClaimVerdict::tryFrom((string) ($raw['verdict'] ?? ''));

        if ($verdict === null || blank($raw['summary'] ?? null)) {
            return null;
        }

        $reasons = array_values(array_filter(
            (array) ($raw['reasons'] ?? []),
            fn ($r) => is_string($r) && $r !== '',
        ));

        // Model yang tidak memberi alasan apa pun = tidak bisa diaudit. Buang.
        if ($reasons === []) {
            return null;
        }

        return [
            'verdict'        => $verdict->value,
            'confidence'     => max(0, min(100, (int) ($raw['confidence'] ?? 60))),
            'summary'        => (string) $raw['summary'],
            'reasons'        => array_slice($reasons, 0, 5),
            'red_flags'      => $this->cleanFlags($raw['red_flags'] ?? []),
            'recommendation' => (string) ($raw['recommendation'] ?? ''),
            // Sinyal aturan tetap ditempel di samping alasan model: satu untuk
            // pola yang bisa dihitung, satu untuk konteks yang butuh pemahaman.
            'signals'        => $this->scanner->scan($text)['signals'],
            'excerpt'        => Str::limit($text, 600, '…'),
            'char_count'     => mb_strlen($text),
            'engine'         => 'ai',
            'model'          => $this->llm->model(),
        ];
    }

    /**
     * Konteks provenance untuk model. Ditulis sebagai blok terpisah supaya
     * model tahu ini keterangan TENTANG teks, bukan bagian dari teks yang
     * harus dinilai kata per kata.
     */
    private function contextBrief(array $context): string
    {
        $lines = [];

        if ($context['type'] === 'link') {
            $lines[] = 'Sumber: artikel dari tautan '.(string) ($context['label'] ?? 'tak dikenal');

            if ($title = $context['title'] ?? null) {
                $lines[] = 'Judul halaman: '.$title;
            }

            if ($reputation = $context['reputation'] ?? null) {
                $lines[] = 'Reputasi domain: '.$reputation['label'].' — '.$reputation['note'];
            }

            $attr = $context['attribution'] ?? [];

            $lines[] = 'Atribusi: penulis '.(($attr['author'] ?? false) ? 'ada' : 'tidak ada')
                .', tanggal terbit '.(($attr['date'] ?? false) ? 'ada' : 'tidak ada');
        }

        if ($context['type'] === 'image') {
            $lines[] = 'Sumber: gambar yang diunggah user (hasil pembacaan mesin visual).';

            if ($cues = $context['cues'] ?? []) {
                $lines[] = 'Sinyal visual yang terlihat: '.implode('; ', $cues);
            }
        }

        if ($lines === []) {
            return '';
        }

        return "\n\nKONTEKS TAMBAHAN (keterangan tentang sumber, bukan bagian dari teks):\n- ".implode("\n- ", $lines);
    }

    private function cleanFlags(mixed $flags): array
    {
        if (! is_array($flags)) {
            return [];
        }

        return collect($flags)
            ->filter(fn ($f) => is_array($f) && filled($f['label'] ?? null))
            ->map(fn ($f) => [
                'label' => (string) $f['label'],
                'note'  => (string) ($f['note'] ?? ''),
            ])
            ->take(6)
            ->values()
            ->all();
    }

    // ------------------------------------------------------------ lapis aturan

    private function fromRules(string $text, array $context = []): array
    {
        $scan    = $this->scanner->scan($text);
        $signals = array_merge($this->contextSignals($context), $scan['signals']);
        $score   = max(0, min(100, $scan['score'] + $this->contextScoreShift($context)));

        $verdict = match (true) {
            $score >= HeuristicClaimScanner::FACT_AT => ClaimVerdict::Fact,
            $score <= HeuristicClaimScanner::HOAX_AT => ClaimVerdict::Hoax,
            default                                  => ClaimVerdict::Uncertain,
        };

        // Teks terlalu pendek untuk dinilai apa pun. Jangan mengarang.
        if (mb_strlen($text) < HeuristicClaimScanner::MIN_LENGTH) {
            $verdict = ClaimVerdict::Uncertain;
        }

        $risks  = collect($signals)->where('tone', 'risk')->values();
        $trusts = collect($signals)->where('tone', 'trust')->values();

        $reasons = $verdict === ClaimVerdict::Hoax
            ? $risks->take(4)->pluck('note')->all()
            : $trusts->take(4)->pluck('note')->all();

        if ($reasons === []) {
            $reasons = ['Tidak ada sinyal kuat yang bisa dijadikan dasar penilaian dari teks ini saja.'];
        }

        return [
            'verdict'        => $verdict->value,
            'confidence'     => $this->ruleConfidence($score, count($signals)),
            'summary'        => $this->ruleSummary($verdict, $score, $signals),
            'reasons'        => $reasons,
            'red_flags'      => $risks->take(6)->map(fn ($s) => ['label' => $s['label'], 'note' => $s['note']])->all(),
            'recommendation' => 'Cari berita pembanding dari media arus utama, cek tanggal terbit, dan pastikan ada nama penulis serta lembaga yang bisa dihubungi.',
            'signals'        => $signals,
            'excerpt'        => Str::limit($text, 600, '…'),
            'char_count'     => mb_strlen($text),
            'engine'         => 'rules',
            'model'          => null,
        ];
    }

    /**
     * Sinyal dari provenance (domain, atribusi, tanda visual) — bentuknya sama
     * dengan sinyal pemindai teks supaya UI tidak perlu cabang khusus.
     *
     * Bobotnya kecil dan sengaja: reputasi domain tidak membuktikan isi berita
     * benar, dan tangkapan layar tanpa logo belum tentu palsu.
     *
     * @return list<array{key:string, label:string, tone:string, note:string}>
     */
    private function contextSignals(array $context): array
    {
        $signals = [];

        $reputation = $context['reputation'] ?? null;

        if ($reputation !== null) {
            $signals[] = [
                'key'   => 'source_domain',
                'label' => (string) ($reputation['label'] ?? 'Sumber'),
                'tone'  => ($reputation['tier'] ?? 'unknown') === 'trusted' ? 'trust' : (($reputation['tier'] ?? '') === 'suspect' ? 'risk' : 'trust'),
                'note'  => (string) ($reputation['note'] ?? ''),
            ];
        }

        $attr = $context['attribution'] ?? null;

        if ($attr !== null && ! ($attr['author'] ?? false) && ! ($attr['date'] ?? false)) {
            $signals[] = [
                'key'   => 'no_attribution',
                'label' => 'Tanpa Atribusi',
                'tone'  => 'risk',
                'note'  => 'Halaman ini tidak mencantumkan nama penulis maupun tanggal terbit.',
            ];
        }

        foreach (array_slice($context['cues'] ?? [], 0, 5) as $i => $cue) {
            $signals[] = [
                'key'   => 'visual_'.$i,
                'label' => 'Sinyal visual',
                'tone'  => 'risk',
                'note'  => (string) $cue,
            ];
        }

        return $signals;
    }

    /** Pergeseran skor dari provenance. Maksimal ±15 poin — bukan penentu verdict. */
    private function contextScoreShift(array $context): int
    {
        $shift = 0;

        if ($reputation = $context['reputation'] ?? null) {
            $shift += (int) round(((int) ($reputation['score'] ?? 0)) * 1.5);
        }

        $attr = $context['attribution'] ?? null;

        if ($attr !== null && ! ($attr['author'] ?? false) && ! ($attr['date'] ?? false)) {
            $shift -= 6;
        }

        $shift -= min(10, count($context['cues'] ?? []) * 2);

        return max(-15, min(15, $shift));
    }

    /**
     * Keyakinan naik seiring jarak dari titik netral, DAN seiring jumlah
     * sinyal yang ditemukan. Teks tanpa sinyal sama sekali tidak boleh
     * keluar dengan keyakinan tinggi meski skornya pas 50.
     */
    private function ruleConfidence(int $score, int $signalCount): int
    {
        $distance = abs($score - 50);          // 0..50
        $base     = 30 + (int) round($distance * 1.1);
        $bonus    = min(15, $signalCount * 3);

        return max(30, min(92, $base + $bonus));
    }

    private function ruleSummary(ClaimVerdict $verdict, int $score, array $signals): string
    {
        $risks  = collect($signals)->where('tone', 'risk')->count();
        $trusts = collect($signals)->where('tone', 'trust')->count();

        return match ($verdict) {
            ClaimVerdict::Hoax => "Terdeteksi {$risks} sinyal manipulatif dan hanya {$trusts} penanda kredibel. Skor kredibilitas {$score}/100.",
            ClaimVerdict::Fact => "Terdeteksi {$trusts} penanda kredibel dan {$risks} sinyal manipulatif. Skor kredibilitas {$score}/100.",
            default            => "Sinyal saling bertentangan atau terlalu sedikit (risiko {$risks}, kredibel {$trusts}). Skor {$score}/100 — teks ini butuh pengecekan manual.",
        };
    }
}