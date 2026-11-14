<?php

use App\Settings\GeneralSettings;
use App\Settings\IntegrationSettings;
use App\Support\RuntimeConfig;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

function setIntegrations(array $values): IntegrationSettings
{
    $s = app(IntegrationSettings::class);
    foreach ($values as $k => $v) {
        $s->{$k} = $v;
    }
    $s->save();

    return $s;
}

/** Giả lập "một request mới": config về giá trị .env rồi mới áp settings. */
function envConfig(array $overrides = []): void
{
    config(array_merge([
        'app.name' => 'Env Name',
        'app.default_locale' => 'vi',
        'mail.default' => 'array',
        'mail.mailers.smtp.host' => 'env-host',
        'mail.mailers.smtp.password' => 'env-pass',
        'mail.from.address' => 'env@example.com',
        'services.google.client_id' => 'env-gid',
        'services.google.client_secret' => 'env-gsecret',
        'sepay.webhook_secret' => 'env-sepay-secret',
        'sepay.api_token' => 'env-sepay-token',
    ], $overrides));
}

function withArtisanCommand(string $command, Closure $fn): void
{
    $argv = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = ['artisan', $command];
    try {
        $fn();
    } finally {
        $_SERVER['argv'] = $argv;
    }
}

it('DB rỗng → giữ nguyên config từ .env', function () {
    envConfig();
    $general = app(GeneralSettings::class);
    $general->site_name = '';
    $general->default_locale = null;
    $general->save();

    RuntimeConfig::apply();

    expect(config('app.name'))->toBe('Env Name')
        ->and(config('mail.default'))->toBe('array')
        ->and(config('mail.mailers.smtp.host'))->toBe('env-host')
        ->and(config('services.google.client_id'))->toBe('env-gid')
        ->and(config('sepay.webhook_secret'))->toBe('env-sepay-secret')
        ->and(config('sepay.api_token'))->toBe('env-sepay-token');
});

it('DB có giá trị → đè config', function () {
    envConfig();
    $general = app(GeneralSettings::class);
    $general->site_name = 'Cửa Hàng Của Tôi';
    $general->default_locale = 'en';
    $general->save();
    setIntegrations([
        'smtp_host' => 'smtp.db.test', 'smtp_port' => 587, 'smtp_username' => 'u', 'smtp_password' => 'p',
        'smtp_encryption' => 'ssl', 'mail_from_address' => 'shop@db.test',
        'google_client_id' => 'db-gid', 'google_client_secret' => 'db-gsecret',
        'sepay_webhook_secret' => 'db-whsec', 'sepay_api_token' => 'db-token',
    ]);

    RuntimeConfig::apply();

    expect(config('app.name'))->toBe('Cửa Hàng Của Tôi')
        ->and(config('mail.from.name'))->toBe('Cửa Hàng Của Tôi')
        ->and(config('app.default_locale'))->toBe('en')
        ->and(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.db.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)
        ->and(config('mail.mailers.smtp.username'))->toBe('u')
        ->and(config('mail.mailers.smtp.password'))->toBe('p')
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.from.address'))->toBe('shop@db.test')
        ->and(config('services.google.client_id'))->toBe('db-gid')
        ->and(config('services.google.client_secret'))->toBe('db-gsecret')
        ->and(config('services.google.redirect'))->toBe(url('/auth/google/callback'))
        ->and(config('sepay.webhook_secret'))->toBe('db-whsec')
        ->and(config('sepay.api_token'))->toBe('db-token');
});

it('mail.default thành smtp chỉ khi có smtp_host; STARTTLS map sang scheme smtp', function () {
    envConfig();
    setIntegrations(['smtp_encryption' => 'tls', 'smtp_username' => 'only-user']);
    RuntimeConfig::apply();
    expect(config('mail.default'))->toBe('array');

    setIntegrations(['smtp_host' => 'smtp.db.test']);
    RuntimeConfig::apply();
    expect(config('mail.default'))->toBe('smtp')->and(config('mail.mailers.smtp.scheme'))->toBe('smtp');
});

it('smtp_host trong DB → không dùng MAIL_USERNAME/MAIL_PASSWORD của .env cho host đó', function () {
    envConfig();
    config(['mail.mailers.smtp.username' => 'env-user', 'mail.mailers.smtp.password' => 'env-pass']);
    setIntegrations(['smtp_host' => 'smtp.attacker.test']);
    RuntimeConfig::apply();

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.attacker.test')
        ->and(config('mail.mailers.smtp.username'))->toBeNull()
        ->and(config('mail.mailers.smtp.password'))->toBeNull();
});

