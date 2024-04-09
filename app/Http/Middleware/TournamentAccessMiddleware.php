<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TournamentAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tournament = $request->route('tournament');

        if ($tournament->isOrganizer($request->user()->id)) {
            return $next($request);
        }

        if ($tournament->published == false) {
            return redirect()->route('tournaments.index')->withDanger('This tournament is not published');
        }

        return $next($request);
    }
}
