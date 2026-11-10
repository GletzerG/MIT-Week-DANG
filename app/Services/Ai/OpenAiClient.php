<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\LlmClient;
use Illuminate\Support\Facades\Http;

class OpenAiClient implements LlmClient
{
    public function __construct(private array $config) {}

    public function driver(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    public function complete(string $system, string $prompt, array $options = []): array
    {
        $key = $this->config['key'] ?? null;

        if (blank($key)) {
            throw LlmException::missingKey('openai');
        }

        $payload = [
            'model'       => $this->model(),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens'  => $options['max_tokens'] ?? 700,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if ($options['json'] ?? false) {
            // Mode JSON: model dipaksa mengembalikan objek valid, bukan prosa
            // yang harus kita parsing dengan regex. Ini menghilangkan 90% bug
            // "LLM balas pakai ```json fence" saat generate konten.
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken($key)
            ->timeout((int) config('guardian.ai.timeout', 20))
            ->acceptJson()
            ->post(rtrim($this->config['base_url'], '/').'/chat/completions', $payload);

        if ($response->failed()) {
            throw LlmException::badResponse('openai', $response->status().' '.$response->body());
        }

        return [
            'text'   => (string) data_get($response->json(), 'choices.0.message.content', ''),
            'model'  => (string) data_get($response->json(), 'model', $this->model()),
            'tokens' => data_get($response->json(), 'usage.total_tokens'),
        ];
    }

    public function json(string $system, string $prompt, array $options = []): array
    {
        $result = $this->complete($system, $prompt, $options + ['json' => true]);

        return JsonBlock::decode($result['text']);
    }

    public function visionModel(): string
    {
        return (string) ($this->config['vision_model'] ?? '') ?: $this->model();
    }

    public function vision(string $system, string $prompt, array $images, array $options = []): array
    {
        $key = $this->config['key'] ?? null;

        if (blank($key)) {
            throw LlmException::missingKey('openai');
        }

        $content = [['type' => 'text', 'text' => $prompt]];

        foreach ($images as $uri) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $uri]];
        }

        $payload = [
            'model'       => $this->visionModel(),
            'temperature' => $options['temperature'] ?? 0.1,
            'max_tokens'  => $options['max_tokens'] ?? 900,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $content],
            ],
        ];

        $response = Http::withToken($key)
            ->timeout((int) config('guardian.ai.vision_timeout', 60))
            ->acceptJson()
            ->post(rtrim($this->config['base_url'], '/').'/chat/completions', $payload);

        if ($response->failed()) {
            throw LlmException::badResponse('openai.vision', $response->status().' '.mb_substr($response->body(), 0, 300));
        }

        return [
            'text'   => (string) data_get($response->json(), 'choices.0.message.content', ''),
            'model'  => (string) data_get($response->json(), 'model', $this->visionModel()),
            'tokens' => data_get($response->json(), 'usage.total_tokens'),
        ];
    }
}
