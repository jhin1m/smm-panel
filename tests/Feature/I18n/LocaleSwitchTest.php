<?php

use App\Models\User;
use App\Support\Locales;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Locales::flush());

it('liệt kê ngôn ngữ từ thư mục lang kèm tên hiển thị', function () {
    expect(Locales::supported())->toContain('vi', 'en')
        ->and(Locales::name('vi'))->toBe('Tiếng Việt')
        ->and(Locales::name('en'))->toBe('English')
        ->and(Locales::isSupported('xx'))->toBeFalse();
});

it('khách chưa đăng nhập: mặc định vi, đổi sang en lưu session và áp ngay', function () {
    $this->get('/login')->assertSee('Đăng nhập');

    $this->from('/login')->post('/locale', ['locale' => 'en'])
        ->assertRedirect('/login')
        ->assertSessionHas('locale', 'en');

    $this->get('/login')->assertSee('Log in')->assertDontSee('Đăng nhập');
});

it('user đăng nhập đổi ngôn ngữ: lưu DB và tự áp ở lần sau, kể cả phiên mới', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/locale', ['locale' => 'en'])->assertRedirect();
    expect($user->fresh()->locale)->toBe('en');

    $this->actingAs($user->fresh())->get('/')->assertSee('Overview')->assertDontSee('Tổng quan');

    // Phiên mới (không có session('locale')) vẫn theo users.locale.
    $this->flushSession();
    $this->actingAs($user->fresh())->get('/nap-tien')->assertSee('Add funds');
});

it('users.locale ưu tiên hơn session', function () {
    $user = User::factory()->create(['locale' => 'vi']);

    $this->actingAs($user)->withSession(['locale' => 'en'])->get('/')->assertSee('Tổng quan');
});

it('locale lạ → 422, không đổi session/DB', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/locale', ['locale' => 'xx'])->assertUnprocessable()->assertJsonValidationErrors('locale');
    $this->postJson('/locale', [])->assertUnprocessable();
    $this->postJson('/locale', ['locale' => '../etc'])->assertUnprocessable();

    expect($user->fresh()->locale)->toBeNull();
    $this->assertNull(session('locale'));
});

it('locale trong DB không còn được hỗ trợ → rơi về mặc định', function () {
    $user = User::factory()->create(['locale' => 'xx']);

    $this->actingAs($user)->get('/')->assertSee('Tổng quan');
});

it('mặc định theo config app.default_locale', function () {
    config(['app.default_locale' => 'en']);

    $this->get('/login')->assertSee('Log in');
});

it('email reset mật khẩu render đúng ngôn ngữ của user', function () {
    $user = User::factory()->create(['locale' => 'en']);

    expect($user->preferredLocale())->toBe('en');

    $user->notify(new ResetPassword('token-123'));

    $mail = collect(Mail::mailer('array')->getSymfonyTransport()->messages())->last()->getOriginalMessage();
    $body = $mail->getHtmlBody().$mail->getTextBody();

    expect($mail->getSubject())->toContain('Reset your')
        ->and($body)->toContain('Reset password')
        ->and($body)->toContain('This link expires in')
        ->and($body)->not->toContain('Đặt lại mật khẩu');
});

it('email reset của user vi vẫn là tiếng Việt', function () {
    $user = User::factory()->create(['locale' => 'vi']);

    $user->notify(new ResetPassword('token-123'));

    $mail = collect(Mail::mailer('array')->getSymfonyTransport()->messages())->last()->getOriginalMessage();

    expect($mail->getSubject())->toContain('Đặt lại mật khẩu')
        ->and($mail->getHtmlBody())->toContain('Liên kết hết hạn sau');
});

it('trang khách ở en không còn chữ Việt có dấu', function () {
    $user = User::factory()->create(['locale' => 'en']);

    foreach (['/', '/dat-don', '/don-hang', '/nap-tien'] as $path) {
        // Bỏ <select> (tên ngôn ngữ tự đặt tên, vd "Tiếng Việt"), tên app, tên user (dữ liệu, không dịch) và đ (ký hiệu tiền VND).
        $html = preg_replace('#<select.*?</select>#s', '', $this->actingAs($user)->get($path)->assertOk()->getContent());
        expect(str_replace([config('app.name'), $user->name], '', strip_tags($html)))->not->toMatch('/[ăâêôơưáàảãạấầẩẫậắằẳẵặéèẻẽẹếềểễệíìỉĩịóòỏõọốồổỗộớờởỡợúùủũụứừửữựýỳỷỹỵ]/u', $path);
    }
});

it('trang auth ở en không còn chữ Việt có dấu', function () {
    foreach (['/login', '/register', '/forgot-password', '/reset-password/abc?email=a@b.c'] as $path) {
        $html = preg_replace('#<select.*?</select>#s', '', $this->withSession(['locale' => 'en'])->get($path)->assertOk()->getContent());
        expect(str_replace(config('app.name'), '', strip_tags($html)))->not->toMatch('/[ăâêôơưáàảãạấầẩẫậắằẳẵặéèẻẽẹếềểễệíìỉĩịóòỏõọốồổỗộớờởỡợúùủũụứừửữựýỳỷỹỵ]/u', $path);
    }
});
