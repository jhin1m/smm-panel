<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('access.registration_enabled', true);
        $this->migrator->add('access.google_login_enabled', true);
        $this->migrator->add('access.customer_2fa_enabled', true);
        $this->migrator->add('access.maintenance_enabled', false);
        $this->migrator->add('access.maintenance_message', null);
    }
};
