<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    protected $fillable = ['title', 'body', 'is_published', 'published_at'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Gán ở PHP (không dựa default DB) để so sánh với now() nhất quán.
        static::creating(fn (Notice $n) => $n->published_at ??= now());
    }

    /** Thông báo đang hiển thị cho khách, mới nhất trước. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('published_at', '<=', now())->orderByDesc('published_at')->orderByDesc('id');
    }
}
