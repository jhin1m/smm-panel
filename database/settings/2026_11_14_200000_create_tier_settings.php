<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('tier.agency_threshold', 5_000_000);
        $this->migrator->add('tier.agency_discount_percent', 8);
        $this->migrator->add('tier.member_discount_percent', 0);
    }
};
