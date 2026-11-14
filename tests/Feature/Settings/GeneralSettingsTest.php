<?php

use App\Filament\Pages\ManageGeneralSettings;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Support\Branding;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    filament()->setCurrentPanel('admin');
});

it('migration seed site_name từ config và default_locale hợp lệ', function () {
    $s = app(GeneralSettings::class);

    expect($s->site_name)->not->toBe('')->and($s->default_locale)->toBeIn(['vi', 'en']);
});

it('đổi tên site + ngôn ngữ → lưu, audit, brand admin đổi theo', function () {
    Livewire::test(ManageGeneralSettings::class)
        ->fillForm(['site_name' => 'Shop Của Tôi', 'default_locale' => 'en', 'support_email' => 'hi@shop.test'])
        ->call('save')
        ->assertHasNoFormErrors();

    $s = app(GeneralSettings::class);
    expect($s->site_name)->toBe('Shop Của Tôi')->and($s->default_locale)->toBe('en')
        ->and(Filament::getPanel('admin')->getBrandName())->toBe('Shop Của Tôi')
        ->and(AdminAuditLog::firstWhere('action', 'settings.general')->changes['after']['site_name'])->toBe('Shop Của Tôi');
});

it('thiếu tên site / email sai / locale lạ → lỗi form, không lưu', function () {
    Livewire::test(ManageGeneralSettings::class)
        ->fillForm(['site_name' => '', 'support_email' => 'khong-phai-email'])
        ->call('save')
        ->assertHasFormErrors(['site_name', 'support_email']);

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm(['site_name' => 'X', 'default_locale' => 'xx'])
        ->call('save')
        ->assertHasFormErrors(['default_locale']);
});

it('upload logo PNG → lưu path trên disk public, URL dùng cho brand', function () {
    Storage::fake('public');

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm(['site_name' => 'Shop', 'logo_path' => UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='))])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = app(GeneralSettings::class)->logo_path;
    expect($path)->toStartWith('branding/');
    Storage::disk('public')->assertExists($path);
    expect(Branding::logoUrl())->toContain($path)
        ->and(Filament::getPanel('admin')->getBrandLogo())->toContain($path);
});

it('từ chối logo không phải ảnh (svg/pdf)', function () {
    Storage::fake('public');

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm(['site_name' => 'Shop', 'logo_path' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])
        ->call('save')
        ->assertHasFormErrors(['logo_path']);
});

it('khách không vào được trang cài đặt', function () {
    auth()->logout();
    $this->actingAs(User::factory()->create());

    $this->get('/admin/manage-general-settings')->assertForbidden();
});
