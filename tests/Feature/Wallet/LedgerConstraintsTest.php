<?php

use App\Enums\TxType;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('CHECK chặn số dư âm (SQLSTATE 23514)', function () {
    $u = User::factory()->create();

    try {
        DB::table('users')->where('id', $u->id)->update(['balance' => -1]);
        $this->fail('Phải bị CHECK chặn');
    } catch (QueryException $e) {
        expect($e->getCode())->toBe('23514');
    }
});

it('trigger chặn UPDATE/DELETE trên sổ cái', function (string $sql) {
    $u = User::factory()->create();
    app(WalletService::class)->credit($u, 1000, TxType::Topup, 'k');

    expect(fn () => DB::transaction(fn () => DB::statement($sql)))
        ->toThrow(QueryException::class, 'append-only');
})->with([
    'update' => ['UPDATE wallet_transactions SET amount = 999999'],
    'delete' => ['DELETE FROM wallet_transactions'],
]);

it('model chặn update/delete bút toán', function () {
    $u = User::factory()->create();
    $tx = app(WalletService::class)->credit($u, 1000, TxType::Topup, 'k');

    expect(fn () => $tx->update(['amount' => 5]))->toThrow(LogicException::class);
    expect(fn () => $tx->delete())->toThrow(LogicException::class);
});

it('CHECK chặn amount = 0 và type lạ', function (array $row) {
    $u = User::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::table('wallet_transactions')->insert($row + [
        'user_id' => $u->id, 'balance_after' => 0, 'idempotency_key' => uniqid(),
    ])))->toThrow(QueryException::class);
})->with([
    'amount 0' => [['type' => 'topup', 'amount' => 0]],
    'type lạ' => [['type' => 'gift', 'amount' => 10]],
]);

it('balance/total_deposited/tier/deposit_code không mass-assign được', function () {
    $u = User::factory()->create();
    $code = $u->deposit_code;

    $u->update(['balance' => 999, 'total_deposited' => 999, 'tier' => 'agency', 'deposit_code' => 'BPAAAAAA']);

    $u->refresh();
    expect($u->balance)->toBe(0)->and($u->total_deposited)->toBe(0)->and($u->tier->value)->toBe('member')->and($u->deposit_code)->toBe($code);
});
