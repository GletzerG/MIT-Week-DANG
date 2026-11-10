<?php

namespace App\Support;

/**
 * Pemindai sinyal hoax berbasis aturan — TANPA jaringan, TANPA token.
 *
 * Ini bukan "AI palsu". Ini lapisan pertama yang selalu jalan: kalau LLM mati,
 * kena rate-limit, atau API key belum diisi, alat deteksi tetap memberi jawaban
 * yang bisa dipertanggungjawabkan. Yang hilang hanya nuansa bahasa; yang tetap
 * ada adalah bukti yang bisa ditunjuk.
 *
 * Skor 0..100 = kredibilitas:
 *   mulai dari 50, sinyal manipulatif mengurangi, sinyal kredibel menambah.
 */
final class HeuristicClaimScanner
{
    /** Ambang batas verdict. Diekspos supaya test bisa mengunci perilakunya. */
    public const FACT_AT      = 65;
    public const HOAX_AT      = 35;
    public const MIN_LENGTH   = 24;

    /**
     * @return array{score:int, risk:int, trust:int, signals:list<array{key:string,label:string,note:string,tone:string}>}
     */
    public function scan(string $text): array
    {
        $lower   = mb_strtolower($text);
        $signals = [];
        $risk    = 0;
        $trust   = 0;

        foreach ($this->rules() as $rule) {
            if (! ($rule['test'])($text, $lower)) {
                continue;
            }

            $signals[] = [
                'key'   => $rule['key'],
                'label' => $rule['label'],
                'note'  => $rule['note'],
                'tone'  => $rule['kind'],
            ];

            $rule['kind'] === 'risk'
                ? $risk  += $rule['weight']
                : $trust += $rule['weight'];
        }

        return [
            'score'   => max(0, min(100, 50 + $trust - $risk)),
            'risk'    => $risk,
            'trust'   => $trust,
            'signals' => $signals,
        ];
    }

