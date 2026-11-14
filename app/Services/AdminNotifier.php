<?php

namespace App\Services;

use App\Jobs\SendTelegramMessage;
use Illuminate\Support\Str;

/**
 * Điểm duy nhất gửi thông báo cho admin qua Telegram.
 * Job dispatch afterCommit: transaction rollback → không gửi.
 */
class AdminNotifier
{
    /** Văn bản thuần — tự escape HTML. */
    public function send(string $text): void
    {
        $this->sendHtml(e(Str::limit($text, 3500)));
    }

    /** HTML đã escape từng phần bởi nơi gọi (dùng khi cần <b>, xuống dòng). */
    public function sendHtml(string $html): void
    {
        SendTelegramMessage::dispatch($html)->afterCommit();
    }
}
