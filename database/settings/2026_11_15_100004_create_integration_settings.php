<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Không seed từ .env: DB rỗng = .env tiếp tục áp dụng, bí mật không bị chép vào DB.
        $this->migrator->add('integrations.smtp_host', null);
        $this->migrator->add('integrations.smtp_port', null);
        $this->migrator->add('integrations.smtp_username', null);
        $this->migrator->addEncrypted('integrations.smtp_password', null);
        $this->migrator->add('integrations.smtp_encryption', null);
        $this->migrator->add('integrations.mail_from_address', null);
        $this->migrator->add('integrations.google_client_id', null);
        $this->migrator->addEncrypted('integrations.google_client_secret', null);
        $this->migrator->addEncrypted('integrations.sepay_webhook_secret', null);
        $this->migrator->addEncrypted('integrations.sepay_api_token', null);
    }
};
