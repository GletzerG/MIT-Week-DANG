<?php

namespace App\Http\Controllers;

use App\Enums\QuestVerdict;
use App\Http\Resources\GuardianResource;
use App\Http\Resources\VerifiedNewsResource;
use App\Models\NewsQuest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard = etalase bukti, bukan arena ujian.
 *
 * Semua kartu di sini SUDAH terverifikasi: verdict, skor kredibilitas, dan
 * rujukan cek faktanya terbuka. Remaja bisa membaca polanya dulu sebelum
 * masuk ke Trial Quest yang menyembunyikan jawaban.
 */
class FeedController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $type = $request->string('type')->toString();
        $type = in_array($type, ['all', 'fact', 'hoax'], true) ? $type : 'all';

        $search = trim($request->string('q')->toString());

        $feed = NewsQuest::query()
            ->where('is_active', true)
            ->when($type !== 'all', fn ($q) => $q->where('verdict', $type))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($w) => $w
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('source_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%"));
            })
            ->orderByDesc('published_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (NewsQuest $quest) => (new VerifiedNewsResource($quest))->resolve());

        $counts = NewsQuest::query()
            ->where('is_active', true)
            ->selectRaw('verdict, count(*) as total')
            ->groupBy('verdict')
            ->pluck('total', 'verdict');

        return Inertia::render('Dashboard', [
            'guardian' => new GuardianResource($user),
            'feed'     => $feed,
            'filters'  => [
                'type'   => $type,
                'search' => $search,
            ],
            'totals' => [
                'all'   => (int) $counts->sum(),
                'fact'  => (int) ($counts[QuestVerdict::Fact->value] ?? 0),
                'hoax'  => (int) ($counts[QuestVerdict::Hoax->value] ?? 0),
            ],
            'today' => [
                'solved' => $user->attempts()
                    ->whereDate('created_at', today())
                    ->where('is_correct', true)
                    ->count(),
            ],
        ]);
    }
}