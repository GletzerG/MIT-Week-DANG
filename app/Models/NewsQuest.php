<?php

namespace App\Models;

use App\Enums\QuestDifficulty;
use App\Enums\QuestVerdict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsQuest extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id', 'title', 'source_name', 'author', 'source_url', 'image_url',
        'excerpt', 'body', 'verdict', 'difficulty', 'category', 'locale',
        'credibility', 'fact_check_source', 'fact_check_url',
        'red_flags', 'ai_explanation', 'ai_model', 'ai_generated_at',
        'origin', 'title_hash', 'is_active', 'published_at',
    ];

    /**
     * Sabuk pengaman kedua. Resource layer sudah menyaring, tapi kalau ada
     * yang lupa (mis. endpoint debug, tinker dump), verdict tetap tidak bocor.
     */
    protected $hidden = ['verdict', 'ai_explanation', 'title_hash'];

    protected function casts(): array
    {
        return [
            'verdict'          => QuestVerdict::class,
            'difficulty'       => QuestDifficulty::class,
            'red_flags'        => 'array',
            'credibility'      => 'integer',
            'is_active'        => 'boolean',
            'published_at'     => 'datetime',
            'ai_generated_at'  => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(UserAttempt::class);
    }

    /** Pool quest aktif yang BELUM pernah dikerjakan user ini. */
    public function scopePlayableBy(Builder $query, User $user): Builder
    {
        return $query->where('is_active', true)
            ->whereNotIn('id', function ($sub) use ($user) {
                $sub->select('news_quest_id')
                    ->from('user_attempts')
                    ->where('user_id', $user->id)
                    ->where('is_correct', true);
            });
    }

    /** Level user menentukan bobot quest yang boleh muncul (matchmaking ringan). */
    public function scopeForLevel(Builder $query, int $level): Builder
    {
        return $query->where('difficulty', '<=', QuestDifficulty::ceilingForLevel($level)->value);
    }
}
