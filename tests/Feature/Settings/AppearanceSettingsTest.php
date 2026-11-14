<?php

use App\Filament\Pages\ManageAppearanceSettings;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Settings\AppearanceSettings;
use Livewire\Livewire;

function setAppearance(array $values): void
{
    $s = app(AppearanceSettings::class);
    foreach ($values as $k => $v) {
        $s->{$k} = $v;
    }
    $s->save();
}

beforeEach(function () {
    setAppearance([
        'primary_color' => '#e11d48',
        'custom_css' => '.brand{letter-spacing:2px}',
        'custom_head_html' => '<script>window.__HEAD_MARK=1</script>',
        'custom_body_html' => '<div id="body-mark">BODY_MARK</div>',
    ]);
});

it('khách chưa đăng nhập: in màu chính + custom CSS/head/body', function () {
    $this->get('/login')->assertOk()
        ->assertSee('--color-accent:#e11d48', false)
        ->assertSee('.brand{letter-spacing:2px}', false)
        ->assertSee('window.__HEAD_MARK=1', false)
        ->assertSee('BODY_MARK', false);
});

it('khách đã đăng nhập (trang khách) cũng nhận custom code', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertOk()->assertSee('window.__HEAD_MARK=1', false)->assertSee('BODY_MARK', false);
});

it('admin xem trang khách: KHÔNG render custom CSS/head/body (màu vẫn in)', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/')->assertOk()
        ->assertSee('--color-accent:#e11d48', false)
        ->assertDontSee('window.__HEAD_MARK=1', false)
        ->assertDontSee('letter-spacing:2px', false)
        ->assertDontSee('BODY_MARK', false);
});

it('custom CSS không thoát được thẻ <style>', function () {
    setAppearance(['custom_css' => 'a{color:red}</style><script>window.__ESCAPED=1</script>']);

    $this->get('/login')->assertDontSee('</style><script>window.__ESCAPED', false);
});

it('màu chính sai định dạng → quay về màu mặc định khi render', function () {
    setAppearance(['primary_color' => 'red;}</style>']);

    $this->get('/login')->assertSee('--color-accent:#1f47e6', false);
});

it('trang admin: validate hex, lưu + audit; admin panel dùng màu mới', function () {
    $this->actingAs(User::factory()->admin()->create());
    filament()->setCurrentPanel('admin');

    Livewire::test(ManageAppearanceSettings::class)
        ->fillForm(['primary_color' => 'xanh'])
        ->call('save')
        ->assertHasFormErrors(['primary_color']);

    Livewire::test(ManageAppearanceSettings::class)
        ->fillForm(['primary_color' => '#0a7c4a'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(AppearanceSettings::class)->primary_color)->toBe('#0a7c4a')
        ->and(AdminAuditLog::firstWhere('action', 'settings.appearance'))->not->toBeNull()
        ->and(filament()->getPanel('admin')->getColors())->toHaveKey('primary');
});
