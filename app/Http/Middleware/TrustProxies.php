<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Tin reverse proxy (Caddy) theo config('app.trusted_proxies'): đọc qua config
 * (không đọc biến môi trường trực tiếp) để vẫn đúng sau `php artisan config:cache`.
 */
class TrustProxies extends Middleware
{
    protected function proxies()
    {
        $proxies = config('app.trusted_proxies', []);

        return $proxies === [] ? null : $proxies;
    }
}
