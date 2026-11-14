<?php

use App\Exceptions\ConfirmationRequired;
use App\Exceptions\DepositAlreadyProcessed;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Models\AdminAuditLog;
use App\Models\BankTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DepositService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    configureDeposit();
    $this->admin = User::factory()->admin()->create();
});

function unmatchedTx(array $o = []): BankTransaction
{
    app(DepositService::class)->handleWebhook(sepayPayload(['content' => 'khong co ma'] + $o), 'webhook');

    return BankTransaction::latest('id')->first();
}

it('gán tay: unmatched → credited, ledger created_by admin + audit', function () {
    $u = User::factory()->create();
    $tx = unmatchedTx();

    app(DepositService::class)->assign($tx->id, $u, $this->admin);

    expect($tx->fresh()->status)->toBe('credited')->and($tx->fresh()->assigned_by)->toBe($this->admin->id)
        ->and(WalletTransaction::sole()->created_by)->toBe($this->admin->id)
        ->and(AdminAuditLog::where('action', 'deposit.assign')->count())->toBe(1);
});

it('gán lần 2 → DepositAlreadyProcessed', function () {
    $u = User::factory()->create();
    $tx = unmatchedTx();
    app(DepositService::class)->assign($tx->id, $u, $this->admin);

    expect(fn () => app(DepositService::class)->assign($tx->id, $u, $this->admin))->toThrow(DepositAlreadyProcessed::class);
    expect(WalletTransaction::count())->toBe(1);
});

it('gán giao dịch out → từ chối', function () {
    app(DepositService::class)->handleWebhook(sepayPayload(['transferType' => 'out']), 'webhook');

    expect(fn () => app(DepositService::class)->assign(BankTransaction::sole()->id, User::factory()->create(), $this->admin))
        ->toThrow(DepositAlreadyProcessed::class);
});

it('gán giao dịch đã bỏ qua cần xác nhận', function () {
    $u = User::factory()->create();
    $tx = unmatchedTx();
    app(DepositService::class)->ignore($tx->id, $this->admin, 'chuyển nhầm');

    expect(fn () => app(DepositService::class)->assign($tx->id, $u, $this->admin))->toThrow(ConfirmationRequired::class);
    app(DepositService::class)->assign($tx->id, $u, $this->admin, confirmIgnored: true);
    expect($tx->fresh()->status)->toBe('credited');
});

it('Filament: action gán từ tab Chưa khớp', function () {
    $u = User::factory()->create();
    $tx = unmatchedTx();
    filament()->setCurrentPanel('admin');

    Livewire::actingAs($this->admin)->test(ListBankTransactions::class)
        ->assertCanSeeTableRecords([$tx])
        ->callAction(TestAction::make('assign')->table($tx), ['user_id' => $u->id])
        ->assertHasNoActionErrors();

    expect($u->fresh()->balance)->toBe(100_000);
});

it('khách không vào được trang giao dịch', function () {
    $this->actingAs(User::factory()->create())->get('/admin/bank-transactions')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin/bank-transactions')->assertOk();
});
