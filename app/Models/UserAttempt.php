<?php

namespace App\Models;

use App\Enums\QuestVerdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAttempt extends Model
{
    protected $fillable = [
        'user_id', 'news_quest_id', 'chosen_verdict', 'is_correct',
        'is_first_attempt', 'xp_delta', 'hp_delta', 'xp_before', 'xp_after',
        'streak_after', 'response_ms', 'ai_model', 'ai_fallback', 'ai_feedback',
    ];

    protected function casts(): array
    {
        return [
            'chosen_verdict'   => QuestVerdict::class,
            'is_correct'       => 'boolean',
            'is_first_attempt' => 'boolean',
            'ai_fallback'      => 'boolean',
            'ai_feedback'      => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(NewsQuest::class, 'news_quest_id');
    }
}
