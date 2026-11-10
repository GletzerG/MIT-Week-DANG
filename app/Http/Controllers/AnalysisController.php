<?php

namespace App\Http\Controllers;

use App\Http\Resources\GuardianResource;
use App\Models\ClaimAnalysis;
use App\Services\Ai\LlmManager;
use App\Services\ClaimAnalysisService;
use App\Services\Inspection\ImageInspector;
use App\Services\Inspection\InspectionException;
use App\Services\Inspection\LinkInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stasiun deteksi klaim.
 *
 * Tiga pintu masuk, satu mesin penilai:
 *
 *   1. teks   — pesan berantai, caption, potongan artikel
 *   2. gambar — tangkapan layar berita / postingan (dibaca model visual dulu)
 *   3. tautan — URL artikel (halaman diambil, isinya diekstrak, domain dinilai)
 *
 * Dua pintu tambahan itu BUKAN penilai baru. Mereka hanya mengubah input
 * menjadi teks + konteks provenance, lalu menyerahkannya ke
 * ClaimAnalysisService yang sama. Jadi tidak pernah ada dua definisi "hoax".
 */
class AnalysisController extends Controller
{
    /** Batas XP harian supaya alat ini tidak bisa dipakai farming. */
    private const XP_PER_ANALYSIS = 8;
    private const XP_DAILY_CAP    = 5;

    public function index(Request $request): Response
    {
        $llm = app(LlmManager::class);

        return Inertia::render('Analysis/Index', [
            'guardian' => new GuardianResource($request->user()),

            'ai' => [
                'enabled'     => $llm->enabled(),
                // Nama model yang benar-benar dipanggil, bukan nama driver.
                'model'       => $llm->enabled() ? $llm->model() : null,
                'vision'      => $llm->visionEnabled(),
                'visionModel' => $llm->visionEnabled() ? $llm->visionModel() : null,
            ],

            'limits' => [
                'min'         => 24,
                'max'         => 5000,
                'image_kb'    => (int) config('guardian.inspection.image.max_kb', 6144),
                'image_mimes' => array_values((array) config('guardian.inspection.image.mimes', ['png', 'jpeg', 'webp'])),
                'url'         => 2048,
            ],

            'quota' => [
                'used' => $this->dailyCount($request),
                'cap'  => self::XP_DAILY_CAP,
                'each' => self::XP_PER_ANALYSIS,
            ],

            'recent' => ClaimAnalysis::query()
                ->latest('updated_at')
                ->take(4)
                ->get()
                ->map(fn (ClaimAnalysis $a) => [
                    'key'        => $a->text_hash,
                    'excerpt'    => $a->excerpt(120),
                    'verdict'    => $a->verdict->label(),
                    'tone'       => $a->verdict->tone(),
                    'confidence' => $a->confidence,
                    'type'       => $a->source_type ?: 'text',
                    'label'      => $a->source_label,
                ])
                ->all(),
        ]);
    }

    /**
     * JSON, bukan Inertia redirect: hasil analisis menggerakkan animasi
     * (scan line, reveal kartu, bar keyakinan) yang tidak boleh ter-reset.
     */
    public function store(
        Request $request,
        ClaimAnalysisService $service,
        ImageInspector $images,
        LinkInspector $links,
    ): JsonResponse {
        $mode = (string) $request->input('mode', 'text');

        try {
            [$text, $context] = match ($mode) {
                'image' => $this->fromImage($request, $images),
                'link'  => $this->fromLink($request, $links),
                default => $this->fromText($request),
            };
        } catch (InspectionException $e) {
            // Bukan 500: ini jawaban jujur yang harus dibaca user.
            return response()->json([
                'message' => $e->getMessage(),
                'mode'    => $mode,
            ], 422);
        }

        $analysis = $service->analyze($text, false, $context);
        $xp       = $this->awardXp($request, $request->user());

        return response()->json([
            'verdict' => [
                'value'    => $analysis->verdict->value,
                'label'    => $analysis->verdict->label(),
                'headline' => $analysis->verdict->headline(),
                'tone'     => $analysis->verdict->tone(),
                'icon'     => $analysis->verdict->icon(),
            ],
            'confidence'     => $analysis->confidence,
            'summary'        => $analysis->payload['summary'] ?? '',
            'reasons'        => $analysis->payload['reasons'] ?? [],
            'red_flags'      => $analysis->payload['red_flags'] ?? [],
            'recommendation' => $analysis->payload['recommendation'] ?? '',
            'signals'        => $analysis->payload['signals'] ?? [],
            'engine'         => $analysis->payload['engine'] ?? 'rules',
            'model'          => $analysis->model,
            'from_fallback'  => $analysis->from_fallback,
            'char_count'     => (int) ($analysis->payload['char_count'] ?? mb_strlen($text)),

            // Provenance: dari mana klaim ini datang, dan apa yang dibaca mesin.
            'source' => $this->sourcePayload($analysis, $context, $text),

            'xp_awarded' => $xp,
            'guardian'   => (new GuardianResource($request->user()->fresh()))->resolve(),
            'quota'      => [
                'used' => $this->dailyCount($request),
                'cap'  => self::XP_DAILY_CAP,
            ],
        ]);
    }

