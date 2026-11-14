<?php

namespace App\Livewire;

use App\Models\WalletTransaction;
use App\Settings\DepositSettings;
use App\Support\Money;
use App\Support\VietQr;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Màn Nạp tiền: QR VietQR theo STK + số tiền + nội dung (= mã nạp cố định của khách).
 * Poll 10s khi tab hiển thị: số dư đổi → toast + nạp lại lịch sử; không đổi → không render lại.
 */
class Deposit extends Component
{
    public const PRESETS = [50_000, 100_000, 200_000, 500_000, 1_000_000];

    public const MIN_AMOUNT = 10_000;

    public $amount = 200_000;

    #[Locked]
    public int $lastBalance = 0;

    public function mount(): void
    {
        $this->lastBalance = (int) auth()->user()->balance;
    }

    public function pick(int $amount): void
    {
        if (in_array($amount, self::PRESETS, true)) {
            $this->amount = $amount;
        }
    }

    public function checkBalance(): void
    {
        $balance = (int) auth()->user()->fresh()->balance;

        if ($balance === $this->lastBalance) {
            $this->skipRender();

            return;
        }

        $diff = $balance - $this->lastBalance;
        $this->lastBalance = $balance;

        if ($diff > 0) {
            $this->dispatch('toast', message: __('deposit.received', ['amount' => Money::vnd($diff), 'balance' => Money::vnd($balance)]));
        }
    }

    /** Số tiền hợp lệ để đưa vào QR (null = để khách tự nhập trong app ngân hàng). */
    private function validAmount(): ?int
    {
        $a = filter_var($this->amount, FILTER_VALIDATE_INT);

        return $a !== false && $a >= self::MIN_AMOUNT && $a <= 1_000_000_000 ? $a : null;
    }

    public function render()
    {
        $settings = app(DepositSettings::class);
        $user = auth()->user();
        $amount = $this->validAmount();

        return view('livewire.deposit', [
            'settings' => $settings,
            'configured' => $settings->isConfigured(),
            'code' => $user->deposit_code,
            'validAmount' => $amount,
            'bonus' => $amount ? intdiv($amount * $settings->bonusPercentFor($amount), 100) : 0,
            'qrUrl' => $settings->isConfigured()
                ? VietQr::imageUrl((string) $settings->bank_bin, (string) $settings->account_number, $amount, $user->deposit_code, (string) $settings->account_name)
                : null,
            'history' => WalletTransaction::where('user_id', $user->id)
                ->whereIn('type', ['topup', 'bonus', 'topup_reversal', 'bonus_reversal'])
                ->latest('id')->limit(20)->get(),
        ])->title(__('deposit.title'));
    }
}
