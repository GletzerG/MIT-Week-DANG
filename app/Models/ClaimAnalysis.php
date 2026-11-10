<?php

namespace App\Models;

use App\Enums\ClaimVerdict;
use Illuminate\Database\Eloquent\Model;

class ClaimAnalysis extends Model
{
    protected $fillable = [
        'text_hash', 'verdict', 'confidence', 'payload',
        'model', 'from_fallback', 'hits',
        'source_type', 'source_ref', 'source_label',
        'media_path', 'extracted_text', 'visual_cues',
    ];

    protected function casts(): array
    {
        return [
            'verdict'       => ClaimVerdict::class,
            'confidence'    => 'integer',
            'payload'       => 'array',
            'from_fallback' => 'boolean',
            'hits'          => 'integer',
            'visual_cues'   => 'array',
        ];
    }

    /** Teks yang dianalisis disimpan di payload, bukan kolom terpisah. */
    public function excerpt(int $limit = 180): string
    {
        $text = (string) ($this->payload['excerpt'] ?? '');

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit).'…' : $text;
    }
}