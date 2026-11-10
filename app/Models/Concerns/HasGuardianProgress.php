<?php

namespace App\Models\Concerns;

use App\Enums\GuardianRank;
use App\Models\NewsQuest;
use App\Models\UserAttempt;
use App\Support\ProgressionCalculator;

trait HasGuardianProgress
{
    public function attempts()
    {
        return $this->hasMany(UserAttempt::class);
    }

    public function solvedQuests()
    {
        return $this->belongsToMany(NewsQuest::class, 'user_attempts')
            ->wherePivot('is_correct', true);
    }

    /** Apakah quest ini sudah pernah dijawab BENAR oleh user? */
    public function hasSolved(NewsQuest $quest): bool
    {
        return $this->attempts()
            ->where('news_quest_id', $quest->id)
            ->where('is_correct', true)
            ->exists();
    }

    public function calculator(): ProgressionCalculator
    {
        return app(ProgressionCalculator::class);
    }

    /**
     * HP di-regenerate secara lazy saat dibaca — tidak perlu scheduler.
     * Ini dipanggil di accessor supaya semua tempat otomatis dapat HP terbaru.
     */
    public function currentHp(): int
    {
        return $this->calculator()->regenerateHp(
            (int) $this->hp,
            $this->hp_updated_at,
        );
    }

    /** Tulis ulang kolom level & rank dari XP. Idempoten, aman dipanggil berkali-kali. */
    public function syncProgression(): void
    {
        $calc  = $this->calculator();
        $level = $calc->levelFromXp((int) $this->xp);

        $this->forceFill([
            'level' => $level,
            'rank'  => $calc->rankFor($level),
        ])->save();
    }

    public function isAtRank(GuardianRank $rank): bool
    {
        return $this->rank === $rank->value;
    }
}
