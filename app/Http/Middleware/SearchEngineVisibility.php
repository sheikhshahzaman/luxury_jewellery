<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends "X-Robots-Tag: noindex, nofollow" while the store is hidden from
 * search engines (Settings → Search engines), and always for the admin area.
 * The storefront layout adds the matching <meta name="robots"> tag.
 */
class SearchEngineVisibility
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (self::hidden() || $request->is('admin', 'admin/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    public static function hidden(): bool
    {
        return (string) setting('hide_from_search', '1') === '1';
    }
}
