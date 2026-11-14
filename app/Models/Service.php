<?php

namespace App\Models;

use App\Enums\ServiceUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'description', 'unit', 'price', 'min_quantity', 'max_quantity',
        'speed', 'start_time', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'unit' => ServiceUnit::class,
            'price' => 'integer',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Dịch vụ đặt được: bản thân đang bán VÀ danh mục đang bật. */
    public function scopeOrderable(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereHas('category', fn (Builder $c) => $c->where('is_active', true));
    }
}
