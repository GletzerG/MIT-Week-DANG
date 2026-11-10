<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\LlmClient;
use Illuminate\Support\Facades\Log;

/**
 * Facade tipis di atas driver LLM.
 *
 * Tanggung jawabnya: pilih driver dari config, dan JANGAN pernah melempar
 * error ke controller game. LLM adalah fitur tambahan — kalau mati, quest
 * tetap harus bisa dijawab. Karena itu ada safeJson().
 */
class LlmManager implements LlmClient
{
    public function __construct(private ?LlmClient $driver = null) {}

    public function driver(): string
    {
        return $this->resolve()?->driver() ?? 'none';
    }

    public function model(): string
    {
        return $this->resolve()?->model() ?? 'none';
    }

    public static function make(): self
    {
        $name = (string) config('guardian.ai.driver', 'openai');

        $client = match ($name) {
            'openai' => new OpenAiClient((array) config('guardian.ai.openai')),
            'gemini' => new GeminiClient((array) config('guardian.ai.gemini')),
            default  => null,
        };

        return new self($client);
    }

    public function enabled(): bool
    {
        return $this->resolve() !== null;
    }

    private function resolve(): ?LlmClient
    {
        return $this->driver;
    }

    public function complete(string $system, string $prompt, array $options = []): array
    {
        $client = $this->resolve();

        if (! $client) {
            throw LlmException::missingKey('none');
        }

        return $client->complete($system, $prompt, $options);
    }

    public function json(string $system, string $prompt, array $options = []): array
    {
        $client = $this->resolve();

        if (! $client) {
            throw LlmException::missingKey('none');
        }

        return $client->json($system, $prompt, $options);
    }

    /**
     * Versi yang tidak akan pernah melempar exception.
     * Return null = caller wajib pakai fallback deterministik.
     *
     * @return array<string, mixed>|null
     */
    public function safeJson(string $system, string $prompt, array $options = []): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            return $this->json($system, $prompt, $options);
        } catch (LlmException $e) {
            Log::warning('guardian.ai.failed', [
                'driver' => $this->driver(),
                'error'  => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('guardian.ai.unexpected', [
                'driver'  => $this->driver(),
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // ------------------------------------------------------------------ vision

    public function visionModel(): string
    {
        return $this->resolve()?->visionModel() ?? 'none';
    }

    /**
     * Apakah ada model yang benar-benar bisa melihat gambar?
     *
     * Driver openai/compatible TIDAK otomatis multimodal: model teks murah
     * sering menolak content block berisi image_url. Karena itu model vision
     * harus dinyatakan eksplisit di config, bukan diasumsikan.
     */
    public function visionEnabled(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if ($this->driver() === 'gemini') {
            return true;
        }

        return filled(config('guardian.ai.openai.vision_model'))
            && filled(config('guardian.ai.openai.key'));
    }

    public function vision(string $system, string $prompt, array $images, array $options = []): array
    {
        $client = $this->resolve();

        if (! $client || ! $this->visionEnabled()) {
            throw LlmException::visionDisabled();
        }

        return $client->vision($system, $prompt, $images, $options);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function safeVisionJson(string $system, string $prompt, array $images, array $options = []): ?array
    {
        if (! $this->visionEnabled()) {
            return null;
        }

        // Model murah sesekali balik dengan konten kosong (semua token habis
        // untuk berpikir). Satu percobaan ulang jauh lebih murah daripada
        // membuat user menekan tombol scan lagi.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $result = $this->vision($system, $prompt, $images, $options);
                $decoded = JsonBlock::decode($result['text']);

                if ($decoded !== []) {
                    return $decoded;
                }
            } catch (\Throwable $e) {
                Log::warning('guardian.ai.vision-failed', [
                    'model'   => $this->visionModel(),
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }
}
