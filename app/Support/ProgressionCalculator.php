<?php

namespace App\Support;

use App\Enums\GuardianRank;
use App\Enums\QuestDifficulty;

/**
 * Satu-satunya sumber kebenaran untuk matematika Level / XP / HP.
 *
 * KENAPA dipisah dari Model: rumus progresi dipakai di 4 tempat berbeda
 * (resolve attempt, resource API, job, seeder). Kalau rumusnya bercabang,
 * user bisa "naik level" di backend tapi XP bar di frontend tidak sinkron.
 * Satu class, satu rumus, semua pemanggil ikut.
 */
final class ProgressionCalculator
{
    /**
     * Total XP KUMULATIF yang dibutuhkan untuk BERADA di $level.
     *
     * Biaya naik dari level n -> n+1 = base + (n-1) * step
     *   L1->L2 = 100, L2->L3 = 150, L3->L4 = 200 ...
     *
     * Dijumlahkan jadi bentuk tertutup (closed form) supaya O(1), bukan loop:
     *   cumulative(n) = base*(n-1) + step*(n-1)*(n-2)/2
     */
    public function cumulativeXpForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        $base = (int) config('guardian.progression.base_level_cost', 100);
        $step = (int) config('guardian.progression.level_cost_step', 50);
        $n    = $level - 1;

        return $base * $n + intdiv($step * $n * ($n - 1), 2);
    }

    /** Biaya XP untuk naik dari $level ke $level + 1. */
    public function xpCostForLevel(int $level): int
    {
        $base = (int) config('guardian.progression.base_level_cost', 100);
        $step = (int) config('guardian.progression.level_cost_step', 50);

        return $base + ($level - 1) * $step;
    }

    /**
     * Level dari total XP.
     *
     * KENAPA bukan loop: user bisa punya jutaan XP, dan ini dipanggil tiap
     * request. Binary search -> O(log n), aman untuk level berapa pun.
     */
    public function levelFromXp(int $xp): int
    {
        $max = (int) config('guardian.progression.max_level', 60);

        if ($xp < $this->cumulativeXpForLevel(2)) {
            return 1;
        }

        $low  = 1;
        $high = $max;

        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);

            if ($this->cumulativeXpForLevel($mid) <= $xp) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }

        return min($low, $max);
    }

    /** XP yang sudah terkumpul DI DALAM level saat ini (0 .. xpCostForLevel). */
    public function xpIntoLevel(int $xp, int $level): int
    {
        return max(0, $xp - $this->cumulativeXpForLevel($level));
    }

    /** 0.0 .. 1.0 — langsung dipakai sebagai width XP bar. */
    public function progressRatio(int $xp): float
    {
        $level = $this->levelFromXp($xp);

        if ($this->isMaxLevel($level)) {
            return 1.0;
        }

        $into = $this->xpIntoLevel($xp, $level);
        $cost = max(1, $this->xpCostForLevel($level));

        return round(min(1.0, $into / $cost), 4);
    }

    public function isMaxLevel(int $level): bool
    {
        return $level >= (int) config('guardian.progression.max_level', 60);
    }

    public function rankFor(int $level): GuardianRank
    {
        return GuardianRank::fromLevel($level);
    }

    public function maxHp(): int
    {
        return (int) config('guardian.progression.max_hp', 100);
    }

    /**
     * XP delta untuk satu attempt.
     *
     * Benar : reward dasar + bonus streak (10% per beruntun, cap +100%)
     * Salah : -30% dari reward dasar (tetap belajar dari kesalahan, tapi ada harga)
     */
    public function xpDelta(bool $correct, QuestDifficulty $difficulty, int $streak = 0): int
    {
        $reward = $difficulty->baseReward();

        if (! $correct) {
            $ratio = (float) config('guardian.progression.wrong_answer_xp_penalty', 0.30);

            return -max(5, (int) round($reward * $ratio));
        }

        $perStep = (float) config('guardian.progression.streak_bonus_per_step', 0.10);
        $cap     = (float) config('guardian.progression.streak_bonus_cap', 1.00);
        $bonus   = min($cap, max(0, $streak) * $perStep);

        return (int) round($reward * (1 + $bonus));
    }

    public function hpDelta(bool $correct): int
    {
        if ($correct) {
            return 0;
        }

        return -(int) config('guardian.progression.wrong_answer_hp_cost', 20);
    }

    /**
     * HP regen lazy: tidak ada cron yang menaikkan HP tiap 30 menit.
     * HP dihitung dari selisih waktu saat dibaca. Lebih murah, lebih tahan
     * restart worker, dan tidak butuh scheduler untuk fitur kecil ini.
     */
    public function regenerateHp(int $currentHp, ?\DateTimeInterface $since, ?\DateTimeInterface $now = null): int
    {
        $max = $this->maxHp();

        if ($currentHp >= $max) {
            return $max;
        }

        if (! $since) {
            return $currentHp;
        }

        $now ??= now();

        $interval = (int) config('guardian.progression.hp_regen_minutes', 30);
        $amount   = (int) config('guardian.progression.hp_regen_amount', 10);

        $elapsed = max(0, $now->getTimestamp() - $since->getTimestamp());
        $ticks   = intdiv($elapsed, max(1, $interval * 60));

        return min($max, $currentHp + $ticks * $amount);
    }

    /** Snapshot lengkap untuk frontend — dipakai GuardianResource. */
    public function snapshot(int $xp, int $level, GuardianRank $rank): array
    {
        $level  = $this->isMaxLevel($level) ? $level : $this->levelFromXp($xp);
        $cost   = $this->xpCostForLevel($level);
        $into   = $this->xpIntoLevel($xp, $level);

        return [
            'level'          => $level,
            'rank'           => $rank->value,
            'rank_label'     => $rank->label(),
            'accent'         => $rank->accent(),
            'xp'             => $xp,
            'xp_into_level'  => $into,
            'xp_for_next'    => $this->isMaxLevel($level) ? 0 : max(0, $cost - $into),
            'xp_level_cost'  => $cost,
            'progress'       => $this->progressRatio($xp),
            'is_max_level'   => $this->isMaxLevel($level),
            'next_rank'      => $rank->next() ? [
                'value'     => $rank->next()->value,
                'label'     => $rank->next()->label(),
                'min_level' => $rank->next()->minLevel(),
            ] : null,
        ];
    }
}
