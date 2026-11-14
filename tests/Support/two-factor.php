<?php

use App\Models\User;
use App\Settings\AccessSettings;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/** Mã TOTP của secret ở cửa sổ hiện tại (+offset × 30s); các offset khác nhau cho mã khác nhau, tránh chặn dùng lại mã. */
function totpCode(string $secret, int $offset = 0): string
{
    $g = app(Google2FA::class);

    return $g->oathTotp($secret, $g->getTimestamp() + $offset);
}

/** Bật + xác nhận 2FA cho khách; trả secret thô để test sinh mã. */
function enableTwoFactor(User $user, string $secret = 'JBSWY3DPEHPK3PXP'): string
{
    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(array_map(fn ($i) => "rc{$i}-aaaaaaaaaa", range(1, 8)))),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}

function twoFactorSecretOf(User $user): string
{
    return Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
}

function setCustomerTwoFactorSwitch(bool $on): void
{
    $s = app(AccessSettings::class);
    $s->customer_2fa_enabled = $on;
    $s->save();
}
