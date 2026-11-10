<?php

namespace App\Services\Ai;

use RuntimeException;

/** Dilempar oleh driver LLM. Selalu ditangkap oleh NewsAnalysisService. */
class LlmException extends RuntimeException
{
    public static function missingKey(string $driver): self
    {
        return new self("API key untuk driver [{$driver}] belum di-set.");
    }

    public static function badResponse(string $driver, string $snippet = ''): self
    {
        return new self("Respons tidak valid dari [{$driver}]".($snippet ? ': '.$snippet : ''));
    }

    public static function visionDisabled(): self
    {
        return new self('Model visual belum dikonfigurasi. Isi OPENAI_VISION_MODEL atau pakai provider yang multimodal.');
    }
}
