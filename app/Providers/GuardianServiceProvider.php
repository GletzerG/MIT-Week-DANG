<?php

namespace App\Providers;

use App\Services\Ai\LlmManager;
use App\Services\NewsAnalysisService;
use App\Services\QuestGeneratorService;
use App\Support\ProgressionCalculator;
use Illuminate\Support\ServiceProvider;

class GuardianServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton: koneksi HTTP & config dibaca sekali per request.
        $this->app->singleton(LlmManager::class, fn () => LlmManager::make());
        $this->app->singleton(ProgressionCalculator::class, fn () => new ProgressionCalculator);
        $this->app->singleton(NewsAnalysisService::class);
        $this->app->singleton(QuestGeneratorService::class);
    }
}
