<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Penjaga URL sebelum server kita menyentuh jaringan.
 *
 * Kenapa ini ada: fitur "periksa tautan" berarti server pengguna membuka URL
 * yang diketik orang lain. Tanpa penjagaan, itu bukan fitur — itu SSRF.
 * Siapa pun bisa mengetik http://127.0.0.1:8000/admin atau
 * http://169.254.169.254/latest/meta-data dan memakai aplikasi ini sebagai
 * proxy untuk membaca jaringan internal.
 *
 * Aturan:
 *  - hanya http/https, hanya port 80/443
 *  - host literal IP dicek langsung; hostname di-resolve dulu, semua IP hasil
 *    resolve ikut dicek (satu saja private = tolak)
 *  - redirect TIDAK diikuti otomatis oleh HTTP client; hop demi hop lewat sini
 *    lagi, karena redirect ke 127.0.0.1 adalah trik klasik
 */
final class UrlGuard
{
    /** Host yang namanya privat walau resolve-nya publik. */
    private const BLOCKED_NAMES = [
        'localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback',
        'metadata.google.internal', 'metadata.goog', 'instance-data',
    ];

    private const ALLOWED_PORTS = [80, 443];

    /**
     * @return array{url:string, host:string, scheme:string}
     *
     * @throws InvalidArgumentException
     */
    public static function inspect(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            throw new InvalidArgumentException('Tautan masih kosong.');
        }

        if (mb_strlen($raw) > 2048) {
            throw new InvalidArgumentException('Tautan terlalu panjang.');
        }

        // Orang menempel "www.kompas.com/judul" tanpa skema. Itu legal, bukan error.
        if (! preg_match('~^[a-z][a-z0-9+.\-]*://~i', $raw)) {
            $raw = 'https://'.ltrim($raw, '/');
        }

        $parts = parse_url($raw);

        if ($parts === false || empty($parts['host'])) {
            throw new InvalidArgumentException('Tautan tidak bisa dibaca. Formatnya: https://contoh.com/berita');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host   = strtolower(rtrim($parts['host'], '.'));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Hanya tautan http/https yang bisa diperiksa.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Tautan dengan username/password tidak diperiksa.');
        }

        if (isset($parts['port']) && ! in_array((int) $parts['port'], self::ALLOWED_PORTS, true)) {
            throw new InvalidArgumentException('Port '.$parts['port'].' tidak diizinkan. Gunakan tautan web biasa (80/443).');
        }

        if (in_array($host, self::BLOCKED_NAMES, true) || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new InvalidArgumentException('Alamat jaringan lokal tidak bisa diperiksa.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            self::assertPublicIp($host);
        } else {
            foreach (self::resolve($host) as $ip) {
                self::assertPublicIp($ip);
            }
        }

        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return [
            'url'    => $scheme.'://'.$host.$port.$path.$query,
            'host'   => $host,
            'scheme' => $scheme,
        ];
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        $ips = @gethostbynamel($host) ?: [];

        // Hostname yang tidak resolve berarti tidak bisa dihubungi sama sekali —
        // tidak ada risiko SSRF, dan permintaan HTTP-nya akan gagal di lapisan
        // berikutnya dengan pesan yang lebih jelas untuk user.
        return $ips;
    }

    private static function assertPublicIp(string $ip): void
    {
        // Cakupan privat + reserved. filter_var menangani sebagian, tapi TIDAK
        // semua range milik cloud metadata dan CGNAT, jadi dicek eksplisit.
        $private = [
            '10.', '127.', '169.254.', '192.168.', '0.', '100.64.', '100.65.',
            '100.66.', '100.67.', '100.68.', '100.69.', '100.7', '100.8', '100.9',
        ];

        foreach ($private as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                throw new InvalidArgumentException('Alamat jaringan lokal tidak bisa diperiksa.');
            }
        }

        if (preg_match('~^172\.(1[6-9]|2[0-9]|3[01])\.~', $ip)) {
            throw new InvalidArgumentException('Alamat jaringan lokal tidak bisa diperiksa.');
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidArgumentException('Alamat jaringan lokal tidak bisa diperiksa.');
        }

        if (str_contains($ip, ':')) {
            $low = strtolower($ip);

            if ($low === '::1' || str_starts_with($low, 'fc') || str_starts_with($low, 'fd') || str_starts_with($low, 'fe80')) {
                throw new InvalidArgumentException('Alamat jaringan lokal tidak bisa diperiksa.');
            }
        }
    }
}