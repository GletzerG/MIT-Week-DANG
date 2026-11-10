<?php

namespace App\Services\Inspection;

use App\Support\ArticleExtractor;
use App\Support\DomainReputation;
use App\Support\UrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * ============================================================
 *  MEMERIKSA TAUTAN: ambil halaman, baca isinya, nilai reputasi domain
 * ============================================================
 *
 * Yang diperiksa sengaja dipersempit ke hal-hal yang bisa dibuktikan:
 *  - domain: punya proses editorial atau tidak (DomainReputation)
 *  - kelengkapan atribusi: ada penulis? ada tanggal terbit? ada nama media?
 *  - isi artikelnya sendiri → masuk ke ClaimAnalysisService seperti klaim biasa
 *
 * Redirect diikuti MANUAL, satu hop demi satu, dan tiap hop lewat UrlGuard
 * lagi. Redirect otomatis adalah pintu SSRF yang paling sering lolos:
 * tautan publik yang 302 ke http://127.0.0.1 akan dibaca server tanpa curiga.
 */
final class LinkInspector
{
    /**
     * @return array{url:string, host:string, title:string, site:string, author:string|null, published_at:string|null, body:string, chars:int, reputation:array{tier:string,label:string,score:int,note:string}, attribution:array{author:bool,date:bool,site:bool}, status:int}
     */
    public function inspect(string $rawUrl): array
    {
        $url    = $this->normalize($rawUrl);
        $config = (array) config('guardian.inspection.link');

        $response = $this->fetch($url, $config);

        $contentType = (string) $response->header('Content-Type');

        if ($contentType !== '' && ! str_contains(strtolower($contentType), 'html') && ! str_contains(strtolower($contentType), 'text/plain')) {
            throw InspectionException::notHtml();
        }

        $html = mb_substr($response->body(), 0, (int) ($config['max_bytes'] ?? 3145728));

        if (trim($html) === '') {
            throw InspectionException::pageTooThin();
        }

        $article = ArticleExtractor::parse($html);

        if ($article['chars'] < ArticleExtractor::MIN_BODY_CHARS) {
            throw InspectionException::pageTooThin();
        }

        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');

        return [
            'url'          => $url,
            'host'         => $host,
            'title'        => $article['title'] !== '' ? $article['title'] : Str::headline($host),
            'site'         => $article['site'] !== '' ? $article['site'] : $host,
            'author'       => $article['author'],
            'published_at' => $article['published_at'],
            'body'         => $article['body'],
            'chars'        => $article['chars'],
            'reputation'   => DomainReputation::for($host),
            'attribution'  => [
                'author' => filled($article['author']),
                'date'   => filled($article['published_at']),
                'site'   => $article['site'] !== '',
            ],
            'status'       => $response->status(),
        ];
    }

    // ------------------------------------------------------------------ fetch

    private function normalize(string $raw): string
    {
        try {
            return UrlGuard::inspect($raw)['url'];
        } catch (InvalidArgumentException $e) {
            throw new InspectionException($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function fetch(string $url, array $config): \Illuminate\Http\Client\Response
    {
        $maxHops = (int) ($config['max_redirects'] ?? 3);
        $current = $url;

        for ($hop = 0; $hop <= $maxHops; $hop++) {
            try {
                $response = Http::withoutRedirecting()
                    ->withHeaders([
                        'User-Agent'      => (string) ($config['user_agent'] ?? 'GuardianGenesis/1.0'),
                        'Accept'          => 'text/html,application/xhtml+xml',
                        'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.6',
                    ])
                    ->timeout((int) ($config['timeout'] ?? 12))
                    ->connectTimeout(6)
                    ->get($current);
            } catch (ConnectionException $e) {
                Log::info('guardian.link.unreachable', ['host' => parse_url($current, PHP_URL_HOST), 'reason' => class_basename($e)]);

                throw InspectionException::unreachable();
            }

            if ($response->status() >= 300 && $response->status() < 400) {
                $location = (string) $response->header('Location');

                if ($location === '') {
                    break;
                }

                // Setiap hop dicek ulang: jangan percaya Location dari situs asing.
                $current = $this->normalize($this->absolute($current, $location));

                continue;
            }

            if ($response->failed()) {
                throw match (true) {
                    in_array($response->status(), [404, 410], true)        => InspectionException::notFound(),
                    in_array($response->status(), [401, 403, 429], true)   => InspectionException::blocked($response->status()),
                    $response->status() >= 500                             => InspectionException::serverError($response->status()),
                    default                                                => InspectionException::unreachable(),
                };
            }

            return $response;
        }

        throw InspectionException::tooManyRedirects();
    }

    /** Resolve Location relatif ("/berita/123") terhadap URL halaman. */
    private function absolute(string $base, string $location): string
    {
        if (preg_match('~^[a-z][a-z0-9+.\-]*://~i', $location)) {
            return $location;
        }

        $parts  = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host   = $parts['host'] ?? '';
        $port   = isset($parts['port']) ? ':'.$parts['port'] : '';

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $scheme.'://'.$host.$port.$location;
        }

        $dir = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $scheme.'://'.$host.$port.$dir.'/'.ltrim($location, '/');
    }
}