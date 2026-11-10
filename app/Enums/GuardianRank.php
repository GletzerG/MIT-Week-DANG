<?php

namespace App\Enums;

enum GuardianRank: string
{
    case NoviceGuardian       = 'novice_guardian';
    case Seeker               = 'seeker';
    case TruthKnight          = 'truth_knight';
    case InquisitorOfGenesis  = 'inquisitor_of_genesis';
    case GrandmasterOfGenesis = 'grandmaster_of_genesis';

    public function label(): string
    {
        return match ($this) {
            self::NoviceGuardian       => 'Novice Guardian',
            self::Seeker               => 'Seeker',
            self::TruthKnight          => 'Truth Knight',
            self::InquisitorOfGenesis  => 'Inquisitor of Genesis',
            self::GrandmasterOfGenesis => 'Grandmaster of Genesis',
        };
    }

    public function minLevel(): int
    {
        return match ($this) {
            self::NoviceGuardian       => 1,
            self::Seeker               => 5,
            self::TruthKnight          => 10,
            self::InquisitorOfGenesis  => 20,
            self::GrandmasterOfGenesis => 35,
        };
    }

    /** Warna aksen UI — dipakai frontend untuk tint progress bar & badge. */
    public function accent(): string
    {
        return match ($this) {
            self::NoviceGuardian       => '#8b9bb4',
            self::Seeker               => '#22d3ee',
            self::TruthKnight          => '#38bdf8',
            self::InquisitorOfGenesis  => '#a855f7',
            self::GrandmasterOfGenesis => '#f5b942',
        };
    }

    public static function fromLevel(int $level): self
    {
        $rank = self::NoviceGuardian;

        foreach (self::cases() as $candidate) {
            if ($level >= $candidate->minLevel()) {
                $rank = $candidate;
            }
        }

        return $rank;
    }

    /** Rank berikutnya + level syaratnya, untuk progress "menuju rank". */
    public function next(): ?self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, true);

        return $cases[$index + 1] ?? null;
    }
}
