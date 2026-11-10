<?php

namespace App\Services\Inspection;

use RuntimeException;

/**
 * Kegagalan yang aman ditampilkan ke user apa adanya.
 *
 * Bedanya dengan LlmException: yang ini BUKAN bug, melainkan jawaban jujur
 * ("gambarmu tidak ada teksnya", "domain ini tidak bisa dihubungi"). Controller
 * meneruskannya sebagai 422, bukan 500.
 */
final class InspectionException extends RuntimeException
{
    public static function visionOff(): self
    {
        return new self('Scan gambar butuh model AI visual. Belum ada yang dikonfigurasi — coba tempel teksnya atau periksa tautannya.');
    }

    public static function unreadableImage(): self
    {
        return new self('Gambar tidak bisa dibaca. Format yang didukung: PNG, JPG, WEBP.');
    }

    public static function imageTooLarge(int $maxKb): self
    {
        return new self('Gambar terlalu besar (maksimal '.round($maxKb / 1024).' MB). Coba perkecil atau potong bagian pentingnya saja.');
    }

    public static function noTextFound(): self
    {
        return new self('Tidak ada teks yang bisa dibaca dari gambar ini. Coba potong bagian teksnya, atau tempel tulisannya langsung.');
    }

    public static function visionFailed(): self
    {
        return new self('Mesin visual sedang tidak menjawab. Coba lagi sebentar, atau tempel teksnya langsung.');
    }

    public static function unreachable(): self
    {
        return new self('Tautan tidak bisa dihubungi. Mungkin alamatnya salah, situsnya lambat, atau memblokir robot.');
    }

    public static function notFound(): self
    {
        return new self('Halaman tidak ditemukan (404). Tautannya mungkin salah ketik, atau artikelnya sudah dihapus.');
    }

    public static function blocked(int $status): self
    {
        return new self('Situs itu menolak diakses robot (HTTP '.$status.'). Buka sendiri di browser, lalu tempel teksnya di sini.');
    }

    public static function serverError(int $status): self
    {
        return new self('Situs sumber sedang bermasalah (HTTP '.$status.'). Coba lagi nanti.');
    }

    public static function tooManyRedirects(): self
    {
        return new self('Tautan itu mengalihkan terlalu banyak kali — biasanya tanda tautan pendek yang mencurigakan.');
    }

    public static function notHtml(): self
    {
        return new self('Tautan itu bukan halaman artikel (bukan HTML) — mungkin file gambar, PDF, atau video.');
    }

    public static function pageTooThin(): self
    {
        return new self('Halaman itu tidak memuat teks berita yang bisa dibaca — biasanya butuh login, atau isinya dimuat lewat JavaScript.');
    }
}