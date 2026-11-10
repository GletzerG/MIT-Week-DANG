<?php

namespace App\Jobs;

use App\Services\QuestGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Dijalankan scheduler tiap hari 03:00.
 * Ditaruh di queue supaya request web tidak pernah menunggu LLM.
 */
class GenerateDailyQuests implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $count = 0) {}

    public function handle(QuestGeneratorService $generator): void
    {
        $count = $this->count ?: (int) config('guardian.generator.daily_batch', 8);

        $created = $generator->generate($count);

        Log::info('guardian.generator.done', [
            'requested' => $count,
            'created'   => $created->count(),
        ]);
    }
}
