<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('appearance.primary_color', '#1f47e6');
        $this->migrator->add('appearance.custom_css', null);
        $this->migrator->add('appearance.custom_head_html', null);
        $this->migrator->add('appearance.custom_body_html', null);
    }
};
