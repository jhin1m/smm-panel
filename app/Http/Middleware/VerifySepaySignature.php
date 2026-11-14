<?php

namespace App\Http\Middleware;

use App\Services\AdminNotifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác thực webhook SePay CHỈ bằng HMAC-SHA256 + timestamp (không có nhánh API key).
 * - Thiếu secret → 503 (fail closed, SePay retry; đối soát bù sau khi sửa) + log critical + Telegram ≤ 1 lần/giờ.
 * - Sai chữ ký / lệch giờ → 401; ≥ 10 lần trong 10 phút → Telegram ≤ 1 lần/giờ.
 * - IP ngoài allowlist chỉ log cảnh báo.
 */
class VerifySepaySignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('sepay.webhook_secret');

        if ($secret === '') {
            Log::critical('SePay webhook: thiếu SEPAY_WEBHOOK_SECRET — đang trả 503');
            $this->notifyOncePerHour('sepay:alert:no-secret', __('telegram.sepay_no_secret', [], config('app.default_locale')));

            return response()->json(['success' => false, 'message' => 'Webhook not configured'], 503);
        }

        $timestamp = (string) $request->header('X-SePay-Timestamp', '');
        $signature = (string) $request->header('X-SePay-Signature', '');
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        $fresh = ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= (int) config('sepay.timestamp_tolerance', 300);

        if (! $fresh || ! hash_equals($expected, $signature)) {
            $this->countFailure($request);

            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        if (! IpUtils::checkIp((string) $request->ip(), config('sepay.allowed_ips', []))) {
            Log::warning('SePay webhook từ IP ngoài allowlist (chữ ký hợp lệ)', ['ip' => $request->ip()]);
        }

        return $next($request);
    }

    private function countFailure(Request $request): void
    {
        Log::warning('SePay webhook: chữ ký hoặc timestamp không hợp lệ', ['ip' => $request->ip()]);

        // Cửa sổ cố định 10 phút.
        $key = 'sepay:401:'.intdiv(time(), 600);
        Cache::add($key, 0, 660);
        $count = Cache::increment($key);

        if ($count >= 10) {
            $this->notifyOncePerHour('sepay:alert:401', __('telegram.sepay_bad_signature', ['count' => $count, 'ip' => $request->ip()], config('app.default_locale')));
        }
    }

    private function notifyOncePerHour(string $key, string $message): void
    {
        if (Cache::add($key, true, 3600)) {
            app(AdminNotifier::class)->send($message);
        }
    }
}
