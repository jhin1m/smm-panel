<?php

use App\Filament\Pages\ManageUpdates;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    $this->dir = sys_get_temp_dir().'/updates-page-'.bin2hex(random_bytes(6));
    mkdir($this->dir.'/inbox', 0775, true);
    config(['updates.path' => $this->dir, 'app.version' => '1.2.0', 'updates.allow_prerelease' => false]);
    file_put_contents($this->dir.'/heartbeat', (string) time());

    Http::fake([
        'api.github.com/repos/jhin1m/smm-panel/releases/latest' => Http::response([
            'tag_name' => 'v1.3.0', 'name' => 'v1.3.0', 'body' => "## Thay đổi\n\n- Sửa lỗi nạp tiền <script>alert(1)</script>", 'html_url' => 'https://github.com/jhin1m/smm-panel/releases/tag/v1.3.0', 'draft' => false,
        ]),
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

function actAsAdminOnPanel(): User
{
    $admin = User::factory()->admin()->create();
    test()->actingAs($admin);
    filament()->setCurrentPanel('admin');

    return $admin;
}

it('admin thấy phiên bản hiện tại, bản mới và changelog (HTML bị loại)', function () {
    actAsAdminOnPanel();

    Livewire::test(ManageUpdates::class)
        ->assertSee('1.2.0')
        ->assertSee('1.3.0')
        ->assertSee('Sửa lỗi nạp tiền')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertActionVisible('update');
});

it('bấm Cập nhật ghi request.json đúng và audit system.update_requested', function () {
    $admin = actAsAdminOnPanel();

    Livewire::test(ManageUpdates::class)->callAction('update')->assertNotified();

    $req = json_decode(file_get_contents($this->dir.'/inbox/request.json'), true);
    expect($req['version'])->toBe('1.3.0')->and($req['requested_by'])->toBe($admin->id);

    $log = AdminAuditLog::firstWhere('action', 'system.update_requested');
    expect($log)->not->toBeNull()->and($log->admin_id)->toBe($admin->id)
        ->and($log->changes)->toEqual(['from' => '1.2.0', 'to' => '1.3.0']);
});

it('đã ở bản mới nhất: không có nút Cập nhật', function () {
    config(['app.version' => '1.3.0']);
    actAsAdminOnPanel();

    Livewire::test(ManageUpdates::class)->assertActionHidden('update');
});

it('đang chạy: nút bị khoá và yêu cầu thứ hai không ghi file', function () {
    actAsAdminOnPanel();
    file_put_contents($this->dir.'/status.json', json_encode(['state' => 'running', 'step' => 'backup', 'from' => '1.2.0', 'to' => '1.3.0']));

    Livewire::test(ManageUpdates::class)
        ->assertSee(__('updates.steps.backup'))
        ->assertActionDisabled('update')
        ->call('requestUpdate');

    expect(file_exists($this->dir.'/inbox/request.json'))->toBeFalse()
        ->and(AdminAuditLog::where('action', 'system.update_requested')->exists())->toBeFalse();
});

it('updater offline: nút bị khoá, hiện cảnh báo', function () {
    actAsAdminOnPanel();
    unlink($this->dir.'/heartbeat');

    Livewire::test(ManageUpdates::class)
        ->assertSee(__('updates.updater_offline'))
        ->assertActionDisabled('update');
});

it('hiện lý do thất bại của lần chạy trước', function () {
    actAsAdminOnPanel();
    file_put_contents($this->dir.'/status.json', json_encode(['state' => 'failed', 'step' => 'health', 'message' => 'App không healthy sau 300s', 'from' => '1.2.0', 'to' => '1.3.0']));

    Livewire::test(ManageUpdates::class)->assertSee('App không healthy sau 300s');
});

it('khách và người chưa đăng nhập không vào được trang', function () {
    $this->get('/admin/manage-updates')->assertRedirect();

    $this->actingAs(User::factory()->create());
    $this->get('/admin/manage-updates')->assertForbidden();
});

it('admin vào được trang qua HTTP', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/manage-updates')->assertOk();
});
