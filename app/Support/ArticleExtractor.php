<?php

namespace App\Support;

/**
 * Mengubah HTML halaman berita menjadi bahan yang bisa dinilai.
 *
 * Prinsipnya: ambil yang bisa dibaca manusia, buang yang tidak — script, style,
 * nav, footer, komentar, dan blok "baca juga" yang panjangnya mirip judul.
 * Kalau sebuah halaman tidak menghasilkan setidaknya ~200 karakter bermakna,
 * lebih jujur bilang "tidak bisa dibaca" daripada menilai halaman kosong.
 */
final class ArticleExtractor
{
    public const MIN_BODY_CHARS = 180;

    /**
     * @return array{title:string, site:string, description:string, author:string|null, published_at:string|null, body:string, canonical:string|null, chars:int}
     */
    public static function parse(string $html): array
    {
        $previous = libxml_use_internal_errors(true);

        $doc = new \DOMDocument();

        // Prefix XML encoding: banyak situs Indonesia masih mengirim HTML
        // tanpa meta charset yang benar, dan DOMDocument default ke ISO-8859-1.
        $doc->loadHTML('<?xml encoding="UTF-8">'.self::stripNoise($html), LIBXML_NOWARNING | LIBXML_NOERROR);

        $xpath = new \DOMXPath($doc);

        $title = self::meta($xpath, 'og:title')
            ?? self::text($xpath, '//title')
            ?? '';

        $site = self::meta($xpath, 'og:site_name')
            ?? self::text($xpath, '//meta[@name="application-name"]/@content')
            ?? '';

        $description = self::meta($xpath, 'og:description')
            ?? self::meta($xpath, 'description', false)
            ?? '';

        $author = self::meta($xpath, 'author', false)
            ?? self::meta($xpath, 'article:author')
            ?? self::text($xpath, '//*[@rel="author"]')
            ?: null;

        $published = self::meta($xpath, 'article:published_time')
            ?? self::meta($xpath, 'pubdate', false)
            ?? self::meta($xpath, 'date', false);

        $canonical = self::text($xpath, '//link[@rel="canonical"]/@href') ?: null;

        $body = self::body($xpath);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return [
            'title'        => self::clean($title),
            'site'         => self::clean($site),
            'description'  => self::clean($description),
            'author'       => $author ? self::clean($author) : null,
            'published_at' => $published ? self::clean($published) : null,
            'body'         => $body,
            'canonical'    => $canonical,
            'chars'        => mb_strlen($body),
        ];
    }

    /** Buang elemen yang tidak pernah berisi isi berita. */
    private static function stripNoise(string $html): string
    {
        $html = preg_replace('~<(script|style|noscript|svg|iframe|form|nav|footer|aside)\b.*?</\1>~is', ' ', $html) ?? $html;
        $html = preg_replace('~<!--.*?-->~s', ' ', $html) ?? $html;

        return $html;
    }

    private static function body(\DOMXPath $xpath): string
    {
        // Utamakan <article>: itu penanda paling jujur di HTML5 modern.
        $nodes = $xpath->query('//article//p | //*[@itemprop="articleBody"]//p');

        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query('//p');
        }

        if ($nodes === false) {
            return '';
        }

        $paragraphs = [];
        $seen       = [];

        foreach ($nodes as $node) {
            $text = self::clean($node->textContent);

            // Paragraf pendek hampir selalu navigasi, caption, atau "Baca juga:".
            if (mb_strlen($text) < 45) {
                continue;
            }

            $fingerprint = mb_substr($text, 0, 60);

            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $paragraphs[] = $text;

            if (mb_strlen(implode("\n\n", $paragraphs)) > 6000) {
                break;
            }
        }

        $body = implode("\n\n", $paragraphs);

        return mb_substr($body, 0, 4000);
    }

    private static function meta(\DOMXPath $xpath, string $key, bool $ogPrefix = true): ?string
    {
        $candidates = $ogPrefix
            ? ["//meta[@property='{$key}']/@content", "//meta[@name='{$key}']/@content"]
            : ["//meta[@name='{$key}']/@content", "//meta[@property='{$key}']/@content"];

        foreach ($candidates as $query) {
            $value = self::text($xpath, $query);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function text(\DOMXPath $xpath, string $query): string
    {
        $nodes = @$xpath->query($query);

        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        return trim($nodes->item(0)->nodeValue ?? '');
    }

    private static function clean(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('~\s+~u', ' ', $text) ?? $text;

        return trim($text);
    }
}