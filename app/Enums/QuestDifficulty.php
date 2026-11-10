<?php

namespace App\Enums;

enum QuestDifficulty: int
{
    case Novice      = 1;
    case Seeker      = 2;
    case Knight      = 3;
    case Grandmaster = 4;

    public function key(): string
    {
        return match ($this) {
            self::Novice      => 'novice',
            self::Seeker      => 'seeker',
            self::Knight      => 'knight',
            self::Grandmaster => 'grandmaster',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Novice      => 'Novice',
            self::Seeker      => 'Seeker',
            self::Knight      => 'Truth Knight',
            self::Grandmaster => 'Grandmaster',
        };
    }

    /** Reward dasar (XP) sebelum bonus streak. */
    public function baseReward(): int
    {
        return (int) config("guardian.rewards.{$this->key()}", 30);
    }

    /**
     * Kesulitan tertinggi yang boleh MUNCUL untuk seorang user.
     *
     * KENAPA dipusatkan di sini: sebelumnya scopeForLevel() dan guard di
     * controller memakai aturan yang sedikit berbeda, jadi user bisa melihat
     * quest yang lalu ditolak 403 saat dijawab. Satu fungsi, dua pemakai.
     */
    public static function ceilingForLevel(int $level): self
    {
        $allowed = self::Novice;

        foreach (self::cases() as $case) {
            if ($case->minLevel() <= $level + 3) {
                $allowed = $case;
            }
        }

        return $allowed;
    }

    /** Level minimum yang disarankan untuk menerima quest ini. */
    public function minLevel(): int
    {
        return match ($this) {
            self::Novice      => 1,
            self::Seeker      => 5,
            self::Knight      => 12,
            self::Grandmaster => 25,
        };
    }
}
