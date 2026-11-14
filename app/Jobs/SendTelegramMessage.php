<?php

namespace App\Jobs;

use App\Settings\TelegramSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Gửi 1 tin HTML (đã escape sẵn) tới chat admin qua Telegram Bot API.
 * 429 → release theo retry_after; 4xx khác → fail luôn; 5xx/lỗi mạng → retry theo backoff.
 */
final class SendTelegramMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $html) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function handle(TelegramSettings $settings): void
    {
        if (! $settings->enabled || blank($settings->bot_token) || blank($settings->chat_id)) {
            return;
        }

        $res = self::post((string) $settings->bot_token, (string) $settings->chat_id, $this->html);

        if ($res->status() === 429) {
            $this->release((int) $res->json('parameters.retry_after', 30));

            return;
        }

        if ($res->clientError()) {
            $this->fail(new RuntimeException('Telegram 4xx: '.$res->status()));

            return;
        }

        if ($res->serverError()) {
            throw new RuntimeException('Telegram 5xx: '.$res->status());
        }
    }

    /**
     * Gọi sendMessage. Dùng chung cho job và nút "Gửi thử" ở trang cài đặt.
     * Lỗi kết nối có thể chứa URL kèm token → che token trước khi exception lan ra log/failed_jobs.
     */
    public static function post(string $token, string $chatId, string $html): Response
    {
        try {
            return Http::timeout(10)->asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $html,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException(str_replace($token, '***', $e->getMessage()));
        }
    }
}
