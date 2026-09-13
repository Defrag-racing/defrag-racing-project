<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remember the page somebody was on when they went to log in.
 *
 * `redirect()->intended()` already works, but only the `auth` middleware ever
 * fills the session key it reads - so being bounced off a protected page
 * brings you back afterwards, while clicking Login from an ordinary page has
 * always dumped you on the front page. That is the case people actually hit:
 * reading a map page, logging in to report a run, and losing their place.
 *
 * Runs on the Fortify routes, so it covers every way in - the nav link, a
 * prompt on a page, a bookmark - rather than one link that has to be found
 * and updated everywhere.
 */
class RememberIntendedUrl
{
    /**
     * Paths that must never become a destination. Landing back on the login
     * form after logging in, or on the logout route, is worse than the front
     * page - and a failed attempt makes the login page its own referrer.
     */
    private const SKIP = [
        'login',
        'register',
        'logout',
        'forgot-password',
        'reset-password',
        'two-factor-challenge',
        'user/confirm-password',
        'user/confirmed-password-status',
        'email/verify',
    ];

    /**
     * Path prefixes that are not pages at all. They matter because Laravel
     * records the previous URL for every non-ajax GET that reaches it, an
     * `<img>` whose src came out `/storage/null` included - and the browser
     * sends no Referer when a link is opened from an email client, so that
     * asset 404 is what `url()->previous()` then answers with. A new player
     * verified his address on 13 Sep 2026 and landed on `/storage/null`.
     */
    private const NOT_A_PAGE = [
        'storage',
        'build',
        'images',
        'img',
        'fonts',
        'css',
        'js',
        'api',
        'livewire',
        'vendor',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->session()->has('url.intended')) {
            $previous = url()->previous();

            if ($this->isWorthReturningTo($request, $previous)) {
                $request->session()->put('url.intended', $previous);
            }
        }

        return $next($request);
    }

    /**
     * Only same-host pages: not an auth page, not an asset, not a file.
     * `url()->previous()` falls back to the app root when there is no referrer
     * at all, which is what we would have done anyway - so storing it costs
     * nothing and saves a branch.
     */
    private function isWorthReturningTo(Request $request, string $previous): bool
    {
        $parts = parse_url($previous);

        if ($parts === false || ($parts['host'] ?? null) !== $request->getHost()) {
            return false;
        }

        $path = trim($parts['path'] ?? '', '/');

        foreach (self::SKIP as $skip) {
            if ($path === $skip || str_starts_with($path, $skip . '/')) {
                return false;
            }
        }

        foreach (self::NOT_A_PAGE as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return false;
            }
        }

        // A file, not a page: a thumbnail, a stylesheet, a favicon.
        if (preg_match('/\.[A-Za-z0-9]{1,5}$/', $path)) {
            return false;
        }

        return true;
    }
}
