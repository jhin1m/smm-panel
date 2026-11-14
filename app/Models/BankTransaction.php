<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Lang;

class BankTransaction extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CREDITED = 'credited';

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_IGNORED = 'ignored';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sepay_id' => 'integer',
            'transfer_amount' => 'integer',
            'bonus_percent' => 'integer',
            'bonus_amount' => 'integer',
            'assignment_no' => 'integer',
            'payload' => 'array',
            'transaction_date' => 'datetime',
            'credited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeUnmatched(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNMATCHED);
    }

    public static function statusLabel(string $status): string
    {
        return self::translateOr("admin.bank_transactions.statuses.{$status}", $status);
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_CREDITED => 'success',
            self::STATUS_UNMATCHED => 'warning',
            self::STATUS_IGNORED => 'gray',
            default => 'info',
        };
    }

    public static function sourceLabel(string $source): string
    {
        return self::translateOr("admin.bank_transactions.sources.{$source}", $source);
    }

    /**
     * Ghi chú hệ thống dạng key dịch, kèm tham số (nếu có) mã hoá JSON sau dấu "|":
     * vd `deposit.notes.suspected_duplicate|{"twin":123}`. Cột note dài 255 ký tự.
     *
     * @param  array<string, scalar>  $params
     */
    public static function noteKey(string $key, array $params = []): string
    {
        return $params === [] ? $key : $key.'|'.json_encode($params, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Ghi chú hiển thị: ghi chú hệ thống (key dịch, có thể kèm tham số) được dịch theo locale hiện tại;
     * dòng cũ tiếng Việt và ghi chú admin nhập tay giữ nguyên văn.
     */
    public function noteLabel(): ?string
    {
        if ($this->note === null) {
            return null;
        }

        [$key, $json] = array_pad(explode('|', $this->note, 2), 2, null);
        $params = $json === null ? [] : json_decode($json, true);

        if (! is_array($params)) {
            return $this->note;
        }

        return self::translateOr($key, $this->note, $params);
    }

    /** @param  array<string, mixed>  $params */
    private static function translateOr(string $key, string $fallback, array $params = []): string
    {
        if (! Lang::has($key)) {
            return $fallback;
        }

        $text = __($key, $params);

        return is_string($text) ? $text : $fallback;
    }
}
