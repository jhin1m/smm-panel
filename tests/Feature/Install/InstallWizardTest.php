<?php

use App\Livewire\Install\InstallWizard;
use App\Models\User;
use App\Settings\DepositSettings;
use App\Settings\GeneralSettings;
use App\Settings\IntegrationSettings;
use App\Support\InstallState;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    markNotInstalled();
    config(['app.install_token' => 'tok-0123456789abcdef']);
});

/** Wizard đã qua bước token (giả lập session đã xác thực). */
function verifiedWizard(): Testable
{
    session()->put(InstallWizard::SESSION_KEY, true);

    return Livewire::test(InstallWizard::class);
}

function fillSiteAndAdmin(Testable $wizard, array $admin = []): Testable
{
    return $wizard
        ->set('site_name', 'Shop Của Tôi')
        ->set('default_locale', 'en')
        ->set('support_email', 'help@example.com')
        ->call('next')
        ->assertSet('step', InstallWizard::ADMIN_STEP)
        ->set('admin_name', $admin['name'] ?? 'Chủ Shop')
        ->set('admin_email', $admin['email'] ?? 'Owner@Example.com')
        ->set('admin_password', $admin['password'] ?? 'super-secret-123')
        ->set('admin_password_confirmation', $admin['confirm'] ?? 'super-secret-123');
}

it('GET /install chưa cài hiển thị bước nhập token', function () {
    $this->withoutVite();

    $this->get('/install')->assertOk()->assertSee(__('install.fields.token'));
});

it('URL /install?token=... điền sẵn ô token nhưng chưa xác thực', function () {
    Livewire::withQueryParams(['token' => 'tok-0123456789abcdef'])
        ->test(InstallWizard::class)
        ->assertSet('token', 'tok-0123456789abcdef')
        ->assertSet('step', InstallWizard::FIRST_STEP);

    expect(session(InstallWizard::SESSION_KEY))->toBeNull();
});

it('không cấu hình INSTALL_TOKEN: báo hướng dẫn và không cho qua bước 1', function () {
    config(['app.install_token' => null]);

    Livewire::test(InstallWizard::class)
        ->assertSee(__('install.token.not_configured'))
        ->set('token', 'bat-ky-gia-tri-nao')
        ->call('verifyToken')
        ->assertHasErrors('token')
        ->assertSet('step', InstallWizard::FIRST_STEP);

    expect(session(InstallWizard::SESSION_KEY))->toBeNull();
});

it('token sai bị từ chối, đúng thì sang bước 2 và đặt cờ xác thực', function () {
    Livewire::test(InstallWizard::class)
        ->set('token', 'sai')
        ->call('verifyToken')
        ->assertHasErrors('token')
        ->assertSet('step', InstallWizard::FIRST_STEP);

    expect(session(InstallWizard::SESSION_KEY))->toBeNull();

    Livewire::test(InstallWizard::class)
        ->set('token', 'tok-0123456789abcdef')
        ->call('verifyToken')
        ->assertHasNoErrors()
        ->assertSet('step', InstallWizard::SITE_STEP)
        ->assertSet('token', '');

    expect(session(InstallWizard::SESSION_KEY))->toBeTrue();
});

it('token đúng đổi session id (cookie phiên cài sẵn không thừa hưởng cờ xác thực)', function () {
    $before = session()->getId();

    Livewire::test(InstallWizard::class)
        ->set('token', 'tok-0123456789abcdef')
        ->call('verifyToken')
        ->assertHasNoErrors();

    expect(session()->getId())->not->toBe($before)
        ->and(session(InstallWizard::SESSION_KEY))->toBeTrue();
});

