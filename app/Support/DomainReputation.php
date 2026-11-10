<?php

namespace App\Support;

/**
 * Reputasi domain — lapis ketiga yang selalu jalan tanpa AI.
 *
 * Ini BUKAN daftar "domain ini pasti benar". Media kredibel tetap bisa salah,
 * dan situs tak dikenal bisa saja menerbitkan fakta. Yang dinilai di sini
 * hanya satu hal: apakah domain ini punya proses editorial yang bisa ditelusuri
 * dan bisa dihubungi kalau keliru. Itu pertanyaan yang berbeda dari
 * "apakah isinya benar", dan itu sebabnya bobotnya kecil (±10 poin), bukan
 * penentu verdict.
 */
final class DomainReputation
{
    /** Media dengan proses editorial publik + halaman koreksi. */
    private const TRUSTED = [
        'kompas.com', 'kompas.tv', 'tempo.co', 'cnnindonesia.com', 'cnbcindonesia.com',
        'detik.com', 'antaranews.com', 'liputan6.com', 'kumparan.com', 'tirto.id',
        'katadata.co.id', 'bisnis.com', 'kontan.co.id', 'thejakartapost.com',
        'jakartaglobe.id', 'medcom.id', 'nu.or.id', 'suaramuhammadiyah.id',
        'bbc.com', 'reuters.com', 'apnews.com', 'nytimes.com', 'theguardian.com',
        'aljazeera.com', 'channelnewsasia.com', 'straitstimes.com', 'npr.org',
        'who.int', 'un.org', 'worldbank.org',
    ];

    /** Organisasi pemeriksa fakta — domain ini justru tugasnya membongkar hoax. */
    private const FACT_CHECKERS = [
        'turnbackhoax.id', 'mafindo.or.id', 'cekfakta.com', 'cekfakta.tempo.co',
        'factcheck.afp.com', 'siberkreasi.id', 'jabarprov.go.id',
    ];

    /** TLD yang murah dan paling sering dipakai jaringan hoax. */
    private const SUSPECT_TLDS = [
        'xyz', 'top', 'buzz', 'click', 'icu', 'loan', 'work', 'gq', 'ml', 'tk', 'cf', 'ga', 'rest',
    ];

    /**
     * @return array{tier:string, label:string, score:int, note:string}
     */
    public static function for(string $host): array
    {
        $host = strtolower(ltrim($host, '.'));

        foreach (self::FACT_CHECKERS as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return [
                    'tier'  => 'trusted',
                    'label' => 'Pemeriksa fakta',
                    'score' => 10,
                    'note'  => 'Domain ini organisasi pemeriksa fakta — isinya justru hasil verifikasi.',
                ];
            }
        }

        foreach (self::TRUSTED as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return [
                    'tier'  => 'trusted',
                    'label' => 'Media bereputasi',
                    'score' => 8,
                    'note'  => 'Domain ini punya proses editorial dan halaman koreksi yang bisa dihubungi.',
                ];
            }
        }

        if (str_ends_with($host, '.go.id') || str_ends_with($host, '.ac.id') || str_ends_with($host, '.gov')) {
            return [
                'tier'  => 'trusted',
                'label' => 'Lembaga resmi',
                'score' => 7,
                'note'  => 'Domain pemerintah/kampus — tapi tetap cek apakah pengumumannya masih berlaku.',
            ];
        }

        if (str_ends_with($host, '.blogspot.com') || str_ends_with($host, '.wordpress.com') || str_ends_with($host, '.wixsite.com')) {
            return [
                'tier'  => 'suspect',
                'label' => 'Blog gratisan',
                'score' => -5,
                'note'  => 'Situs gratisan: siapa saja bisa menerbitkan apa saja tanpa editor.',
            ];
        }

        $tld = substr(strrchr('.'.$host, '.'), 1);

        if (in_array($tld, self::SUSPECT_TLDS, true)) {
            return [
                'tier'  => 'suspect',
                'label' => 'Domain mencurigakan',
                'score' => -8,
                'note'  => 'TLD .'.$tld.' murah dan sering dipakai jaringan situs hoax.',
            ];
        }

        return [
            'tier'  => 'unknown',
            'label' => 'Domain tak dikenal',
            'score' => 0,
            'note'  => 'Belum ada rekam jejak. Cek halaman "Tentang" dan nama penulisnya.',
        ];
    }
}