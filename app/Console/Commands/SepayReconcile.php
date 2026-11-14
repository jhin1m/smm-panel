<?php

namespace App\Console\Commands;

use App\Services\AdminNotifier;
use App\Services\SepayApiClient;
use App\Services\SepayReconciler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('app:sepay-reconcile {--from= : Từ thời điểm (Y-m-d [H:i:s]), mặc định 3 ngày trước} {--to= : Đến thời điểm, mặc định bây giờ}')]
#[Description('Đối soát giao dịch SePay qua User API: cộng giao dịch bị lỡ webhook (không trùng)')]
class SepayReconcile extends Command
{
    public function handle(SepayApiClient $api, SepayReconciler $reconciler): int
    {
        if (! $api->isConfigured()) {
            $this->warn('Chưa cấu hình SEPAY_API_TOKEN — bỏ qua đối soát.');

            return self::SUCCESS;
        }

        $from = $this->option('from') ? CarbonImmutable::parse($this->option('from')) : now()->subDays(3)->toImmutable();
        $to = $this->option('to') ? CarbonImmutable::parse($this->option('to')) : now()->toImmutable();

        try {
            $counts = $reconciler->run($from, $to);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            report($e);
            $this->alertOncePerHour('sepay:alert:reconcile-failed', __('telegram.reconcile_failed', ['error' => $e->getMessage()], config('app.default_locale')));

            return self::FAILURE;
        }

        $this->info(collect($counts)->map(fn ($n, $k) => "{$k}={$n}")->implode(' '));

        if ($counts['errors'] > 0) {
            $this->alertOncePerHour('sepay:alert:reconcile-rows', __('telegram.reconcile_row_errors', ['count' => $counts['errors']], config('app.default_locale')));
        }

        return self::SUCCESS;
    }

    private function alertOncePerHour(string $key, string $message): void
    {
        if (Cache::add($key, true, 3600)) {
            app(AdminNotifier::class)->send($message);
        }
    }
}
