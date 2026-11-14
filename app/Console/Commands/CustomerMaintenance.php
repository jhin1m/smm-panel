<?php

namespace App\Console\Commands;

use App\Settings\AccessSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:customer-maintenance {state : on|off}')]
#[Description('Bật/tắt bảo trì trang khách (admin, webhook, /up vẫn chạy)')]
class CustomerMaintenance extends Command
{
    public function handle(AccessSettings $access): int
    {
        $state = strtolower((string) $this->argument('state'));

        if (! in_array($state, ['on', 'off'], true)) {
            $this->error('Tham số phải là "on" hoặc "off".');

            return self::INVALID;
        }

        $access->maintenance_enabled = $state === 'on';
        $access->save();

        $this->info($state === 'on' ? 'Đã bật bảo trì trang khách.' : 'Đã tắt bảo trì trang khách.');

        return self::SUCCESS;
    }
}