    /** Rasio huruf kapital — pesan berantai penuh SHOUTING. */
    private function capsRatio(string $text): float
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $text) ?? '';
        $len     = strlen($letters);

        if ($len < 12) {
            return 0.0;
        }

        $upper = strlen(preg_replace('/[^A-Z]/', '', $letters) ?? '');

        return $upper / $len;
    }

    /** @return list<array{key:string,label:string,note:string,weight:int,kind:string,test:callable}> */
    private function rules(): array
    {
        return [
            // ── risiko: manipulatif ──────────────────────────────────────────
            [
                'key' => 'shouting', 'label' => 'Huruf Kapital Berlebihan',
                'note' => 'Teks ditulis kapital sebagian besar — teknik menaikkan emosi, bukan menyampaikan fakta.',
                'weight' => 14, 'kind' => 'risk',
                'test' => fn (string $t) => $this->capsRatio($t) > 0.40,
            ],
            [
                'key' => 'exclamation', 'label' => 'Tanda Seru Beruntun',
                'note' => 'Dua tanda seru atau lebih dipakai untuk mendesak pembaca berpikir cepat, bukan teliti.',
                'weight' => 8, 'kind' => 'risk',
                'test' => fn (string $t, string $l) => preg_match('/!{2,}/u', $t) === 1,
            ],
            [
                'key' => 'chain_message', 'label' => 'Ajakan Sebarkan Ulang',
                'note' => 'Ada instruksi "sebarkan/teruskan". Pesan berantai tidak pernah punya penulis yang bisa dimintai tanggung jawab.',
                'weight' => 20, 'kind' => 'risk',
                'test' => fn (string $t, string $l) => preg_match('/\b(sebarkan|teruskan|forward|broadcast|kirim ke grup|share ke)\b/u', $l) === 1,
            ],
            [
                'key' => 'urgency', 'label' => 'Desakan Waktu Palsu',
                'note' => 'Frasa "segera", "buruan", "sebelum dihapus" menekan pembaca agar tidak sempat memverifikasi.',
                'weight' => 12, 'kind' => 'risk',
                'test' => fn (string $t, string $l) => preg_match('/\b(segera|buruan|sebelum dihapus|dalam 24 jam|jangan sampai|jam ini juga)\b/u', $l) === 1,
            ],
            [
                'key' => 'anonymous_authority', 'label' => 'Otoritas Tanpa Nama',
                'note' => 'Menyebut "dokter/ahli/profesor" tanpa nama, institusi, atau publikasi — klaim yang tidak bisa dicek.',
                'weight' => 14, 'kind' => 'risk',
                'test' => fn (string $t, string $l) => preg_match('/(dokter|ahli|profesor|peneliti|ilmuwan)\s+(ternama|terkenal|top|besar)|menurut (seorang )?(dokter|ahli|profesor)/u', $l) === 1,
            ],
            [
                'key' => 'absolute_claim', 'label' => 'Klaim Mutlak',
                'note' => 'Ilmu pengetahuan berbicara dengan probabilitas. Kata "100%", "dijamin", "pasti" adalah tanda bahaya.',
                'weight' => 12, 'kind' => 'risk',
                'test' => fn (string $t, string $l) => preg_match('/(100\s*%|dijamin|pasti sembuh|pasti berhasil|selalu|tidak pernah gagal|mutlak)/u', $l) === 1,
            ],
            [
                'key' => 'miracle', 'label' => 'Janji Mukjizat',
                'note' => 'Perubahan besar dalam hitungan hari/jam. Tubuh dan dunia nyata tidak bekerja secepat itu.',
                'weight' => 14, 'kind' => 'risk',
                'test' => fn (string $t, string $l) => preg_match('/\b(dalam|hanya)\s+\d+\s*(hari|jam|menit)\b|\b(mujarab|ajaib|instan|langsung sembuh)\b/u', $l) === 1,
            ],
            [
                'key' => 'conspiracy', 'label' => 'Narasi Konspirasi',
                'note' => 'Klaim bahwa informasi "disembunyikan" tanpa menyebut siapa yang menyembunyikan dan untuk apa.',
                'weight' => 12, 'kind' => 'risk',
                'test' => fn (string $t, string $l) => preg_match('/(disembunyikan|ditutupi|media tidak mau|rahasia besar|jangan sampai tahu|dibungkam)/u', $l) === 1,
            ],
            [
                'key' => 'fear_appeal', 'label' => 'Memicu Rasa Takut',
                'note' => 'Kata-kata mengerikan dipakai untuk mematikan nalar kritis pembaca.',
                'weight' => 8, 'kind' => 'risk',
                'test' => fn (string $t, string $l) => preg_match('/(mengerikan|mematikan|bahaya banget|mematikan|ancaman besar|kiamat)/u', $l) === 1,
            ],

            // ── kepercayaan: sinyal kredibel ─────────────────────────────────
            [
                'key' => 'institution', 'label' => 'Institusi Bisa Dicek',
                'note' => 'Menyebut lembaga yang keberadaannya bisa diverifikasi (kementerian, badan, universitas, jurnal).',
                'weight' => 14, 'kind' => 'trust',
                'test' => fn (string $t, string $l) => preg_match('/(kementerian|badan [a-z]+|universitas|lembaga|who|kemenkes|bmkg|bps|jurnal|riset nasional)/u', $l) === 1,
            ],
            [
                'key' => 'attribution', 'label' => 'Atribusi Jelas',
                'note' => 'Ada penunjukan sumber: "menurut", "kata", "ujar", "dikutip dari".',
                'weight' => 10, 'kind' => 'trust',
                'test' => fn (string $t, string $l) => preg_match('/\b(menurut|kata |ujar |dikutip dari|pernyataan|keterangan)\b/u', $l) === 1,
            ],
            [
                'key' => 'numbers', 'label' => 'Data Angka',
                'note' => 'Angka spesifik dengan satuan — jauh lebih sulit dipalsukan daripada klaim tanpa ukuran.',
                'weight' => 12, 'kind' => 'trust',
                'test' => fn (string $t, string $l) => preg_match('/\d+([.,]\d+)?\s*(%|persen|orang|kasus|juta|ribu|km|derajat|juta)/u', $l) === 1,
            ],
            [
                'key' => 'dated', 'label' => 'Tanggal Terbit',
                'note' => 'Ada keterangan waktu. Hoax paling sering menghilangkan tanggal agar tidak pernah kedaluwarsa.',
                'weight' => 10, 'kind' => 'trust',
                'test' => fn (string $t, string $l) => preg_match('/\b\d{1,2}\s+(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\s+\d{4}\b/u', $l) === 1,
            ],
            [
                'key' => 'hedged', 'label' => 'Bahasa Terukur',
                'note' => 'Memakai "diperkirakan", "berdasarkan data", "sementara" — penanda tulisan yang tidak sedang menjual kepastian.',
                'weight' => 8, 'kind' => 'trust',
                'test' => fn (string $t, string $l) => preg_match('/(diperkirakan|berdasarkan data|sementara itu|kemungkinan|menurut data|diestimasi)/u', $l) === 1,
            ],
        ];
    }
}