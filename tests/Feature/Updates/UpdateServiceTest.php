<?php

use App\Exceptions\UpdateRejected;
use App\Models\User;
use App\Services\UpdateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/** Thư mục /updates giả cho mỗi test; luôn dọn sau khi xong. */
function useUpdatesDir(): string
{
    $dir = sys_get_temp_dir().'/updates-'.bin2hex(random_bytes(6));
    mkdir($dir.'/inbox', 0775, true);
    config(['updates.path' => $dir]);
    test()->updatesDir = $dir;

    return $dir;
}

function beat(string $dir, int $ageSeconds = 0): void
{
    file_put_contents("{$dir}/heartbeat", (string) (time() - $ageSeconds));
}

function writeStatus(string $dir, array $status): void
{
    file_put_contents("{$dir}/status.json", json_encode($status));
}

beforeEach(function () {
    Cache::flush();
    $this->updatesDir = useUpdatesDir();
    config(['app.version' => '1.2.0', 'updates.allow_prerelease' => false]);
});

afterEach(function () {
    File::deleteDirectory($this->updatesDir);
});

it('so sánh semver: bản ổn định lớn hơn rc cùng số, rc.N so theo số', function () {
    expect(UpdateService::isNewer('1.3.0', '1.2.9'))->toBeTrue()
        ->and(UpdateService::isNewer('1.10.0', '1.9.0'))->toBeTrue()
        ->and(UpdateService::isNewer('1.2.0', '1.2.0-rc.3'))->toBeTrue()
        ->and(UpdateService::isNewer('1.2.0-rc.3', '1.2.0'))->toBeFalse()
        ->and(UpdateService::isNewer('1.2.0-rc.10', '1.2.0-rc.9'))->toBeTrue()
        ->and(UpdateService::isNewer('1.2.0', '1.2.0'))->toBeFalse()
        ->and(UpdateService::isNewer('1.1.9', '1.2.0'))->toBeFalse()
        ->and(UpdateService::isNewer('abc', '1.2.0'))->toBeFalse();
});

it('chỉ nhận X.Y.Z hoặc X.Y.Z-rc.N, từ chối mọi chuỗi lạ', function (string $bad) {
    expect(UpdateService::parse($bad))->toBeNull();
})->with(['v1.2.3', '1.2', '1.2.3-beta.1', '1.2.3-rc', '1.2.3; rm -rf /', '../1.2.3', "1.2.3\n", '1.2.3-rc.1-x', '']);

it('currentVersion bỏ tiền tố v', function () {
    config(['app.version' => 'v1.4.0']);

    expect(app(UpdateService::class)->currentVersion())->toBe('1.4.0');
});

it('latestRelease lấy bản stable từ repo cấu hình và cache 1 giờ', function () {
    Http::fake([
        'api.github.com/repos/jhin1m/smm-panel/releases/latest' => Http::response([
            'tag_name' => 'v1.3.0', 'name' => 'v1.3.0', 'body' => '## Gì mới', 'html_url' => 'https://github.com/x', 'published_at' => '2026-11-01T00:00:00Z', 'draft' => false,
        ]),
    ]);

    $svc = app(UpdateService::class);
    $first = $svc->latestRelease();
    $svc->latestRelease();

    expect($first['version'])->toBe('1.3.0')->and($first['prerelease'])->toBeFalse()->and($first['body'])->toBe('## Gì mới');
    Http::assertSentCount(1);
});

it('latestRelease bỏ qua tag lạ và draft; lỗi GitHub trả null và không gọi lại ngay', function () {
    Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'nightly', 'draft' => false])]);
    expect(app(UpdateService::class)->latestRelease())->toBeNull();

    Cache::flush();
    Http::fake(['api.github.com/*' => Http::response('boom', 500)]);
    $svc = app(UpdateService::class);
    expect($svc->latestRelease())->toBeNull()->and($svc->latestRelease())->toBeNull();
    Http::assertSentCount(1);
});

