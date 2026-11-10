<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk payload untuk FEED TERVERIFIKASI.
 *
 * Beda tegas dengan NewsQuestResource:
 *   - Feed  → verdict DITAMPILKAN. Tujuannya mengajari pola, bukan menguji.
 *   - Trial → verdict disembunyikan. Tujuannya menguji.
 *
 * Dua resource terpisah supaya tidak ada developer yang "lupa" dan
 * menumpang verdict ke halaman quest.
 */
class VerifiedNewsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->public_id,
            'title'      => $this->title,
            'source'     => $this->source_name,
            'author'     => $this->author,
            'excerpt'    => $this->excerpt,
            'image_url'  => $this->image_url,
            'category'   => $this->category,
            'published_at' => $this->published_at?->toIso8601String(),

            'verdict' => [
                'value' => $this->verdict->value,
                'label' => $this->verdict->label(),
                'tone'  => $this->verdict->value === 'hoax' ? 'magenta' : 'acid',
            ],

            'credibility' => (int) $this->credibility,

            'fact_check' => [
                'source' => $this->fact_check_source,
                'url'    => $this->fact_check_url,
            ],

            'red_flags' => collect($this->red_flags ?? [])
                ->map(fn ($f) => [
                    'label' => (string) ($f['label'] ?? $f['type'] ?? 'Sinyal'),
                    'note'  => (string) ($f['note'] ?? ''),
                ])
                ->values()
                ->all(),

            'difficulty' => [
                'value' => $this->difficulty->value,
                'label' => $this->difficulty->label(),
            ],
        ];
    }
}