<?php

use App\Filament\Pages\ManageIntegrationSettings;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Settings\IntegrationSettings;
use App\Support\RuntimeConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['email' => 'boss@example.test']);
    $this->actingAs($this->admin);
    filament()->setCurrentPanel('admin');
    Cache::forget('illuminate:queue:restart');
});

it('lưu SMTP + bí mật; DB mã hoá; audit không lộ bí mật; queue:restart được gọi', function () {
    Livewire::test(ManageIntegrationSettings::class)
        ->fillForm(['smtp_host' => 'smtp.shop.test', 'smtp_port' => 587, 'smtp_password' => 'mat-khau-smtp', 'sepay_webhook_secret' => 'whsec-new'])
        ->call('save')
        ->assertHasNoFormErrors();

    $s = app(IntegrationSettings::class);
    expect($s->smtp_host)->toBe('smtp.shop.test')->and($s->smtp_password)->toBe('mat-khau-smtp')->and($s->sepay_webhook_secret)->toBe('whsec-new');

    $raw = DB::table('settings')->where('group', 'integrations')->pluck('payload', 'name');
    expect($raw['smtp_password'])->not->toContain('mat-khau-smtp')->and($raw['sepay_webhook_secret'])->not->toContain('whsec-new');

    $log = json_encode(AdminAuditLog::firstWhere('action', 'settings.integrations')->changes);
    expect($log)->not->toContain('mat-khau-smtp')->not->toContain('whsec-new')->toContain('smtp.shop.test');
    expect(Cache::get('illuminate:queue:restart'))->not->toBeNull();
});

it('form không gửi bí mật đã lưu xuống trình duyệt; để trống = giữ cũ', function () {
    $s = app(IntegrationSettings::class);
    $s->sepay_api_token = 'tok-cu';
    $s->save();

    Livewire::test(ManageIntegrationSettings::class)
        ->assertFormSet(['sepay_api_token' => null])
        ->fillForm(['smtp_host' => 'smtp.shop.test', 'sepay_api_token' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(IntegrationSettings::class)->sepay_api_token)->toBe('tok-cu');
});

it('đổi bí mật bằng giá trị mới', function () {
    $s = app(IntegrationSettings::class);
    $s->sepay_api_token = 'tok-cu';
    $s->save();

    Livewire::test(ManageIntegrationSettings::class)
        ->fillForm(['sepay_api_token' => 'tok-moi'])
        ->call('save');

    expect(app(IntegrationSettings::class)->sepay_api_token)->toBe('tok-moi');
});

it('port ngoài khoảng / email From sai → lỗi form', function () {
    Livewire::test(ManageIntegrationSettings::class)
        ->fillForm(['smtp_port' => 70000, 'mail_from_address' => 'khong-hop-le'])
        ->call('save')
        ->assertHasFormErrors(['smtp_port', 'mail_from_address']);
});

it('"Xoá (dùng .env)" xoá 1 bí mật, ghi audit, restart queue; request sau dùng lại .env', function () {
    config(['sepay.webhook_secret' => 'env-secret']);
    $s = app(IntegrationSettings::class);
    $s->sepay_webhook_secret = 'db-secret';
    $s->save();
    RuntimeConfig::apply();
    expect(config('sepay.webhook_secret'))->toBe('db-secret');

    Livewire::test(ManageIntegrationSettings::class)->call('clearSecret', 'sepay_webhook_secret');

    expect(app(IntegrationSettings::class)->sepay_webhook_secret)->toBeNull()
        ->and(Cache::get('illuminate:queue:restart'))->not->toBeNull()
        ->and(AdminAuditLog::where('action', 'settings.integrations')->exists())->toBeTrue();

    config(['sepay.webhook_secret' => 'env-secret']);   // request mới nạp lại .env
    RuntimeConfig::apply();
    expect(config('sepay.webhook_secret'))->toBe('env-secret');
});

it('clearSecret từ chối khoá không phải bí mật', function () {
    $s = app(IntegrationSettings::class);
    $s->smtp_host = 'smtp.shop.test';
    $s->save();

    Livewire::test(ManageIntegrationSettings::class)->call('clearSecret', 'smtp_host')->assertNotFound();

    expect(app(IntegrationSettings::class)->smtp_host)->toBe('smtp.shop.test');
});

it('webhook SePay: secret trong DB có hiệu lực, secret .env cũ bị 401; xoá DB → .env có hiệu lực lại', function () {
    configureDeposit();
    config(['sepay.webhook_secret' => 'env-secret']);
    $s = app(IntegrationSettings::class);
    $s->sepay_webhook_secret = 'db-secret';
    $s->save();
    RuntimeConfig::apply();

    postSepay(sepayPayload(), secret: 'db-secret')->assertOk();
    postSepay(sepayPayload(), secret: 'env-secret')->assertStatus(401);

    $s->sepay_webhook_secret = null;
    $s->save();
    config(['sepay.webhook_secret' => 'env-secret']);
    RuntimeConfig::apply();

    postSepay(sepayPayload(), secret: 'env-secret')->assertOk();
});

it('Gửi thử mail tới email admin hiện tại', function () {
    Livewire::test(ManageIntegrationSettings::class)->callAction('sendTestMail')->assertNotified();

    $messages = app('mailer')->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->getEnvelope()->getRecipients()[0]->getAddress())->toBe('boss@example.test');
});

it('Gửi thử mail lỗi kết nối → báo lỗi, không crash', function () {
    $s = app(IntegrationSettings::class);
    $s->smtp_host = '127.0.0.1';
    $s->smtp_port = 1;   // không ai nghe
    $s->save();

    Livewire::test(ManageIntegrationSettings::class)->callAction('sendTestMail')->assertNotified(__('admin.settings.integrations.test_mail_failed'));
});

it('Kiểm tra SePay: thiếu token → báo lỗi; có token → gọi API 1 ngày', function () {
    config(['sepay.api_token' => null]);
    Livewire::test(ManageIntegrationSettings::class)->callAction('checkSepay')->assertNotified(__('admin.settings.integrations.sepay_no_token'));

    Http::fake([config('sepay.api_url').'*' => Http::response(['status' => 200, 'transactions' => [['id' => 1, 'amount_in' => '1000', 'transaction_content' => 'x']]])]);
    $s = app(IntegrationSettings::class);
    $s->sepay_api_token = 'tok';
    $s->save();

    Livewire::test(ManageIntegrationSettings::class)->callAction('checkSepay')->assertNotified(__('admin.settings.integrations.sepay_ok', ['count' => 1]));
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer tok'));
});

it('Kiểm tra SePay: API lỗi → báo lỗi', function () {
    $s = app(IntegrationSettings::class);
    $s->sepay_api_token = 'tok';
    $s->save();
    Http::fake([config('sepay.api_url').'*' => Http::response('no', 500)]);

    Livewire::test(ManageIntegrationSettings::class)->callAction('checkSepay')->assertNotified(__('admin.settings.integrations.sepay_failed'));
});
