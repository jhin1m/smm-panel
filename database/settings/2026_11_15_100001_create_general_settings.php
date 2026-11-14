<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Chỉ seed giá trị không nhạy cảm từ .env để instance cũ giữ nguyên tên + ngôn ngữ.
        $this->migrator->add('general.site_name', (string) config('app.name'));
        $this->migrator->add('general.logo_path', null);
        $this->migrator->add('general.favicon_path', null);
        $this->migrator->add('general.support_email', null);
        $this->migrator->add('general.support_zalo', null);
        $this->migrator->add('general.support_telegram', null);
        $this->migrator->add('general.default_locale', (string) config('app.default_locale', 'vi'));
        $this->migrator->add('general.seo_title', null);
        $this->migrator->add('general.seo_description', null);
    }
};
