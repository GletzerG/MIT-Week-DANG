<?php

namespace App\Actions;

use App\Enums\QuestVerdict;
use App\Models\NewsQuest;
use App\Models\User;
use App\Models\UserAttempt;
use App\Services\NewsAnalysisService;
use App\Support\ProgressionCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalur yang boleh mengubah XP/HP user.
 *
 * KENAPA dijadikan Action: logika ini dipakai oleh controller HTTP,
 * nanti mungkin juga oleh API mobile. Kalau ditulis langsung di controller,
 * rumusnya akan terduplikasi dan gampang drift.
 */
final class ResolveQuestAttempt
{
    public function __construct(
        private ProgressionCalculator $calc,
        private NewsAnalysisService $ai,
    ) {}

    /**
     * @return array{attempt: UserAttempt, guardian: array, leveled_up: bool}
     */
    public function handle(
        User $user,
        NewsQuest $quest,
        QuestVerdict $chosen,
        ?int $responseMs = null,
    ): array {
        return DB::transaction(function () use ($user, $quest, $chosen, $responseMs) {

            // Lock baris user: mencegah double-submit (tombol diklik 2x)
            // menaikkan XP dua kali. Sangat nyata terjadi di mobile.
            /** @var User $locked */
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $isCorrect = $chosen === $quest->verdict;

            // Satu quest = satu kali hadiah/penalti, apa pun hasilnya.
            // KENAPA: tanpa guard ini, user bisa mengulang jawaban salah terus
            // menerus dan XP-nya terkuras (atau sebaliknya, farming XP dengan
            // mengulang jawaban benar). Menjawab ulang tetap boleh untuk latihan,
            // tapi tidak menggerakkan XP/HP.
            $isFirstAttempt = ! $locked->attempts()
                ->where('news_quest_id', $quest->id)
                ->exists();

            $xpBefore   = (int) $locked->xp;
            $hpBefore   = $locked->currentHp();
            $levelBefore = (int) $locked->level;

            $awardable = $isFirstAttempt;

            $xpDelta = $awardable
                ? $this->calc->xpDelta(
                    $isCorrect,
                    $quest->difficulty,
                    $isCorrect ? (int) $locked->current_streak : 0,
                )
                : 0;

            $hpDelta = $awardable ? $this->calc->hpDelta($isCorrect) : 0;

            // XP tidak boleh minus total, HP tidak boleh minus.
            $xpAfter = max(0, $xpBefore + $xpDelta);
            $hpAfter = max(0, $hpBefore + $hpDelta);

            $streak = $isCorrect
                ? (int) $locked->current_streak + 1
                : 0;

            $levelAfter = $this->calc->levelFromXp($xpAfter);
            $rankAfter  = $this->calc->rankFor($levelAfter);

            $locked->forceFill([
                'xp'             => $xpAfter,
                'level'          => $levelAfter,
                'rank'           => $rankAfter,
                'hp'             => $hpAfter,
                'hp_updated_at'  => now(),
                'current_streak' => $streak,
                'best_streak'    => max((int) $locked->best_streak, $streak),
                'last_played_at' => now(),
            ])->save();

            // AI dipanggil SETELAH state user tersimpan — kalau LLM lambat,
            // XP user sudah aman.
            $feedback = $this->ai->forAttempt($quest, $chosen, $isCorrect);

            $attempt = UserAttempt::create([
                'user_id'          => $locked->id,
                'news_quest_id'    => $quest->id,
                'chosen_verdict'   => $chosen,
                'is_correct'       => $isCorrect,
                'is_first_attempt' => $isFirstAttempt,
                'xp_delta'         => $xpAfter - $xpBefore,
                'hp_delta'         => $hpAfter - $hpBefore,
                'xp_before'        => $xpBefore,
                'xp_after'         => $xpAfter,
                'streak_after'     => $streak,
                'response_ms'      => $responseMs,
                'ai_model'         => $feedback->model,
                'ai_fallback'      => $feedback->fromFallback,
                'ai_feedback'      => $feedback->toArray(),
            ]);

            return [
                'attempt'     => $attempt,
                'feedback'    => $feedback,
                'guardian'    => $this->calc->snapshot($xpAfter, $levelAfter, $rankAfter),
                'leveled_up'  => $levelAfter > $levelBefore,
                'ranked_up'   => $rankAfter->value !== $this->calc->rankFor($levelBefore)->value,
                'streak'      => $streak,
                'hp'          => $hpAfter,
                'max_hp'      => $this->calc->maxHp(),
            ];
        });
    }
}
