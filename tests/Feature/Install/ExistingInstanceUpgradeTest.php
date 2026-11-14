<?php

use App\Models\User;
use App\Settings\InstallSettings;
use App\Support\InstallState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** Tái hiện DB của bản cũ: chưa có nhóm settings `install`, migration của nó chưa chạy. */
function legacyDatabase(): void
{
    DB::table('settings')->where('group', 'install')->delete();
    DB::table('migrations')->where('migration', 'like', '%create_install_settings')->delete();
    app()->forgetInstance(InstallSettings::class);
}

it('instance cũ có admin: migrate tự đánh dấu đã cài, trang khách không bị đẩy về /install', function () {
    User::factory()->admin()->create();
    legacyDatabase();

    expect(InstallState::isInstalled())->toBeFalse();
    expect(Artisan::call('migrate', ['--force' => true]))->toBe(0);

    expect(InstallState::isInstalled())->toBeTrue();

    $response = $this->get('/');
    expect($response->isRedirect() && str_contains((string) $response->headers->get('Location'), '/install'))->toBeFalse();
    $this->get('/install')->assertNotFound();
});

it('instance cũ chưa có admin: migrate chạy được, vẫn chưa cài', function () {
    legacyDatabase();

    expect(Artisan::call('migrate', ['--force' => true]))->toBe(0)
        ->and(InstallState::isInstalled())->toBeFalse();
});

it('thiếu dòng install trong settings: đọc ra mặc định null, không ném MissingSettings', function () {
    legacyDatabase();

    expect(app(InstallSettings::class)->installed_at)->toBeNull();
});
