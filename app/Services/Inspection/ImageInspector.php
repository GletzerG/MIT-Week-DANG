<?php

namespace App\Services\Inspection;

use App\Services\Ai\LlmManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ============================================================
 *  MEMBACA GAMBAR: screenshot berita, postingan, poster, chat
 * ============================================================
 *
 * Tiga keputusan yang menentukan kualitas fitur ini:
 *
 * 1. Model TIDAK diminta menilai benar/salah dari gambar. Model visual pandai
 *    membaca, tapi buruk menilai. Jadi tugasnya cuma satu: pindahkan apa yang
 *    terlihat ke teks + catat sinyal visual. Penilaian tetap di ClaimAnalysisService,
 *    yang punya prompt penilaian dan validasi enum.
 *
 * 2. Gambar ditulis ulang lewat GD sebelum dikirim. Dua alasan: menyeragamkan
 *    ukuran (hemat token) dan membuang EXIF — foto dari HP bisa membawa lokasi
 *    GPS, dan itu tidak boleh ikut keluar ke penyedia model.
 *
 * 3. "Tidak terbaca" adalah jawaban yang sah. Prompt melarang menebak; lebih
 *    baik user dapat 422 daripada verdict yang dibangun di atas teks karangan.
 */
final class ImageInspector
{
    public function __construct(private LlmManager $llm) {}

    /**
     * @return array{text:string, headline:string, kind:string, cues:list<string>, path:string|null, width:int, height:int, model:string|null}
     */
    public function inspect(UploadedFile $file): array
    {
        if (! $this->llm->visionEnabled()) {
            throw InspectionException::visionOff();
        }

        $maxKb = (int) config('guardian.inspection.image.max_kb', 6144);

        $info = @getimagesize($file->getRealPath());

        if ($info === false) {
            throw InspectionException::unreadableImage();
        }

        if ((int) $file->getSize() > $maxKb * 1024) {
            throw InspectionException::imageTooLarge($maxKb);
        }

        [$normalized, $width, $height] = $this->normalize($file);

        $path = $this->store($normalized);

        $raw = $this->llm->safeVisionJson(
            $this->systemPrompt(),
            $this->userPrompt(),
            ['data:image/jpeg;base64,'.base64_encode($normalized)],
            // 2600, bukan 900: model penalaran membakar ratusan token berpikir
            // sebelum menulis satu huruf pun. 900 membuat salinan teks terpotong.
            ['temperature' => 0.1, 'max_tokens' => 2600],
        );

        if ($raw === null) {
            throw InspectionException::visionFailed();
        }

        $text = $this->extractText($raw);

        if (mb_strlen($text) < 12) {
            throw InspectionException::noTextFound();
        }

        return [
            'text'     => $text,
            'headline' => $this->cleanText((string) $this->firstValue($raw, ['judul', 'title', 'headline'])),
            'kind'     => Str::limit($this->cleanText((string) $this->firstValue($raw, ['jenis', 'type', 'kategori'])), 40, ''),
            'cues'     => $this->cleanCues($this->firstValue($raw, ['sinyal_visual', 'signals', 'cues', 'sinyal'])),
            'path'     => $path,
            'width'    => $width,
            'height'   => $height,
            'model'    => $this->llm->visionModel(),
        ];
    }

    // --------------------------------------------------------------- pembacaan

    /**
     * Ambil teks dari JSON model — apa pun bentuk yang ia pilih.
     *
     * Model murah tidak selalu menghormati skema yang kita minta. Yang satu
     * membalas {"teks": "..."}, yang lain {"content": [{"section","text"}]},
     * yang lain lagi {"title","summary"}. Semua bentuk itu sama-sama berguna;
     * yang tidak boleh terjadi adalah membuang hasil bacaan hanya karena
     * namanya beda. Kalau benar-benar tidak ada teks, kembalikan string kosong
     * dan biar pemanggil menjawab "tidak terbaca".
     *
     * @param  array<string, mixed>  $raw
     */
    private function extractText(array $raw): string
    {
        foreach (['teks', 'text', 'transkrip', 'isi', 'hasil'] as $key) {
            if (isset($raw[$key]) && is_string($raw[$key]) && trim($raw[$key]) !== '') {
                return $this->cleanText($raw[$key]);
            }
        }

        $content = $raw['content'] ?? $raw['konten'] ?? null;

        if (is_string($content) && trim($content) !== '') {
            return $this->cleanText($content);
        }

        if (is_array($content)) {
            $lines = [];

            foreach ($content as $item) {
                if (is_string($item)) {
                    $lines[] = $item;

                    continue;
                }

                if (! is_array($item)) {
                    continue;
                }

                $value = $this->firstValue($item, ['text', 'teks', 'isi', 'value']);

                if (is_string($value) && trim($value) !== '') {
                    $lines[] = $value;
                }
            }

            if ($lines !== []) {
                return $this->cleanText(implode("\n", $lines));
            }
        }

        // Bentuk terakhir yang masih masuk akal: judul + ringkasan.
        $title   = $this->firstValue($raw, ['judul', 'title', 'headline']);
        $summary = $this->firstValue($raw, ['summary', 'ringkasan', 'deskripsi', 'description']);

        return $this->cleanText(trim((string) $title."\n\n".(string) $summary));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $keys
     */
    private function firstValue(array $raw, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($raw[$key]) && $raw[$key] !== '' && $raw[$key] !== []) {
                return $raw[$key];
            }
        }

