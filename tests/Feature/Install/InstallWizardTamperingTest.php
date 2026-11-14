<?php

use App\Livewire\Install\InstallWizard;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    markNotInstalled();
    config(['app.install_token' => 'tok-0123456789abcdef']);
});

it('chưa xác thực token: gọi thẳng next/back/skipOptional/finish → 404, không tạo admin', function (string $action) {
    Livewire::test(InstallWizard::class)
        ->set('site_name', 'Shop')
        ->set('admin_email', 'hacker@example.com')
        ->set('admin_password', 'super-secret-123')
        ->set('admin_password_confirmation', 'super-secret-123')
        ->call($action)
        ->assertNotFound();

    expect(User::count())->toBe(0);
})->with(['next', 'back', 'skipOptional', 'finish']);

it('sửa step từ client bị chặn bởi #[Locked]', function () {
    expect(fn () => Livewire::test(InstallWizard::class)->set('step', 5))
        ->toThrow(Exception::class, 'Cannot update locked property');
});

it('đã có session xác thực nhưng đứng ở bước 2: finish() bị 404 (không nhảy cóc tới bước xác nhận)', function () {
    session()->put(InstallWizard::SESSION_KEY, true);

    Livewire::test(InstallWizard::class)
        ->set('site_name', 'Shop')
        ->set('admin_email', 'hacker@example.com')
        ->set('admin_password', 'super-secret-123')
        ->set('admin_password_confirmation', 'super-secret-123')
        ->call('finish')
        ->assertNotFound();

    expect(User::count())->toBe(0);
});

/** Wizard đã mở ở bước xác nhận với dữ liệu hợp lệ (như một tab đang mở). */
function wizardAtConfirmStep(string $email): Testable
{
    session()->put(InstallWizard::SESSION_KEY, true);

    return Livewire::test(InstallWizard::class)
        ->set('site_name', 'Shop')
        ->call('next')
        ->set('admin_name', 'Chủ')
        ->set('admin_email', $email)
        ->set('admin_password', 'super-secret-123')
        ->set('admin_password_confirmation', 'super-secret-123')
        ->call('next')
        ->call('skipOptional')
        ->assertSet('step', InstallWizard::CONFIRM_STEP);
}

it('đã cài: tab cũ còn mở gọi action hay đổi property đều 404, không tạo admin thứ 2', function () {
    $first = wizardAtConfirmStep('owner@example.com');
    // Mỗi request 404 làm hỏng snapshot của component trong Livewire::test nên dùng 1 tab cũ cho mỗi kiểm tra.
    $staleTabs = [wizardAtConfirmStep('second@example.com'), wizardAtConfirmStep('third@example.com'), wizardAtConfirmStep('fourth@example.com')];

    $first->call('finish')->assertRedirect();
    expect(User::where('is_admin', true)->count())->toBe(1);

    session()->put(InstallWizard::SESSION_KEY, true);
    $staleTabs[0]->call('finish')->assertNotFound();
    $staleTabs[1]->set('admin_email', 'x@example.com')->assertNotFound();
    $staleTabs[2]->call('verifyToken')->assertNotFound();

    expect(User::where('is_admin', true)->count())->toBe(1)
        ->and(User::where('email', '!=', 'owner@example.com')->exists())->toBeFalse();
});

it('đã cài: mở component mới cũng 404', function () {
    markInstalled();

    Livewire::test(InstallWizard::class)->assertNotFound();
});
