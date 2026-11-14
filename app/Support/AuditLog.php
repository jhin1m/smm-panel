<?php

namespace App\Support;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Nhật ký thao tác admin: 1 hàm ghi, gọi trong cùng transaction với thao tác. */
final class AuditLog
{
    /** @param  array<string, mixed>  $changes  thường là ['before' => ..., 'after' => ...] */
    public static function record(?User $admin, string $action, ?Model $subject = null, array $changes = []): AdminAuditLog
    {
        return AdminAuditLog::create([
            'admin_id' => $admin?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes ?: null,
            'ip' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->ip(),
        ]);
    }
}
