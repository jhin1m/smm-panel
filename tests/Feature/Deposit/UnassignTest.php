<?php

use App\Enums\Tier;
use App\Enums\TxType;
use App\Exceptions\InsufficientBalance;
use App\Models\BankTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DepositService;
use App\Services\WalletService;

beforeEach(function () {
    configureDeposit(['bonus_rules' => [['min_amount' => 500_000, 'percent' => 5]]]);
    $this->admin = User::factory()->admin()->create();
    $this->svc = app(DepositService::class);
});

it('huỷ gán đủ số dư → 2 bút toán đảo, tổng nạp giảm, về unmatched, gán lại khách khác (key :2)', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $this->svc->handleWebhook(sepayPayload(['code' => $a->deposit_code, 'transferAmount' => 6_000_000]), 'webhook');
    $tx = BankTransaction::sole();
    expect($a->fresh()->tier)->toBe(Tier::Agency);

    $this->svc->unassign($tx->id, $this->admin, 'gán nhầm');

    expect(WalletTransaction::where('type', 'topup_reversal')->sole()->amount)->toBe(-6_000_000)
        ->and(WalletTransaction::where('type', 'bonus_reversal')->sole()->amount)->toBe(-300_000)
        ->and($a->fresh()->balance)->toBe(0)->and($a->fresh()->total_deposited)->toBe(0)
        ->and($a->fresh()->tier)->toBe(Tier::Agency)          // không tự hạ cấp
        ->and($tx->fresh()->status)->toBe('unmatched');

    $this->svc->assign($tx->id, $b, $this->admin);
    expect($b->fresh()->balance)->toBe(6_300_000)
        ->and(WalletTransaction::where('idempotency_key', "sepay:{$tx->sepay_id}:2")->exists())->toBeTrue();
});

it('huỷ gán thiếu số dư → không đổi gì', function () {
    $a = User::factory()->create();
    $this->svc->handleWebhook(sepayPayload(['code' => $a->deposit_code, 'transferAmount' => 100_000]), 'webhook');
    $tx = BankTransaction::sole();
    app(WalletService::class)->debit($a, 50_000, TxType::Order, 'spent');

    expect(fn () => $this->svc->unassign($tx->id, $this->admin, 'x'))->toThrow(InsufficientBalance::class);
    expect($tx->fresh()->status)->toBe('credited')->and($a->fresh()->balance)->toBe(50_000)
        ->and(WalletTransaction::whereIn('type', ['topup_reversal', 'bonus_reversal'])->count())->toBe(0);
});

it('ghi chú huỷ gán hiển thị lý do, vừa cột note kể cả khi lý do dài và chứa ký tự bị escape', function () {
    $a = User::factory()->create();
    $this->svc->handleWebhook(sepayPayload(['code' => $a->deposit_code, 'transferAmount' => 100_000]), 'webhook');
    $tx = BankTransaction::sole();

    $this->svc->unassign($tx->id, $this->admin, str_repeat("\"\n", 100));

    expect(mb_strlen($tx->fresh()->note))->toBeLessThanOrEqual(255)
        ->and($tx->fresh()->noteLabel())->toStartWith('Huỷ gán: "');
});
