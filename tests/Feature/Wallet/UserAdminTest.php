<?php

use App\Enums\Tier;
use App\Enums\TxType;
use App\Filament\Pages\ManageTierSettings;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\UserAdminService;
use App\Services\WalletService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    filament()->setCurrentPanel('admin');
});

it('liệt kê khách, không có admin', function () {
    $customer = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$customer])
        ->assertCanNotSeeTableRecords([$this->admin]);
});

it('khoá / mở khoá ghi audit', function () {
    $u = User::factory()->create();

    Livewire::test(ListUsers::class)->callAction(TestAction::make('lock')->table($u));
    expect($u->fresh()->locked_at)->not->toBeNull();

    Livewire::test(ListUsers::class)->callAction(TestAction::make('unlock')->table($u));
    expect($u->fresh()->locked_at)->toBeNull()
        ->and(AdminAuditLog::where('subject_id', $u->id)->pluck('action')->all())->toBe(['user.lock', 'user.unlock']);
});

it('đổi cấp → tier_manual, có audit before/after', function () {
    $u = User::factory()->create();

    Livewire::test(ListUsers::class)->callAction(TestAction::make('changeTier')->table($u), ['tier' => 'agency']);

    expect($u->fresh()->tier)->toBe(Tier::Agency)->and($u->fresh()->tier_manual)->toBeTrue();
    $log = AdminAuditLog::firstWhere('action', 'user.tier');
    expect($log->changes['before']['tier'])->toBe('member')->and($log->changes['after']['tier'])->toBe('agency')
        ->and($log->admin_id)->toBe($this->admin->id);
});

it('điều chỉnh số dư tạo bút toán adjust + audit; cùng key không tạo 2 lần', function () {
    $u = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $u->id])
        ->callAction('adjustBalance', ['amount' => 50_000, 'reason' => 'khách bị trừ nhầm', 'idempotency_key' => 'adjust:fixed'])
        ->assertHasNoActionErrors();
    app(UserAdminService::class)->adjustBalance($u, 50_000, 'khách bị trừ nhầm', $this->admin, 'adjust:fixed');

    $tx = WalletTransaction::sole();
    expect($tx->type)->toBe(TxType::Adjust)->and($tx->amount)->toBe(50_000)->and($tx->created_by)->toBe($this->admin->id)
        ->and($u->fresh()->balance)->toBe(50_000)
        ->and(AdminAuditLog::where('action', 'user.adjust_balance')->count())->toBe(1);
});

it('điều chỉnh bắt buộc lý do và số tiền khác 0', function () {
    $u = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $u->id])
        ->callAction('adjustBalance', ['amount' => 0, 'reason' => '', 'idempotency_key' => 'adjust:x'])
        ->assertHasActionErrors(['amount', 'reason']);

    expect(WalletTransaction::count())->toBe(0);
});

it('trừ quá số dư → báo lỗi, không ghi sổ', function () {
    $u = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $u->id])
        ->callAction('adjustBalance', ['amount' => -1, 'reason' => 'thử', 'idempotency_key' => 'adjust:y']);

    expect(WalletTransaction::count())->toBe(0)->and(AdminAuditLog::count())->toBe(0);
});

it('cấp lại mã nạp đổi mã + audit', function () {
    $u = User::factory()->create();
    $old = $u->deposit_code;

    Livewire::test(ListUsers::class)->callAction(TestAction::make('reissueDepositCode')->table($u));

    expect($u->fresh()->deposit_code)->not->toBe($old)->toMatch('/^BP[A-HJ-NP-Z2-9]{6}$/');
    expect(AdminAuditLog::firstWhere('action', 'user.deposit_code')->changes['before']['deposit_code'])->toBe($old);
});

it('bulk lên Đại lý', function () {
    $users = User::factory()->count(2)->create();

    Livewire::test(ListUsers::class)->callTableBulkAction('bulkPromote', $users)->assertHasNoActionErrors();

    expect(User::whereIn('id', $users->pluck('id'))->where('tier', 'agency')->count())->toBe(2);
});

it('lưu cài đặt cấp: audit + nâng ngay khách đủ ngưỡng mới', function () {
    $u = User::factory()->create();
    app(WalletService::class)->credit($u, 3_500_000, TxType::Topup, 'seed');

    Livewire::test(ManageTierSettings::class)
        ->fillForm(['agency_threshold' => 3_000_000, 'agency_discount_percent' => 10, 'member_discount_percent' => 0])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($u->fresh()->tier)->toBe(Tier::Agency)
        ->and(AdminAuditLog::firstWhere('action', 'settings.tier')->changes['after'])->toBe(['agency_threshold' => 3_000_000, 'agency_discount_percent' => 10])
        ->and(AdminAuditLog::firstWhere('action', 'tier.promote_all')->changes['promoted'])->toBe(1);
});

it('cài đặt cấp validate % 0–100', function () {
    Livewire::test(ManageTierSettings::class)
        ->fillForm(['agency_threshold' => -1, 'agency_discount_percent' => 101, 'member_discount_percent' => 0])
        ->call('save')
        ->assertHasFormErrors(['agency_threshold', 'agency_discount_percent']);
});

it('khách không vào được trang quản trị khách/sổ cái/nhật ký', function (string $url) {
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
})->with(['/admin/users', '/admin/wallet-transactions', '/admin/admin-audit-logs', '/admin/manage-tier-settings']);

it('admin mở được các trang', function (string $url) {
    User::factory()->create();
    $this->get($url)->assertOk();
})->with(['/admin/users', '/admin/wallet-transactions', '/admin/admin-audit-logs', '/admin/manage-tier-settings']);