it('khi bật prerelease, chọn bản mới nhất theo semver trong danh sách', function () {
    config(['updates.allow_prerelease' => true]);
    Http::fake([
        'api.github.com/repos/jhin1m/smm-panel/releases?*' => Http::response([
            ['tag_name' => 'v1.3.0-rc.1', 'draft' => false, 'prerelease' => true],
            ['tag_name' => 'v1.3.0-rc.2', 'draft' => false, 'prerelease' => true],
            ['tag_name' => 'v1.2.0', 'draft' => false, 'prerelease' => false],
            ['tag_name' => 'v1.4.0-rc.1', 'draft' => true],
        ]),
    ]);

    $latest = app(UpdateService::class)->latestRelease();

    expect($latest['version'])->toBe('1.3.0-rc.2')->and($latest['prerelease'])->toBeTrue();
});

it('latestRelease không bao giờ trả rc khi prerelease tắt, dù API trả về rc', function () {
    Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v1.3.0-rc.1', 'draft' => false])]);

    expect(app(UpdateService::class)->latestRelease())->toBeNull();
});

it('updaterAlive theo tuổi heartbeat', function () {
    $svc = app(UpdateService::class);
    expect($svc->updaterAlive())->toBeFalse();

    beat($this->updatesDir, 10);
    expect($svc->updaterAlive())->toBeTrue();

    beat($this->updatesDir, 61);
    expect($svc->updaterAlive())->toBeFalse();
});

it('state: rỗng là idle; running + heartbeat cũ là failed (stale); running + heartbeat mới giữ nguyên', function () {
    $svc = app(UpdateService::class);
    expect($svc->state()['state'])->toBe('idle');

    writeStatus($this->updatesDir, ['state' => 'running', 'step' => 'backup', 'from' => '1.2.0', 'to' => '1.3.0']);
    beat($this->updatesDir, 120);
    $stale = $svc->state();
    expect($stale['state'])->toBe('failed')->and($stale['stale'])->toBeTrue();

    beat($this->updatesDir, 5);
    $live = $svc->state();
    expect($live['state'])->toBe('running')->and($live['step'])->toBe('backup')->and($live['stale'])->toBeFalse();
});

it('state: file status hỏng không làm sập, coi như idle', function () {
    file_put_contents($this->updatesDir.'/status.json', '{không phải json');

    expect(app(UpdateService::class)->state()['state'])->toBe('idle');
});

it('requestUpdate ghi request.json đúng nội dung, quyền đọc cho mọi user, không để file tạm', function () {
    beat($this->updatesDir);
    $admin = User::factory()->admin()->create();

    app(UpdateService::class)->requestUpdate('1.3.0', $admin);

    $file = $this->updatesDir.'/inbox/request.json';
    $data = json_decode(file_get_contents($file), true);
    expect($data['version'])->toBe('1.3.0')->and($data['requested_by'])->toBe($admin->id)->and($data['at'])->not->toBeEmpty()
        ->and(decoct(fileperms($file) & 0777))->toBe('644')
        ->and(glob($this->updatesDir.'/inbox/.request-*'))->toBe([])
        ->and(app(UpdateService::class)->state()['state'])->toBe('pending');
});

it('requestUpdate chỉ ghi vào inbox/: thư mục gốc (của updater) không ghi được thì vẫn chạy, thiếu inbox thì từ chối', function () {
    beat($this->updatesDir);
    $admin = User::factory()->admin()->create();
    chmod($this->updatesDir, 0555);

    try {
        app(UpdateService::class)->requestUpdate('1.3.0', $admin);
        expect(is_file($this->updatesDir.'/inbox/request.json'))->toBeTrue()
            ->and(file_exists($this->updatesDir.'/request.json'))->toBeFalse();
    } finally {
        chmod($this->updatesDir, 0775);
    }

    File::deleteDirectory($this->updatesDir.'/inbox');
    expect(fn () => app(UpdateService::class)->requestUpdate('1.3.0', $admin))
        ->toThrow(UpdateRejected::class, __('updates.errors.not_writable'));
});

it('requestUpdate từ chối: sai định dạng, trùng/hạ cấp, rc khi chưa bật', function (string $version) {
    beat($this->updatesDir);

    expect(fn () => app(UpdateService::class)->requestUpdate($version, User::factory()->admin()->create()))
        ->toThrow(UpdateRejected::class);
    expect(file_exists($this->updatesDir.'/inbox/request.json'))->toBeFalse();
})->with(['1.3.0; id', 'v1.3.0', '1.2.0', '1.1.0', '1.3.0-rc.1']);

