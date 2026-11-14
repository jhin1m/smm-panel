<?php

namespace App\Filament\Concerns;

use App\Support\AuditLog;

/**
 * Ghi 1 dòng audit khi lưu SettingsPage (before/after các trường đổi). Trường bí mật chỉ ghi "đã đổi".
 */
trait AuditsSettingsChanges
{
    /** @var array<string, mixed> */
    protected array $settingsBefore = [];

    /** @return array<int, string> */
    protected function secretSettingKeys(): array
    {
        return [];
    }

    protected function beforeSave(): void
    {
        $this->settingsBefore = app(static::getSettings())->toArray();
    }

    protected function afterSave(): void
    {
        $after = app(static::getSettings())->toArray();
        $before = [];
        $changed = [];

        foreach ($after as $key => $value) {
            if (($this->settingsBefore[$key] ?? null) === $value) {
                continue;
            }
            $secret = in_array($key, $this->secretSettingKeys(), true);
            $before[$key] = $secret ? '***' : ($this->settingsBefore[$key] ?? null);
            $changed[$key] = $secret ? '*** (đã đổi)' : $value;
        }

        if ($changed !== []) {
            AuditLog::record(auth()->user(), 'settings.'.app(static::getSettings())::group(), null, ['before' => $before, 'after' => $changed]);
        }

        $this->afterSettingsAudited($before, $changed);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $changed
     */
    protected function afterSettingsAudited(array $before, array $changed): void {}
}
