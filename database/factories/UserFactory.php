<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function locked(): static
    {
        return $this->afterMaking(fn (User $u) => $u->forceFill(['locked_at' => now()]));
    }

    /** Admin đã thiết lập MFA (secret cố định để test sinh mã TOTP). */
    public function admin(bool $withMfa = true): static
    {
        return $this->afterMaking(fn (User $u) => $u->forceFill([
            'is_admin' => true,
            'app_authentication_secret' => $withMfa ? 'JBSWY3DPEHPK3PXP' : null,
        ]));
    }
}
