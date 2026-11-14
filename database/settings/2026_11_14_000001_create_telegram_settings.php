<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('telegram.enabled', false);
        $this->migrator->addEncrypted('telegram.bot_token', null);
        $this->migrator->add('telegram.chat_id', null);
    }
};
