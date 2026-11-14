<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<string, mixed> */
    private array $before = [];

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function beforeSave(): void
    {
        $this->before = $this->record->only(['name', 'email']);
    }

    protected function afterSave(): void
    {
        $after = $this->record->only(['name', 'email']);

        if ($after !== $this->before) {
            AuditLog::record(auth()->user(), 'user.update', $this->record, ['before' => $this->before, 'after' => $after]);
        }
    }
}
