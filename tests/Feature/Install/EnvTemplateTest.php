<?php

/** @return array<string, string> */
function envTemplateKeys(): array
{
    $keys = [];
    foreach (file(base_path('.env.template'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $m)) {
            $keys[$m[1]] = $m[2];
        }
    }

    return $keys;
}

it('.env.template chứa đủ các khoá bắt buộc', function () {
    $required = [
        'APP_URL', 'APP_DOMAIN', 'APP_KEY', 'APP_VERSION', 'APP_IMAGE', 'UPDATER_IMAGE',
        'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
        'INSTALL_TOKEN', 'INSTALL_DIR', 'BACKUP_DIR', 'TRUSTED_PROXIES',
        'APP_LOCALE', 'APP_FALLBACK_LOCALE', 'SESSION_DRIVER', 'CACHE_STORE', 'QUEUE_CONNECTION',
        'APP_MAINTENANCE_DRIVER', 'APP_MAINTENANCE_STORE', 'MAIL_MAILER',
    ];

    expect(array_diff($required, array_keys(envTemplateKeys())))->toBe([]);
});

it('.env.template: giá trị mặc định đúng cho môi trường production', function () {
    $env = envTemplateKeys();

    expect($env['APP_ENV'])->toBe('production')
        ->and($env['APP_DEBUG'])->toBe('false')
        ->and($env['SESSION_DRIVER'])->toBe('database')
        ->and($env['QUEUE_CONNECTION'])->toBe('database')
        ->and($env['APP_MAINTENANCE_DRIVER'])->toBe('cache')
        ->and($env['APP_MAINTENANCE_STORE'])->toBe('database')
        ->and($env['MAIL_MAILER'])->toBe('smtp');
});

it('.env.template không chứa bí mật dựng sẵn', function () {
    $env = envTemplateKeys();

    foreach (['APP_KEY', 'DB_PASSWORD', 'INSTALL_TOKEN', 'APP_IMAGE', 'UPDATER_IMAGE', 'APP_VERSION', 'MAIL_PASSWORD'] as $secret) {
        expect($env[$secret])->toBe('');
    }
});

it('.env.template không có cấu hình Google/SePay (nhập trong wizard/admin)', function () {
    $keys = array_keys(envTemplateKeys());

    expect(preg_grep('/^(GOOGLE_|SEPAY_)/', $keys))->toBe([]);
});
