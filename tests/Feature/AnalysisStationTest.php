<?php

namespace Tests\Feature;

use App\Models\ClaimAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Stasiun deteksi klaim.
 *
 * Yang dijaga test ini:
 *   1. Verdict selalu salah satu dari enum — LLM tidak boleh menyelundupkan
 *      nilai bebas ke UI.
 *   2. Teks terlalu pendek ditolak; memaksa mesin menilai 5 kata adalah
 *      sumber kesalahan paling umum.
 *   3. Cache by hash: pesan berantai yang sama tidak dibakar dua kali.
 *   4. Kuota XP harian benar-benar menghentikan farming.
 */
class AnalysisStationTest extends TestCase
{
    use RefreshDatabase;

    private const HOAX_SAMPLE = 'AWAS!!! SEBARKAN KE SEMUA GRUP! Air lemon hangat terbukti membunuh sel kanker dalam 3 hari. Menurut dokter ternama, tidak perlu kemoterapi lagi. Buruan sebelum dihapus!!';

    private const FACT_SAMPLE = 'Badan Meteorologi Nasional menyatakan gempa magnitudo 5,2 mengguncang Sukabumi pada 14 Maret 2026 pukul 04.12 WIB. Berdasarkan data kedalaman 34 kilometer, gempa tidak berpotensi tsunami.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_reach_the_detector(): void
    {
        $this->get('/analisis')->assertRedirect(route('login'));
        $this->postJson('/analisis', ['text' => self::HOAX_SAMPLE])->assertUnauthorized();
    }

    public function test_detector_page_renders_with_its_tooling_context(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/analisis')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Analysis/Index')
                ->has('guardian')
                ->has('ai.enabled')
                ->has('ai.vision')
                ->where('limits.min', 24)
                ->where('limits.image_kb', 6144)
                ->where('quota.cap', 5)
                ->where('quota.each', 8));
    }

    public function test_short_snippet_is_rejected_instead_of_guessed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/analisis', ['text' => 'hoax gak sih'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('text');
    }

    public function test_hoax_claim_gets_a_hoax_verdict_with_reasons(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/analisis', ['text' => self::HOAX_SAMPLE])
            ->assertOk()
            ->assertJsonPath('verdict.value', 'hoax')
            ->assertJsonPath('verdict.tone', 'magenta')
            ->assertJsonPath('engine', 'rules')
            ->assertJsonPath('xp_awarded', 8);

        $payload = $response->json();

        $this->assertNotEmpty($payload['reasons'], 'Verdict tanpa alasan tidak mengajari apa pun.');
        $this->assertNotEmpty($payload['recommendation']);
        $this->assertGreaterThan(0, $payload['confidence']);
        $this->assertLessThanOrEqual(100, $payload['confidence']);
    }

    public function test_factual_claim_gets_a_fact_verdict(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/analisis', ['text' => self::FACT_SAMPLE])
            ->assertOk()
            ->assertJsonPath('verdict.value', 'fact')
            ->assertJsonPath('verdict.tone', 'acid');
    }

    public function test_identical_chain_message_is_served_from_cache(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/analisis', ['text' => self::HOAX_SAMPLE])->assertOk();
        $this->actingAs($user)->postJson('/analisis', ['text' => self::HOAX_SAMPLE])->assertOk();

        $this->assertSame(1, ClaimAnalysis::query()->count());
    }

    public function test_daily_xp_quota_eventually_returns_zero(): void
    {
        $user = User::factory()->create();

        $awarded = [];

        // Lima pesan berbeda supaya cache tidak menutupi uji kuota.
        foreach (range(1, 7) as $i) {
            $awarded[] = $this->actingAs($user)
                ->postJson('/analisis', ['text' => self::HOAX_SAMPLE.' Pesan varian nomor '.$i.'.'])
                ->assertOk()
                ->json('xp_awarded');
        }

        $this->assertSame([8, 8, 8, 8, 8, 0, 0], $awarded);
    }

    public function test_verdict_payload_never_leaks_a_raw_llm_string(): void
    {
        $user = User::factory()->create();

        $value = $this->actingAs($user)
            ->postJson('/analisis', ['text' => self::FACT_SAMPLE])
            ->assertOk()
            ->json('verdict.value');

        $this->assertContains($value, ['fact', 'hoax', 'uncertain'], 'Verdict wajib salah satu nilai enum.');
    }
}