it('luồng đầy đủ: tạo admin, ghi settings, đặt installed_at, chuyển tới đăng nhập admin', function () {
    $wizard = fillSiteAndAdmin(verifiedWizard())
        ->call('next')
        ->assertSet('step', InstallWizard::OPTIONAL_STEP)
        ->set('bank_bin', '970422')
        ->set('bank_name', 'MB Bank')
        ->set('account_number', '0123456789')
        ->set('account_name', 'NGUYEN VAN A')
        ->set('smtp_host', 'smtp.example.com')
        ->set('smtp_port', '587')
        ->set('smtp_username', 'mailer')
        ->set('smtp_password', 'mail-pass')
        ->call('next')
        ->assertSet('step', InstallWizard::CONFIRM_STEP)
        ->call('finish')
        ->assertHasNoErrors()
        ->assertRedirect(route('filament.admin.auth.login'));

    $admin = User::firstWhere('email', 'owner@example.com');
    expect($admin)->not->toBeNull()
        ->and($admin->is_admin)->toBeTrue()
        ->and($admin->name)->toBe('Chủ Shop')
        ->and(Hash::check('super-secret-123', $admin->password))->toBeTrue()
        ->and($admin->app_authentication_secret)->toBeNull()
        ->and(InstallState::isInstalled())->toBeTrue()
        ->and(session(InstallWizard::SESSION_KEY))->toBeNull();

    $general = app(GeneralSettings::class)->refresh();
    expect($general->site_name)->toBe('Shop Của Tôi')
        ->and($general->default_locale)->toBe('en')
        ->and($general->support_email)->toBe('help@example.com');

    $deposit = app(DepositSettings::class)->refresh();
    expect($deposit->bank_bin)->toBe('970422')->and($deposit->account_number)->toBe('0123456789');

    $smtp = app(IntegrationSettings::class)->refresh();
    expect($smtp->smtp_host)->toBe('smtp.example.com')->and($smtp->smtp_port)->toBe(587)->and($smtp->smtp_password)->toBe('mail-pass');
});

it('bước 4 bỏ qua được: không ghi ngân hàng/SMTP', function () {
    fillSiteAndAdmin(verifiedWizard())
        ->call('next')
        ->set('smtp_host', 'nhap-do.example.com')
        ->call('skipOptional')
        ->assertSet('step', InstallWizard::CONFIRM_STEP)
        ->assertSet('smtp_host', '')
        ->call('finish')
        ->assertRedirect(route('filament.admin.auth.login'));

    expect(app(IntegrationSettings::class)->refresh()->smtp_host)->toBeNull()
        ->and(app(DepositSettings::class)->refresh()->bank_bin)->toBeNull();
});

it('email admin trùng user có sẵn → lỗi validate, không sang bước tiếp', function () {
    User::factory()->create(['email' => 'owner@example.com']);

    fillSiteAndAdmin(verifiedWizard())
        ->call('next')
        ->assertHasErrors('admin_email')
        ->assertSet('step', InstallWizard::ADMIN_STEP);

    expect(User::where('is_admin', true)->count())->toBe(0);
});

it('mật khẩu ngắn hoặc không khớp → lỗi validate', function () {
    fillSiteAndAdmin(verifiedWizard(), ['password' => 'short', 'confirm' => 'short'])
        ->call('next')
        ->assertHasErrors('admin_password');

    fillSiteAndAdmin(verifiedWizard(), ['confirm' => 'khac-hoan-toan-123'])
        ->call('next')
        ->assertHasErrors('admin_password');
});

it('nhập dở thông tin ngân hàng thì bắt đủ trường; có SMTP host thì bắt cổng', function () {
    fillSiteAndAdmin(verifiedWizard())
        ->call('next')
        ->set('bank_bin', '970422')
        ->call('next')
        ->assertHasErrors(['account_number', 'account_name'])
        ->set('bank_bin', '')
        ->set('smtp_host', 'smtp.example.com')
        ->call('next')
        ->assertHasErrors('smtp_port');
});

it('ngôn ngữ mặc định không hỗ trợ bị từ chối', function () {
    verifiedWizard()
        ->set('site_name', 'Shop')
        ->set('default_locale', 'xx')
        ->call('next')
        ->assertHasErrors('default_locale')
        ->assertSet('step', InstallWizard::SITE_STEP);
});

it('sau khi cài: GET /install lần 2 trả 404', function () {
    $this->withoutVite();

    fillSiteAndAdmin(verifiedWizard())->call('next')->call('skipOptional')->call('finish');

    $this->get('/install')->assertNotFound();
});

it('cảnh báo (không chặn) khi APP_URL khác host đang truy cập', function () {
    config(['app.url' => 'https://khac-host.example']);

    Livewire::test(InstallWizard::class)
        ->assertSee('khac-host.example')
        ->assertSee(__('install.fields.token'));
});

it('lang install có đủ khoá cho mọi bước', function () {
    foreach (range(1, InstallWizard::CONFIRM_STEP) as $step) {
        expect(__("install.steps.{$step}"))->not->toBe("install.steps.{$step}");
    }
});
