<?php

namespace Tests\Feature;

use App\Enums\QuestDifficulty;
use App\Models\NewsQuest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuestFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(\Database\Seeders\NewsQuestSeeder::class);
    }

    public function test_battle_page_loads_and_does_not_leak_verdict(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/quest')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Quest/Battle')
                // guardian harus FLAT (bukan { data: {...} }) — ini menangkap
                // regresi kalau JsonResource::withoutWrapping() hilang.
                ->has('guardian.level')
                ->has('guardian.rank_label')
                ->has('guardian.hp')
                ->has('quest.id')
                ->has('quest.title')
                ->has('quest.difficulty.reward')
                ->missing('quest.verdict')
            );
    }

    public function test_correct_answer_awards_xp_and_levels_up(): void
    {
        $user = User::factory()->create();

        $quests = NewsQuest::where('difficulty', QuestDifficulty::Novice->value)
            ->orderBy('id')
            ->take(4)
            ->get();

        $this->assertGreaterThanOrEqual(4, $quests->count(), 'Seeder butuh minimal 4 quest Novice.');

        $last = null;

        foreach ($quests as $quest) {
            $last = $this->actingAs($user)
                ->postJson('/quest/attempt', [
                    'quest_id' => $quest->public_id,
                    'verdict'  => $quest->verdict->value,
                ])
                ->assertOk()
                ->assertJson(['correct' => true, 'chosen' => $quest->verdict->value]);
        }

        // 30 + 33 + 36 + 39 = 138 XP -> lewat ambang level 2 (100 XP)
        $this->assertSame(138, $user->fresh()->xp);
        $this->assertSame(2, (int) $user->fresh()->level);
        $this->assertTrue($last->json('leveled_up'));
        $this->assertSame(4, $user->fresh()->current_streak);
    }

    public function test_replaying_the_same_quest_gives_zero_xp(): void
    {
        $user  = User::factory()->create();
        $quest = NewsQuest::where('difficulty', QuestDifficulty::Novice->value)->firstOrFail();

        $this->actingAs($user)->postJson('/quest/attempt', [
            'quest_id' => $quest->public_id,
            'verdict'  => $quest->verdict->value,
        ])->assertOk();

        $xpAfterFirst = $user->fresh()->xp;

        $second = $this->actingAs($user)->postJson('/quest/attempt', [
            'quest_id' => $quest->public_id,
            'verdict'  => $quest->verdict->value,
        ])->assertOk();

        $this->assertSame(0, $second->json('xp_delta'));
        $this->assertSame($xpAfterFirst, $user->fresh()->xp);
        $this->assertSame(2, $user->fresh()->attempts()->count());
    }

    public function test_wrong_answer_deducts_xp_and_hp(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['xp' => 200])->save();

        $quest = NewsQuest::where('difficulty', QuestDifficulty::Novice->value)->firstOrFail();
        $wrong = $quest->verdict->value === 'hoax' ? 'fact' : 'hoax';

        $res = $this->actingAs($user)->postJson('/quest/attempt', [
            'quest_id' => $quest->public_id,
            'verdict'  => $wrong,
        ])->assertOk()->assertJson(['correct' => false]);

        $this->assertSame(-9, $res->json('xp_delta'));   // 30 * 30% = 9
        $this->assertSame(-20, $res->json('hp_delta'));
        $this->assertSame(191, $user->fresh()->xp);
        $this->assertSame(80, (int) $user->fresh()->hp);
        $this->assertSame(0, $user->fresh()->current_streak);
    }

    public function test_xp_never_goes_below_zero(): void
    {
        $user  = User::factory()->create(); // xp = 0
        $quest = NewsQuest::where('difficulty', QuestDifficulty::Novice->value)->firstOrFail();
        $wrong = $quest->verdict->value === 'hoax' ? 'fact' : 'hoax';

        $res = $this->actingAs($user)->postJson('/quest/attempt', [
            'quest_id' => $quest->public_id,
            'verdict'  => $wrong,
        ])->assertOk();

        $this->assertSame(0, $res->json('xp_delta')); // penalti terpotong di nol
        $this->assertSame(0, $user->fresh()->xp);
        $this->assertSame(80, (int) $user->fresh()->hp); // HP tetap kena
    }

    public function test_locked_difficulty_is_rejected(): void
    {
        $user       = User::factory()->create(); // level 1
        $grandmaster = NewsQuest::where('difficulty', QuestDifficulty::Grandmaster->value)->firstOrFail();

        $this->actingAs($user)->postJson('/quest/attempt', [
            'quest_id' => $grandmaster->public_id,
            'verdict'  => 'hoax',
        ])->assertForbidden();
    }

    public function test_solved_quest_is_not_offered_again(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->get('/quest')->assertOk();
        $solvedId = $first->viewData('page')['props']['quest']['id'];

        $quest = NewsQuest::where('public_id', $solvedId)->firstOrFail();

        $this->actingAs($user)->postJson('/quest/attempt', [
            'quest_id' => $quest->public_id,
            'verdict'  => $quest->verdict->value,
        ])->assertOk();

        $second = $this->actingAs($user)->get('/quest')->assertOk();
        $nextId = $second->viewData('page')['props']['quest']['id'];

        $this->assertNotSame($solvedId, $nextId);
    }

    public function test_invalid_verdict_is_a_validation_error(): void
    {
        $user  = User::factory()->create();
        $quest = NewsQuest::where('difficulty', QuestDifficulty::Novice->value)->firstOrFail();

        $this->actingAs($user)->postJson('/quest/attempt', [
            'quest_id' => $quest->public_id,
            'verdict'  => 'mungkin',
        ])->assertStatus(422)->assertJsonValidationErrors('verdict');
    }
}
