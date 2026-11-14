<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Instance đang chạy (đã có admin) nâng cấp lên bản có wizard: đánh dấu đã cài để không bị đẩy về /install.
        // Kiểm cột trước vì migrate:fresh chạy migration này khi bảng users đã có nhưng chưa có dữ liệu.
        $hasAdmin = Schema::hasTable('users')
            && Schema::hasColumn('users', 'is_admin')
            && DB::table('users')->where('is_admin', true)->exists();

        $this->migrator->add('install.installed_at', $hasAdmin ? now()->toIso8601String() : null);
    }
};
