<?php

use App\Actions\Fortify\CreateNewUser;
use App\Filament\Pages\ManageAccessSettings;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Settings\AccessSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;

function setAccess(array $values): void
{
    $s = app(AccessSettings::class);
    foreach ($values as $k => $v) {
        $s->{$k} = $v;
    }
    $s->save();
}

function stubGoogleCallback(string $email): void
{
    $g = (new SocialiteUser)->setRaw(['email_verified' => true])->map(['id' => 'g-access', 'name' => 'G User', 'email' => $email]);
    Socialite::shouldReceive('driver->user')->andReturn($g);
}

it('mặc định: đăng ký + Google bật, không bảo trì', function () {
    $s = app(AccessSettings::class);

    expect($s->registration_enabled)->toBeTrue()->and($s->google_login_enabled)->toBeTrue()->and($s->maintenance_enabled)->toBeFalse();
    $this->get('/register')->assertOk();
});

it('tắt đăng ký → /register 404 (GET, POST), /login vẫn 200 và ẩn nút đăng ký', function () {
    setAccess(['registration_enabled' => false]);

    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'A', 'email' => 'a@x.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->assertNotFound();
    $this->get('/login')->assertOk()->assertDontSee(route('register'));
    expect(User::where('email', 'a@x.test')->exists())->toBeFalse();
});

it('CreateNewUser từ chối tạo TK khi tắt đăng ký', function () {
    setAccess(['registration_enabled' => false]);

    expect(fn () => app(CreateNewUser::class)->create(['name' => 'A', 'email' => 'a@x.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!']))
        ->toThrow(ValidationException::class);
    expect(User::where('email', 'a@x.test')->exists())->toBeFalse();
});

it('tắt đăng ký → Google không tạo TK mới', function () {
    setAccess(['registration_enabled' => false]);

    stubGoogleCallback('brandnew@gmail.com');
    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');
    expect(User::where('email', 'brandnew@gmail.com')->exists())->toBeFalse();

});

it('tắt đăng ký → Google vẫn đăng nhập TK có sẵn', function () {
    setAccess(['registration_enabled' => false]);
    $existing = User::factory()->create(['email' => 'old@gmail.com']);

    stubGoogleCallback('old@gmail.com');
    $this->get('/auth/google/callback')->assertRedirect('/');
    $this->assertAuthenticatedAs($existing);
});

it('tắt Google → route 404 và ẩn nút; đăng ký vẫn hoạt động', function () {
    setAccess(['google_login_enabled' => false]);

    $this->get('/auth/google/redirect')->assertNotFound();
    $this->get('/auth/google/callback')->assertNotFound();
    $this->get('/login')->assertOk()->assertDontSee('Tiếp tục với Google');
    $this->get('/register')->assertOk();
});

it('bảo trì: khách thấy thông điệp 503; webhook, /up, /admin/login vẫn chạy', function () {
    configureDeposit();
    config(['sepay.webhook_secret' => SEPAY_TEST_SECRET]);
    setAccess(['maintenance_enabled' => true, 'maintenance_message' => 'Quay lại lúc 9h sáng']);

    $this->get('/login')->assertStatus(503)->assertSee('Quay lại lúc 9h sáng')->assertHeader('Retry-After');
    $this->get('/register')->assertStatus(503);
    $this->actingAs(User::factory()->create())->get('/')->assertStatus(503);
    auth()->logout();
    postSepay(sepayPayload())->assertOk();
    $this->get('/up')->assertOk();
    $this->get('/admin/login')->assertOk();
});

it('bảo trì: thông điệp rỗng → dùng mặc định; admin đăng nhập vẫn xem được trang khách', function () {
    setAccess(['maintenance_enabled' => true]);

    $this->get('/login')->assertStatus(503)->assertSee(__('common.maintenance.default_message'));

    $this->actingAs(User::factory()->admin()->create());
    $this->get('/')->assertOk();
});

it('bảo trì: action Livewire của trang khách đang mở cũng bị chặn, admin không bị ảnh hưởng', function () {
    $user = User::factory()->create();
    $html = $this->actingAs($user)->get('/dat-don')->assertOk()->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
    $snapshot = html_entity_decode($m[1], ENT_QUOTES);

    $update = fn () => $this->withHeader('X-Livewire', 'true')->postJson(app('livewire')->getUpdateUri(), [
        'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]]]],
    ]);

    // Chỉ gọi 1 lần: Livewire nhớ route đã áp persistent middleware trong cùng tiến trình (test chạy chung tiến trình).
    setAccess(['maintenance_enabled' => true]);
    $update()->assertStatus(503);

    // Admin dùng Livewire (Filament) bình thường khi bảo trì.
    $this->actingAs(User::factory()->admin()->create());
    expect($this->get('/admin')->status())->not->toBe(503);
});

it('app:customer-maintenance on|off ghi cờ, tham số sai báo lỗi', function () {
    $this->artisan('app:customer-maintenance', ['state' => 'on'])->assertSuccessful();
    expect(app(AccessSettings::class)->maintenance_enabled)->toBeTrue();
    $this->get('/login')->assertStatus(503);

    $this->artisan('app:customer-maintenance', ['state' => 'off'])->assertSuccessful();
    expect(app(AccessSettings::class)->maintenance_enabled)->toBeFalse();
    $this->get('/login')->assertOk();

    expect(Artisan::call('app:customer-maintenance', ['state' => 'maybe']))->not->toBe(0);
});

it('trang admin lưu công tắc + ghi audit', function () {
    $this->actingAs(User::factory()->admin()->create());
    filament()->setCurrentPanel('admin');

    Livewire::test(ManageAccessSettings::class)
        ->fillForm(['registration_enabled' => false, 'maintenance_message' => 'Bảo trì'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(AccessSettings::class)->registration_enabled)->toBeFalse()
        ->and(AdminAuditLog::firstWhere('action', 'settings.access')->changes['after'])->toHaveKey('registration_enabled');
});
