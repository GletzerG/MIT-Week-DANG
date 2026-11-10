<?php

namespace Tests\Feature;

use App\Models\ClaimAnalysis;
use App\Models\User;
use App\Services\Ai\Contracts\LlmClient;
use App\Services\Ai\LlmManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dua pintu masuk baru: gambar dan tautan.
 *
 * Yang dijaga test ini — dan semuanya soal kejujuran, bukan akurasi:
 *   1. Kalau tidak ada model visual, aplikasi BILANG. Tidak pura-pura bisa.
 *   2. Model visual hanya membaca; verdict tetap dari ClaimAnalysisService.
 *   3. Tautan lokal (127.0.0.1, metadata cloud) tidak bisa dipakai jadi proxy.
 *   4. Redirect ke alamat lokal juga ditolak — hop demi hop.
 *   5. Teks yang sama dari gambar dan dari tempelan tidak saling menimpa cache.
 */
class SourceScanTest extends TestCase
{
    use RefreshDatabase;

    private const HOAX_TEXT = 'AWAS!!! SEBARKAN KE SEMUA GRUP! Air lemon hangat terbukti membunuh sel kanker dalam 3 hari. Menurut dokter ternama, tidak perlu kemoterapi lagi. Buruan sebelum dihapus!!';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
    }

    // ------------------------------------------------------------------ gambar

    public function test_image_scan_admits_it_when_no_vision_model_exists(): void
    {
        $this->app->instance(LlmManager::class, new LlmManager(null));

        $this->actingAs(User::factory()->create())
            ->post('/analisis', [
                'mode'  => 'image',
                'image' => UploadedFile::fake()->image('berita.png', 800, 1200),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('mode', 'image')
            ->assertJsonFragment([
                'message' => 'Scan gambar butuh model AI visual. Belum ada yang dikonfigurasi — coba tempel teksnya atau periksa tautannya.',
            ]);
    }

    public function test_image_scan_reads_the_screenshot_then_judges_its_text(): void
    {
        $this->fakeVision([
            'teks'           => self::HOAX_TEXT,
            'judul'          => 'Air Lemon Bisa Bunuh Sel Kanker',
            'jenis'          => 'screenshot_berita',
            'sinyal_visual'  => ['tidak ada nama media', 'tidak ada tanggal terbit', 'huruf kapital seluruhnya'],
            'kualitas_baca'  => 'jelas',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->post('/analisis', [
                'mode'  => 'image',
                'image' => UploadedFile::fake()->image('berita.png', 800, 1200),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $payload = $response->json();

        $this->assertSame('image', $payload['source']['type']);
        $this->assertSame('hoax', $payload['verdict']['value'], 'Verdict tetap datang dari mesin penilai, bukan dari model visual.');

        // Judul ikut dibaca dan ikut dinilai.
        $this->assertStringContainsString('Air Lemon', $payload['source']['text']);
        $this->assertStringContainsString('SEBARKAN', $payload['source']['text']);

        $this->assertNotEmpty($payload['source']['cues'], 'Sinyal visual harus sampai ke user.');
        $this->assertContains('tidak ada nama media', $payload['source']['cues']);

        $this->assertSame('screenshot_berita', $payload['source']['meta']['kind']);
        $this->assertSame(800, $payload['source']['meta']['width']);
        $this->assertGreaterThan(0, $payload['source']['chars']);

        $stored = ClaimAnalysis::query()->firstOrFail();
        $this->assertSame('image', $stored->source_type);
        $this->assertNotNull($stored->media_path, 'Gambar milik user harus tersimpan supaya bisa ditelusuri.');
        $this->assertNotEmpty($stored->visual_cues);
        Storage::disk('local')->assertExists($stored->media_path);
    }

    public function test_image_without_readable_text_is_an_answer_not_a_guess(): void
    {
        $this->fakeVision([
            'teks'          => '  ',
            'judul'         => '',
            'jenis'         => 'foto_kejadian',
            'sinyal_visual' => [],
            'kualitas_baca' => 'buruk',
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/analisis', [
                'mode'  => 'image',
                'image' => UploadedFile::fake()->image('foto.jpg', 600, 400),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Tidak ada teks yang bisa dibaca dari gambar ini. Coba potong bagian teksnya, atau tempel tulisannya langsung.',
            ]);
    }

    public function test_image_scan_rejects_a_file_that_is_not_an_image(): void
    {
        $this->fakeVision(['teks' => self::HOAX_TEXT]);

        $this->actingAs(User::factory()->create())
            ->post('/analisis', [
                'mode'  => 'image',
                'image' => UploadedFile::fake()->create('dokumen.pdf', 120, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    // ----------------------------------------------------------------- tautan

    public function test_link_scan_refuses_internal_addresses_before_touching_the_network(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();

        foreach (['http://127.0.0.1/admin', 'http://169.254.169.254/latest/meta-data', 'http://localhost/berita'] as $url) {
            $this->actingAs($user)
                ->postJson('/analisis', ['mode' => 'link', 'url' => $url])
                ->assertStatus(422)
                ->assertJsonFragment(['message' => 'Alamat jaringan lokal tidak bisa diperiksa.']);
        }
    }

    public function test_link_scan_refuses_a_public_page_that_redirects_inward(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://93.184.216.34/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/analisis', ['mode' => 'link', 'url' => 'http://93.184.216.34/artikel'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Alamat jaringan lokal tidak bisa diperiksa.']);
    }

    public function test_link_scan_extracts_the_article_and_scores_the_domain(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://93.184.216.34/*' => Http::response($this->articleHtml(), 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/analisis', ['mode' => 'link', 'url' => 'http://93.184.216.34/berita/gempa'])
            ->assertOk();

        $payload = $response->json();

        $this->assertSame('link', $payload['source']['type']);
        $this->assertSame('93.184.216.34', $payload['source']['label']);
        $this->assertStringContainsString('Gempa', $payload['source']['text']);
        $this->assertNotNull($payload['source']['reputation']);
        $this->assertSame('unknown', $payload['source']['reputation']['tier'], 'Domain tak dikenal tidak boleh dicap kredibel.');
        $this->assertTrue($payload['source']['attribution']['author']);
        $this->assertTrue($payload['source']['attribution']['date']);

        $stored = ClaimAnalysis::query()->firstOrFail();
        $this->assertSame('link', $stored->source_type);
        $this->assertSame('http://93.184.216.34/berita/gempa', $stored->source_ref);
    }

    public function test_dead_link_is_explained_in_plain_language(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://93.184.216.34/*' => Http::response('', 404),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/analisis', ['mode' => 'link', 'url' => 'http://93.184.216.34/hilang'])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Halaman tidak ditemukan (404). Tautannya mungkin salah ketik, atau artikelnya sudah dihapus.',
            ]);
    }

    public function test_page_without_readable_body_is_rejected_not_scored(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://93.184.216.34/*' => Http::response(
                '<html><head><title>Judul</title></head><body><div id="app"></div></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/analisis', ['mode' => 'link', 'url' => 'http://93.184.216.34/spa'])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Halaman itu tidak memuat teks berita yang bisa dibaca — biasanya butuh login, atau isinya dimuat lewat JavaScript.',
            ]);
    }

    // ----------------------------------------------------------------- cache

    public function test_same_text_from_different_sources_does_not_share_one_cache_row(): void
    {
        $this->fakeVision([
            'teks'          => self::HOAX_TEXT,
            'judul'         => '',
            'jenis'         => 'postingan_sosmed',
            'sinyal_visual' => ['tanda air aplikasi chat'],
            'kualitas_baca' => 'jelas',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/analisis', ['mode' => 'image', 'image' => UploadedFile::fake()->image('a.png', 400, 400)], ['Accept' => 'application/json'])
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/analisis', ['mode' => 'text', 'text' => self::HOAX_TEXT])
            ->assertOk();

        $this->assertSame(2, ClaimAnalysis::query()->count(), 'Provenance ikut jadi sidik jari cache.');
        $this->assertEqualsCanonicalizing(
            ['image', 'text'],
            ClaimAnalysis::query()->pluck('source_type')->all(),
        );
    }

    // ------------------------------------------------------------------ bantu

    /**
     * Model visual palsu: membaca gambar jadi JSON, tidak pernah menilai.
     *
     * @param  array<string, mixed>  $payload
     */
    private function fakeVision(array $payload): void
    {
        config([
            'guardian.ai.openai.vision_model' => 'fake-vision',
            'guardian.ai.openai.key'          => 'test-key',
        ]);

        $client = new class(json_encode($payload, JSON_UNESCAPED_UNICODE)) implements LlmClient {
            public function __construct(private string $payload) {}

            public function driver(): string
            {
                return 'openai';
            }

            public function model(): string
            {
                return 'fake-text';
            }

            public function complete(string $system, string $prompt, array $options = []): array
            {
                throw new \RuntimeException('Test ini tidak memakai jalur teks.');
            }

            public function json(string $system, string $prompt, array $options = []): array
            {
                throw new \RuntimeException('Test ini tidak memakai jalur teks.');
            }

            public function visionModel(): string
            {
                return 'fake-vision';
            }

            public function vision(string $system, string $prompt, array $images, array $options = []): array
            {
                return ['text' => $this->payload, 'model' => 'fake-vision', 'tokens' => 42];
            }
        };

        $this->app->instance(LlmManager::class, new LlmManager($client));
    }

    private function articleHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="id">
<head>
    <title>Gempa Magnitudo 5,2 Guncang Sukabumi, Tidak Berpotensi Tsunami</title>
    <meta property="og:site_name" content="Kabar Nusantara">
    <meta property="article:published_time" content="2026-03-14T04:40:00+07:00">
    <meta name="author" content="Rani Prasetya">
    <meta name="description" content="Gempa dangkal terasa hingga Bogor.">
</head>
<body>
<article>
    <h1>Gempa Magnitudo 5,2 Guncang Sukabumi, Tidak Berpotensi Tsunami</h1>
    <p>Badan Meteorologi Nasional mencatat gempa berkekuatan magnitudo 5,2 mengguncang wilayah Sukabumi pada Sabtu dini hari pukul 04.12 WIB. Pusat gempa berada pada kedalaman 34 kilometer di barat daya kota.</p>
    <p>Getaran dilaporkan terasa hingga Bogor, Cianjur, dan sebagian Jakarta Selatan selama sekitar sepuluh detik. Warga sempat keluar rumah, namun tidak ada laporan kerusakan bangunan maupun korban jiwa dari otoritas setempat.</p>
    <p>Berdasarkan pemodelan pusat gempa, kedalaman dan mekanisme sesar tidak memenuhi kriteria pembentukan tsunami. Peringatan dini tidak dikeluarkan, dan warga diminta tetap tenang serta kembali beraktivitas normal.</p>
    <p>Otoritas mengimbau masyarakat memeriksa informasi resmi melalui kanal pemerintah, bukan pesan berantai yang beredar di aplikasi perpesanan tanpa sumber jelas.</p>
</article>
</body>
</html>
HTML;
    }
}