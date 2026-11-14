<?php

use App\Settings\InstallSettings;
use Illuminate\Support\Facades\DB;

/** Ghi thẳng cờ installed_at (bỏ qua singleton settings để mọi lần đọc sau đó thấy giá trị mới). */
function setInstalledAt(?string $value): void
{
    DB::table('settings')->updateOrInsert(
        ['group' => 'install', 'name' => 'installed_at'],
        ['payload' => json_encode($value), 'locked' => false, 'created_at' => now(), 'updated_at' => now()],
    );
    app()->forgetInstance(InstallSettings::class);
}

/** Mặc định mọi test coi instance đã cài, để EnsureInstalled không chặn các test không liên quan. */
function markInstalled(): void
{
    setInstalledAt(now()->toIso8601String());
}

/** Test của wizard gọi hàm này để quay về trạng thái chưa cài. */
function markNotInstalled(): void
{
    setInstalledAt(null);
}
