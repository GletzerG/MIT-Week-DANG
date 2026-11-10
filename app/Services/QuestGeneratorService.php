<?php

namespace App\Services;

use App\Enums\QuestDifficulty;
use App\Enums\QuestVerdict;
use App\Models\NewsQuest;
use App\Services\Ai\LlmManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ============================================================
 *  INTEGRASI AI — PRODUKSI KONTEN HARIAN (CRON)
 * ============================================================
 *
 * Dipanggil oleh App\Jobs\GenerateDailyQuests (scheduler harian).
 * LLM membuat batch berita fiktif baru supaya konten tidak repetitif.
 *
 * Prinsip yang dipegang:
 *  1. SEMUA tokoh/tempat/instansi fiktif — tidak ada nama nyata.
 *  2. Output divalidasi ketat sebelum masuk DB (enum, panjang, jumlah flag).
 *  3. Dedupe via title_hash — LLM sering mengulang topik yang sama.
 *  4. Kalau LLM gagal total, job tidak crash: cukup log & selesai.
 */
final class QuestGeneratorService
{
    private const CATEGORIES = [
        'kesehatan', 'politik', 'sains', 'teknologi',
        'selebriti', 'keuangan', 'bencana', 'kriminal',
    ];

    public function __construct(private LlmManager $llm) {}

    /**
     * @return Collection<int, NewsQuest>
     */
    public function generate(int $count = 6): Collection
    {
        if (! $this->llm->enabled()) {
            Log::info('guardian.generator.skipped', ['reason' => 'llm disabled']);

            return collect();
        }

        $raw = $this->llm->safeJson($this->systemPrompt(), $this->userPrompt($count), [
            'temperature' => 0.95, // tinggi: kita ingin variasi, bukan konsistensi
            'max_tokens'  => 3000,
        ]);

        if ($raw === null) {
            Log::warning('guardian.generator.failed');

            return collect();
        }

        $items = $raw['quests'] ?? $raw ?? [];

        return collect($items)
            ->map(fn ($item) => $this->normalize($item))
            ->filter()
            ->reject(fn ($data) => $this->isDuplicate($data['title_hash']))
            ->take($count)
            ->map(fn ($data) => NewsQuest::create($data))
            ->values();
    }

    /**
     * Validasi + normalisasi satu item mentah dari LLM.
     * Mengembalikan null kalau item tidak layak tayang.
     */
    private function normalize(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $title = Str::limit(trim((string) ($item['title'] ?? '')), 180, '');

        if (mb_strlen($title) < 12) {
            return null;
        }

        $verdict = QuestVerdict::fromAnswer((string) ($item['verdict'] ?? ''));

        if (! $verdict) {
            return null;
        }

        $difficulty = (int) ($item['difficulty'] ?? 1);
        $difficulty = QuestDifficulty::tryFrom($difficulty) ?? QuestDifficulty::Novice;

        $category = strtolower(trim((string) ($item['category'] ?? 'teknologi')));
        $category = in_array($category, self::CATEGORIES, true) ? $category : 'teknologi';

        $excerpt = trim((string) ($item['excerpt'] ?? ''));

        if (mb_strlen($excerpt) < 60) {
            return null;
        }

        $flags = collect($item['red_flags'] ?? [])
            ->filter(fn ($f) => is_array($f) && filled($f['note'] ?? null))
            ->map(fn ($f) => [
                'type'  => Str::slug((string) ($f['type'] ?? 'signal'), '_'),
                'label' => Str::limit((string) ($f['label'] ?? 'Sinyal'), 48, ''),
                'note'  => Str::limit((string) $f['note'], 200, ''),
            ])
            ->take(4)
            ->values()
            ->all();

        return [
            'public_id'    => (string) Str::ulid(),
            'title'        => $title,
            'source_name'  => Str::limit((string) ($item['source_name'] ?? 'Portal Nusantara'), 80, ''),
            'source_url'   => null, // sengaja null: sumber fiktif tidak boleh jadi tautan hidup
            'image_url'    => null,
            'excerpt'      => $excerpt,
            'body'         => null,
            'verdict'      => $verdict,
            'difficulty'   => $difficulty,
            'category'     => $category,
            'locale'       => 'id',
            'red_flags'    => $flags,
            'origin'       => 'ai',
            'title_hash'   => hash('sha256', Str::lower($title)),
            'is_active'    => true,
            'published_at' => now(),
        ];
    }

    private function isDuplicate(string $hash): bool
    {
        return NewsQuest::where('title_hash', $hash)->exists();
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
Kamu adalah "Genesis Quest Forge", generator konten untuk platform edukasi
literasi media remaja Indonesia (12-18 tahun).

Buat berita FIKTIF yang realistis dan mendidik. ATURAN KERAS:
- Semua nama orang, media, instansi, perusahaan, dan tempat harus FIKTIF.
- Jangan gunakan tokoh politik nyata, merek nyata, atau peristiwa nyata.
- Jangan memuat instruksi berbahaya, dosis obat, atau cara membuat sesuatu.
- Hoax harus hoax yang umum di Indonesia (pesan berantai, clickbait kesehatan,
  foto suntingan, klaim tanpa sumber, judul provokatif).
- Fakta harus terlihat kredibel: sumber jelas, ada data, nada netral.

Balas HANYA JSON valid:
{
  "quests": [
    {
      "title": "judul berita, 40-140 karakter",
      "source_name": "nama media fiktif",
      "excerpt": "cuplikan 2-4 kalimat, 120-500 karakter",
      "verdict": "fact" | "hoax",
      "difficulty": 1 | 2 | 3 | 4,
      "category": "kesehatan|politik|sains|teknologi|selebriti|keuangan|bencana|kriminal",
      "red_flags": [
        {"type": "clickbait", "label": "Judul Clickbait", "note": "penjelasan 1 kalimat"}
      ]
    }
  ]
}
Untuk verdict "fact", red_flags berisi sinyal kredibilitas (bukan manipulasi),
contoh type: "clear_source", "verifiable_data", "named_author", "neutral_tone".
TXT;
    }

    private function userPrompt(int $count): string
    {
        $mixHoax = (int) ceil($count * 0.6);

        return "Buat {$count} quest. Sekitar {$mixHoax} berita hoax dan sisanya fakta. "
            .'Sebar difficulty dari 1 sampai 4. Jangan ulangi topik yang sama.';
    }
}
