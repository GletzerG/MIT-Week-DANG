<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Titik masuk aplikasi.
 *
 * Ini bukan test kosmetik. Sebelumnya `/` menampilkan halaman sambutan Laravel
 * dan `/dashboard` menampilkan "You're logged in!" milik Breeze. Akibatnya user
 * berhasil login tapi tidak pernah melihat game-nya sama sekali.
 */
class EntryPointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_lands_on_guardian_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('canLogin', true)
                ->where('canRegister', true));
    }

    public function test_authenticated_guardian_is_sent_home_to_the_feed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_is_the_verified_feed_not_breeze_empty_screen(): void
    {
        // Breeze mengarahkan login ke /dashboard. Kalau halaman itu dirender apa
        // adanya, user mendarat di layar "You're logged in!" dan mengira ini
        // masih Laravel polos.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('guardian')
                ->has('feed.data')
                ->has('totals.all')
                ->has('today.solved'));
    }

    public function test_feed_exposes_the_verdict_but_trial_quest_does_not(): void
    {
        // Inti dua mode:
        //   Feed  → verdict terbuka, tujuannya mengajari pola.
        //   Trial → verdict disembunyikan, tujuannya menguji.
        // Kalau salah satu resource "kebablasan", test ini pecah.
        $this->seed(\Database\Seeders\NewsQuestSeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('feed.data.0.verdict')
                ->has('feed.data.0.credibility')
                ->has('feed.data.0.fact_check'));

        $this->actingAs($user)
            ->get('/quest')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Quest/Battle')
                ->missing('quest.verdict'));
    }

    public function test_login_page_uses_guardian_layout(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_unverified_guardian_can_still_enter_the_arena(): void
    {
        // Route arena sengaja tanpa middleware `verified`: proyek edukasi, bukan
        // sistem perbankan. Pendaftar baru harus langsung bisa main.
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)
            ->get('/quest')
            ->assertOk();
    }
}