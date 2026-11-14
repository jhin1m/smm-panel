<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\ServiceUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mọi đổi trạng thái/tiến độ đi qua OrderService (không có $fillable cho status/done/total). */
class Order extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'unit' => ServiceUnit::class,
            'unit_price' => 'integer',
            'discount_percent' => 'integer',
            'quantity' => 'integer',
            'done' => 'integer',
            'total' => 'integer',
            'refunded_amount' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Định nghĩa duy nhất "đơn chờ xử lý" — badge Filament + dashboard dùng chung. */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::Pending->value);
    }

    /** Đơn đang chạy theo góc nhìn khách: chờ xử lý + đang chạy. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Processing->value]);
    }

    public function progressPercent(): int
    {
        return $this->quantity > 0 ? (int) floor($this->done * 100 / $this->quantity) : 0;
    }
}