it('requestUpdate nhận rc khi bật prerelease', function () {
    config(['updates.allow_prerelease' => true]);
    beat($this->updatesDir);

    app(UpdateService::class)->requestUpdate('1.3.0-rc.1', User::factory()->admin()->create());

    expect(json_decode(file_get_contents($this->updatesDir.'/inbox/request.json'), true)['version'])->toBe('1.3.0-rc.1');
});

it('requestUpdate từ chối khi updater offline', function () {
    expect(fn () => app(UpdateService::class)->requestUpdate('1.3.0', User::factory()->admin()->create()))
        ->toThrow(UpdateRejected::class);
});

it('requestUpdate chặn yêu cầu trùng: đang chờ hoặc đang chạy', function () {
    beat($this->updatesDir);
    $svc = app(UpdateService::class);
    $admin = User::factory()->admin()->create();

    $svc->requestUpdate('1.3.0', $admin);
    expect(fn () => $svc->requestUpdate('1.3.0', $admin))->toThrow(UpdateRejected::class);

    unlink($this->updatesDir.'/inbox/request.json');
    writeStatus($this->updatesDir, ['state' => 'running', 'step' => 'pull']);
    expect(fn () => $svc->requestUpdate('1.4.0', $admin))->toThrow(UpdateRejected::class);
});

it('requestUpdate cho phép yêu cầu mới khi lần chạy kẹt đã thành stale và updater sống lại', function () {
    writeStatus($this->updatesDir, ['state' => 'running', 'step' => 'pull']);
    $svc = app(UpdateService::class);
    $admin = User::factory()->admin()->create();

    beat($this->updatesDir, 3);
    expect(fn () => $svc->requestUpdate('1.3.0', $admin))->toThrow(UpdateRejected::class);

    // Updater chết giữa chừng rồi khởi động lại nhưng chưa kịp sửa status: nhịp tim mới, status vẫn "running".
    // Hết 60 giây không nhịp tim thì stale; ở đây mô phỏng sau khi updater ghi lại failed.
    writeStatus($this->updatesDir, ['state' => 'failed', 'step' => 'recover', 'message' => 'interrupted']);
    beat($this->updatesDir, 1);

    $svc->requestUpdate('1.3.0', $admin);
    expect(file_exists($this->updatesDir.'/inbox/request.json'))->toBeTrue();
});

it('state running với heartbeat cũ không chặn yêu cầu mới ở tầng trạng thái (chỉ updater offline mới chặn)', function () {
    writeStatus($this->updatesDir, ['state' => 'running', 'step' => 'pull']);
    beat($this->updatesDir, 300);

    expect(app(UpdateService::class)->state()['state'])->toBe('failed');
    expect(fn () => app(UpdateService::class)->requestUpdate('1.3.0', User::factory()->admin()->create()))
        ->toThrow(UpdateRejected::class, __('updates.errors.updater_offline'));
});

it('requestUpdate báo lỗi rõ ràng khi không đặt được request.json và dọn file tạm', function () {
    beat($this->updatesDir);
    mkdir($this->updatesDir.'/inbox/request.json'); // rename đè lên thư mục sẽ thất bại

    expect(fn () => app(UpdateService::class)->requestUpdate('1.3.0', User::factory()->admin()->create()))
        ->toThrow(UpdateRejected::class, __('updates.errors.not_writable'));
    expect(glob($this->updatesDir.'/inbox/.request-*'))->toBe([]);

    rmdir($this->updatesDir.'/inbox/request.json');
});

it('history trả các lần chạy mới nhất trước', function () {
    file_put_contents($this->updatesDir.'/history.jsonl', implode("\n", [
        json_encode(['state' => 'failed', 'from' => '1.0.0', 'to' => '1.1.0']),
        'rác',
        json_encode(['state' => 'success', 'from' => '1.1.0', 'to' => '1.2.0']),
    ])."\n");

    $rows = app(UpdateService::class)->history();

    expect($rows)->toHaveCount(2)->and($rows[0]['to'])->toBe('1.2.0');
});
