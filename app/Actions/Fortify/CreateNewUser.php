<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Settings\AccessSettings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /** @param  array<string, string>  $input */
    public function create(array $input): User
    {
        // Chốt chặn thứ hai ngoài middleware route: tắt đăng ký thì không tạo TK bằng bất kỳ đường nào.
        if (! app(AccessSettings::class)->registration_enabled) {
            throw ValidationException::withMessages(['email' => __('account.errors.registration_disabled')]);
        }

        $input['email'] = User::normalizeEmail($input['email'] ?? '');

        Validator::make($input, [
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => trim($input['name']),
            'email' => $input['email'],
            'password' => $input['password'],   // cast 'hashed'
        ]);
    }
}
