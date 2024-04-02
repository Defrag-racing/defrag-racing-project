<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Inertia\Middleware;

use App\Models\TournamentNew;

class TournamentPinnedNewsMiddleware extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function share(Request $request): array
    {
        $pinnedNews = TournamentNew::where('tournament_id', $request->tournament->id)
            ->where('pinned', true)
            ->first();

        return array_merge(parent::share($request), [
            'pinnedNews'      =>      $pinnedNews
        ]);
    }
}
