<?php

namespace App\Services\Ai;

/**
 * Decoder JSON yang toleran.
 *
 * KENAPA perlu: walau sudah minta response_format=json_object, model murah
 * kadang masih membungkus output dengan ```json ... ``` atau menambah kalimat
 * pembuka. Daripada membuang seluruh hasil (dan membakar token lagi), kita
 * bersihkan dulu.
 */
final class JsonBlock
{
    /** @return array<string, mixed> */
    public static function decode(string $raw): array
    {
        $text = trim($raw);

        if ($text === '') {
            throw LlmException::badResponse('llm', 'empty body');
        }

        // Buang code fence markdown kalau ada.
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $text, $m)) {
            $text = $m[1];
        }

        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Terakhir: ambil substring dari '{' pertama sampai '}' terakhir.
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Model kehabisan token di tengah JSON (finish_reason=length). Untuk
        // pembaca gambar, teks yang tersalin 90% masih berguna — asal kita
        // tutup string dan kurawal yang menggantung sendiri. Kalau hasil
        // tambalan tetap tidak valid, baru menyerah.
        if ($start !== false) {
            $salvaged = self::salvage(substr($text, $start));

            if (is_array($salvaged)) {
                return $salvaged;
            }
        }

        throw LlmException::badResponse('llm', mb_substr($text, 0, 200));
    }

    /**
     * Tutup JSON yang terpotong: hentikan string yang menggantung, lalu tambah
     * penutup yang kurang. Tidak menebak isi — hanya menutup apa yang ada.
     *
     * @return array<string, mixed>|null
     */
    private static function salvage(string $partial): ?array
    {
        $out    = '';
        $inStr  = false;
        $escape = false;
        $stack  = [];

        foreach (mb_str_split($partial) as $ch) {
            $out .= $ch;

            if ($inStr) {
                if ($escape) {
                    $escape = false;
                } elseif ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === '"') {
                    $inStr = false;
                }

                continue;
            }

            if ($ch === '"') {
                $inStr = true;
            } elseif ($ch === '{' || $ch === '[') {
                $stack[] = $ch === '{' ? '}' : ']';
            } elseif ($ch === '}' || $ch === ']') {
                array_pop($stack);
            }
        }

        if ($inStr) {
            $out .= '"';
        }

        // Buang sisa "kunci": yang menggantung tanpa nilai ("teks": ).
        $out = preg_replace('/,\s*$/', '', rtrim($out)) ?? $out;
        $out = preg_replace('/:\s*$/', ': null', rtrim($out)) ?? $out;

        while ($stack !== []) {
            $out .= array_pop($stack);
        }

        $decoded = json_decode($out, true);

        return is_array($decoded) ? $decoded : null;
    }
}
