<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Tanpa ini, setiap JsonResource yang dikirim sebagai prop Inertia
        // terbungkus { data: ... } — frontend harus baca props.guardian.data
        // dan tipe TS-nya jadi meleset. Untuk aplikasi Inertia, flat lebih benar.
        JsonResource::withoutWrapping();
    }
}
