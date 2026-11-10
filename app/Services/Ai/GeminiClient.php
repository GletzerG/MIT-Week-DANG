<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\LlmClient;
use Illuminate\Support\Facades\Http;

class GeminiClient implements LlmClient
{
    public function __construct(private array $config) {}

    public function driver(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        return (string) ($this->config['model'] ?? 'gemini-2.0-flash');
    }

    public function complete(string $system, string $prompt, array $options = []): array
    {
        $key = $this->config['key'] ?? null;

        if (blank($key)) {
            throw LlmException::missingKey('gemini');
        }

        $url = sprintf(
            '%s/models/%s:generateContent',
            rtrim($this->config['base_url'], '/'),
            $this->model(),
        );

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $system]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature'      => $options['temperature'] ?? 0.7,
                'maxOutputTokens'  => $options['max_tokens'] ?? 700,
            ],
        ];

        if ($options['json'] ?? false) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        $response = Http::withHeaders(['x-goog-api-key' => $key])
            ->timeout((int) config('guardian.ai.timeout', 20))
            ->acceptJson()
            ->post($url, $payload);

        if ($response->failed()) {
            throw LlmException::badResponse('gemini', $response->status().' '.$response->body());
        }

        $parts = data_get($response->json(), 'candidates.0.content.parts', []);
        $text  = collect($parts)->pluck('text')->filter()->implode("\n");

        return [
            'text'   => $text,
            'model'  => $this->model(),
            'tokens' => data_get($response->json(), 'usageMetadata.totalTokenCount'),
        ];
    }

    public function json(string $system, string $prompt, array $options = []): array
    {
        $result = $this->complete($system, $prompt, $options + ['json' => true]);

        return JsonBlock::decode($result['text']);
    }

    public function visionModel(): string
    {
        // Model Gemini 2.x flash/pro sudah multimodal — tidak perlu model terpisah.
        return (string) ($this->config['vision_model'] ?? '') ?: $this->model();
    }

    public function vision(string $system, string $prompt, array $images, array $options = []): array
    {
        $key = $this->config['key'] ?? null;

        if (blank($key)) {
            throw LlmException::missingKey('gemini');
        }

        $parts = [['text' => $prompt]];

        foreach ($images as $uri) {
            // "data:image/jpeg;base64,XXXX" → inlineData
            if (! preg_match('~^data:(?<mime>[^;]+);base64,(?<data>.+)$~s', $uri, $m)) {
                continue;
            }

            $parts[] = ['inlineData' => ['mimeType' => $m['mime'], 'data' => $m['data']]];
        }

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents'          => [['role' => 'user', 'parts' => $parts]],
            'generationConfig'  => [
                'temperature'     => $options['temperature'] ?? 0.1,
                'maxOutputTokens' => $options['max_tokens'] ?? 900,
            ],
        ];

        $url = sprintf(
            '%s/models/%s:generateContent',
            rtrim($this->config['base_url'], '/'),
            $this->visionModel(),
        );

        $response = Http::withHeaders(['x-goog-api-key' => $key])
            ->timeout((int) config('guardian.ai.vision_timeout', 60))
            ->acceptJson()
            ->post($url, $payload);

        if ($response->failed()) {
            throw LlmException::badResponse('gemini.vision', $response->status().' '.mb_substr($response->body(), 0, 300));
        }

        $text = collect((array) data_get($response->json(), 'candidates.0.content.parts', []))
            ->pluck('text')
            ->filter()
            ->implode("\n");

        return [
            'text'   => $text,
            'model'  => $this->visionModel(),
            'tokens' => data_get($response->json(), 'usageMetadata.totalTokenCount'),
        ];
    }
}