it('xoá giá trị DB → request kế dùng lại .env', function () {
    envConfig();
    setIntegrations(['sepay_webhook_secret' => 'db-whsec']);
    RuntimeConfig::apply();
    expect(config('sepay.webhook_secret'))->toBe('db-whsec');

    setIntegrations(['sepay_webhook_secret' => null]);
    envConfig();   // request mới nạp lại .env
    RuntimeConfig::apply();

    expect(config('sepay.webhook_secret'))->toBe('env-sepay-secret');
});

it('bí mật trong DB lưu mã hoá, không phải plaintext', function () {
    setIntegrations(['sepay_webhook_secret' => 'plain-secret-xyz']);

    $raw = DB::table('settings')->where('group', 'integrations')->where('name', 'sepay_webhook_secret')->value('payload');

    expect($raw)->not->toContain('plain-secret-xyz');
});

it('không giải mã được bí mật (đổi APP_KEY) → không crash, giữ .env', function () {
    envConfig();
    $foreign = (new Encrypter(random_bytes(32), 'AES-256-CBC'))->encrypt('whatever');
    DB::table('settings')->where('group', 'integrations')->where('name', 'sepay_api_token')->update(['payload' => json_encode($foreign)]);
    app()->forgetInstance(IntegrationSettings::class);

    RuntimeConfig::apply();

    expect(config('sepay.api_token'))->toBe('env-sepay-token');
});

it('thiếu bảng settings (DB chưa migrate) → không crash, giữ .env', function () {
    envConfig();
    app()->forgetInstance(GeneralSettings::class);
    app()->forgetInstance(IntegrationSettings::class);
    // Savepoint: Postgres huỷ cả transaction sau lỗi truy vấn, rollBack trả DB test về trạng thái cũ.
    DB::beginTransaction();
    DB::statement('ALTER TABLE settings RENAME TO settings_tmp');

    try {
        RuntimeConfig::apply();
    } finally {
        DB::rollBack();
    }

    expect(config('app.name'))->toBe('Env Name');
});

it('lệnh migrate/config:cache/settings:* không áp config DB', function (string $command) {
    envConfig();
    setIntegrations(['sepay_webhook_secret' => 'db-whsec', 'smtp_host' => 'smtp.db.test']);

    withArtisanCommand($command, fn () => RuntimeConfig::apply());

    expect(config('sepay.webhook_secret'))->toBe('env-sepay-secret')->and(config('mail.default'))->toBe('array');
})->with(['migrate', 'migrate:fresh', 'migrate:status', 'config:cache', 'config:clear', 'optimize', 'optimize:clear', 'package:discover', 'settings:discover', 'filament:upgrade']);

it('option đứng trước tên lệnh vẫn bị nhận ra là lệnh không áp config DB', function (array $args) {
    envConfig();
    setIntegrations(['sepay_webhook_secret' => 'db-whsec']);

    $argv = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = ['artisan', ...$args];
    try {
        RuntimeConfig::apply();
    } finally {
        $_SERVER['argv'] = $argv;
    }

    expect(config('sepay.webhook_secret'))->toBe('env-sepay-secret');
})->with([
    'short option' => [['-q', 'optimize']],
    'long option' => [['--no-interaction', 'config:cache']],
    'option with separate value' => [['--env', 'production', 'config:cache']],
]);

it('lệnh thường (queue:work) vẫn áp config DB', function () {
    envConfig();
    setIntegrations(['sepay_webhook_secret' => 'db-whsec']);

    withArtisanCommand('queue:work', fn () => RuntimeConfig::apply());

    expect(config('sepay.webhook_secret'))->toBe('db-whsec');
});

it('config:cache chỉ chứa giá trị .env, không chứa bí mật DB', function () {
    envConfig();
    setIntegrations(['smtp_password' => 'db-smtp-pass-123', 'google_client_secret' => 'db-gsecret-123', 'sepay_webhook_secret' => 'db-whsec-123', 'sepay_api_token' => 'db-token-123']);

    withArtisanCommand('config:cache', function () {
        RuntimeConfig::apply();

        $dump = var_export(config()->all(), true);   // đúng thứ config:cache ghi ra đĩa
        expect($dump)->not->toContain('db-smtp-pass-123')->not->toContain('db-gsecret-123')
            ->not->toContain('db-whsec-123')->not->toContain('db-token-123');
    });
});
