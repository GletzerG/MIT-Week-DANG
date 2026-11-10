<?php

namespace App\Enums;

/**
 * Tiga hasil, bukan dua.
 *
 * "Tidak pasti" itu jawaban yang jujur. Memaksa AI memilih fakta/hoax pada
 * teks yang minim bukti adalah sumber kesalahan paling umum di alat deteksi
 * hoax — dan justru melatih remaja jadi terlalu percaya pada mesin.
 */
enum ClaimVerdict: string
{
    case Fact      = 'fact';
    case Hoax      = 'hoax';
    case Uncertain = 'uncertain';

    public function label(): string
    {
        return match ($this) {
            self::Fact      => 'Fakta',
            self::Hoax      => 'Hoax',
            self::Uncertain => 'Belum Pasti',
        };
    }

    public function headline(): string
    {
        return match ($this) {
            self::Fact      => 'SINYAL KREDIBEL',
            self::Hoax      => 'SINYAL MANIPULATIF',
            self::Uncertain => 'BUKTI TIDAK CUKUP',
        };
    }

    /** Token warna ZZZ: acid / magenta / amber. */
    public function tone(): string
    {
        return match ($this) {
            self::Fact      => 'acid',
            self::Hoax      => 'magenta',
            self::Uncertain => 'amber',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Fact      => 'check',
            self::Hoax      => 'cross',
            self::Uncertain => 'question',
        };
    }
}