    // ------------------------------------------------------------------- modes

    /**
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function fromText(Request $request): array
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'min:24', 'max:5000'],
        ], [
            'text.min' => 'Tempel minimal 24 karakter supaya bisa dinilai.',
            'text.max' => 'Maksimal 5000 karakter sekali analisis.',
        ]);

        return [$data['text'], ['type' => 'text']];
    }

    /**
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function fromImage(Request $request, ImageInspector $images): array
    {
        $maxKb = (int) config('guardian.inspection.image.max_kb', 6144);
        $mimes = implode(',', (array) config('guardian.inspection.image.mimes', ['png', 'jpeg', 'webp']));

        $request->validate([
            'image' => ['required', 'file', 'mimes:'.$mimes, 'max:'.$maxKb],
        ], [
            'image.required' => 'Pilih gambar dulu.',
            'image.mimes'    => 'Format yang didukung: PNG, JPG, WEBP.',
            'image.max'      => 'Gambar terlalu besar (maksimal '.round($maxKb / 1024).' MB).',
        ]);

        $file   = $request->file('image');
        $result = $images->inspect($file);

        // Judul ikut dinilai: di tangkapan layar berita, judul justru bagian
        // yang paling sering memuat klaimnya.
        $text = trim(($result['headline'] !== '' ? $result['headline']."\n\n" : '').$result['text']);

        return [$text, [
            'type'       => 'image',
            'label'      => $file->getClientOriginalName(),
            'title'      => $result['headline'] ?: null,
            'media_path' => $result['path'],
            'cues'       => $result['cues'],
            'meta'       => [
                'kind'   => $result['kind'],
                'width'  => $result['width'],
                'height' => $result['height'],
                'model'  => $result['model'],
            ],
        ]];
    }

    /**
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function fromLink(Request $request, LinkInspector $links): array
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ], [
            'url.required' => 'Tempel tautan artikelnya dulu.',
            'url.max'      => 'Tautan terlalu panjang.',
        ]);

        $page = $links->inspect($data['url']);

        $text = trim($page['title']."\n\n".$page['body']);

        return [$text, [
            'type'        => 'link',
            'ref'         => $page['url'],
            'label'       => $page['host'],
            'title'       => $page['title'],
            'reputation'  => $page['reputation'],
            'attribution' => $page['attribution'],
            'cues'        => [],
            'meta'        => [
                'site'         => $page['site'],
                'author'       => $page['author'],
                'published_at' => $page['published_at'],
                'chars'        => $page['chars'],
                'status'       => $page['status'],
            ],
        ]];
    }

    // -------------------------------------------------------------- provenance

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sourcePayload(ClaimAnalysis $analysis, array $context, string $text): array
    {
        return [
            'type'        => $context['type'] ?? 'text',
            'ref'         => $context['ref'] ?? null,
            'label'       => $context['label'] ?? null,
            'title'       => $context['title'] ?? null,
            'reputation'  => $context['reputation'] ?? null,
            'attribution' => $context['attribution'] ?? null,
            'cues'        => $context['cues'] ?? [],
            'meta'        => $context['meta'] ?? [],
            // Teks yang benar-benar dinilai. Untuk gambar ini hasil pembacaan
            // mesin — user harus bisa melihat (dan menyalahkan) apa yang dibaca.
            'text'        => mb_substr($text, 0, 1800),
            'chars'       => mb_strlen($text),
            'cached'      => $analysis->hits > 1,
        ];
    }

    // ------------------------------------------------------------------ internal

    /** XP kecil untuk apresiasi belajar, dibatasi per hari. */
    private function awardXp(Request $request, $user): int
    {
        $key  = $this->quotaKey($request);
        $used = (int) Cache::get($key, 0);

        if ($used >= self::XP_DAILY_CAP) {
            return 0;
        }

        Cache::put($key, $used + 1, now()->endOfDay());

        $user->xp += self::XP_PER_ANALYSIS;
        $user->save();
        $user->syncProgression();

        return self::XP_PER_ANALYSIS;
    }

    private function dailyCount(Request $request): int
    {
        return (int) Cache::get($this->quotaKey($request), 0);
    }

    private function quotaKey(Request $request): string
    {
        return 'guardian:analysis-xp:'.$request->user()->id.':'.now()->toDateString();
    }
}