        return '';
    }

    // ------------------------------------------------------------------ prompt

    private function systemPrompt(): string
    {
        return <<<'TXT'
Kamu adalah mesin pembaca gambar untuk platform literasi media remaja Indonesia.
Tugasmu BUKAN menilai benar atau salah. Tugasmu hanya dua:

1. Menyalin teks yang terlihat, apa adanya, termasuk judul, nama akun, jumlah
   suka/bagikan, dan tulisan kecil di footer.
2. Mencatat sinyal visual yang berguna untuk verifikasi.

Aturan menyalin:
- Jangan menebak. Kalau ada bagian buram atau terpotong, tulis [tidak terbaca]
  pada bagian itu. Lebih baik tidak lengkap daripada karangan.
- Jangan memperbaiki ejaan, kapital, atau tanda baca. Keaslian itu bukti.
- Kalau gambar bukan tangkapan teks (misal foto kejadian), tulis deskripsi
  singkat apa yang terlihat.

Sinyal visual yang perlu dicatat bila ada:
- tidak ada nama media / logo / alamat situs
- tidak ada tanggal terbit atau nama penulis
- terlihat tanda air aplikasi (screenshot WhatsApp, Telegram, TikTok, IG)
- teks terpotong di tepi, atau tangkapan dari tangkapan (double screenshot)
- emoji berlebihan, huruf kapital seluruhnya, tanda seru beruntun
- ajakan menyebarkan / "sebelum dihapus"
- jumlah share/like yang tidak wajar untuk akun kecil
- alamat situs yang tidak cocok dengan logo yang dipakai

Balas HANYA JSON valid tanpa penjelasan:
{
  "teks": "seluruh teks yang terlihat, dipisah baris baru",
  "judul": "judul/headline kalau ada, string kosong kalau tidak",
  "jenis": "screenshot_berita | postingan_sosmed | tangkapan_chat | poster | meme | foto_kejadian | lainnya",
  "sinyal_visual": ["sinyal 1", "sinyal 2"],
  "kualitas_baca": "jelas | sebagian | buruk"
}
TXT;
    }

    private function userPrompt(): string
    {
        return 'Baca gambar ini. Salin teksnya persis seperti yang tertulis, lalu catat sinyal visualnya.';
    }

    // ------------------------------------------------------------ normalisasi

    /**
     * @return array{0:string, 1:int, 2:int}  [jpeg bytes, width, height]
     */
    private function normalize(UploadedFile $file): array
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw InspectionException::unreadableImage();
        }

        $width  = imagesx($source);
        $height = imagesy($source);
        $limit  = (int) config('guardian.inspection.image.max_dimension', 1600);

        $scale = $width > $limit ? $limit / $width : 1.0;

        if ($scale < 1.0) {
            $newW = (int) round($width * $scale);
            $newH = (int) round($height * $scale);
            $canvas = imagecreatetruecolor($newW, $newH);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($source);
            $source = $canvas;
            $width  = $newW;
            $height = $newH;
        }

        ob_start();
        imagejpeg($source, null, 84);
        $bytes = (string) ob_get_clean();
        imagedestroy($source);

        return [$bytes, $width, $height];
    }

    private function store(string $bytes): ?string
    {
        try {
            // Disimpan lokal, bukan publik: ini gambar milik user, bukan aset situs.
            $relative = 'inspections/'.now()->format('Ymd').'/'.Str::uuid().'.jpg';
            Storage::disk('local')->put($relative, $bytes);

            return $relative;
        } catch (\Throwable $e) {
            Log::warning('guardian.inspection.store-failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    // ------------------------------------------------------------------- util

    private function cleanText(string $text): string
    {
        $text = str_replace(['\\n', "\r"], ["\n", ''], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return list<string>
     */
    private function cleanCues(mixed $cues): array
    {
        // Sebagian model membalas sinyal sebagai satu paragraf, bukan array.
        if (is_string($cues)) {
            $cues = preg_split('/[\n;•]+|(?<=\.)\s+(?=[A-Z])/', $cues) ?: [];
        }

        if (! is_array($cues)) {
            return [];
        }

        return collect($cues)
            ->filter(fn ($c) => is_string($c) && trim($c) !== '')
            ->map(fn ($c) => Str::limit(trim($c), 140, ''))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }
}