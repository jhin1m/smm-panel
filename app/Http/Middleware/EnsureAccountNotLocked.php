<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** User bị khoá trong lúc đang đăng nhập → đăng xuất ở request kế tiếp. */
class EnsureAccountNotLocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isLocked()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('account.errors.locked')]);
        }

        return $next($request);
    }
}
