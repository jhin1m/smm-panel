<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chọn ngôn ngữ mỗi request: users.locale → session('locale') → config('app.default_locale').
 * Dùng key default_locale riêng vì app()->setLocale() ghi đè config('app.locale') ở mỗi request.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = collect([
            $request->user()?->locale,
            $request->hasSession() ? $request->session()->get('locale') : null,
            config('app.default_locale'),
        ])->first(fn ($l) => is_string($l) && Locales::isSupported($l));

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
