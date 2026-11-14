<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Đối soát: kéo giao dịch từ SePay User API, đưa giao dịch chưa có qua handleWebhook(source: reconcile).
 * Chặn cộng 2 lần khi id API ≠ id webhook nằm trong DepositService (cùng mã tham chiếu → lưu "chưa khớp" + báo admin).
 */
class SepayReconciler
{
    public function __construct(private SepayApiClient $api, private DepositService $deposits) {}

    /** @return array<string, int> đếm theo kết quả */
    public function run(CarbonInterface $from, CarbonInterface $to): array
    {
        $counts = ['fetched' => 0, DepositService::CREDITED => 0, DepositService::UNMATCHED => 0, DepositService::IGNORED => 0, DepositService::DUPLICATE => 0, 'errors' => 0];

        foreach ($this->api->transactions($from, $to) as $p) {
            $counts['fetched']++;

            if ($p['id'] <= 0 || $p['transferAmount'] <= 0) {
                continue;
            }

            try {
                $counts[$this->deposits->handleWebhook($p, 'reconcile')]++;
            } catch (Throwable $e) {
                // 1 dòng lỗi không chặn cả lô; nơi gọi báo admin khi errors > 0.
                $counts['errors']++;
                Log::error('Đối soát SePay: lỗi xử lý giao dịch', ['id' => $p['id'], 'error' => $e->getMessage()]);
            }
        }

        return $counts;
    }
}
