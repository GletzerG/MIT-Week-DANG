<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuardianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\User $user */
        $user = $this->resource;
        $calc = app(\App\Support\ProgressionCalculator::class);
        $maxHp = $calc->maxHp();
        $hp = $user->currentHp();

        return [
            'name'          => $user->name,
            ...$calc->snapshot((int) $user->xp, (int) $user->level, $user->rank_object),
            'hp'            => $hp,
            'max_hp'        => $maxHp,
            'hp_ratio'      => $maxHp > 0 ? round($hp / $maxHp, 4) : 0,
            'streak'        => (int) $user->current_streak,
            'best_streak'   => (int) $user->best_streak,
            'solved_count'  => $user->attempts()->where('is_correct', true)->count(),
            'accuracy'      => $this->accuracy($user),
        ];
    }

    private function accuracy(\App\Models\User $user): float
    {
        $total = $user->attempts()->count();

        if ($total === 0) {
            return 0.0;
        }

        return round($user->attempts()->where('is_correct', true)->count() / $total, 3);
    }
}
