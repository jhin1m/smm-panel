<?php

use App\Support\CustomOverrides;
use App\Support\Locales;
use Illuminate\Support\Facades\Blade;

/** Trỏ custom_path vào fixture (hoặc thư mục khác) rồi đăng ký lại; app được dựng mới mỗi test nên không rò state. */
function renderGuestLayout(): string
{
    return trim(Blade::render('<x-layouts::guest>nội dung</x-layouts::guest>'));
}

function useCustomPath(string $path): void
{
    config(['app.custom_path' => $path]);
    app('translator')->setLoaded([]);
    CustomOverrides::register();
}

beforeEach(fn () => Locales::flush());
afterEach(fn () => Locales::flush());

it('view trong custom/views ghi đè view lõi cùng tên', function () {
    expect(renderGuestLayout())->not->toBe('GUEST-LAYOUT-OVERRIDDEN');

    useCustomPath(base_path('tests/Fixtures/custom'));

    expect(renderGuestLayout())->toBe('GUEST-LAYOUT-OVERRIDDEN');
});

it('key trong custom/lang ghi đè từng key, key khác giữ nguyên bản lõi', function () {
    app()->setLocale('vi');
    $coreLogout = __('common.nav.logout');
    expect(__('common.guest.headline'))->not->toBe('Tiêu đề từ custom');

    useCustomPath(base_path('tests/Fixtures/custom'));

    expect(__('common.guest.headline'))->toBe('Tiêu đề từ custom')
        ->and(__('common.nav.logout'))->toBe($coreLogout)
        ->and(__('common.guest.points.deposit'))->not->toBe('common.guest.points.deposit');
});

it('locale mới trong custom/lang xuất hiện với tên trong meta.php; locale lõi giữ tên cũ', function () {
    expect(Locales::isSupported('th'))->toBeFalse();
    $viName = Locales::name('vi');

    useCustomPath(base_path('tests/Fixtures/custom'));

    expect(Locales::isSupported('th'))->toBeTrue()
        ->and(Locales::name('th'))->toBe('ไทย')
        ->and(Locales::name('vi'))->toBe($viName)
        ->and(Locales::supported())->toContain('vi', 'en', 'th');
});

it('locale mới thiếu key thì rơi về fallback locale', function () {
    useCustomPath(base_path('tests/Fixtures/custom'));
    app()->setLocale('th');

    expect(__('common.nav.logout'))->toBe(trans('common.nav.logout', [], config('app.fallback_locale')));
});

it('thư mục custom không tồn tại thì app chạy như cũ', function () {
    $before = Locales::supported();

    useCustomPath(base_path('tests/Fixtures/does-not-exist'));

    expect(Locales::supported())->toBe($before)
        ->and(renderGuestLayout())->toContain('authwrap');
});

it('filesystems.links có symlink public/custom trỏ vào custom/public', function () {
    expect(config('filesystems.links'))->toHaveKey(public_path('custom'))
        ->and(config('filesystems.links')[public_path('custom')])->toEndWith('/custom/public');
});

it('layout nạp custom.css chỉ khi file tồn tại', function () {
    $public = sys_get_temp_dir().'/smm-public-'.uniqid();
    mkdir("{$public}/custom", 0777, true);
    app()->usePublicPath($public);
    $this->withoutVite(); // public tạm không có file manifest của Vite

    try {
        expect(renderGuestLayout())->not->toContain('custom/custom.css');

        copy(base_path('tests/Fixtures/custom/public/custom.css'), "{$public}/custom/custom.css");
        expect(renderGuestLayout())->toContain('custom/custom.css?v=');
    } finally {
        @unlink("{$public}/custom/custom.css");
        @rmdir("{$public}/custom");
        @rmdir($public);
    }
});
