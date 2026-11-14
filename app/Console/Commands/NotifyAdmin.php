<?php

namespace App\Console\Commands;

use App\Services\AdminNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:notify-admin {message : Nội dung gửi admin qua Telegram}')]
#[Description('Gửi thông báo cho admin qua Telegram (dùng cho script backup, cron...)')]
class NotifyAdmin extends Command
{
    public function handle(AdminNotifier $notifier): int
    {
        $notifier->send((string) $this->argument('message'));
        $this->info('Đã đưa thông báo vào hàng đợi.');

        return self::SUCCESS;
    }
}
