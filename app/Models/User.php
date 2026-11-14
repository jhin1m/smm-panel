<?php

namespace App\Models;

use App\Enums\Tier;
use App\Support\DepositCode;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * is_admin, locked_at, google_id, balance, total_deposited, tier, deposit_code không nằm trong $fillable —
 * chỉ set qua forceFill ở nơi có kiểm soát. balance/total_deposited CHỈ đổi qua WalletService.
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
            'locked_at' => 'datetime',
            'balance' => 'integer',
            'total_deposited' => 'integer',
            'tier' => Tier::class,
            'tier_manual' => 'boolean',
            'tier_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Mã nạp cố định gán lúc tạo user (unique index là lớp chặn cuối).
        static::creating(function (User $user) {
            $user->deposit_code ??= DepositCode::unique();
        });
    }

    /** Notification/mail gửi cho user tự dùng ngôn ngữ đã chọn (null → ngôn ngữ request/mặc định). */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function chatThread(): HasOne
    {
        return $this->hasOne(ChatThread::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin && $this->locked_at === null;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** Chuẩn hoá email: trim + lowercase (đăng ký, đăng nhập, reset, Google dùng chung). */
    public static function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
