<?php

use App\Settings\AccessSettings;
use App\Settings\AppearanceSettings;
use App\Settings\DepositSettings;
use App\Settings\GeneralSettings;
use App\Settings\IntegrationSettings;
use App\Support\RuntimeConfig;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** Tái hiện DB của bản cũ: còn 3 nhóm settings cũ, chưa có 4 nhóm mới. */
beforeEach(function () {
    $newGroups = ['general', 'access', 'appearance', 'integrations'];
    DB::table('settings')->whereIn('group', $newGroups)->delete();
    DB::table('migrations')->where('migration', 'like', '2026\_11\_15\_1000%')->delete();

    foreach ([GeneralSettings::class, AccessSettings::class, AppearanceSettings::class, IntegrationSettings::class] as $class) {
        app()->forgetInstance($class);
    }
});

it('3 nhóm cũ còn nguyên, 4 nhóm mới chưa có dòng nào', function () {
    expect(DB::table('settings')->whereIn('group', ['telegram', 'tier', 'deposit'])->count())->toBeGreaterThan(0)
        ->and(DB::table('settings')->whereIn('group', ['general', 'access', 'appearance', 'integrations'])->count())->toBe(0);
});

it('thiếu dòng settings mới → đọc ra giá trị mặc định, không ném MissingSettings', function () {
    expect(app(GeneralSettings::class)->site_name)->toBe('')
        ->and(app(AccessSettings::class)->registration_enabled)->toBeTrue()
        ->and(app(AccessSettings::class)->maintenance_enabled)->toBeFalse()
        ->and(app(AppearanceSettings::class)->primary_color)->toBe('#1f47e6')
        ->and(app(IntegrationSettings::class)->smtp_host)->toBeNull()
        ->and(app(DepositSettings::class))->toBeInstanceOf(DepositSettings::class);
});

it('boot (RuntimeConfig) không lỗi và không đổi config .env khi chưa có dòng mới', function () {
    config(['app.name' => 'Env Name', 'sepay.webhook_secret' => 'env-secret', 'mail.default' => 'array']);

    RuntimeConfig::apply();

    expect(config('app.name'))->toBe('Env Name')
        ->and(config('sepay.webhook_secret'))->toBe('env-secret')
        ->and(config('mail.default'))->toBe('array');
});

it('artisan migrate chạy được trên DB cũ và tạo 4 nhóm mới', function () {
    config(['app.name' => 'Tên Từ Env']);

    expect(Artisan::call('migrate', ['--force' => true]))->toBe(0);

    foreach (['general', 'access', 'appearance', 'integrations'] as $group) {
        expect(DB::table('settings')->where('group', $group)->count())->toBeGreaterThan(0);
    }
    app()->forgetInstance(GeneralSettings::class);
    expect(app(GeneralSettings::class)->site_name)->toBe('Tên Từ Env');
    // Không seed integrations từ .env: mọi giá trị rỗng.
    app()->forgetInstance(IntegrationSettings::class);
    $i = app(IntegrationSettings::class);
    expect($i->smtp_host)->toBeNull()->and($i->sepay_webhook_secret)->toBeNull()->and($i->google_client_secret)->toBeNull();
});
