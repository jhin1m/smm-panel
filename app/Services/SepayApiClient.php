<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SePay User API (legacy userapi: id giao dịch dạng số, trùng với `id` trong webhook).
 * API v2 (userapi.sepay.vn/v2) dùng UUID, không khớp id webhook → chưa dùng. Cô lập ở đây để đổi sau.
 *
 * GET {api_url}?transaction_date_min=Y-m-d H:i:s&transaction_date_max=...&limit=5000
 * Authorization: Bearer {token} → {"status":200,"transactions":[{id, bank_brand_name, account_number, sub_account,
 *   transaction_date, amount_in, amount_out, transaction_content, reference_number, code, ...}]}
 */
class SepayApiClient
{
    public function isConfigured(): bool
    {
        return filled(config('sepay.api_token'));
    }

    /**
     * Giao dịch trong khoảng thời gian, chuẩn hoá về dạng payload webhook.
     *
     * @return array<int, array<string, mixed>>
     */
    public function transactions(CarbonInterface $from, CarbonInterface $to): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Chưa cấu hình SEPAY_API_TOKEN');
        }

        $res = Http::timeout(20)->retry(2, 1000, throw: false)
            ->withToken((string) config('sepay.api_token'))
            ->acceptJson()
            ->get((string) config('sepay.api_url'), [
                'transaction_date_min' => $from->format('Y-m-d H:i:s'),
                'transaction_date_max' => $to->format('Y-m-d H:i:s'),
                'limit' => 5000,
            ]);

        if (! $res->successful() || ! is_array($res->json('transactions'))) {
            throw new RuntimeException(__('admin.bank_transactions.errors.sepay_api_error', ['status' => $res->status()]));
        }

        // Bỏ dòng thiếu id/số tiền thay vì làm hỏng cả lô.
        $rows = array_filter($res->json('transactions'), fn ($t) => is_array($t) && isset($t['id']) && is_numeric($t['id']));

        return array_values(array_map(fn (array $t) => $this->normalize($t), $rows));
    }

    /** @param  array<string, mixed>  $t */
    public function normalize(array $t): array
    {
        $in = (int) round((float) ($t['amount_in'] ?? 0));
        $out = (int) round((float) ($t['amount_out'] ?? 0));

        return [
            'id' => (int) $t['id'],
            'gateway' => $t['bank_brand_name'] ?? null,
            'transactionDate' => $t['transaction_date'] ?? null,
            'accountNumber' => $t['account_number'] ?? null,
            'subAccount' => $t['sub_account'] ?? null,
            'code' => $t['code'] ?? null,
            'content' => (string) ($t['transaction_content'] ?? ''),
            'transferType' => $in > 0 ? 'in' : 'out',
            'transferAmount' => $in > 0 ? $in : $out,
            'referenceCode' => $t['reference_number'] ?? null,
            'accumulated' => isset($t['accumulated']) ? (int) round((float) $t['accumulated']) : null,
            'source_api' => 'userapi',
        ];
    }
}
