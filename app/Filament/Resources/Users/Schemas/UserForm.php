<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class UserForm
{
    /** Chỉ sửa tên + email. Tiền, cấp, khoá đi qua action có audit. */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('admin.users.display_name'))->required()->maxLength(50),
            TextInput::make('email')->label(__('admin.users.email'))->email()->required()->maxLength(255)
                ->dehydrateStateUsing(fn ($state) => User::normalizeEmail($state))
                ->rule(fn (?User $record) => Rule::unique('users', 'email')->ignore($record?->id)),
        ]);
    }
}
