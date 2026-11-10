<?php

namespace App\Enums;

enum QuestVerdict: string
{
    case Fact = 'fact';
    case Hoax = 'hoax';

    public function label(): string
    {
        return match ($this) {
            self::Fact => 'Fakta',
            self::Hoax => 'Hoax',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Fact => 'shield-check',
            self::Hoax => 'skull',
        };
    }

    public static function fromAnswer(string $answer): ?self
    {
        return self::tryFrom(strtolower(trim($answer)));
    }
}
