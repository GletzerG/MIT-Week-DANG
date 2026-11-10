<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Landing untuk tamu; Guardian yang sudah login langsung dilempar ke arena.
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

// Breeze mengarahkan login ke sini. Sekarang ini rumah yang sebenarnya:
// feed berita terverifikasi + progres Guardian.
Route::get('/dashboard', [FeedController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // Stasiun deteksi klaim: tempel teks, AI menilai.
    Route::get('/analisis', [AnalysisController::class, 'index'])->name('analysis.index');
    Route::post('/analisis', [AnalysisController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('analysis.store');

    // Battle page (Inertia) + resolve jawaban (JSON, anti spam 30/menit)
    Route::get('/quest', [QuestController::class, 'index'])->name('quest.index');
    Route::post('/quest/attempt', [QuestController::class, 'attempt'])
        ->middleware('throttle:30,1')
        ->name('quest.attempt');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
