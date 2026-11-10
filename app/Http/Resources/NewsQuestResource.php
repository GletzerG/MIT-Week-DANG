<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PENTING: resource ini adalah SATU-SATUNYA bentuk quest yang dilihat browser.
 * Kolom `verdict` dan `ai_explanation` sengaja tidak ada di sini.
 *
 * Kalau verdict ikut terkirim, user cukup buka DevTools > Network dan seluruh
 * game kehilangan maknanya. Ini bukan sekadar "best practice" — ini fondasinya.
 */
class NewsQuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->public_id,
            'title'       => $this->title,
            'source'      => $this->source_name,
            'excerpt'     => $this->excerpt,
            'image_url'   => $this->image_url,
            'category'    => $this->category,
            'difficulty'  => [
                'value' => $this->difficulty->value,
                'key'   => $this->difficulty->key(),
                'label' => $this->difficulty->label(),
                'reward'=> $this->difficulty->baseReward(),
            ],
            'published_at' => $this->published_at?->toIso8601String(),
            'ai_ready'     => filled($this->ai_explanation),
        ];
    }
}
