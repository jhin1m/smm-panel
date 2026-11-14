<?php

namespace App\Http\Middleware;

use App\Support\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chưa cài → mọi route web + /admin chuyển về wizard /install. Đã cài → đi tiếp (route /install tự trả 404).
 *
 * Allowlist (không bao giờ bị chuyển hướng): chính wizard, endpoint Livewire (wizard chạy bằng Livewire, nên
 * `default-livewire.update`, `livewire.*` phải qua được), đổi ngôn ngữ, health check và webhook.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isAllowlisted($request) || InstallState::isInstalled()) {
            return $next($request);
        }

        return redirect()->route('install');
    }

    private function isAllowlisted(Request $request): bool
    {
        return $request->routeIs('install', 'default-livewire.update', 'livewire.*', 'locale.update')
            || $request->is('up', 'webhooks/*');
    }
}
