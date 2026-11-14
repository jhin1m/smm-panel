<?php

use App\Enums\TxType;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\InsufficientBalance;
use App\Exceptions\SelfAdjustmentForbidden;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;

function wallet(): WalletService
{
    return app(WalletService::class);
}

function assertLedgerInvariant(User $user): void
{
    $u = $user->fresh();
    $sum = (int) WalletTransaction::where('user_id', $u->id)->sum('amount');
    $deposited = (int) WalletTransaction::where('user_id', $u->id)->whereIn('type', ['topup', 'topup_reversal'])->sum('amount');

    expect($u->balance)->toBe($sum)->and($u->total_deposited)->toBe($deposited);
}

it('credit/debit cập nhật balance_after và users.balance', function () {
    $u = User::factory()->create();

    $t1 = wallet()->credit($u, 500_000, TxType::Topup, 'k1');
    $t2 = wallet()->debit($u, 120_000, TxType::Order, 'k2');

    expect($t1->balance_after)->toBe(500_000)->and($t2->amount)->toBe(-120_000)->and($t2->balance_after)->toBe(380_000)
        ->and($u->fresh()->balance)->toBe(380_000);
    assertLedgerInvariant($u);
});

it('topup tăng tổng nạp, topup_reversal giảm, bonus/refund/adjust không đổi', function () {
    $u = User::factory()->create();
    $admin = User::factory()->admin()->create();

    wallet()->credit($u, 1_000_000, TxType::Topup, 'a');
    wallet()->credit($u, 50_000, TxType::Bonus, 'b');
    wallet()->credit($u, 10_000, TxType::Refund, 'c');
    wallet()->adjust($u, 5_000, 'bù lỗi', $admin, 'd');
    expect($u->fresh()->total_deposited)->toBe(1_000_000);

    wallet()->debit($u, 300_000, TxType::TopupReversal, 'e');
    expect($u->fresh()->total_deposited)->toBe(700_000)->and($u->fresh()->balance)->toBe(765_000);
    assertLedgerInvariant($u);
});

it('trừ quá số dư → InsufficientBalance, không ghi sổ', function () {
    $u = User::factory()->create();
    wallet()->credit($u, 100, TxType::Topup, 'seed');

    expect(fn () => wallet()->debit($u, 101, TxType::Order, 'x'))->toThrow(InsufficientBalance::class);
    expect(WalletTransaction::count())->toBe(1)->and($u->fresh()->balance)->toBe(100);
});

it('cùng key gọi 2 lần → 1 dòng; cùng key khác số tiền → IdempotencyConflict', function () {
    $u = User::factory()->create();

    $a = wallet()->credit($u, 1000, TxType::Topup, 'same');
    $b = wallet()->credit($u, 1000, TxType::Topup, 'same');

    expect($a->id)->toBe($b->id)->and(WalletTransaction::count())->toBe(1)->and($u->fresh()->balance)->toBe(1000);
    expect(fn () => wallet()->credit($u, 999, TxType::Topup, 'same'))->toThrow(IdempotencyConflict::class);
    expect(fn () => wallet()->credit(User::factory()->create(), 1000, TxType::Topup, 'same'))->toThrow(IdempotencyConflict::class);
});

it('sai dấu theo loại hoặc số tiền 0 → exception', function (Closure $call) {
    $u = User::factory()->create();
    wallet()->credit($u, 1000, TxType::Topup, 'seed');

    expect(fn () => $call($u))->toThrow(InvalidArgumentException::class);
})->with([
    'credit loại order' => [fn ($u) => wallet()->credit($u, 10, TxType::Order, 'k')],
    'debit loại topup' => [fn ($u) => wallet()->debit($u, 10, TxType::Topup, 'k')],
    'credit 0' => [fn ($u) => wallet()->credit($u, 0, TxType::Topup, 'k')],
    'debit âm' => [fn ($u) => wallet()->debit($u, -5, TxType::Order, 'k')],
]);

it('adjust lưu created_by = admin và lý do; adjust 0 bị chặn', function () {
    $u = User::factory()->create();
    $admin = User::factory()->admin()->create();
    wallet()->credit($u, 10_000, TxType::Topup, 'seed');

    $tx = wallet()->adjust($u, -3_000, 'trừ nhầm đơn', $admin, 'adj-1');

    expect($tx->created_by)->toBe($admin->id)->and($tx->description)->toBe('trừ nhầm đơn')->and($tx->type)->toBe(TxType::Adjust)
        ->and($u->fresh()->balance)->toBe(7_000);
    expect(fn () => wallet()->adjust($u, 0, 'x', $admin, 'adj-2'))->toThrow(InvalidArgumentException::class);
    expect(fn () => wallet()->adjust($u, 10, '  ', $admin, 'adj-3'))->toThrow(InvalidArgumentException::class);
});

it('admin không tự điều chỉnh số dư của mình', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => wallet()->adjust($admin, 1000, 'tự cộng', $admin, 'k'))->toThrow(SelfAdjustmentForbidden::class);
});

it('credit truyền actor lưu đúng created_by', function () {
    $u = User::factory()->create();
    $admin = User::factory()->admin()->create();

    expect(wallet()->credit($u, 10, TxType::Refund, 'r', actor: $admin)->created_by)->toBe($admin->id);
});
