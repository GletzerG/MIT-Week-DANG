<?php

namespace App\Services\Ai\Contracts;

interface LlmClient
{
    public function driver(): string;

    public function model(): string;

    /**
     * @param  array{temperature?:float, max_tokens?:int, json?:bool}  $options
     * @return array{text:string, model:string, tokens:int|null}
     */
    public function complete(string $system, string $prompt, array $options = []): array;

    /**
     * Paksa output JSON dan decode. Mengembalikan array asosiatif.
     *
     * @return array<string, mixed>
     */
    public function json(string $system, string $prompt, array $options = []): array;

    /**
     * Model multimodal untuk membaca gambar. Bisa berbeda dari model teks —
     * gateway lokal sering punya model murah untuk teks dan model lain yang
     * benar-benar bisa melihat gambar.
     */
    public function visionModel(): string;

    /**
     * Kirim gambar + instruksi. $images berisi data URI lengkap
     * ("data:image/jpeg;base64,...") supaya tidak ada file yang perlu
     * diunggah ke mana pun selain ke model itu sendiri.
     *
     * @param  list<string>  $images
     * @param  array{temperature?:float, max_tokens?:int}  $options
     * @return array{text:string, model:string, tokens:int|null}
     */
    public function vision(string $system, string $prompt, array $images, array $options = []): array;
}
