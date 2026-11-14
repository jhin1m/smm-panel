<?php

namespace App\Http\Middleware;

use App\Settings\AccessSettings;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bảo trì CHỈ trang khách (gắn vào route khách + auth khách). /admin, webhook, /up không qua middleware này,
 * nên tiền vẫn về và admin vẫn làm việc. Admin đang đăng nhập vẫn xem được trang khách để kiểm tra.
 *
 * Đăng ký là persistent middleware của Livewire (AppServiceProvider) để chặn cả action Livewire
 * (/livewire/update) của trang khách đang mở. Livewire bỏ qua response không phải redirect của
 * persistent middleware, nên phải ném HttpResponseException thay vì return.
 */
class CustomerMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $access = app(AccessSettings::class);

        if (! $access->maintenance_enabled || $request->user()?->is_admin) {
            return $next($request);
        }

        throw new HttpResponseException(response()->view('errors.maintenance', [
            'message' => filled($access->maintenance_message) ? $access->maintenance_message : __('common.maintenance.default_message'),
        ], 503, ['Retry-After' => 300]));
    }
}
