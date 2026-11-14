<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('deposit.bank_bin', null);
        $this->migrator->add('deposit.bank_name', null);
        $this->migrator->add('deposit.account_number', null);
        $this->migrator->add('deposit.account_name', null);
        $this->migrator->add('deposit.bonus_rules', []);
    }
};
