<?php

namespace App\Http\Controllers;

use App\Actions\ResolveQuestAttempt;
use App\Enums\QuestVerdict;
use App\Http\Requests\SubmitAttemptRequest;
use App\Http\Resources\GuardianResource;
use App\Http\Resources\NewsQuestResource;
use App\Models\NewsQuest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestController extends Controller
{
    /**
     * Halaman Battle. Props yang dikirim hanya kartu + status guardian.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Quest/Battle', [
            'guardian' => new GuardianResource($user),
            'quest'    => fn () => $this->nextQuestFor($user),
            'stats'    => [
                'solved_today' => $user->attempts()
                    ->whereDate('created_at', today())
                    ->count(),
            ],
        ]);
    }

    /**
     * Submit jawaban. Mengembalikan JSON (bukan Inertia redirect).
     *
     * KENAPA JSON: frontend butuh animasi (+XP melayang, shake kartu, level up
     * overlay) tanpa full page reload. Inertia redirect akan membuang state
     * animasi dan membuat transisi terasa lambat.
     */
    public function attempt(SubmitAttemptRequest $request, ResolveQuestAttempt $resolve): JsonResponse
    {
        $user = $request->user();

        $quest = NewsQuest::where('public_id', $request->string('quest_id')->toString())->firstOrFail();

        // Guard: user tidak bisa menembak quest yang bukan jatah levelnya.
        abort_if($quest->difficulty->minLevel() > (int) $user->level + 3, 403, 'Quest ini belum terbuka untuk levelmu.');

        $result = $resolve->handle(
            user: $user,
            quest: $quest,
            chosen: QuestVerdict::from($request->string('verdict')->toString()),
            responseMs: $request->integer('response_ms') ?: null,
        );

        $next = $this->nextQuestFor($user->fresh(), exclude: $quest->id);

        return response()->json([
            'correct'    => $result['attempt']->is_correct,
            'chosen'     => $request->string('verdict')->toString(),
            'truth'      => $quest->verdict->value,
            'truth_label'=> $quest->verdict->label(),
            'xp_delta'   => $result['attempt']->xp_delta,
            'hp_delta'   => $result['attempt']->hp_delta,
            'leveled_up' => $result['leveled_up'],
            'ranked_up'  => $result['ranked_up'],
            'streak'     => $result['streak'],
            'guardian'   => (new GuardianResource($user->fresh()))->resolve(),
            'feedback'   => $result['feedback']->toArray(),
            'next_quest' => $next,
        ]);
    }

    private function nextQuestFor(\App\Models\User $user, ?int $exclude = null): ?array
    {
        $quest = NewsQuest::query()
            ->playableBy($user)
            ->forLevel((int) $user->level)
            ->when($exclude, fn ($q) => $q->where('id', '!=', $exclude))
            ->orderByDesc('published_at')
            ->inRandomOrder()
            ->first();

        // Tidak ada fallback yang membuka semua difficulty: itu membuat user
        // melihat quest yang akan ditolak 403 saat dijawab. Kalau pool habis,
        // frontend menampilkan jeda naratif — lebih jujur daripada tombol mati.

        return $quest ? (new NewsQuestResource($quest))->resolve() : null;
    }
}
