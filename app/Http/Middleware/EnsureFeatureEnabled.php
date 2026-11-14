<?php

namespace App\Http\Middleware;

use App\Settings\AccessSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn route theo công tắc trong settings (404 khi tắt). Route vẫn khai tĩnh để `route:cache` hoạt động.
 * Dùng: ensure.feature:registration | ensure.feature:google_login
 */
class EnsureFeatureEnabled
{
    private const FEATURES = [
        'registration' => 'registration_enabled',
        'google_login' => 'google_login_enabled',
    ];

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $property = self::FEATURES[$feature] ?? null;

        abort_if($property === null || ! app(AccessSettings::class)->{$property}, 404);

        return $next($request);
    }
}
